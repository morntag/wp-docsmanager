<?php

namespace Morntag\WpDocsManager\Tests\Unit;

use Morntag\WpDocsManager\Services\FileScanner;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class FileScannerTest extends TestCase {

	private string $tmp_dir;

	protected function setUp(): void {
		$this->tmp_dir = sys_get_temp_dir() . '/wp_docsmanager_test_' . uniqid();
		mkdir( $this->tmp_dir, 0755, true );
	}

	protected function tearDown(): void {
		$this->remove_dir( $this->tmp_dir );
	}

	/*
	|----------------------------------------------------------------------
	| extract_title() — private, tested via reflection
	|----------------------------------------------------------------------
	*/

	public function test_extract_title_finds_h1(): void {
		$content = "# My Module Title\nSome body text.";

		$this->assertSame( 'My Module Title', $this->invoke_extract_title( $content ) );
	}

	public function test_extract_title_finds_frontmatter_title(): void {
		$content = "---\ntitle: Frontmatter Title\n---\nBody.";

		$this->assertSame( 'Frontmatter Title', $this->invoke_extract_title( $content ) );
	}

	public function test_extract_title_prefers_h1_over_frontmatter(): void {
		$content = "---\ntitle: FM Title\n---\n# H1 Title\nBody.";

		// H1 regex is checked first.
		$this->assertSame( 'H1 Title', $this->invoke_extract_title( $content ) );
	}

	public function test_extract_title_uses_fallback(): void {
		$content = 'No heading, no frontmatter.';

		$this->assertSame( 'my-fallback', $this->invoke_extract_title( $content, 'my-fallback' ) );
	}

	/*
	|----------------------------------------------------------------------
	| scan_module_readmes()
	|----------------------------------------------------------------------
	*/

	public function test_scan_module_readmes_returns_empty_for_nonexistent_dir(): void {
		$scanner = new FileScanner( '/nonexistent/path/', '' );

		$this->assertSame( array(), $scanner->scan_module_readmes() );
	}

	public function test_scan_module_readmes_discovers_readme_files(): void {
		// Create modules_dir/module-a/README.md
		$module_dir = $this->tmp_dir . '/modules/';
		mkdir( $module_dir . 'module-a', 0755, true );
		file_put_contents( $module_dir . 'module-a/README.md', "# Module A\nDescription." );

		// Create modules_dir/module-b/README.md
		mkdir( $module_dir . 'module-b', 0755, true );
		file_put_contents( $module_dir . 'module-b/README.md', "# Module B\nAnother module." );

		// Module without README should be skipped.
		mkdir( $module_dir . 'module-c', 0755, true );

		$scanner = new FileScanner( $module_dir, '' );
		$results = $scanner->scan_module_readmes();

		$this->assertCount( 2, $results );

		$titles = array_column( $results, 'title' );
		$this->assertContains( 'Module A', $titles );
		$this->assertContains( 'Module B', $titles );

		// Verify structure.
		$this->assertSame( 'module', $results[0]['type'] );
		$this->assertArrayHasKey( 'path', $results[0] );
		$this->assertArrayHasKey( 'content', $results[0] );
		$this->assertArrayHasKey( 'module', $results[0] );
	}

	/*
	|----------------------------------------------------------------------
	| scan_docs_directory()
	|----------------------------------------------------------------------
	*/

	public function test_scan_docs_directory_returns_empty_for_nonexistent_dir(): void {
		$scanner = new FileScanner( '', '/nonexistent/path/' );

		$this->assertSame( array(), $scanner->scan_docs_directory() );
	}

	public function test_scan_docs_directory_finds_markdown_files(): void {
		$docs_dir = $this->tmp_dir . '/docs';
		mkdir( $docs_dir . '/sub', 0755, true );
		file_put_contents( $docs_dir . '/guide.md', "# Guide\nGuide content." );
		file_put_contents( $docs_dir . '/sub/deep.md', "# Deep Doc\nNested content." );
		// Non-markdown file should be skipped.
		file_put_contents( $docs_dir . '/notes.txt', 'Not markdown.' );

		$scanner = new FileScanner( '', $docs_dir );
		$results = $scanner->scan_docs_directory();

		$this->assertCount( 2, $results );

		$titles = array_column( $results, 'title' );
		$this->assertContains( 'Guide', $titles );
		$this->assertContains( 'Deep Doc', $titles );

		// Verify structure.
		$this->assertSame( 'docs', $results[0]['type'] );
		$this->assertArrayHasKey( 'relative_path', $results[0] );
	}

	public function test_scan_skips_hidden_and_vendor_directories(): void {
		$docs_dir = $this->tmp_dir . '/docs2';
		mkdir( $docs_dir . '/.hidden', 0755, true );
		mkdir( $docs_dir . '/vendor', 0755, true );
		mkdir( $docs_dir . '/node_modules', 0755, true );
		mkdir( $docs_dir . '/valid', 0755, true );
		file_put_contents( $docs_dir . '/.hidden/secret.md', '# Secret' );
		file_put_contents( $docs_dir . '/vendor/dep.md', '# Dep' );
		file_put_contents( $docs_dir . '/node_modules/pkg.md', '# Pkg' );
		file_put_contents( $docs_dir . '/valid/ok.md', '# OK' );

		$scanner = new FileScanner( '', $docs_dir );
		$results = $scanner->scan_docs_directory();

		$this->assertCount( 1, $results );
		$this->assertSame( 'OK', $results[0]['title'] );
	}

	public function test_scan_respects_depth_limit(): void {
		// Build a directory tree deeper than 5 levels.
		$docs_dir = $this->tmp_dir . '/deep';
		$path     = $docs_dir;
		for ( $i = 0; $i < 8; $i++ ) {
			$path .= '/level' . $i;
		}
		mkdir( $path, 0755, true );
		file_put_contents( $path . '/too-deep.md', '# Too Deep' );

		// Also add a file at a valid depth (directory already exists from the deep path).
		file_put_contents( $docs_dir . '/level0/level1/ok.md', '# Shallow' );

		$scanner = new FileScanner( '', $docs_dir );
		$results = $scanner->scan_docs_directory();

		$titles = array_column( $results, 'title' );
		$this->assertContains( 'Shallow', $titles );
		$this->assertNotContains( 'Too Deep', $titles );
	}

	/*
	|----------------------------------------------------------------------
	| Helpers
	|----------------------------------------------------------------------
	*/

	private function invoke_extract_title( string $content, string $fallback = 'Untitled' ): string {
		$scanner = new FileScanner();
		$method  = new ReflectionMethod( FileScanner::class, 'extract_title' );

		return $method->invoke( $scanner, $content, $fallback );
	}

	private function remove_dir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$items = scandir( $dir );
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dir . '/' . $item;
			is_dir( $path ) ? $this->remove_dir( $path ) : unlink( $path );
		}
		rmdir( $dir );
	}
}
