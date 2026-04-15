<?php
namespace Morntag\WpDocsManager\Services;

use Morntag\WpDocsManager\Models\Documentation;

/**
 * Search Service
 *
 * Provides unified search across all documentation types.
 *
 * @package Morntag\WpDocsManager\Services
 */
class SearchService {

	private FileScanner $file_scanner;
	private MarkdownParser $markdown_parser;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->file_scanner    = new FileScanner();
		$this->markdown_parser = new MarkdownParser();
	}

	/**
	 * Search all documentation sources
	 *
	 * @param string                   $query Search query.
	 * @param array{category?: string} $filters Optional filters (e.g., 'category' for taxonomy filtering).
	 * @return array<int, array{id?: int, path?: string, title: string, excerpt: string, type: string, relevance: int}> Search results sorted by relevance.
	 */
	public function search( string $query, array $filters = array() ): array {
		$results = array();

		// Search custom posts.
		$results = array_merge( $results, $this->search_posts( $query, $filters ) );

		// Search module READMEs.
		$results = array_merge( $results, $this->search_files( $query, $filters, 'module' ) );

		// Search .docs files.
		$results = array_merge( $results, $this->search_files( $query, $filters, 'docs' ) );

		// Sort by relevance.
		usort(
			$results,
			function ( $a, $b ) {
				return $b['relevance'] <=> $a['relevance'];
			}
		);

		return $results;
	}

	/**
	 * Search custom post type documents
	 *
	 * @param string                   $query Search query.
	 * @param array{category?: string} $filters Optional filters (e.g., 'category' slug).
	 * @return array<int, array{id: int, title: string, excerpt: string, type: string, relevance: int}> Search results with post metadata.
	 */
	private function search_posts( string $query, array $filters ): array {
		$args = array(
			'post_type'      => Documentation::POST_TYPE,
			'post_status'    => 'publish',
			's'              => $query,
			'posts_per_page' => -1,
		);

		if ( ! empty( $filters['category'] ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Required for category filtering, query is limited to documentation CPT only.
			$args['tax_query'] = array(
				array(
					'taxonomy' => Documentation::TAXONOMY,
					'field'    => 'slug',
					'terms'    => $filters['category'],
				),
			);
		}

		$posts   = get_posts( $args );
		$results = array();

		foreach ( $posts as $post ) {
			$results[] = array(
				'id'        => $post->ID,
				'title'     => $post->post_title,
				'excerpt'   => $this->generate_excerpt( $post->post_content, $query ),
				'type'      => 'custom',
				'relevance' => $this->calculate_relevance( $post->post_content, $post->post_title, $query ),
			);
		}

		return $results;
	}

	/**
	 * Search file-based documentation
	 *
	 * @param string                   $query Search query.
	 * @param array{category?: string} $filters Optional filters (currently unused for files).
	 * @param string                   $type File type ('module' for READMEs, 'docs' for .docs files).
	 * @return array<int, array{path: string, title: string, excerpt: string, type: string, relevance: int}> Search results with file metadata.
	 */
	private function search_files( string $query, array $filters, string $type ): array {
		$files = 'module' === $type
			? $this->file_scanner->scan_module_readmes()
			: $this->file_scanner->scan_docs_directory();

		$results = array();

		foreach ( $files as $file ) {
			if ( $this->matches_query( $file['content'], $file['title'], $query ) ) {
				$results[] = array(
					'path'      => $file['path'],
					'title'     => $file['title'],
					'excerpt'   => $this->generate_excerpt( $file['content'], $query ),
					'type'      => $type,
					'relevance' => $this->calculate_relevance( $file['content'], $file['title'], $query ),
				);
			}
		}

		return $results;
	}

	/**
	 * Check if content matches query
	 *
	 * @param string $content Document content.
	 * @param string $title Document title.
	 * @param string $query Search query.
	 * @return bool True if query found in content or title.
	 */
	private function matches_query( string $content, string $title, string $query ): bool {
		$query   = strtolower( $query );
		$content = strtolower( $content );
		$title   = strtolower( $title );

		return false !== strpos( $content, $query ) || false !== strpos( $title, $query );
	}

	/**
	 * Calculate relevance score
	 *
	 * @param string $content Document content.
	 * @param string $title Document title.
	 * @param string $query Search query.
	 * @return int Relevance score (higher = more relevant).
	 */
	private function calculate_relevance( string $content, string $title, string $query ): int {
		$score = 0;
		$query = strtolower( $query );

		// Title matches are worth more.
		if ( false !== stripos( $title, $query ) ) {
			$score += 10;
		}

		// Count occurrences in content.
		$score += substr_count( strtolower( $content ), $query );

		return $score;
	}

	/**
	 * Generate excerpt with highlighted terms
	 *
	 * @param string $content Document content.
	 * @param string $query Search query.
	 * @param int    $length Excerpt length (default 200 characters).
	 * @return string Excerpt with highlighted query terms.
	 */
	private function generate_excerpt( string $content, string $query, int $length = 200 ): string {
		// Strip markdown.
		$content = wp_strip_all_tags( $this->markdown_parser->parse_markdown( $content ) );

		// Find position of query.
		$pos = stripos( $content, $query );

		if ( false !== $pos ) {
			$start   = max( 0, $pos - 50 );
			$excerpt = substr( $content, $start, $length );

			// Highlight query terms.
			$excerpt = preg_replace(
				'/(' . preg_quote( $query, '/' ) . ')/i',
				'<mark>$1</mark>',
				$excerpt
			);

			return '...' . $excerpt . '...';
		}

		return substr( $content, 0, $length ) . '...';
	}
}
