<?php

namespace Morntag\WpDocsManager\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WP_Test_Role;

/**
 * Phase 1 acceptance: activating the plugin must grant the three renamed
 * UI-guard capabilities (docsmanager_access_docs, docsmanager_edit_docs,
 * docsmanager_delete_docs) to the administrator role.
 *
 * ASSUMPTION: the implementer exposes the activation handler as
 * Morntag\WpDocsManager\Activation::activate(). If the implementer chooses a
 * different class/method name, this test file must be updated to match.
 */
class ActivationTest extends TestCase {

	protected function setUp(): void {
		wp_test_reset_recorders();

		// Seed the administrator role so get_role('administrator') returns a real recorder.
		$GLOBALS['wp_test_roles']['administrator'] = new WP_Test_Role( 'administrator' );
	}

	public function test_activation_class_exists(): void {
		$this->assertTrue(
			class_exists( 'Morntag\\WpDocsManager\\Activation' ),
			'Expected class Morntag\\WpDocsManager\\Activation with a static activate() method.'
		);
	}

	public function test_activate_method_exists(): void {
		$this->assertTrue(
			class_exists( 'Morntag\\WpDocsManager\\Activation' ),
			'Activation class missing.'
		);
		$this->assertTrue(
			method_exists( 'Morntag\\WpDocsManager\\Activation', 'activate' ),
			'Expected Activation::activate() method.'
		);
	}

	public function test_activate_grants_all_three_caps_to_administrator(): void {
		$this->assertTrue( class_exists( 'Morntag\\WpDocsManager\\Activation' ) );

		call_user_func( array( 'Morntag\\WpDocsManager\\Activation', 'activate' ) );

		/** @var WP_Test_Role $admin */
		$admin = $GLOBALS['wp_test_roles']['administrator'];

		$granted = array_map(
			static fn( array $entry ): string => $entry['cap'],
			$admin->added_caps
		);

		foreach ( array( 'docsmanager_access_docs', 'docsmanager_edit_docs', 'docsmanager_delete_docs' ) as $cap ) {
			$this->assertContains(
				$cap,
				$granted,
				"Activation must call add_cap('{$cap}') on the administrator role."
			);
		}
	}

	public function test_activate_is_safe_when_administrator_role_missing(): void {
		// Simulate an environment where get_role('administrator') returns null.
		$GLOBALS['wp_test_roles'] = array();

		$this->assertTrue( class_exists( 'Morntag\\WpDocsManager\\Activation' ) );

		// Should not throw — activation handlers must be resilient on fresh installs
		// and sites with customised role sets.
		call_user_func( array( 'Morntag\\WpDocsManager\\Activation', 'activate' ) );

		$this->assertTrue( true );
	}
}
