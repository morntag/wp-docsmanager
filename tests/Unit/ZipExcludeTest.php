<?php

namespace Morntag\WpDocsManager\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Phase 3 AC #5: a repo-root exclude list ensures rsync/zip never copy dev
 * tooling into the release archive. The plan calls the file `.zipexclude`;
 * the plugin-header ecosystem also knows `.distignore`. Either filename is
 * accepted so long as one exists and carries the required entries.
 */
class ZipExcludeTest extends TestCase {

	private string $contents;

	protected function setUp(): void {
		$root = dirname( __DIR__, 2 );

		$candidates = array(
			$root . '/.zipexclude',
			$root . '/.distignore',
		);

		$found = null;
		foreach ( $candidates as $path ) {
			if ( file_exists( $path ) ) {
				$found = $path;
				break;
			}
		}

		$this->assertNotNull(
			$found,
			'Phase 3 requires a repo-root zip-exclude list file (.zipexclude per the plan, or .distignore). Neither was found.'
		);

		$this->contents = (string) file_get_contents( (string) $found );
	}

	/**
	 * @dataProvider required_exclude_entry_provider
	 */
	public function test_zip_exclude_file_contains_entry( string $entry ): void {
		// Match on word-ish boundary so `tests` doesn't accidentally match
		// inside a longer token. Rsync/zip ignore files are line-based, so
		// we look for the entry either on its own line or with trailing slash.
		$pattern = '/(^|\n)\s*' . preg_quote( $entry, '/' ) . '\/?\s*(\n|$)/';
		$this->assertMatchesRegularExpression(
			$pattern,
			$this->contents,
			"Zip-exclude file must list '{$entry}' so it is stripped from the release ZIP."
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
			'CLAUDE.md',
			'.claude',
			'lefthook.yml',
			'commitlint.config.js',
			'.release-it.json',
			'CHANGELOG.md',
			'plans',
			'.env',
		);

		$provider = array();
		foreach ( $entries as $entry ) {
			$provider[ $entry ] = array( $entry );
		}
		return $provider;
	}
}
