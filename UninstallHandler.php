<?php
/**
 * Plugin uninstall handler.
 *
 * Autoloaded by Composer (PSR-4) as Morntag\WpDocsManager\UninstallHandler so
 * unit tests and in-process callers can invoke the class directly. The
 * WordPress dispatcher lives in the sibling `uninstall.php` entry point.
 *
 * @package Morntag\WpDocsManager
 */

namespace Morntag\WpDocsManager;

/**
 * Handles plugin uninstall tasks.
 *
 * Removes the plugin's option, the FileScanner transients, and strips the
 * three UI-guard capabilities from every role. Posts, taxonomy terms, and
 * post meta are intentionally left intact — that data may belong to a
 * previous baspo-bundled installation and must survive the plugin removal.
 */
class UninstallHandler {

	/**
	 * UI-guard capabilities stripped from every role on uninstall.
	 *
	 * @var string[]
	 */
	private const CAPS = array(
		'docsmanager_access_docs',
		'docsmanager_edit_docs',
		'docsmanager_delete_docs',
	);

	/**
	 * FileScanner transient names cleared on uninstall.
	 *
	 * @var string[]
	 */
	private const TRANSIENTS = array(
		'morntag_docs_module_readmes_cache',
		'morntag_docs_files_cache',
	);

	/**
	 * Run uninstall tasks.
	 */
	public static function run(): void {
		delete_option( 'docsmanager_settings' );

		foreach ( self::TRANSIENTS as $transient ) {
			delete_transient( $transient );
		}

		$wp_roles = wp_roles();
		if ( ! isset( $wp_roles->roles ) || ! is_array( $wp_roles->roles ) ) {
			return;
		}

		foreach ( $wp_roles->roles as $role_name => $_role_data ) {
			$role = $wp_roles->get_role( $role_name );
			if ( null === $role ) {
				continue;
			}
			foreach ( self::CAPS as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}
}
