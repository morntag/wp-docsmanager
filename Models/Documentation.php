<?php
/**
 * Documentation Custom Post Type and Taxonomy Registration
 *
 * - Registers mcc_documentation CPT (hierarchical, private, custom capabilities)
 * - Registers mcc_doc_category taxonomy (hierarchical, private)
 * - Creates default category terms
 *
 * @package Morntag\WpDocsManager
 * @subpackage Models
 */

namespace Morntag\WpDocsManager\Models;

/**
 * Documentation Custom Post Type
 */
class Documentation {

	const POST_TYPE = 'mcc_documentation';
	const TAXONOMY  = 'mcc_doc_category';

	/**
	 * Register CPT and taxonomy
	 *
	 * Called from DocsManager::init() which runs on 'init' hook.
	 * Methods are called directly since we're already in the init hook.
	 */
	public function register(): void {
		$this->register_post_type();
		$this->register_taxonomy();
		$this->add_default_terms();
	}

	/**
	 * Register mcc_documentation custom post type
	 *
	 * Post Meta Fields Used:
	 * - _mcc_doc_type: 'custom' | 'module' | 'docs' (source type)
	 * - _mcc_doc_source_path: Original file path for readonly docs
	 * - _mcc_doc_frontmatter: Serialized frontmatter data
	 * - _mcc_doc_order: Display order within parent
	 * - _mcc_doc_readonly: Boolean flag for readonly status
	 *
	 * @hook init 10 0
	 */
	public function register_post_type(): void {
		$labels = array(
			'name'               => 'Documentation',
			'singular_name'      => 'Document',
			'add_new'            => 'Add New',
			'add_new_item'       => 'Add New Document',
			'edit_item'          => 'Edit Document',
			'new_item'           => 'New Document',
			'view_item'          => 'View Document',
			'search_items'       => 'Search Documentation',
			'not_found'          => 'No documents found',
			'not_found_in_trash' => 'No documents found in trash',
			'parent_item_colon'  => 'Parent Document:',
			'menu_name'          => 'Documentation',
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => false, // We'll use custom UI.
			'show_in_menu'       => false,
			'query_var'          => false,
			'rewrite'            => false,
			'capability_type'    => array( 'mcc_doc', 'mcc_docs' ), // Use array form for proper pluralization.
			'map_meta_cap'       => true,
			'hierarchical'       => true,
			'supports'           => array( 'title', 'editor', 'author', 'page-attributes', 'revisions' ),
			'has_archive'        => false,
			'show_in_rest'       => false,
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Register mcc_doc_category taxonomy
	 *
	 * @hook init 10 0
	 */
	public function register_taxonomy(): void {
		$labels = array(
			'name'              => 'Categories',
			'singular_name'     => 'Category',
			'search_items'      => 'Search Categories',
			'all_items'         => 'All Categories',
			'parent_item'       => 'Parent Category',
			'parent_item_colon' => 'Parent Category:',
			'edit_item'         => 'Edit Category',
			'update_item'       => 'Update Category',
			'add_new_item'      => 'Add New Category',
			'new_item_name'     => 'New Category Name',
			'menu_name'         => 'Categories',
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'hierarchical'       => true,
			'show_ui'            => false,
			'show_in_menu'       => false,
			'show_in_nav_menus'  => false,
			'show_tagcloud'      => false,
			'show_in_quick_edit' => false,
			'show_admin_column'  => false,
			'rewrite'            => false,
		);

		register_taxonomy( self::TAXONOMY, array( self::POST_TYPE ), $args );
	}

	/**
	 * Create default category terms
	 *
	 * @hook init 10 0
	 */
	public function add_default_terms(): void {
		$terms = array( 'Modules', 'Development', 'Setup', 'Configuration' );

		foreach ( $terms as $term ) {
			if ( ! term_exists( $term, self::TAXONOMY ) ) {
				wp_insert_term( $term, self::TAXONOMY );
			}
		}
	}
}
