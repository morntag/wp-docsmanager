<?php

namespace Morntag\WpDocsManager\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards the release-ZIP exclude list. Exclusions live inline in the
 * `Package release ZIP` step of `.github/workflows/release.yml` (rather
 * than a committed `.zipexclude` file) because past runs picked up a
 * stale on-disk file and shipped bloat.
 */
class ZipExcludeTest extends TestCase {

	private string $contents;

	protected function setUp(): void {
		$workflow = dirname( __DIR__, 2 ) . '/.github/workflows/release.yml';

		$this->assertFileExists(
			$workflow,
			'Release workflow is missing — inline exclude list cannot be validated.'
		);

		$this->contents = (string) file_get_contents( $workflow );

		$this->assertStringContainsString(
			".release-excludes <<'EOF'",
			$this->contents,
			'Release workflow must contain an inline exclude heredoc for the ZIP packaging step.'
		);
	}

	/**
	 * @dataProvider required_exclude_entry_provider
	 */
	public function test_release_workflow_excludes_entry( string $entry ): void {
		$pattern = '/(^|\n)\s*' . preg_quote( $entry, '/' ) . '\/?\s*(\n|$)/';
		$this->assertMatchesRegularExpression(
			$pattern,
			$this->contents,
			"Release workflow inline exclude list must list '{$entry}' so it is stripped from the release ZIP."
		);
	}

	/**
	 * @return array<string, array{0:string}>
	 */
	public static function required_exclude_entry_provider(): array {
		$entries = array(
			'.git',
			'.github',
			'node_modules',
			'tests',
			'phpunit.xml',
			'phpcs.xml',
			'phpstan.neon',
			'package.json',
			'package-lock.json',
			'composer.json',
			'composer.lock',
			'CLAUDE.md',
			'.claude',
			'lefthook.yml',
			'commitlint.config.js',
			'.release-it.json',
			'CHANGELOG.md',
			'plans',
			'.env',
			'vendor/bin',
		);

		$provider = array();
		foreach ( $entries as $entry ) {
			$provider[ $entry ] = array( $entry );
		}
		return $provider;
	}
}
