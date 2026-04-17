<?php

namespace Morntag\WpDocsManager\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Phase 3 AC #2: wp-docsmanager.php must wire the plugin-update-checker (PUC)
 * against the public morntag/wp-docsmanager GitHub repo, branch `main`, with
 * release assets enabled, and without any token/.env loading.
 *
 * All assertions are pure text inspection — we do NOT require() the bootstrap
 * file, which would kick off PUC and register WP hooks at import time.
 */
class UpdateCheckerWiringTest extends TestCase {

	private string $contents;

	protected function setUp(): void {
		$plugin_file = dirname( __DIR__, 2 ) . '/wp-docsmanager.php';
		$this->assertFileExists( $plugin_file );
		$this->contents = (string) file_get_contents( $plugin_file );
	}

	public function test_bootstrap_references_puc_factory(): void {
		$matches_v5_namespace = (bool) preg_match(
			'#(YahnisElsts\\\\PluginUpdateChecker\\\\v5\\\\PucFactory|Puc\\\\v5[p_][^\s\\\\]+\\\\PucFactory|\\bPucFactory\\b)#',
			$this->contents
		);
		$this->assertTrue(
			$matches_v5_namespace,
			'wp-docsmanager.php must reference PucFactory (YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory, Puc\\v5p*\\PucFactory, or short-alias PucFactory) to wire the update checker.'
		);
	}

	public function test_bootstrap_enables_release_assets(): void {
		$this->assertStringContainsString(
			'enableReleaseAssets(',
			$this->contents,
			'wp-docsmanager.php must call ->enableReleaseAssets() so PUC downloads the ZIP attached to each GitHub release.'
		);
	}

	public function test_bootstrap_points_at_public_repo_url(): void {
		$this->assertStringContainsString(
			'https://github.com/morntag/wp-docsmanager',
			$this->contents,
			'wp-docsmanager.php must pass the public repo URL (https://github.com/morntag/wp-docsmanager) to PucFactory::buildUpdateChecker().'
		);
	}

	public function test_bootstrap_sets_branch_main(): void {
		$this->assertMatchesRegularExpression(
			'/([\'\"])main\1/',
			$this->contents,
			"wp-docsmanager.php must pass 'main' as the branch argument to PucFactory::buildUpdateChecker()."
		);
	}

	public function test_bootstrap_does_not_call_set_authentication(): void {
		$this->assertStringNotContainsString(
			'setAuthentication(',
			$this->contents,
			'wp-docsmanager.php must NOT call setAuthentication() — the repo is public and the plugin ships without a GitHub token.'
		);
	}

	/**
	 * The plugin is standalone and intentionally does not load a .env file at
	 * runtime. None of the classic dotenv-loading patterns should appear.
	 *
	 * @dataProvider forbidden_dotenv_pattern_provider
	 */
	public function test_bootstrap_does_not_load_dotenv( string $needle, string $message ): void {
		$this->assertStringNotContainsString( $needle, $this->contents, $message );
	}

	/**
	 * @return array<string, array{0:string,1:string}>
	 */
	public static function forbidden_dotenv_pattern_provider(): array {
		return array(
			'no Dotenv class'   => array( 'Dotenv', 'wp-docsmanager.php must NOT reference Dotenv — no .env loading in the standalone plugin.' ),
			'no getenv() call'  => array( 'getenv(', 'wp-docsmanager.php must NOT call getenv() for a token/secret.' ),
			'no .env string'    => array( '.env', 'wp-docsmanager.php must NOT mention .env — no dotenv wiring in the standalone plugin.' ),
		);
	}
}
