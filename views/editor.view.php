<?php
/**
 * Document Editor
 *
 * Form for creating/editing custom documentation with:
 * - Title input
 * - Category select
 * - Parent document select
 * - Menu order input
 * - Markdown editor with Tiptap initialization
 * - Form submission handling with nonce verification
 * - Frontmatter meta saving
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

// Variables from admin-page.view.php scope.
$doc_id         = isset( $doc_id ) ? $doc_id : 0; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$current_action = isset( $current_action ) ? $current_action : 'list'; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$edit_post_id = $doc_id;
$edit_post    = $edit_post_id ? get_post( $edit_post_id ) : null;

// Verify post type if editing.
if ( $edit_post && Documentation::POST_TYPE !== $edit_post->post_type ) {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'Invalid document.', 'morntag-docs' ); ?></p>
	</div>
	<?php
	return;
}

// Default values.
$doc_title      = $edit_post ? $edit_post->post_title : '';
$doc_content    = $edit_post ? $edit_post->post_content : '';
$parent_id      = $edit_post ? $edit_post->post_parent : 0;
$doc_menu_order = $edit_post ? $edit_post->menu_order : 0;

// Get frontmatter.
$frontmatter = array();
if ( $edit_post ) {
	$meta = get_post_meta( $edit_post_id, '_mcc_doc_frontmatter', true );
	if ( $meta ) {
		$frontmatter = maybe_unserialize( $meta );
	}
}

// Form submission is handled in DocsManager::handle_form_submission() via admin_init hook
// to ensure redirects work properly before headers are sent.

// Enqueue Tiptap - get DocsManager directory URL.
$docs_manager_url = plugin_dir_url( dirname( __DIR__ ) ) . 'DocsManager/';

wp_enqueue_script(
	'mcc-docs-tiptap-editor',
	$docs_manager_url . 'assets/js/editor.bundle.js',
	array(),
	'1.0.0',
	true
);

?>
<div class="mcc-docs-editor">
	<form method="post" action="">
		<?php wp_nonce_field( 'morntag_docs_save', 'morntag_docs_nonce' ); ?>

		<div class="mcc-docs-editor-header">
			<input type="text"
					name="title"
					id="title"
					value="<?php echo esc_attr( $doc_title ); ?>"
					placeholder="<?php esc_attr_e( 'Document Title', 'morntag-docs' ); ?>"
					class="mcc-docs-title-input"
					required />
		</div>

		<div class="mcc-docs-editor-meta">
			<div class="mcc-docs-meta-row">
				<label for="category"><?php esc_html_e( 'Category:', 'morntag-docs' ); ?></label>
				<select name="category" id="category">
					<option value=""><?php esc_html_e( 'Select Category', 'morntag-docs' ); ?></option>
					<?php
					$categories = get_terms(
						array(
							'taxonomy'   => Documentation::TAXONOMY,
							'hide_empty' => false,
						)
					);

					if ( ! is_wp_error( $categories ) ) :
						foreach ( $categories as $category ) :
							$selected_cat = isset( $frontmatter['category'] ) ? $frontmatter['category'] : '';
							?>
							<option value="<?php echo esc_attr( $category->slug ); ?>"
								<?php selected( $selected_cat, $category->slug ); ?>>
								<?php echo esc_html( $category->name ); ?>
							</option>
							<?php
						endforeach;
					endif;
					?>
				</select>
			</div>

			<div class="mcc-docs-meta-row">
				<label for="parent_id"><?php esc_html_e( 'Parent Document:', 'morntag-docs' ); ?></label>
				<select name="parent_id" id="parent_id">
					<option value="0"><?php esc_html_e( 'None (Top Level)', 'morntag-docs' ); ?></option>
					<?php
					$parent_docs = get_posts(
						array(
							'post_type'      => Documentation::POST_TYPE,
							'post_status'    => 'publish',
							'posts_per_page' => -1,
							'exclude'        => $edit_post_id ? array( $edit_post_id ) : array(),
							'orderby'        => 'title',
							'order'          => 'ASC',
						)
					);

					foreach ( $parent_docs as $parent ) :
						?>
						<option value="<?php echo absint( $parent->ID ); ?>"
							<?php selected( $parent_id, $parent->ID ); ?>>
							<?php echo esc_html( $parent->post_title ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="mcc-docs-meta-row">
				<label for="menu_order"><?php esc_html_e( 'Display Order:', 'morntag-docs' ); ?></label>
				<input type="number"
						name="menu_order"
						id="menu_order"
						value="<?php echo esc_attr( (string) $doc_menu_order ); ?>"
						min="0" />
			</div>

			</div>

		<div class="mcc-docs-editor-content">
			<div id="mcc-docs-editor-container"
				class="mcc-docs-tiptap-container"
				data-doc-id="<?php echo $edit_post_id ? absint( $edit_post_id ) : 'new'; ?>">
			</div>
			<textarea name="content" id="mcc-markdown-content" class="hidden"><?php echo esc_textarea( $doc_content ); ?></textarea>
		</div>

		<div class="mcc-docs-editor-actions">
			<button type="submit" class="button button-primary">
				<?php echo $edit_post_id ? esc_html__( 'Update Document', 'morntag-docs' ) : esc_html__( 'Create Document', 'morntag-docs' ); ?>
			</button>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=mcc-documentation' ) ); ?>" class="button">
				<?php esc_html_e( 'Cancel', 'morntag-docs' ); ?>
			</a>

			<?php if ( $edit_post_id && $this->user_can( 'docsmanager_delete_docs' ) ) : ?>
				<?php
				$delete_url = wp_nonce_url(
					add_query_arg(
						array(
							'page'   => 'mcc-documentation',
							'action' => 'delete',
							'doc_id' => $edit_post_id,
						),
						admin_url( 'admin.php' )
					),
					'morntag_docs_delete_' . $edit_post_id
				);
				?>
				<a href="<?php echo esc_url( $delete_url ); ?>"
					class="button button-link-delete"
					onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this document?', 'morntag-docs' ); ?>');">
					<?php esc_html_e( 'Delete', 'morntag-docs' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	var container = document.getElementById('mcc-docs-editor-container');
	var hiddenTextarea = document.getElementById('mcc-markdown-content');

	if (container && window.MorntagDocsEditor) {
		var initialContent = hiddenTextarea ? hiddenTextarea.value : '';

		window.MorntagDocsEditor.init({
			element: container,
			content: initialContent,
			onChange: function(markdown) {
				if (hiddenTextarea) {
					hiddenTextarea.value = markdown;
				}
			},
			onSave: function(markdown) {
				console.log('MCC Docs: Autosaved');
			}
		});
	}
});
</script>
