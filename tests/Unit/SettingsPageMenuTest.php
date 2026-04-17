<?php

namespace Morntag\WpDocsManager\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Phase 2 acceptance: `Documentation -> Settings` submenu registers via
 *   add_submenu_page(
 *     parent_slug: 'mcc-documentation',
 *     menu_slug:   'mcc-documentation-settings',
 *     capability:  'manage_options',
 *     ...
 *   )
 *
 * ASSUMPTION: the implementer exposes
 *   Morntag\WpDocsManager\Services\SettingsPage
 * with a public instance method that hooks into WordPress `admin_menu` and
 * calls add_submenu_page() with the documented arguments. The test invokes
 * the menu-registration method directly (most likely `add_menu()` or
 * `register_menu()`) — we discover it by probing common names and fall back
 * to firing `admin_menu` via the recorded actions list.
 */
class SettingsPageMenuTest extends TestCase {

	private const CLASS_FQN           = 'Morntag\\WpDocsManager\\Services\\SettingsPage';
	private const EXPECTED_PARENT     = 'mcc-documentation';
	private const EXPECTED_SLUG       = 'mcc-documentation-settings';
	private const EXPECTED_CAPABILITY = 'manage_options';

	protected function setUp(): void {
		wp_test_reset_recorders();
	}

	public function test_settings_page_class_exists(): void {
		$this->assertTrue(
			class_exists( self::CLASS_FQN ),
			'Expected class Morntag\\WpDocsManager\\Services\\SettingsPage.'
		);
	}

	/**
	 * Try to register the submenu by calling whichever public method the
	 * implementer chose (add_menu / register_menu / register / register_submenu /
	 * add_submenu). If none exist, fall back to invoking any `admin_menu`
	 * callback the SettingsPage registered via add_action().
	 */
	private function invoke_registration(): void {
		$page = new ( self::CLASS_FQN )();

		$candidates = array( 'add_menu', 'register_menu', 'register', 'register_submenu', 'add_submenu', 'admin_menu' );
		foreach ( $candidates as $method ) {
			if ( method_exists( $page, $method ) ) {
				$page->$method();
				return;
			}
		}

		// Fall back: find a registered `admin_menu` action whose callback points at this instance.
		foreach ( $GLOBALS['wp_test_actions'] as $action ) {
			if ( 'admin_menu' !== ( $action['hook'] ?? '' ) ) {
				continue;
			}
			$cb = $action['callback'] ?? null;
			if ( is_array( $cb ) && isset( $cb[0] ) && $cb[0] instanceof \Morntag\WpDocsManager\Services\SettingsPage ) {
				call_user_func( $cb );
				return;
			}
		}

		$this->fail(
			'SettingsPage has no obvious menu-registration entry point. Expected one of: '
			. implode( ', ', $candidates ) . ' — or an add_action(\'admin_menu\', ...) binding in its constructor.'
		);
	}

	public function test_settings_submenu_is_registered_with_correct_parent_and_slug(): void {
		$this->assertTrue( class_exists( self::CLASS_FQN ) );

		$this->invoke_registration();

		$this->assertNotEmpty(
			$GLOBALS['wp_test_submenu_pages'],
			'SettingsPage must call add_submenu_page() during menu registration.'
		);

		$match = null;
		foreach ( $GLOBALS['wp_test_submenu_pages'] as $entry ) {
			if ( ( $entry['menu_slug'] ?? '' ) === self::EXPECTED_SLUG ) {
				$match = $entry;
				break;
			}
		}

		$this->assertNotNull(
			$match,
			"Expected a submenu registration with menu_slug '" . self::EXPECTED_SLUG . "'."
		);
		$this->assertSame(
			self::EXPECTED_PARENT,
			$match['parent_slug'],
			"Submenu parent_slug must be '" . self::EXPECTED_PARENT . "'."
		);
		$this->assertSame(
			self::EXPECTED_CAPABILITY,
			$match['capability'],
			"Submenu capability must be '" . self::EXPECTED_CAPABILITY . "'."
		);
	}

	public function test_settings_submenu_callback_is_callable(): void {
		$this->assertTrue( class_exists( self::CLASS_FQN ) );

		$this->invoke_registration();

		$match = null;
		foreach ( $GLOBALS['wp_test_submenu_pages'] as $entry ) {
			if ( ( $entry['menu_slug'] ?? '' ) === self::EXPECTED_SLUG ) {
				$match = $entry;
				break;
			}
		}

		$this->assertNotNull( $match );
		$this->assertTrue(
			is_callable( $match['callback'] ),
			'Submenu callback must be callable so WordPress can render the settings page.'
		);
	}
}
