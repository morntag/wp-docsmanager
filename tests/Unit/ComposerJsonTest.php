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
}
