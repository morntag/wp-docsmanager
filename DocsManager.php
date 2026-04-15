<?php // phpcs:ignoreFile
namespace Morntag\WpDocsManager;

use Morntag\WpDocsManager\Models\Documentation;
use Morntag\WpDocsManager\Services\FileScanner;
use Morntag\WpDocsManager\Services\MarkdownParser;
use Morntag\WpDocsManager\Services\SearchService;

/**
 * Documentation Manager Module
 *
 * Provides centralized documentation system with:
 * - Module README discovery
 * - Custom documentation management
 * - Markdown editing with Tiptap
 * - Hierarchical organization
 * - Search functionality
 *
 * @package Morntag\WpDocsManager
 */
class DocsManager extends Module {

	/**
	 * Post type identifier - duplicated here to avoid autoloading timing issues
	 * when accessing Documentation::POST_TYPE in admin_init hooks.
	 */
	private const POST_TYPE = 'mcc_documentation';

	/**
	 * Taxonomy identifier - duplicated here to avoid autoloading timing issues.
	 */
	private const TAXONOMY = 'mcc_doc_category';

	private string $page_hook = '';
	private Documentation $documentation;
	private FileScanner $file_scanner;
	private MarkdownParser $markdown_parser;
	private SearchService $search_service;

	/**
	 * Module configuration (scan paths, allowed roots, etc.)
	 *
	 * @var array<string,mixed>
	 */
	private array $config = array();

	/**
	 * Pending config injected via boot() and consumed by the next instance() call.
	 *
	 * @var array<string,mixed>
	 */
	private static array $pending_config = array();

	protected $hooks = array(
		'init'                    => 'register_post_types',
		'admin_menu'              => 'add_admin_menu',
		'admin_enqueue_scripts'   => 'enqueue_admin_assets',
		'admin_init'              => array(
			array(
				'method' => 'handle_form_submission',
				'prio'   => 10,
			),
			array(
				'method' => 'handle_delete_action',
				'prio'   => 10,
			),
		),
		'wp_ajax_morntag_docs_search' => 'ajax_search_docs',
		'wp_ajax_morntag_docs_list'   => 'ajax_list_docs',
	);

	protected $filters = array(
		'wp_kses_allowed_html' => array(
			'method'        => 'allow_media_html_tags',
			'prio'          => 10,
			'accepted_args' => 2,
		),
	);

	/**
	 * Bootstrap the module with runtime configuration.
	 *
	 * Stores the config so the next instance() call (which runs the
	 * protected constructor) can read it. After construction, the
	 * pending config is cleared so it cannot leak into later callers.
	 *
	 * Recognised keys:
	 * - 'modules_dir'  (string)   Absolute path to a directory scanned for "<module>/README.md" files
	 * - 'docs_dir'     (string)   Absolute path to a .docs-style tree of markdown files
	 * - 'allowed_roots'(string[]) Directories whose descendants may be rendered by the viewer
	 *
	 * @param array<string,mixed> $config Runtime config.
	 * @return self
	 */
	public static function boot( array $config = array() ): self {
		self::$pending_config = $config;
		return self::instance();
	}

	/**
	 * Initialize module
	 *
	 * Called by parent Module constructor. Instantiates services.
	 * Module is only instantiated on main site (checked before instance creation).
	 * CPT/taxonomy registration happens via 'init' hook, capabilities via 'admin_init'.
	 */
	public function init(): void {
		$this->config         = self::$pending_config;
		self::$pending_config = array();

		$this->documentation   = new Documentation();
		$this->file_scanner    = new FileScanner(
			isset( $this->config['modules_dir'] ) && is_string( $this->config['modules_dir'] ) ? $this->config['modules_dir'] : '',
			isset( $this->config['docs_dir'] ) && is_string( $this->config['docs_dir'] ) ? $this->config['docs_dir'] : ''
		);
		$this->markdown_parser = new MarkdownParser();
		$this->search_service  = new SearchService();
	}

	/**
	 * Get the list of allowed filesystem roots for the viewer.
	 *
	 * Views use this to validate that a requested file path is within
	 * a directory the host plugin has authorised for rendering.
	 *
	 * @return string[]
	 */
	public function get_allowed_roots(): array {
		if ( ! isset( $this->config['allowed_roots'] ) || ! is_array( $this->config['allowed_roots'] ) ) {
			return array();
		}

		$roots = array();
		foreach ( $this->config['allowed_roots'] as $root ) {
			if ( is_string( $root ) && '' !== $root ) {
				$roots[] = $root;
			}
		}
		return $roots;
	}

	/**
	 * Check whether the current user has a given capability.
	 *
	 * Host plugins can wire the `morntag_docs_user_can` filter to map
	 * DocsManager's internal capability strings onto their own system.
	 * When the filter returns null (the default), we fall back to
	 * WordPress's `manage_options` check so administrators always have
	 * access out of the box.
	 *
	 * @param string $cap Capability identifier (e.g. 'mcc_access_docs').
	 * @return bool
	 */
	public function user_can( string $cap ): bool {
		$mapped = apply_filters( 'morntag_docs_user_can', null, $cap );
		if ( is_bool( $mapped ) ) {
			return $mapped;
		}
		return current_user_can( 'manage_options' );
	}

	/**
	 * Register CPT and taxonomy
	 *
	 * Must be called during WordPress 'init' hook, not at module load time.
	 *
	 * @hook init 10 0
	 */
	public function register_post_types(): void {
		$this->documentation->register();
	}

	/**
	 * Add admin menu
	 *
	 * @hook admin_menu 10 0
	 */
	public function add_admin_menu(): void {
		// Check for capability or administrator role as fallback.
		if ( ! $this->user_can( 'mcc_access_docs' ) ) {
			return;
		}

		$this->page_hook = add_menu_page(
			'Documentation',
			'Documentation',
			'manage_options', // Use manage_options for now to ensure admins can access.
			'mcc-documentation',
			array( $this, 'render_admin_page' ),
			'dashicons-book-alt',
			100
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @hook admin_enqueue_scripts 10 1
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( $hook !== $this->page_hook ) {
			return;
		}

		// Load the WP Media Library so file/image uploads are available on the docs page.
		wp_enqueue_media();

		// CSS.
		wp_enqueue_style(
			'morntag-docs-admin',
			plugin_dir_url( __FILE__ ) . 'assets/css/admin.css',
			array(),
			(string) filemtime( plugin_dir_path( __FILE__ ) . 'assets/css/admin.css' )
		);

		// JavaScript.
		wp_enqueue_script(
			'morntag-docs-admin',
			plugin_dir_url( __FILE__ ) . 'assets/js/admin.js',
			array( 'jquery' ),
			(string) filemtime( plugin_dir_path( __FILE__ ) . 'assets/js/admin.js' ),
			true
		);

		// Localize script.
		wp_localize_script(
			'morntag-docs-admin',
			'morntagDocs',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'morntag_docs_nonce' ),
			)
		);
	}

	/**
	 * Render admin page
	 */
	public function render_admin_page(): void {
		require plugin_dir_path( __FILE__ ) . 'views/admin-page.view.php';
	}

	/**
	 * Get the file scanner service
	 *
	 * @return FileScanner
	 */
	public function get_file_scanner(): FileScanner {
		return $this->file_scanner;
	}

	/**
	 * Get the markdown parser service
	 *
	 * @return MarkdownParser
	 */
	public function get_markdown_parser(): MarkdownParser {
		return $this->markdown_parser;
	}

	/**
	 * Handle form submission for document creation/editing
	 *
	 * Processes POST requests before headers are sent to enable proper redirects.
	 *
	 * @hook admin_init 10 0
	 */
	public function handle_form_submission(): void {
		// Check if we're on our admin page.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checking page parameter.
		if ( ! isset( $_GET['page'] ) || 'mcc-documentation' !== $_GET['page'] ) {
			return;
		}

		// Check for POST submission with our nonce.
		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'POST' !== $request_method || ! isset( $_POST['morntag_docs_nonce'] ) ) {
			return;
		}

		// Verify nonce.
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['morntag_docs_nonce'] ) ), 'morntag_docs_save' ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Security check failed. Please try again.', 'morntag-docs' ) . '</p></div>';
				}
			);
			return;
		}

		// Check permissions.
		if ( ! $this->user_can( 'mcc_edit_docs' ) ) {
			return;
		}

		// Process form data.
		$post_title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$post_content = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';
		$post_parent  = isset( $_POST['parent_id'] ) ? absint( $_POST['parent_id'] ) : 0;
		$post_order   = isset( $_POST['menu_order'] ) ? absint( $_POST['menu_order'] ) : 0;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- doc_id is read-only.
		$edit_post_id = isset( $_GET['doc_id'] ) ? absint( $_GET['doc_id'] ) : 0;

		// Validate title.
		if ( empty( $post_title ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Title is required.', 'morntag-docs' ) . '</p></div>';
				}
			);
			return;
		}

		// Prepare post data.
		$post_data = array(
			'post_title'   => $post_title,
			'post_content' => $post_content,
			'post_status'  => 'publish',
			'post_type'    => self::POST_TYPE,
			'post_parent'  => $post_parent,
			'menu_order'   => $post_order,
		);

		// Update or insert - use direct database method to avoid memory exhaustion.
		if ( $edit_post_id ) {
			$post_data['ID'] = $edit_post_id;
			$result          = $this->direct_update_post( $post_data );
		} else {
			$result = $this->direct_insert_post( $post_data );
		}

		if ( is_wp_error( $result ) ) {
			$error_message = $result->get_error_message();
			add_action(
				'admin_notices',
				function () use ( $error_message ) {
					echo '<div class="notice notice-error"><p>' . esc_html( $error_message ) . '</p></div>';
				}
			);
			return;
		}

		$saved_post_id = $edit_post_id ? $edit_post_id : $result;

		// Save category taxonomy.
		$category = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
		if ( ! empty( $category ) ) {
			wp_set_object_terms( $saved_post_id, $category, self::TAXONOMY );
		} else {
			wp_set_object_terms( $saved_post_id, array(), self::TAXONOMY );
		}

		// Save frontmatter meta.
		$frontmatter_data = array(
			'category' => $category,
			'order'    => $post_order,
		);

		update_post_meta( $saved_post_id, '_mcc_doc_frontmatter', $frontmatter_data );
		update_post_meta( $saved_post_id, '_mcc_doc_type', 'custom' );

		// Redirect to view the saved document.
		$redirect_url = add_query_arg(
			array(
				'page'   => 'mcc-documentation',
				'action' => 'view',
				'doc_id' => $saved_post_id,
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Handle document deletion
	 *
	 * Processes delete action with nonce verification and proper redirect.
	 *
	 * @hook admin_init 10 0
	 */
	public function handle_delete_action(): void {
		// Check if we're on our admin page with delete action.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Only checking parameters.
		if ( ! isset( $_GET['page'] ) || 'mcc-documentation' !== $_GET['page'] ) {
			return;
		}

		if ( ! isset( $_GET['action'] ) || 'delete' !== $_GET['action'] ) {
			return;
		}

		$doc_id = isset( $_GET['doc_id'] ) ? absint( $_GET['doc_id'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! $doc_id ) {
			return;
		}

		// Verify nonce.
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'morntag_docs_delete_' . $doc_id ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Security check failed. Please try again.', 'morntag-docs' ) . '</p></div>';
				}
			);
			return;
		}

		// Check permissions.
		if ( ! $this->user_can( 'mcc_delete_docs' ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'You do not have permission to delete documents.', 'morntag-docs' ) . '</p></div>';
				}
			);
			return;
		}

		// Verify the post exists and is our type.
		$post = get_post( $doc_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Invalid document.', 'morntag-docs' ) . '</p></div>';
				}
			);
			return;
		}

		// Delete the post (move to trash).
		$result = wp_trash_post( $doc_id );

		if ( ! $result ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Failed to delete document.', 'morntag-docs' ) . '</p></div>';
				}
			);
			return;
		}

		// Redirect to documentation list with success message.
		$redirect_url = add_query_arg(
			array(
				'page'    => 'mcc-documentation',
				'deleted' => '1',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * AJAX search handler
	 *
	 * @hook wp_ajax_morntag_docs_search 10 0
	 */
	public function ajax_search_docs(): void {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'morntag_docs_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		// Check capabilities.
		if ( ! $this->user_can( 'mcc_access_docs' ) ) {
			wp_die( 'Insufficient permissions' );
		}

		$query   = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';
		$results = $this->search_service->search( $query );

		wp_send_json_success( $results );
	}

	/**
	 * AJAX handler — return all docs grouped by type for the doc picker modal
	 *
	 * Returns module READMEs, dev docs, and custom posts so the editor can
	 * build an internal-link picker without a full-text search query.
	 *
	 * @hook wp_ajax_morntag_docs_list 10 0
	 */
	public function ajax_list_docs(): void {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'morntag_docs_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		// Check capabilities.
		if ( ! $this->user_can( 'mcc_access_docs' ) ) {
			wp_die( 'Insufficient permissions' );
		}

		$result = array(
			'module' => array(),
			'docs'   => array(),
			'custom' => array(),
		);

		// Module READMEs.
		foreach ( $this->get_file_scanner()->scan_module_readmes() as $item ) {
			$result['module'][] = array(
				'id'    => $item['path'],
				'title' => $item['title'],
				'url'   => admin_url( 'admin.php?page=mcc-documentation&type=module&path=' . rawurlencode( $item['path'] ) ),
			);
		}

		// Dev docs (.docs directory).
		foreach ( $this->get_file_scanner()->scan_docs_directory() as $item ) {
			$result['docs'][] = array(
				'id'    => $item['path'],
				'title' => $item['title'],
				'url'   => admin_url( 'admin.php?page=mcc-documentation&type=docs&path=' . rawurlencode( $item['path'] ) ),
			);
		}

		// Custom documentation posts.
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		foreach ( $posts as $post ) {
			$result['custom'][] = array(
				'id'    => $post->ID,
				'title' => $post->post_title,
				'url'   => admin_url( 'admin.php?page=mcc-documentation&action=view&doc_id=' . $post->ID ),
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * Extend allowed HTML tags for media embeds in doc content
	 *
	 * Adds iframe, video, and source tags to the kses allowlist so that
	 * media embedded via the editor is preserved when content is saved.
	 *
	 * @filter wp_kses_allowed_html 10 2
	 * @param  array<string,array<string,bool>> $tags    Allowed HTML tags and attributes.
	 * @param  string                           $context The context for which the tags are allowed.
	 * @return array<string,array<string,bool>> Modified allowed tags.
	 */
	public function allow_media_html_tags( array $tags, string $context ): array {
		if ( 'post' !== $context ) {
			return $tags;
		}

		$tags['iframe'] = array(
			'src'             => true,
			'width'           => true,
			'height'          => true,
			'frameborder'     => true,
			'allowfullscreen' => true,
			'allow'           => true,
			'class'           => true,
			'style'           => true,
		);

		$tags['video'] = array(
			'src'      => true,
			'controls' => true,
			'width'    => true,
			'height'   => true,
			'preload'  => true,
			'class'    => true,
			'style'    => true,
		);

		$tags['source'] = array(
			'src'  => true,
			'type' => true,
		);

		return $this->sanitize_iframe_sources( $tags );
	}

	/**
	 * Placeholder for iframe source sanitization
	 *
	 * Hook point for additional iframe source validation. URL-level
	 * enforcement is handled at render time via CSP or output filtering.
	 *
	 * @param  array<string,array<string,bool>> $tags Allowed HTML tags and attributes.
	 * @return array<string,array<string,bool>> Tags, unmodified at this stage.
	 */
	private function sanitize_iframe_sources( array $tags ): array {
		return $tags;
	}

	/**
	 * Direct database insert to bypass wp_insert_post memory issues
	 *
	 * @param array<string,mixed> $post_data Post data array.
	 * @return int|\WP_Error Post ID on success, WP_Error on failure.
	 */
	private function direct_insert_post( array $post_data ) {
		global $wpdb;

		// Prepare post data for direct insert.
		$insert_data = array(
			'post_author'           => get_current_user_id(),
			'post_date'             => current_time( 'mysql' ),
			'post_date_gmt'         => current_time( 'mysql', 1 ),
			'post_content'          => $post_data['post_content'],
			'post_title'            => $post_data['post_title'],
			'post_excerpt'          => '',
			'post_status'           => $post_data['post_status'],
			'comment_status'        => 'closed',
			'ping_status'           => 'closed',
			'post_password'         => '',
			'post_name'             => sanitize_title( $post_data['post_title'] ),
			'to_ping'               => '',
			'pinged'                => '',
			'post_modified'         => current_time( 'mysql' ),
			'post_modified_gmt'     => current_time( 'mysql', 1 ),
			'post_content_filtered' => '',
			'post_parent'           => $post_data['post_parent'],
			'guid'                  => '',
			'menu_order'            => $post_data['menu_order'],
			'post_type'             => $post_data['post_type'],
			'post_mime_type'        => '',
			'comment_count'         => 0,
		);

		// Insert into database.
		$result = $wpdb->insert( $wpdb->posts, $insert_data );

		if ( false === $result ) {
			return new \WP_Error( 'db_insert_error', 'Failed to insert post into database' );
		}

		$post_id = $wpdb->insert_id;

		// Verify what was actually inserted.
		$check = $wpdb->get_row( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $post_id ) );
		if ( $check ) {
			// Update guid.
			$wpdb->update(
				$wpdb->posts,
				array( 'guid' => get_permalink( $post_id ) ),
				array( 'ID' => $post_id )
			);

			// Clear post cache.
			clean_post_cache( $post_id );
		}

		return $post_id;
	}

	/**
	 * Direct database update to bypass wp_update_post memory issues
	 *
	 * @param array<string,mixed> $post_data Post data array with ID.
	 * @return int|\WP_Error Post ID on success, WP_Error on failure.
	 */
	private function direct_update_post( array $post_data ) {
		global $wpdb;

		$post_id = $post_data['ID'];

		// Prepare update data.
		$update_data = array(
			'post_content'      => $post_data['post_content'],
			'post_title'        => $post_data['post_title'],
			'post_name'         => sanitize_title( $post_data['post_title'] ),
			'post_modified'     => current_time( 'mysql' ),
			'post_modified_gmt' => current_time( 'mysql', 1 ),
			'post_parent'       => $post_data['post_parent'],
			'menu_order'        => $post_data['menu_order'],
		);

		// Update in database.
		$result = $wpdb->update(
			$wpdb->posts,
			$update_data,
			array( 'ID' => $post_id )
		);

		if ( false === $result ) {
			return new \WP_Error( 'db_update_error', 'Failed to update post in database' );
		}

		// Clear post cache.
		clean_post_cache( $post_id );

		return $post_id;
	}
}
