<?php
/**
 * Documentation Admin Page
 *
 * Main admin page with:
 * - Sidebar navigation showing Module, Development, and Custom Documentation
 * - Search input
 * - Content area routing to viewer or editor
 * - Multisite notice
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

// Get current action and parameters.
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only operations.
$current_action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';
$doc_id         = isset( $_GET['doc_id'] ) ? absint( $_GET['doc_id'] ) : 0;
$doc_type       = isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : '';
$doc_path       = isset( $_GET['path'] ) ? sanitize_text_field( wp_unslash( $_GET['path'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

?>
<div class="wrap mcc-docs-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Documentation', 'morntag-docs' ); ?></h1>

	<?php
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display.
	if ( isset( $_GET['deleted'] ) && '1' === $_GET['deleted'] ) :
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Document deleted successfully.', 'morntag-docs' ); ?></p>
		</div>
	<?php endif; ?>

	<?php
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display.
	if ( isset( $_GET['rescanned'] ) && '1' === $_GET['rescanned'] ) :
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Documentation scanner caches cleared.', 'wp-docsmanager' ); ?></p>
		</div>
	<?php endif; ?>

	<?php
	$docsmanager_settings     = $this->get_settings();
	$docsmanager_modules_on   = ! empty( $docsmanager_settings['modules_scan_enabled'] );
	$docsmanager_docs_on      = ! empty( $docsmanager_settings['docs_scan_enabled'] );
	$docsmanager_any_scan_on  = $docsmanager_modules_on || $docsmanager_docs_on;
	$docsmanager_needs_config = (
		empty( $docsmanager_settings['plugin_slug'] )
		|| ! $docsmanager_any_scan_on
	);
	if ( $docsmanager_needs_config ) :
		$docsmanager_settings_url = admin_url( 'admin.php?page=mcc-documentation-settings' );
		?>
		<div class="notice notice-info">
			<p>
				<?php esc_html_e( 'File-based documentation is not configured yet.', 'wp-docsmanager' ); ?>
				<a href="<?php echo esc_url( $docsmanager_settings_url ); ?>"><?php esc_html_e( 'Open Settings →', 'wp-docsmanager' ); ?></a>
				<?php esc_html_e( 'to pick a plugin and subpaths.', 'wp-docsmanager' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $this->user_can( 'docsmanager_edit_docs' ) && ! in_array( $current_action, array( 'new', 'edit' ), true ) ) : ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=mcc-documentation&action=new' ) ); ?>" class="page-title-action">
			<?php esc_html_e( 'Add New', 'morntag-docs' ); ?>
		</a>
	<?php endif; ?>

	<hr class="wp-header-end">

	<div class="mcc-docs-container">
		<!-- Sidebar Navigation -->
		<div class="mcc-docs-sidebar">
			<div class="mcc-docs-search">
				<input type="text" id="mcc-docs-search" placeholder="<?php esc_attr_e( 'Search documentation...', 'morntag-docs' ); ?>" />
				<?php if ( $docsmanager_any_scan_on ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mcc-docs-rescan">
						<input type="hidden" name="action" value="morntag_docs_rescan" />
						<?php wp_nonce_field( 'morntag_docs_rescan' ); ?>
						<button type="submit" class="button button-secondary mcc-docs-rescan-button" title="<?php esc_attr_e( 'Rescan file-based documentation now', 'wp-docsmanager' ); ?>" aria-label="<?php esc_attr_e( 'Rescan file-based documentation now', 'wp-docsmanager' ); ?>">
							<span class="dashicons dashicons-update" aria-hidden="true"></span>
							<span class="screen-reader-text"><?php esc_html_e( 'Rescan now', 'wp-docsmanager' ); ?></span>
						</button>
					</form>
				<?php endif; ?>
			</div>

			<ul class="mcc-docs-nav">
				<?php if ( $docsmanager_modules_on ) : ?>
					<!-- Module Documentation -->
					<li class="mcc-docs-nav-section">
						<h3 data-section="modules">
							<span class="dashicons dashicons-arrow-down mcc-docs-toggle"></span>
							<?php esc_html_e( 'Module Documentation', 'morntag-docs' ); ?>
						</h3>
						<ul class="mcc-docs-nav-items">
							<?php
							$modules = $this->get_file_scanner()->scan_module_readmes();
							foreach ( $modules as $module ) :
								$module_url = add_query_arg(
									array(
										'page' => 'mcc-documentation',
										'type' => 'module',
										'path' => rawurlencode( $module['path'] ),
									),
									admin_url( 'admin.php' )
								);
								$is_current = ( 'module' === $doc_type && $doc_path === $module['path'] );
								?>
								<li class="<?php echo $is_current ? 'current' : ''; ?>">
									<a href="<?php echo esc_url( $module_url ); ?>">
										<?php echo esc_html( $module['title'] ); ?>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</li>
				<?php endif; ?>

				<?php if ( $docsmanager_docs_on ) : ?>
					<!-- Development Documentation -->
					<li class="mcc-docs-nav-section">
						<h3 data-section="development">
							<span class="dashicons dashicons-arrow-down mcc-docs-toggle"></span>
							<?php esc_html_e( 'Development Documentation', 'morntag-docs' ); ?>
						</h3>
						<ul class="mcc-docs-nav-items">
							<?php
							$docs = $this->get_file_scanner()->scan_docs_directory();
							foreach ( $docs as $doc ) :
								$doc_url    = add_query_arg(
									array(
										'page' => 'mcc-documentation',
										'type' => 'docs',
										'path' => rawurlencode( $doc['path'] ),
									),
									admin_url( 'admin.php' )
								);
								$is_current = ( 'docs' === $doc_type && $doc_path === $doc['path'] );
								?>
								<li class="<?php echo $is_current ? 'current' : ''; ?>">
									<a href="<?php echo esc_url( $doc_url ); ?>">
										<?php echo esc_html( $doc['title'] ); ?>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</li>
				<?php endif; ?>

				<!-- Custom Documentation -->
				<li class="mcc-docs-nav-section">
					<h3 data-section="custom">
						<span class="dashicons dashicons-arrow-down mcc-docs-toggle"></span>
						<?php esc_html_e( 'Custom Documentation', 'morntag-docs' ); ?>
					</h3>
					<ul class="mcc-docs-nav-items">
						<?php
						// Fetch all published docs at once and group by parent.
						$all_custom_docs = get_posts(
							array(
								'post_type'      => Documentation::POST_TYPE,
								'post_status'    => 'publish',
								'posts_per_page' => -1,
								'orderby'        => 'menu_order title',
								'order'          => 'ASC',
							)
						);

						// Build a map: parent_id => array of child WP_Post objects.
						$docs_by_parent = array();
						foreach ( $all_custom_docs as $custom_doc ) {
							$docs_by_parent[ $custom_doc->post_parent ][] = $custom_doc;
						}

						/**
						 * Recursively renders a doc tree level as nested <li> elements.
						 *
						 * @param array $docs_map       All docs grouped by post_parent.
						 * @param int   $parent_id      The parent ID whose children to render.
						 * @param int   $depth          Current nesting depth (0-based).
						 * @param int   $current_doc_id ID of the currently viewed doc.
						 */
						$render_doc_level = null;
						$render_doc_level = function ( array $docs_map, int $parent_id, int $depth, int $current_doc_id ) use ( &$render_doc_level ): void {
							if ( empty( $docs_map[ $parent_id ] ) ) {
								return;
							}

							foreach ( $docs_map[ $parent_id ] as $doc ) {
								$has_children = ! empty( $docs_map[ $doc->ID ] );
								$is_current   = ( $current_doc_id === $doc->ID );

								$view_url = add_query_arg(
									array(
										'page'   => 'mcc-documentation',
										'action' => 'view',
										'doc_id' => $doc->ID,
									),
									admin_url( 'admin.php' )
								);

								$li_classes = array();
								if ( $is_current ) {
									$li_classes[] = 'current';
								}
								if ( $has_children ) {
									$li_classes[] = 'mcc-docs-has-children';
								}

								$li_attrs = ' data-depth="' . esc_attr( (string) $depth ) . '"';
								if ( $has_children ) {
									$li_attrs .= ' data-doc-id="' . esc_attr( (string) $doc->ID ) . '"';
								}
								?>
								<li class="<?php echo esc_attr( implode( ' ', $li_classes ) ); ?>"<?php echo $li_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attributes pre-escaped above. ?>>
									<?php if ( $has_children ) : ?>
										<span class="dashicons dashicons-arrow-down mcc-docs-toggle mcc-docs-doc-toggle"></span>
									<?php endif; ?>
									<a href="<?php echo esc_url( $view_url ); ?>">
										<?php echo esc_html( $doc->post_title ); ?>
									</a>
									<?php if ( $has_children ) : ?>
										<ul class="mcc-docs-nav-items mcc-docs-children">
											<?php $render_doc_level( $docs_map, $doc->ID, $depth + 1, $current_doc_id ); ?>
										</ul>
									<?php endif; ?>
								</li>
								<?php
							}
						};

						$render_doc_level(
							$docs_by_parent,
							0,
							0,
							( 'view' === $current_action ? $doc_id : 0 )
						);
						?>
					</ul>
				</li>
			</ul>
		</div>

		<!-- Content Area -->
		<div class="mcc-docs-content">
			<?php
			// Route to appropriate view.
			switch ( $current_action ) {
				case 'new':
				case 'edit':
					if ( $this->user_can( 'docsmanager_edit_docs' ) ) {
						require __DIR__ . '/editor.view.php';
					} else {
						echo '<div class="notice notice-error"><p>' . esc_html__( 'You do not have permission to edit documents.', 'morntag-docs' ) . '</p></div>';
					}
					break;

				case 'view':
				default:
					require __DIR__ . '/viewer.view.php';
					break;
			}
			?>
		</div>
	</div>
</div>
