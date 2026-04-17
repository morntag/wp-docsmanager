<?php

namespace Morntag\WpDocsManager\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Phase 1 acceptance: composer.json flips from "library" to "wordpress-plugin",
 * drops the top-level "version" key (Git tags are the source of truth), and
 * removes the `symfony/dotenv` dependency (no more .env-based runtime config).
 *
 * Plugin-Update-Checker is wired in Phase 3 and intentionally not asserted here.
 */
class ComposerJsonTest extends TestCase {

	/** @var array<string,mixed> */
	private array $composer;

	protected function setUp(): void {
		$path = dirname( __DIR__, 2 ) . '/composer.json';
		$this->assertFileExists( $path );

		$decoded = json_decode( (string) file_get_contents( $path ), true );
		$this->assertIsArray( $decoded, 'composer.json must be valid JSON.' );
		$this->composer = $decoded;
	}

	public function test_type_is_wordpress_plugin(): void {
		$this->assertArrayHasKey( 'type', $this->composer );
		$this->assertSame(
			'wordpress-plugin',
			$this->composer['type'],
			'composer.json "type" must be "wordpress-plugin" for WPackagist/plugin-installer compatibility.'
		);
	}

	public function test_top_level_version_key_is_absent(): void {
		$this->assertArrayNotHasKey(
			'version',
			$this->composer,
			'composer.json must not carry a "version" key — Git tags (via release-it + plugin header) are the source of truth.'
		);
	}

	public function test_symfony_dotenv_is_not_required(): void {
		$require = $this->composer['require'] ?? array();
		$this->assertIsArray( $require );
		$this->assertArrayNotHasKey(
			'symfony/dotenv',
			$require,
			'symfony/dotenv must be removed — the standalone plugin does not read .env files.'
		);
	}

	/**
	 * Phase 3 AC #1: plugin-update-checker must be a runtime dependency so the
	 * shipped ZIP includes it under vendor/ and sites can auto-update against
	 * the public GitHub Releases feed.
	 */
	public function test_plugin_update_checker_is_required(): void {
		$require = $this->composer['require'] ?? array();
		$this->assertIsArray( $require );
		$this->assertArrayHasKey(
			'yahnis-elsts/plugin-update-checker',
			$require,
			'yahnis-elsts/plugin-update-checker must be required (^5.0) for the update-checker wiring in Phase 3.'
		);

		$constraint = (string) $require['yahnis-elsts/plugin-update-checker'];
		$this->assertMatchesRegularExpression(
			'/(\^|>=)5(\.\d+)*/',
			$constraint,
			"yahnis-elsts/plugin-update-checker constraint '{$constraint}' must allow ^5.0 (e.g. '^5.0', '^5.5', or '>=5.0')."
		);
	}
}
