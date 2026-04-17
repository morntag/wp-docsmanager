<?php

namespace Morntag\WpDocsManager\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Phase 3 AC #3 / #6: release-it is configured to patch the plugin-header
 * Version: line in wp-docsmanager.php (NOT composer.json), and package.json
 * exposes `release` and `release:dry` scripts that CI invokes.
 */
class ReleaseItConfigTest extends TestCase {

	private string $after_bump;
	/** @var array<string,mixed> */
	private array $package;

	protected function setUp(): void {
		$release_it_path = dirname( __DIR__, 2 ) . '/.release-it.json';
		$package_path    = dirname( __DIR__, 2 ) . '/package.json';

		$this->assertFileExists( $release_it_path, '.release-it.json must exist at the repo root.' );
		$this->assertFileExists( $package_path, 'package.json must exist at the repo root.' );

		$release_it_decoded = json_decode( (string) file_get_contents( $release_it_path ), true );
		$this->assertIsArray( $release_it_decoded, '.release-it.json must be valid JSON.' );

		$hooks = $release_it_decoded['hooks'] ?? array();
		$this->assertIsArray( $hooks, '.release-it.json must have a hooks object.' );
		$this->assertArrayHasKey(
			'after:bump',
			$hooks,
			'.release-it.json hooks must define an after:bump hook to patch the plugin header Version: line.'
		);

		$this->after_bump = (string) $hooks['after:bump'];

		$package_decoded = json_decode( (string) file_get_contents( $package_path ), true );
		$this->assertIsArray( $package_decoded, 'package.json must be valid JSON.' );
		$this->package = $package_decoded;
	}

	/**
	 * AC #3: the after:bump hook patches the Version: line in wp-docsmanager.php.
	 *
	 * @dataProvider required_after_bump_token_provider
	 */
	public function test_after_bump_patches_plugin_header( string $needle, string $message ): void {
		$this->assertStringContainsString( $needle, $this->after_bump, $message );
	}

	/**
	 * @return array<string, array{0:string,1:string}>
	 */
	public static function required_after_bump_token_provider(): array {
		return array(
			'targets plugin file' => array(
				'wp-docsmanager.php',
				'release-it after:bump hook must reference wp-docsmanager.php (the plugin header file is now the version source of truth).',
			),
			'patches Version line' => array(
				'Version:',
				'release-it after:bump hook must patch the "Version:" line in the plugin header.',
			),
		);
	}

	/**
	 * AC #3 (negative): the after:bump hook must no longer touch composer.json's
	 * "version" field — composer.json has no top-level version key anymore.
	 */
	public function test_after_bump_does_not_patch_composer_json_version_field(): void {
		$this->assertStringNotContainsString(
			'"version"',
			$this->after_bump,
			'release-it after:bump hook must not patch composer.json\'s "version" field — the plugin header is the source of truth.'
		);
	}

	/**
	 * Regression: simulate release-it's template expansion of `${version}`
	 * and run the resulting shell command against a copy of the plugin file.
	 * The corrupted-header bug from 2026-04-17 (release-it ate `${1}` as a
	 * template var, perl then nuked the whole header line) would re-trip this
	 * test before reaching CI. Skipped on environments without /usr/bin/perl.
	 */
	public function test_after_bump_perl_actually_patches_header_correctly(): void {
		if ( ! is_executable( '/usr/bin/perl' ) && ! is_executable( '/opt/local/bin/perl' ) ) {
			$this->markTestSkipped( 'perl not available on this host.' );
		}

		$repo_root   = dirname( __DIR__, 2 );
		$source_file = $repo_root . '/wp-docsmanager.php';
		$tmp_file    = tempnam( sys_get_temp_dir(), 'wpd_header_' );
		copy( $source_file, $tmp_file );

		// Mirror release-it's template expansion. The 2026-04-17 incident
		// proved release-it does NOT respect shell single-quoting: it walks the
		// raw command string and substitutes ANY ${...} pattern. Unknown vars
		// (like a perl backref ${1}) get replaced with the inner token, which
		// is exactly how the corrupted ` * Version:` line ended up as `10.4.0`.
		$expanded_command = str_replace( '${version}', '9.9.9', $this->after_bump );
		$expanded_command = (string) preg_replace( '/\$\{(\w+)\}/', '$1', $expanded_command );

		// Retarget the command at our temp file instead of wp-docsmanager.php.
		$expanded_command = str_replace( 'wp-docsmanager.php', escapeshellarg( $tmp_file ), $expanded_command );

		$exit_code = 0;
		$output    = array();
		exec( $expanded_command . ' 2>&1', $output, $exit_code );

		$this->assertSame(
			0,
			$exit_code,
			"release-it after:bump command failed (exit {$exit_code}): " . implode( "\n", $output )
		);

		$patched = (string) file_get_contents( $tmp_file );
		unlink( $tmp_file );

		$this->assertStringContainsString(
			' * Version:     9.9.9',
			$patched,
			'after:bump must rewrite the plugin header Version line to ` * Version:     9.9.9` exactly. If this fails, release-it likely interpolated a perl backref token (e.g. ${1}) — drop the backref and hardcode the prefix.'
		);
		$this->assertStringNotContainsString(
			'19.9.9',
			$patched,
			'after:bump produced corrupted version "19.9.9" — release-it ate a `${1}` backref again.'
		);
	}

	/**
	 * AC #6: package.json must expose the release scripts used by CI.
	 *
	 * @dataProvider required_package_script_provider
	 */
	public function test_package_json_exposes_release_scripts( string $script_name ): void {
		$scripts = $this->package['scripts'] ?? array();
		$this->assertIsArray( $scripts, 'package.json must have a scripts object.' );
		$this->assertArrayHasKey(
			$script_name,
			$scripts,
			"package.json scripts must define '{$script_name}' — CI and manual dry-runs both rely on it."
		);
		$this->assertNotEmpty(
			$scripts[ $script_name ],
			"package.json scripts.{$script_name} must be a non-empty command."
		);
	}

	/**
	 * @return array<string, array{0:string}>
	 */
	public static function required_package_script_provider(): array {
		return array(
			'release'     => array( 'release' ),
			'release:dry' => array( 'release:dry' ),
		);
	}
}
