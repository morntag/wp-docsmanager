<?php

namespace Morntag\WpDocsManager\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Phase 3 AC #4: .github/workflows/release.yml preserves the PHPCS/PHPStan/
 * PHPUnit gate, drops the .env-creation step, builds a wp-docsmanager-v*.zip
 * and attaches it via `gh release upload`. The bundle is committed so
 * `npm run build` must NOT run. Text inspection only — we never execute
 * the workflow here.
 */
class ReleaseWorkflowTest extends TestCase {

	private string $contents;

	protected function setUp(): void {
		$workflow_path = dirname( __DIR__, 2 ) . '/.github/workflows/release.yml';
		$this->assertFileExists( $workflow_path, 'Expected release workflow at .github/workflows/release.yml' );
		$this->contents = (string) file_get_contents( $workflow_path );
	}

	/**
	 * The test-job structure must still run PHPCS, PHPStan, and PHPUnit
	 * before the release job proceeds.
	 *
	 * @dataProvider required_gate_step_provider
	 */
	public function test_gate_job_runs_existing_quality_steps( string $needle, string $message ): void {
		$this->assertStringContainsString( $needle, $this->contents, $message );
	}

	/**
	 * @return array<string, array{0:string,1:string}>
	 */
	public static function required_gate_step_provider(): array {
		return array(
			'phpcs step'   => array( 'composer phpcs', 'Release workflow must still run `composer phpcs` in the gate job.' ),
			'phpstan step' => array( 'composer phpstan', 'Release workflow must still run `composer phpstan` in the gate job.' ),
			'phpunit step' => array( 'composer test', 'Release workflow must still run `composer test` (PHPUnit) in the gate job.' ),
		);
	}

	/**
	 * AC #4: the release job must NOT write a .env file. All common shell
	 * patterns for piping secrets/env values into .env must be absent.
	 *
	 * @dataProvider forbidden_dotenv_write_pattern_provider
	 */
	public function test_release_job_does_not_create_dotenv_file( string $pattern, string $message ): void {
		$this->assertDoesNotMatchRegularExpression( $pattern, $this->contents, $message );
	}

	/**
	 * @return array<string, array{0:string,1:string}>
	 */
	public static function forbidden_dotenv_write_pattern_provider(): array {
		return array(
			'echo-into-.env'     => array( '/echo\s+[^\n]*>\s*\.env\b/', 'Release workflow must not pipe anything into a .env file.' ),
			'heredoc-into-.env'  => array( '/>\s*\.env\b/', 'Release workflow must not redirect output into a .env file.' ),
			'create-.env-step'   => array( '/name:\s*[^\n]*\.env/i', 'Release workflow must not include a step whose name references .env.' ),
		);
	}

	public function test_release_job_builds_zip_archive(): void {
		$this->assertMatchesRegularExpression(
			'/\bzip\s/',
			$this->contents,
			'Release workflow must invoke `zip` to build the release archive.'
		);
		$this->assertStringContainsString(
			'wp-docsmanager-v',
			$this->contents,
			'Release workflow must produce a ZIP named wp-docsmanager-v<version>.zip.'
		);
	}

	public function test_release_job_extracts_version_from_plugin_header(): void {
		$this->assertMatchesRegularExpression(
			'/grep[^\n]*wp-docsmanager\.php/',
			$this->contents,
			'Release workflow must grep wp-docsmanager.php to extract the current version from the plugin header.'
		);
		$this->assertMatchesRegularExpression(
			'/grep[^\n]*Version:/',
			$this->contents,
			'Release workflow must grep for the "Version:" line when extracting the version from the plugin header.'
		);
	}

	public function test_release_job_uploads_zip_to_github_release(): void {
		$this->assertMatchesRegularExpression(
			'/gh\s+release\s+upload/',
			$this->contents,
			'Release workflow must call `gh release upload` to attach the built ZIP to the GitHub release.'
		);
	}

	public function test_release_job_stages_into_wp_docsmanager_directory(): void {
		$this->assertMatchesRegularExpression(
			'/\brsync\b/',
			$this->contents,
			'Release workflow must use rsync to stage the plugin into a clean directory before zipping.'
		);
		$this->assertStringContainsString(
			'wp-docsmanager',
			$this->contents,
			'Release workflow must stage into a directory literally named wp-docsmanager/ so the ZIP has the correct top-level folder.'
		);
	}

	public function test_release_job_installs_production_composer_deps(): void {
		$this->assertMatchesRegularExpression(
			'/composer\s+install\s+--no-dev\s+--optimize-autoloader/',
			$this->contents,
			'Release workflow must run `composer install --no-dev --optimize-autoloader` before packaging so vendor/ only carries prod deps.'
		);
	}

	public function test_release_job_does_not_run_npm_build(): void {
		$this->assertStringNotContainsString(
			'npm run build',
			$this->contents,
			'Release workflow must NOT run `npm run build` — assets/js/editor.bundle.js is committed and ships as-is.'
		);
	}
}
