<?php

namespace Morntag\WpDocsManager\Tests\Unit;

use Morntag\WpDocsManager\Services\SettingsPage;
use Morntag\WpDocsManager\Services\SettingsRepository;
use PHPUnit\Framework\TestCase;

/**
 * Phase 2 acceptance: the Settings page surfaces non-blocking warnings when:
 *   - the configured plugin is not active
 *   - an enabled scan's resolved subpath does not exist on disk
 *   - the path is overridden by the `morntag_docs_modules_dir` /
 *     `morntag_docs_docs_dir` filter (shown read-only with "overridden by
 *     filter" text)
 *
 * The tests invoke `SettingsPage::render()` directly and capture its output
 * via `ob_start()`, then assert the expected notice strings appear (or don't).
 */
class SettingsPageWarningsTest extends TestCase {

	protected function setUp(): void {
		wp_test_reset_recorders();
		$GLOBALS['wp_test_current_user_can'] = true;
	}

	private function capture_render( SettingsPage $page ): string {
		ob_start();
		$page->render();
		return (string) ob_get_clean();
	}

	public function test_inactive_plugin_warning_is_shown(): void {
		$GLOBALS['wp_test_installed_plugins'] = array(
			'mcc-baspo/mcc-baspo.php' => array( 'Name' => 'MCC Baspo' ),
		);
		// NOT in $GLOBALS['wp_test_active_plugins'] — so is_plugin_active() returns false.

		update_option(
			SettingsRepository::OPTION_NAME,
			array(
				'plugin_slug'          => 'mcc-baspo',
				'modules_scan_enabled' => false,
				'modules_subpath'      => 'includes/Modules',
				'docs_scan_enabled'    => false,
				'docs_subpath'         => '.docs',
			)
		);

		$page   = new SettingsPage();
		$output = $this->capture_render( $page );

		$this->assertStringContainsString( 'mcc-baspo', $output );
		$this->assertStringContainsString( 'is not active', $output );
	}

	public function test_no_inactive_warning_when_plugin_is_active(): void {
		$GLOBALS['wp_test_installed_plugins'] = array(
			'mcc-baspo/mcc-baspo.php' => array( 'Name' => 'MCC Baspo' ),
		);
		$GLOBALS['wp_test_active_plugins']    = array( 'mcc-baspo/mcc-baspo.php' );

		update_option(
			SettingsRepository::OPTION_NAME,
			array(
				'plugin_slug'          => 'mcc-baspo',
				'modules_scan_enabled' => false,
				'modules_subpath'      => 'includes/Modules',
				'docs_scan_enabled'    => false,
				'docs_subpath'         => '.docs',
			)
		);

		$page   = new SettingsPage();
		$output = $this->capture_render( $page );

		$this->assertStringNotContainsString( 'is not active', $output );
	}

	public function test_missing_modules_path_warning_when_scan_enabled(): void {
		$GLOBALS['wp_test_installed_plugins'] = array(
			'mcc-baspo/mcc-baspo.php' => array( 'Name' => 'MCC Baspo' ),
		);
		$GLOBALS['wp_test_active_plugins']    = array( 'mcc-baspo/mcc-baspo.php' );

		update_option(
			SettingsRepository::OPTION_NAME,
			array(
				'plugin_slug'          => 'mcc-baspo',
				'modules_scan_enabled' => true,
				'modules_subpath'      => 'includes/Modules',
				'docs_scan_enabled'    => false,
				'docs_subpath'         => '.docs',
			)
		);

		$page   = new SettingsPage();
		$output = $this->capture_render( $page );

		// Path composed from WP_PLUGIN_DIR ('/tmp/wp/plugins' in bootstrap).
		$this->assertStringContainsString( 'does not exist on disk', $output );
	}

	public function test_no_missing_path_warning_when_scan_disabled(): void {
		$GLOBALS['wp_test_installed_plugins'] = array(
			'mcc-baspo/mcc-baspo.php' => array( 'Name' => 'MCC Baspo' ),
		);
		$GLOBALS['wp_test_active_plugins']    = array( 'mcc-baspo/mcc-baspo.php' );

		update_option(
			SettingsRepository::OPTION_NAME,
			array(
				'plugin_slug'          => 'mcc-baspo',
				'modules_scan_enabled' => false,
				'modules_subpath'      => 'includes/Modules',
				'docs_scan_enabled'    => false,
				'docs_subpath'         => '.docs',
			)
		);

		$page   = new SettingsPage();
		$output = $this->capture_render( $page );

		$this->assertStringNotContainsString( 'does not exist on disk', $output );
	}

	public function test_modules_dir_filter_override_is_shown_read_only(): void {
		$GLOBALS['wp_test_installed_plugins'] = array(
			'mcc-baspo/mcc-baspo.php' => array( 'Name' => 'MCC Baspo' ),
		);
		$GLOBALS['wp_test_active_plugins']    = array( 'mcc-baspo/mcc-baspo.php' );

		update_option(
			SettingsRepository::OPTION_NAME,
			array(
				'plugin_slug'          => 'mcc-baspo',
				'modules_scan_enabled' => false,
				'modules_subpath'      => 'includes/Modules',
				'docs_scan_enabled'    => false,
				'docs_subpath'         => '.docs',
			)
		);

		add_filter(
			'morntag_docs_modules_dir',
			static function ( $path ) {
				return '/custom/override/path';
			}
		);

		$page   = new SettingsPage();
		$output = $this->capture_render( $page );

		$this->assertStringContainsString( 'morntag_docs_modules_dir', $output );
		$this->assertStringContainsString( 'Overridden by', $output );
		// The modules_subpath input should not appear as an editable named field.
		$this->assertStringNotContainsString( 'name="docsmanager_settings[modules_subpath]"', $output );
	}

	public function test_docs_dir_filter_override_is_shown_read_only(): void {
		$GLOBALS['wp_test_installed_plugins'] = array(
			'mcc-baspo/mcc-baspo.php' => array( 'Name' => 'MCC Baspo' ),
		);
		$GLOBALS['wp_test_active_plugins']    = array( 'mcc-baspo/mcc-baspo.php' );

		update_option(
			SettingsRepository::OPTION_NAME,
			array(
				'plugin_slug'          => 'mcc-baspo',
				'modules_scan_enabled' => false,
				'modules_subpath'      => 'includes/Modules',
				'docs_scan_enabled'    => false,
				'docs_subpath'         => '.docs',
			)
		);

		add_filter(
			'morntag_docs_docs_dir',
			static function ( $path ) {
				return '/custom/docs/override';
			}
		);

		$page   = new SettingsPage();
		$output = $this->capture_render( $page );

		$this->assertStringContainsString( 'morntag_docs_docs_dir', $output );
		$this->assertStringContainsString( 'Overridden by', $output );
		$this->assertStringNotContainsString( 'name="docsmanager_settings[docs_subpath]"', $output );
	}

	public function test_no_override_notice_when_no_filter_registered(): void {
		$GLOBALS['wp_test_installed_plugins'] = array(
			'mcc-baspo/mcc-baspo.php' => array( 'Name' => 'MCC Baspo' ),
		);
		$GLOBALS['wp_test_active_plugins']    = array( 'mcc-baspo/mcc-baspo.php' );

		update_option(
			SettingsRepository::OPTION_NAME,
			array(
				'plugin_slug'          => 'mcc-baspo',
				'modules_scan_enabled' => false,
				'modules_subpath'      => 'includes/Modules',
				'docs_scan_enabled'    => false,
				'docs_subpath'         => '.docs',
			)
		);

		$page   = new SettingsPage();
		$output = $this->capture_render( $page );

		$this->assertStringNotContainsString( 'Overridden by', $output );
		// Editable inputs must still be present.
		$this->assertStringContainsString( 'name="docsmanager_settings[modules_subpath]"', $output );
		$this->assertStringContainsString( 'name="docsmanager_settings[docs_subpath]"', $output );
	}
}
