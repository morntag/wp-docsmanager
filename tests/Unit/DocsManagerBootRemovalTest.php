<?php

namespace Morntag\WpDocsManager\Tests\Unit;

use Morntag\WpDocsManager\DocsManager;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Phase 1 acceptance tests for the DocsManager class shape after the standalone
 * conversion:
 *   - the boot() / $pending_config library-style injection API is gone
 *   - UI-guard capability strings are renamed to docsmanager_*_docs
 *   - no main-site multisite guard remains
 */
class DocsManagerBootRemovalTest extends TestCase {

	private string $source;

	protected function setUp(): void {
		$path = dirname( __DIR__, 2 ) . '/DocsManager.php';
		$this->assertFileExists( $path );
		$this->source = (string) file_get_contents( $path );
	}

	/*
	|----------------------------------------------------------------------
	| (2) boot() / pending_config removed
	|----------------------------------------------------------------------
	*/

	public function test_boot_method_is_removed(): void {
		$refl = new ReflectionClass( DocsManager::class );
		$this->assertFalse(
			$refl->hasMethod( 'boot' ),
			'DocsManager::boot() must be removed — the plugin self-bootstraps on plugins_loaded.'
		);
	}

	public function test_pending_config_property_is_removed(): void {
		$refl = new ReflectionClass( DocsManager::class );
		$this->assertFalse(
			$refl->hasProperty( 'pending_config' ),
			'DocsManager::$pending_config must be removed — no external boot() injection.'
		);
	}

	/*
	|----------------------------------------------------------------------
	| (3) UI-guard capabilities renamed
	|----------------------------------------------------------------------
	*/

	public function test_old_mcc_cap_strings_are_absent(): void {
		foreach ( array( 'mcc_access_docs', 'mcc_edit_docs', 'mcc_delete_docs' ) as $cap ) {
			$this->assertStringNotContainsString(
				"'{$cap}'",
				$this->source,
				"DocsManager.php must not reference the legacy cap string '{$cap}'."
			);
		}
	}

	public function test_new_docsmanager_cap_strings_are_present(): void {
		foreach ( array( 'docsmanager_access_docs', 'docsmanager_edit_docs', 'docsmanager_delete_docs' ) as $cap ) {
			$this->assertStringContainsString(
				"'{$cap}'",
				$this->source,
				"DocsManager.php must reference the renamed cap string '{$cap}'."
			);
		}
	}

	/*
	|----------------------------------------------------------------------
	| (6) Multisite: plugin is gated to the main site in the bootstrap file
	|----------------------------------------------------------------------
	*/

	public function test_bootstrap_gates_to_main_site_on_multisite(): void {
		$path = dirname( __DIR__, 2 ) . '/wp-docsmanager.php';
		$this->assertFileExists( $path );
		$source = (string) file_get_contents( $path );

		$this->assertMatchesRegularExpression(
			'/is_multisite\(\).*is_main_site\(\)/s',
			$source,
			'wp-docsmanager.php must skip DocsManager bootstrap on multisite subsites (is_multisite() && ! is_main_site() gate).'
		);
	}

	/*
	|----------------------------------------------------------------------
	| View files: no legacy mcc_*_docs caps
	|----------------------------------------------------------------------
	*/

	/**
	 * @return array<int, array{0: string}>
	 */
	public static function view_files_provider(): array {
		return array(
			array( 'views/admin-page.view.php' ),
			array( 'views/viewer.view.php' ),
			array( 'views/editor.view.php' ),
		);
	}

	/**
	 * @dataProvider view_files_provider
	 */
	public function test_view_files_have_no_legacy_mcc_cap_strings( string $relative_path ): void {
		$path = dirname( __DIR__, 2 ) . '/' . $relative_path;
		$this->assertFileExists( $path );
		$source = (string) file_get_contents( $path );

		foreach ( array( 'mcc_access_docs', 'mcc_edit_docs', 'mcc_create_docs', 'mcc_delete_docs' ) as $cap ) {
			$this->assertStringNotContainsString(
				$cap,
				$source,
				"{$relative_path} must not reference legacy cap '{$cap}' — UI-guard call-sites must use docsmanager_*_docs."
			);
		}
	}

	/*
	|----------------------------------------------------------------------
	| uninstall.php dispatcher exists as a distinct lowercase file
	|----------------------------------------------------------------------
	*/

	public function test_uninstall_php_dispatcher_exists(): void {
		$path = dirname( __DIR__, 2 ) . '/uninstall.php';
		$this->assertTrue(
			file_exists( $path ),
			'Repo root must contain a lowercase uninstall.php entry point — WordPress requires this exact filename.'
		);
	}
}
