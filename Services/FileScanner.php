<?php
namespace Morntag\WpDocsManager\Services;

/**
 * File Scanner Service
 *
 * Discovers and indexes README files from explicitly-provided module and docs
 * directories. Both paths are injected at construction time by the host plugin
 * via DocsManager::boot(), removing any dependency on plugin-specific constants.
 *
 * @package Morntag\WpDocsManager\Services
 */
class FileScanner {

	/**
	 * Modules directory path
	 *
	 * @var string
	 */
	private string $modules_dir;

	/**
	 * Docs directory path
	 *
	 * @var string
	 */
	private string $docs_dir;

	/**
	 * Constructor
	 *
	 * @param string $modules_dir Absolute path to directory containing per-module subfolders
	 *                            whose README.md files will be indexed. Pass empty string to
	 *                            disable module README scanning.
	 * @param string $docs_dir    Absolute path to a .docs-style markdown tree. Pass empty string
	 *                            to disable docs scanning.
	 */
	public function __construct( string $modules_dir = '', string $docs_dir = '' ) {
		$this->modules_dir = $modules_dir;
		$this->docs_dir    = $docs_dir;
	}

	/**
	 * Scan module READMEs with caching
	 *
	 * @return array<int,array{path:string,module:string,title:string,content:string,type:string}> Array of module README metadata.
	 */
	public function scan_module_readmes(): array {
		$cache_key = 'morntag_docs_module_readmes_cache';
		$cached    = get_transient( $cache_key );

		if ( ! empty( $cached ) ) {
			return $cached;
		}

		$readmes = array();

		if ( ! is_dir( $this->modules_dir ) ) {
			return $readmes;
		}

		$modules = scandir( $this->modules_dir );

		foreach ( $modules as $module ) {
			if ( '.' === $module || '..' === $module ) {
				continue;
			}

			$readme_path = $this->modules_dir . $module . '/README.md';

			if ( file_exists( $readme_path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local file, not remote URL.
				$content = file_get_contents( $readme_path );
				if ( false === $content ) {
					continue;
				}
				$readmes[] = array(
					'path'    => $readme_path,
					'module'  => $module,
					'title'   => $this->extract_title( $content, $module ),
					'content' => $content,
					'type'    => 'module',
				);
			}
		}

		set_transient( $cache_key, $readmes, HOUR_IN_SECONDS );

		return $readmes;
	}

	/**
	 * Recursively scan .docs directory
	 *
	 * @return array<int,array{path:string,relative_path:string,directory:string,filename:string,title:string,content:string,type:string}> Array of docs file metadata.
	 */
	public function scan_docs_directory(): array {
		$cache_key = 'morntag_docs_files_cache';
		$cached    = get_transient( $cache_key );

		if ( ! empty( $cached ) ) {
			return $cached;
		}

		$docs = array();

		if ( ! is_dir( $this->docs_dir ) ) {
			return $docs;
		}

		$this->scan_directory_recursive( $this->docs_dir, $docs );

		set_transient( $cache_key, $docs, HOUR_IN_SECONDS );

		return $docs;
	}

	/**
	 * Recursive directory scanner with safety limits
	 *
	 * @param string                                                                                                                      $dir         Directory to scan.
	 * @param array<int,array{path:string,relative_path:string,directory:string,filename:string,title:string,content:string,type:string}> $docs        Reference to docs array (passed by reference).
	 * @param string                                                                                                                      $parent_path Parent path for relative path tracking.
	 * @param int                                                                                                                         $depth       Current recursion depth (default 0).
	 */
	private function scan_directory_recursive( string $dir, array &$docs, string $parent_path = '', int $depth = 0 ): void {
		// Safety limit: prevent deep recursion.
		if ( $depth > 5 ) {
			return;
		}

		// Safety limit: prevent too many files.
		if ( count( $docs ) > 100 ) {
			return;
		}

		// Prevent scanning symlinks to avoid circular references.
		if ( is_link( $dir ) ) {
			return;
		}

		$items = scandir( $dir );
		if ( false === $items ) {
			return;
		}

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			// Skip hidden directories.
			if ( 0 === strpos( $item, '.' ) ) {
				continue;
			}

			// Skip common vendor/dependency directories.
			if ( in_array( $item, array( 'node_modules', 'vendor', '.git', 'dist', 'build' ), true ) ) {
				continue;
			}

			$path = $dir . '/' . $item;

			if ( is_dir( $path ) ) {
				$this->scan_directory_recursive( $path, $docs, $parent_path . '/' . $item, $depth + 1 );
			} elseif ( 'md' === pathinfo( $path, PATHINFO_EXTENSION ) ) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents 
				$content = file_get_contents( $path );
				if ( false === $content ) {
					continue;
				}
				$relative_path = str_replace( $this->docs_dir, '', $path );

				$docs[] = array(
					'path'          => $path,
					'relative_path' => $relative_path,
					'directory'     => dirname( $relative_path ),
					'filename'      => basename( $path ),
					'title'         => $this->extract_title( $content, basename( $path, '.md' ) ),
					'content'       => $content,
					'type'          => 'docs',
				);
			}
		}
	}

	/**
	 * Extract title from markdown content
	 *
	 * @param string $content  Markdown content.
	 * @param string $fallback Fallback title if none found.
	 * @return string Extracted title.
	 */
	private function extract_title( string $content, string $fallback = 'Untitled' ): string {
		// Try to extract from first H1.
		if ( preg_match( '/^#\s+(.+)$/m', $content, $matches ) ) {
			return trim( $matches[1] );
		}

		// Try frontmatter title.
		if ( preg_match( '/^---\s*\n(.*?)\n---/s', $content, $matches ) ) {
			if ( preg_match( '/title:\s*["\']?(.+?)["\']?\s*$/m', $matches[1], $title_matches ) ) {
				return trim( $title_matches[1] );
			}
		}

		return $fallback;
	}

	/**
	 * Clear all caches
	 */
	public function clear_cache(): void {
		delete_transient( 'morntag_docs_module_readmes_cache' );
		delete_transient( 'morntag_docs_files_cache' );
	}
}
