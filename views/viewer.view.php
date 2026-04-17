<?php
/**
 * Document Viewer
 *
 * Displays documentation content with:
 * - Support for module, docs, and custom post types
 * - Table of contents generation
 * - Readonly flag for file-based docs
 * - Edit button for custom docs
 *
 * @package    Morntag\WpDocsManager
 * @subpackage Views
 *
 * @var \Morntag\WpDocsManager\DocsManager $this
 */

use Morntag\WpDocsManager\Models\Documentation;

// Security check.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Variables from admin-page.view.php scope, with direct $_GET fallback to guard against
// the variable being lost between admin-page and viewer in some server environments.
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only display.
$doc_type       = isset( $doc_type ) ? $doc_type : ( isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : '' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$doc_path       = isset( $doc_path ) ? $doc_path : ( isset( $_GET['path'] ) ? sanitize_text_field( wp_unslash( $_GET['path'] ) ) : '' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$doc_id         = isset( $doc_id ) ? $doc_id : ( isset( $_GET['doc_id'] ) ? absint( $_GET['doc_id'] ) : 0 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$current_action = isset( $current_action ) ? $current_action : ( isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$doc_content = '';
$doc_title   = '';
$is_readonly = false;
$raw_content = '';

// Load content based on type.
if ( 'module' === $doc_type || 'docs' === $doc_type ) {
	// File-based documentation (readonly).
	$is_readonly = true;

	if ( ! empty( $doc_path ) && file_exists( $doc_path ) ) {
		// Verify the path is within allowed directories.
		$real_path     = realpath( $doc_path );
		$allowed_roots = $this->get_allowed_roots();
		$is_allowed    = false;
		if ( false !== $real_path ) {
			foreach ( $allowed_roots as $root ) {
				$real_root = realpath( $root );
				if ( false !== $real_root && 0 === strpos( $real_path, $real_root ) ) {
					$is_allowed = true;
					break;
				}
			}
		}

		if ( $is_allowed && is_string( $real_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file read.
			$file_content = file_get_contents( $real_path );
			if ( false !== $file_content ) {
				$parsed      = $this->get_markdown_parser()->parse_frontmatter( $file_content );
				$raw_content = $parsed['content'];
				$doc_content = $this->get_markdown_parser()->parse_markdown( $file_content );

				// Extract title.
				if ( ! empty( $parsed['frontmatter']['title'] ) ) {
					$doc_title = $parsed['frontmatter']['title'];
				} else {
					$doc_title = basename( $doc_path, '.md' );
				}
			}
		}
	}
} elseif ( $doc_id > 0 ) {
	// Custom post documentation.
	$doc_post = get_post( $doc_id );

	if ( $doc_post ) {
		if ( Documentation::POST_TYPE === $doc_post->post_type ) {
			$doc_title   = $doc_post->post_title;
			$raw_content = $doc_post->post_content;
			$doc_content = $this->get_markdown_parser()->parse_markdown( $doc_post->post_content );
		}
	}
}

// Generate TOC from raw markdown content.
$toc = array();
if ( ! empty( $raw_content ) ) {
	$toc = $this->get_markdown_parser()->generate_toc( $raw_content );
}

// Show welcome message only when no document was requested at all.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display.
if ( empty( $doc_content ) && empty( $doc_type ) && 0 === $doc_id && ! isset( $_GET['doc_id'] ) ) :
	?>
	<div class="mcc-docs-welcome">
		<h2><?php esc_html_e( 'Welcome to Documentation', 'morntag-docs' ); ?></h2>
		<p><?php esc_html_e( 'Select a document from the sidebar to view its contents.', 'morntag-docs' ); ?></p>

		<div class="mcc-docs-quick-links">
			<h3><?php esc_html_e( 'Quick Links', 'morntag-docs' ); ?></h3>
			<ul>
				<li>
					<strong><?php esc_html_e( 'Module Documentation', 'morntag-docs' ); ?></strong>
					<?php esc_html_e( '- README files from each module', 'morntag-docs' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Development Documentation', 'morntag-docs' ); ?></strong>
					<?php esc_html_e( '- Project guides and architecture docs', 'morntag-docs' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Custom Documentation', 'morntag-docs' ); ?></strong>
					<?php esc_html_e( '- User-created documentation', 'morntag-docs' ); ?>
				</li>
			</ul>

			<?php if ( $this->user_can( 'docsmanager_edit_docs' ) ) : ?>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=mcc-documentation&action=new' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Create New Document', 'morntag-docs' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return;
endif;

// Show error if document not found.
if ( empty( $doc_content ) && empty( $doc_title ) ) :
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'Document not found or could not be loaded.', 'morntag-docs' ); ?></p>
	</div>
	<?php
	return;
endif;

?>
<div class="mcc-docs-viewer">
	<div class="mcc-docs-header">
		<h2><?php echo esc_html( $doc_title ); ?></h2>

		<div class="mcc-docs-header-actions">
			<?php if ( $is_readonly ) : ?>
				<span class="mcc-docs-badge mcc-docs-badge-readonly">
					<?php esc_html_e( 'Read Only', 'morntag-docs' ); ?>
				</span>
			<?php elseif ( $doc_id > 0 ) : ?>
				<?php if ( $this->user_can( 'docsmanager_edit_docs' ) ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=mcc-documentation&action=edit&doc_id=' . $doc_id ) ); ?>" class="button">
						<?php esc_html_e( 'Edit', 'morntag-docs' ); ?>
					</a>
				<?php endif; ?>
				<?php if ( $this->user_can( 'docsmanager_delete_docs' ) ) : ?>
					<?php
					$delete_url = wp_nonce_url(
						add_query_arg(
							array(
								'page'   => 'mcc-documentation',
								'action' => 'delete',
								'doc_id' => $doc_id,
							),
							admin_url( 'admin.php' )
						),
						'morntag_docs_delete_' . $doc_id
					);
					?>
					<a href="<?php echo esc_url( $delete_url ); ?>"
						class="button button-link-delete"
						onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this document?', 'morntag-docs' ); ?>');">
						<?php esc_html_e( 'Delete', 'morntag-docs' ); ?>
					</a>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( ! empty( $toc ) ) : ?>
		<div class="mcc-docs-toc">
			<h3><?php esc_html_e( 'Table of Contents', 'morntag-docs' ); ?></h3>
			<ul>
				<?php foreach ( $toc as $item ) : ?>
					<li class="toc-level-<?php echo absint( $item['level'] ); ?>">
						<a href="#<?php echo esc_attr( $item['id'] ); ?>">
							<?php echo esc_html( $item['title'] ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<div class="mcc-docs-content-body">
		<?php
		if ( empty( $doc_content ) ) {
			// Show a message if the document has no content.
			?>
			<div class="notice notice-info inline">
				<p><?php esc_html_e( 'This document has no content yet.', 'morntag-docs' ); ?></p>
				<?php if ( ! $is_readonly && $this->user_can( 'docsmanager_edit_docs' ) && $doc_id > 0 ) : ?>
					<p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=mcc-documentation&action=edit&doc_id=' . $doc_id ) ); ?>" class="button button-primary">
							<?php esc_html_e( 'Add Content', 'morntag-docs' ); ?>
						</a>
					</p>
				<?php endif; ?>
			</div>
			<?php
		} else {
			// Output parsed HTML content with allowed tags.
			echo wp_kses_post( $doc_content );
		}
		?>
	</div>

	<?php if ( ! $is_readonly && $doc_id > 0 ) : ?>
		<div class="mcc-docs-footer">
			<p class="mcc-docs-meta">
				<?php
				$post_obj = get_post( $doc_id );
				if ( $post_obj ) {
					$author_id   = (int) $post_obj->post_author;
					$author_name = get_the_author_meta( 'display_name', $author_id );
					$post_date   = get_the_date( '', $post_obj );
					printf(
						/* translators: 1: Author name, 2: Date */
						esc_html__( 'Created by %1$s on %2$s', 'morntag-docs' ),
						esc_html( (string) $author_name ),
						esc_html( (string) $post_date )
					);

					if ( $post_obj->post_modified !== $post_obj->post_date ) {
						echo ' | ';
						$modified_date = get_the_modified_date( '', $post_obj );
						printf(
							/* translators: %s: Date */
							esc_html__( 'Last modified: %s', 'morntag-docs' ),
							esc_html( (string) $modified_date )
						);
					}
				}
				?>
			</p>
		</div>
	<?php endif; ?>
</div>
