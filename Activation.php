<?php
/**
 * Plugin activation handler.
 *
 * @package Morntag\WpDocsManager
 */

namespace Morntag\WpDocsManager;

/**
 * Handles plugin activation tasks.
 *
 * Grants the three UI-guard capabilities to the administrator role so that
 * administrators can access, edit, and delete documentation out of the box.
 */
class Activation {

	/**
	 * UI-guard capabilities granted to the administrator role on activation.
	 *
	 * @var string[]
	 */
	private const CAPS = array(
		'docsmanager_access_docs',
		'docsmanager_edit_docs',
		'docsmanager_delete_docs',
	);

	/**
	 * Run activation tasks.
	 *
	 * Idempotent — WP_Role::add_cap() is a no-op when the cap is already set.
	 * Safe to run on fresh installs and sites with customised role sets: if the
	 * administrator role is absent (e.g. renamed or removed), we silently skip.
	 */
	public static function activate(): void {
		$role = get_role( 'administrator' );
		if ( null === $role ) {
			return;
		}

		foreach ( self::CAPS as $cap ) {
			$role->add_cap( $cap );
		}
	}
}
