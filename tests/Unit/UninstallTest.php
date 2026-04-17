<?php

namespace Morntag\WpDocsManager\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WP_Test_Role;

/**
 * Phase 1 acceptance: uninstalling the plugin must
 *   - delete the `docsmanager_settings` option
 *   - delete the two FileScanner transients
 *     (`morntag_docs_module_readmes_cache`, `morntag_docs_files_cache`)
 *   - strip the three `docsmanager_*_docs` caps from every role
 *
 * ASSUMPTION: the implementer exposes the uninstall handler as
 * Morntag\WpDocsManager\UninstallHandler::run(). An `uninstall.php` at the
 * repo root is the WP-standard dispatcher, but keeping the logic in a
 * namespaced class makes it unit-testable. If the implementer chooses a
 * different location, this test file must be updated to match.
 */
class UninstallTest extends TestCase {

	protected function setUp(): void {
		wp_test_reset_recorders();

		// Seed multiple roles so we can assert remove_cap() runs on all of them.
		$GLOBALS['wp_test_roles'] = array(
			'administrator' => new WP_Test_Role(
				'administrator',
				array(
					'docsmanager_access_docs' => true,
					'docsmanager_edit_docs'   => true,
					'docsmanager_delete_docs' => true,
					'manage_options'          => true,
				)
			),
			'editor'        => new WP_Test_Role(
				'editor',
				array( 'docsmanager_access_docs' => true )
			),
			'subscriber'    => new WP_Test_Role( 'subscriber' ),
		);
	}

	public function test_uninstall_class_exists(): void {
		$this->assertTrue(
			class_exists( 'Morntag\\WpDocsManager\\UninstallHandler' ),
			'Expected class Morntag\\WpDocsManager\\UninstallHandler with a static run() method.'
		);
	}

	public function test_uninstall_run_method_exists(): void {
		$this->assertTrue(
			class_exists( 'Morntag\\WpDocsManager\\UninstallHandler' ),
			'Uninstall class missing.'
		);
		$this->assertTrue(
			method_exists( 'Morntag\\WpDocsManager\\UninstallHandler', 'run' ),
			'Expected Uninstall::run() method.'
		);
	}

	public function test_run_deletes_settings_option(): void {
		$this->assertTrue( class_exists( 'Morntag\\WpDocsManager\\UninstallHandler' ) );

		call_user_func( array( 'Morntag\\WpDocsManager\\UninstallHandler', 'run' ) );

		$this->assertContains(
			'docsmanager_settings',
			$GLOBALS['wp_test_deleted_options'],
			"Uninstall must delete_option('docsmanager_settings')."
		);
	}

	public function test_run_deletes_scanner_transients(): void {
		$this->assertTrue( class_exists( 'Morntag\\WpDocsManager\\UninstallHandler' ) );

		call_user_func( array( 'Morntag\\WpDocsManager\\UninstallHandler', 'run' ) );

		// Transient names pulled from Services/FileScanner.php.
		$expected = array(
			'morntag_docs_module_readmes_cache',
			'morntag_docs_files_cache',
		);

		foreach ( $expected as $transient ) {
			$this->assertContains(
				$transient,
				$GLOBALS['wp_test_deleted_transients'],
				"Uninstall must delete_transient('{$transient}')."
			);
		}
	}

	public function test_run_removes_all_three_caps_from_every_role(): void {
		$this->assertTrue( class_exists( 'Morntag\\WpDocsManager\\UninstallHandler' ) );

		call_user_func( array( 'Morntag\\WpDocsManager\\UninstallHandler', 'run' ) );

		$expected_caps = array( 'docsmanager_access_docs', 'docsmanager_edit_docs', 'docsmanager_delete_docs' );

		foreach ( $GLOBALS['wp_test_roles'] as $role_name => $role ) {
			/** @var WP_Test_Role $role */
			foreach ( $expected_caps as $cap ) {
				$this->assertContains(
					$cap,
					$role->removed_caps,
					"Uninstall must call remove_cap('{$cap}') on role '{$role_name}'."
				);
			}
		}
	}
}
