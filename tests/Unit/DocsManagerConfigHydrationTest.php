<?php

namespace Morntag\WpDocsManager\Tests\Unit;

use Morntag\WpDocsManager\DocsManager;
use Morntag\WpDocsManager\Module;
use Morntag\WpDocsManager\Services\FileScanner;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Phase 2 acceptance: DocsManager::init() reads `docsmanager_settings` via
 * get_option() and hydrates FileScanner with the resolved absolute paths.
 * Fresh install (no saved option) -> FileScanner receives empty paths, so
 * sidebar rendering falls back to the Phase-1 empty-array behaviour.
 *
 * We inspect FileScanner state via reflection on its private $modules_dir /
 * $docs_dir properties (see Services/FileScanner.php).
 *
 * ASSUMPTION: DocsManager::get_file_scanner() returns the hydrated
 * FileScanner instance, and the private property names on FileScanner remain
 * `$modules_dir` and `$docs_dir`.
 */
class DocsManagerConfigHydrationTest extends TestCase {

	protected function setUp(): void {
		wp_test_reset_recorders();
		$this->reset_module_singleton();
	}

	protected function tearDown(): void {
		$this->reset_module_singleton();
	}

	/**
	 * Clears the Module::$instances static so each test constructs a fresh
	 * DocsManager and its init() runs against the current globals.
	 */
	private function reset_module_singleton(): void {
		$refl = new ReflectionClass( Module::class );
		if ( $refl->hasProperty( 'instances' ) ) {
			$prop = $refl->getProperty( 'instances' );
			$prop->setValue( null, array() );
		}
	}

	private function read_private_string( object $obj, string $property ): string {
		$refl  = new ReflectionClass( $obj );
		$prop  = $refl->getProperty( $property );
		$value = $prop->getValue( $obj );
		return is_string( $value ) ? $value : '';
	}

	public function test_fresh_install_hydrates_scanner_with_empty_paths(): void {
		// No option saved.
		$this->assertArrayNotHasKey( 'docsmanager_settings', $GLOBALS['wp_test_options'] );

		$scanner = DocsManager::instance()->get_file_scanner();
		$this->assertInstanceOf( FileScanner::class, $scanner );

		$this->assertSame( '', $this->read_private_string( $scanner, 'modules_dir' ), 'Fresh install must leave modules_dir empty.' );
		$this->assertSame( '', $this->read_private_string( $scanner, 'docs_dir' ), 'Fresh install must leave docs_dir empty.' );
	}

	public function test_both_scans_enabled_hydrates_scanner_with_both_paths(): void {
		update_option(
			'docsmanager_settings',
			array(
				'plugin_slug'          => 'mcc-baspo',
				'modules_scan_enabled' => true,
				'modules_subpath'      => 'includes/Modules',
				'docs_scan_enabled'    => true,
				'docs_subpath'         => '.docs',
			)
		);

		$scanner = DocsManager::instance()->get_file_scanner();

		$this->assertSame(
			WP_PLUGIN_DIR . '/mcc-baspo/includes/Modules',
			$this->read_private_string( $scanner, 'modules_dir' ),
			'Both-enabled: modules_dir must come from (WP_PLUGIN_DIR, slug, modules_subpath).'
		);
		$this->assertSame(
			WP_PLUGIN_DIR . '/mcc-baspo/.docs',
			$this->read_private_string( $scanner, 'docs_dir' ),
			'Both-enabled: docs_dir must come from (WP_PLUGIN_DIR, slug, docs_subpath).'
		);
	}

	public function test_only_modules_enabled_gives_scanner_empty_docs_path(): void {
		update_option(
			'docsmanager_settings',
			array(
				'plugin_slug'          => 'mcc-baspo',
				'modules_scan_enabled' => true,
				'modules_subpath'      => 'includes/Modules',
				'docs_scan_enabled'    => false,
				'docs_subpath'         => '.docs',
			)
		);

		$scanner = DocsManager::instance()->get_file_scanner();

		$this->assertSame(
			WP_PLUGIN_DIR . '/mcc-baspo/includes/Modules',
			$this->read_private_string( $scanner, 'modules_dir' )
		);
		$this->assertSame(
			'',
			$this->read_private_string( $scanner, 'docs_dir' ),
			'Disabled docs scan must not leak a path into FileScanner.'
		);
	}

	public function test_only_docs_enabled_gives_scanner_empty_modules_path(): void {
		update_option(
			'docsmanager_settings',
			array(
				'plugin_slug'          => 'mcc-baspo',
				'modules_scan_enabled' => false,
				'modules_subpath'      => 'includes/Modules',
				'docs_scan_enabled'    => true,
				'docs_subpath'         => '.docs',
			)
		);

		$scanner = DocsManager::instance()->get_file_scanner();

		$this->assertSame(
			'',
			$this->read_private_string( $scanner, 'modules_dir' ),
			'Disabled modules scan must not leak a path into FileScanner.'
		);
		$this->assertSame(
			WP_PLUGIN_DIR . '/mcc-baspo/.docs',
			$this->read_private_string( $scanner, 'docs_dir' )
		);
	}

	public function test_filter_overrides_win_during_hydration(): void {
		update_option(
			'docsmanager_settings',
			array(
				'plugin_slug'          => 'mcc-baspo',
				'modules_scan_enabled' => true,
				'modules_subpath'      => 'includes/Modules',
				'docs_scan_enabled'    => true,
				'docs_subpath'         => '.docs',
			)
		);

		add_filter(
			'morntag_docs_modules_dir',
			static fn( string $_ ): string => '/override/modules',
			10,
			1
		);
		add_filter(
			'morntag_docs_docs_dir',
			static fn( string $_ ): string => '/override/docs',
			10,
			1
		);

		$scanner = DocsManager::instance()->get_file_scanner();

		$this->assertSame( '/override/modules', $this->read_private_string( $scanner, 'modules_dir' ) );
		$this->assertSame( '/override/docs', $this->read_private_string( $scanner, 'docs_dir' ) );
	}
}
