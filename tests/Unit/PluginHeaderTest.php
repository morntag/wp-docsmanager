<?php

namespace Morntag\WpDocsManager\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Phase 1 acceptance: the plugin must expose a standard WP plugin header at
 * wp-docsmanager.php in the repo root. These tests assert the header contents
 * by reading the file as text — no eval/require (which would kick off PUC and
 * other runtime side effects).
 */
class PluginHeaderTest extends TestCase {

	private string $plugin_file;

	protected function setUp(): void {
		$this->plugin_file = dirname( __DIR__, 2 ) . '/wp-docsmanager.php';
	}

	public function test_plugin_bootstrap_file_exists(): void {
		$this->assertFileExists(
			$this->plugin_file,
			'Expected plugin bootstrap file at repo root: wp-docsmanager.php'
		);
	}

	public function test_plugin_header_contains_required_fields(): void {
		$this->assertFileExists( $this->plugin_file );
		$contents = (string) file_get_contents( $this->plugin_file );

		$required = array(
			'plugin_name'  => '/Plugin Name:\s*WP Docs Manager/i',
			'version'      => '/Version:\s*0\.3\.0/i',
			'author'       => '/Author:\s*morntag\.com/i',
			'license'      => '/License:\s*GPL-2\.0-or-later/i',
			'text_domain'  => '/Text Domain:\s*wp-docsmanager/i',
			'plugin_uri'   => '#Plugin URI:\s*https://github\.com/morntag/wp-docsmanager#i',
		);

		foreach ( $required as $label => $pattern ) {
			$this->assertMatchesRegularExpression(
				$pattern,
				$contents,
				"Plugin header missing/invalid for: {$label}"
			);
		}
	}
}
