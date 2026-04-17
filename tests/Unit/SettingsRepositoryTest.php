<?php

namespace Morntag\WpDocsManager\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Phase 2 acceptance: the single WP option `docsmanager_settings` stores an
 * array with the documented keys, and the repository returns sensible
 * fresh-install defaults when nothing is saved yet.
 *
 * Defaults for a fresh install (per plan "Architectural decisions"):
 *   plugin_slug           = ''
 *   modules_scan_enabled  = false
 *   modules_subpath       = 'includes/Modules'
 *   docs_scan_enabled     = false
 *   docs_subpath          = '.docs'
 *
 * ASSUMPTION: the implementer exposes a service
 *   Morntag\WpDocsManager\Services\SettingsRepository
 * with instance methods:
 *   - get(): array
 *   - save(array $settings): void
 * If the implementer names it differently (e.g. static methods, a different
 * class, or builds this directly into DocsManager), flag and update.
 */
class SettingsRepositoryTest extends TestCase {

	private const OPTION_NAME = 'docsmanager_settings';
	private const CLASS_FQN   = 'Morntag\\WpDocsManager\\Services\\SettingsRepository';

	protected function setUp(): void {
		wp_test_reset_recorders();
	}

	public function test_settings_repository_class_exists(): void {
		$this->assertTrue(
			class_exists( self::CLASS_FQN ),
			'Expected class Morntag\\WpDocsManager\\Services\\SettingsRepository.'
		);
	}

	public function test_get_and_save_methods_exist(): void {
		$this->assertTrue( class_exists( self::CLASS_FQN ) );
		$this->assertTrue(
			method_exists( self::CLASS_FQN, 'get' ),
			'SettingsRepository::get() must exist.'
		);
		$this->assertTrue(
			method_exists( self::CLASS_FQN, 'save' ),
			'SettingsRepository::save() must exist.'
		);
	}

	public function test_fresh_install_returns_documented_defaults(): void {
		$this->assertTrue( class_exists( self::CLASS_FQN ) );

		// No option saved — fresh install.
		$this->assertArrayNotHasKey( self::OPTION_NAME, $GLOBALS['wp_test_options'] );

		$repo     = new ( self::CLASS_FQN )();
		$settings = $repo->get();

		$this->assertIsArray( $settings );
		$this->assertSame( '', $settings['plugin_slug'] ?? 'MISSING', 'plugin_slug default must be empty string.' );
		$this->assertSame( false, $settings['modules_scan_enabled'] ?? 'MISSING', 'modules_scan_enabled default must be false.' );
		$this->assertSame( 'includes/Modules', $settings['modules_subpath'] ?? 'MISSING', "modules_subpath default must be 'includes/Modules'." );
		$this->assertSame( false, $settings['docs_scan_enabled'] ?? 'MISSING', 'docs_scan_enabled default must be false.' );
		$this->assertSame( '.docs', $settings['docs_subpath'] ?? 'MISSING', "docs_subpath default must be '.docs'." );
	}

	public function test_get_returns_saved_values(): void {
		$this->assertTrue( class_exists( self::CLASS_FQN ) );

		$saved = array(
			'plugin_slug'          => 'mcc-baspo',
			'modules_scan_enabled' => true,
			'modules_subpath'      => 'includes/Modules',
			'docs_scan_enabled'    => true,
			'docs_subpath'         => '.docs',
		);
		update_option( self::OPTION_NAME, $saved );

		$repo = new ( self::CLASS_FQN )();
		$got  = $repo->get();

		$this->assertSame( 'mcc-baspo', $got['plugin_slug'] );
		$this->assertTrue( $got['modules_scan_enabled'] );
		$this->assertSame( 'includes/Modules', $got['modules_subpath'] );
		$this->assertTrue( $got['docs_scan_enabled'] );
		$this->assertSame( '.docs', $got['docs_subpath'] );
	}

	public function test_save_writes_to_option(): void {
		$this->assertTrue( class_exists( self::CLASS_FQN ) );

		$repo = new ( self::CLASS_FQN )();
		$repo->save(
			array(
				'plugin_slug'          => 'mcc-baspo',
				'modules_scan_enabled' => true,
				'modules_subpath'      => 'includes/Modules',
				'docs_scan_enabled'    => false,
				'docs_subpath'         => '.docs',
			)
		);

		$this->assertArrayHasKey( self::OPTION_NAME, $GLOBALS['wp_test_options'] );
		$written = $GLOBALS['wp_test_options'][ self::OPTION_NAME ];
		$this->assertIsArray( $written );
		$this->assertSame( 'mcc-baspo', $written['plugin_slug'] );
		$this->assertTrue( $written['modules_scan_enabled'] );
		$this->assertFalse( $written['docs_scan_enabled'] );
	}

	public function test_saved_plugin_slug_is_folder_slug_not_plugin_file_path(): void {
		$this->assertTrue( class_exists( self::CLASS_FQN ) );

		// Consumer (admin UI) passes the folder slug, never the full "folder/file.php"
		// that WordPress's plugin_basename() format uses.
		$repo = new ( self::CLASS_FQN )();
		$repo->save(
			array(
				'plugin_slug'          => 'mcc-baspo',
				'modules_scan_enabled' => true,
				'modules_subpath'      => 'includes/Modules',
				'docs_scan_enabled'    => true,
				'docs_subpath'         => '.docs',
			)
		);

		$written = $GLOBALS['wp_test_options'][ self::OPTION_NAME ] ?? array();
		$slug    = $written['plugin_slug'] ?? '';

		$this->assertSame( 'mcc-baspo', $slug );
		$this->assertStringNotContainsString( '/', $slug, 'plugin_slug must be a folder name, not a "folder/file.php" plugin basename.' );
		$this->assertStringNotContainsString( '.php', $slug, 'plugin_slug must never include ".php" — that would be the plugin-file path.' );
	}

	public function test_invalid_subpath_does_not_modify_stored_option(): void {
		$this->assertTrue( class_exists( self::CLASS_FQN ) );

		$existing = array(
			'plugin_slug'          => 'mcc-baspo',
			'modules_scan_enabled' => true,
			'modules_subpath'      => 'includes/Modules',
			'docs_scan_enabled'    => true,
			'docs_subpath'         => '.docs',
		);
		update_option( self::OPTION_NAME, $existing );

		$repo = new ( self::CLASS_FQN )();
		$repo->save(
			array(
				'plugin_slug'          => 'mcc-baspo',
				'modules_scan_enabled' => true,
				// Traversal — must be rejected.
				'modules_subpath'      => '../../etc',
				'docs_scan_enabled'    => true,
				'docs_subpath'         => '.docs',
			)
		);

		$this->assertSame(
			$existing,
			$GLOBALS['wp_test_options'][ self::OPTION_NAME ] ?? null,
			'Option must be unchanged when any subpath is invalid.'
		);
	}

	public function test_invalid_subpath_raises_settings_error(): void {
		$this->assertTrue( class_exists( self::CLASS_FQN ) );

		$repo = new ( self::CLASS_FQN )();
		$repo->save(
			array(
				'plugin_slug'          => 'mcc-baspo',
				'modules_scan_enabled' => true,
				'modules_subpath'      => '/absolute/path',
				'docs_scan_enabled'    => false,
				'docs_subpath'         => '.docs',
			)
		);

		$errors = $GLOBALS['wp_test_settings_errors'] ?? array();
		$this->assertNotEmpty( $errors, 'Invalid subpath must trigger add_settings_error().' );

		$match = null;
		foreach ( $errors as $err ) {
			if ( ( $err['code'] ?? '' ) === 'invalid_subpath' ) {
				$match = $err;
				break;
			}
		}
		$this->assertNotNull( $match, 'Expected settings error with code "invalid_subpath".' );
		$this->assertSame( self::OPTION_NAME, $match['setting'] ?? null );
	}

	public function test_partial_saved_option_is_merged_with_defaults(): void {
		$this->assertTrue( class_exists( self::CLASS_FQN ) );

		// A site upgraded mid-development might have an older partial option shape.
		update_option( self::OPTION_NAME, array( 'plugin_slug' => 'mcc-baspo' ) );

		$repo = new ( self::CLASS_FQN )();
		$got  = $repo->get();

		// Missing keys must fall back to documented defaults, not become null / undefined.
		$this->assertSame( 'mcc-baspo', $got['plugin_slug'] );
		$this->assertSame( 'includes/Modules', $got['modules_subpath'] ?? 'MISSING' );
		$this->assertSame( '.docs', $got['docs_subpath'] ?? 'MISSING' );
		$this->assertFalse( $got['modules_scan_enabled'] ?? 'MISSING' );
		$this->assertFalse( $got['docs_scan_enabled'] ?? 'MISSING' );
	}
}
