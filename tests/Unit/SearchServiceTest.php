<?php

namespace Morntag\WpDocsManager\Tests\Unit;

use Morntag\WpDocsManager\Services\SearchService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class SearchServiceTest extends TestCase {

	private SearchService $service;

	protected function setUp(): void {
		$this->service = new SearchService();
	}

	/*
	|----------------------------------------------------------------------
	| matches_query() — private, tested via reflection
	|----------------------------------------------------------------------
	*/

	public function test_matches_query_finds_in_content(): void {
		$this->assertTrue(
			$this->invoke_matches_query( 'The quick brown fox.', 'Unrelated Title', 'brown' )
		);
	}

	public function test_matches_query_finds_in_title(): void {
		$this->assertTrue(
			$this->invoke_matches_query( 'No match here.', 'Installation Guide', 'guide' )
		);
	}

	public function test_matches_query_is_case_insensitive(): void {
		$this->assertTrue(
			$this->invoke_matches_query( 'UPPER CASE CONTENT', 'Title', 'upper case' )
		);
	}

	public function test_matches_query_returns_false_for_no_match(): void {
		$this->assertFalse(
			$this->invoke_matches_query( 'Some content.', 'Some title', 'nonexistent' )
		);
	}

	/*
	|----------------------------------------------------------------------
	| calculate_relevance() — private, tested via reflection
	|----------------------------------------------------------------------
	*/

	public function test_calculate_relevance_gives_title_bonus(): void {
		$with_title    = $this->invoke_calculate_relevance( 'body text', 'search term here', 'search' );
		$without_title = $this->invoke_calculate_relevance( 'body text with search', 'no match', 'search' );

		// Title match adds 10 points.
		$this->assertGreaterThan( $without_title, $with_title );
	}

	public function test_calculate_relevance_counts_content_occurrences(): void {
		$one   = $this->invoke_calculate_relevance( 'php is great', 'title', 'php' );
		$three = $this->invoke_calculate_relevance( 'php is great and php rocks because php', 'title', 'php' );

		$this->assertSame( 1, $one );
		$this->assertSame( 3, $three );
	}

	public function test_calculate_relevance_combines_title_and_content(): void {
		// Title match (10) + 2 occurrences in content = 12.
		$score = $this->invoke_calculate_relevance( 'use php and php again', 'PHP Guide', 'php' );

		$this->assertSame( 12, $score );
	}

	/*
	|----------------------------------------------------------------------
	| generate_excerpt() — private, tested via reflection
	|----------------------------------------------------------------------
	*/

	public function test_generate_excerpt_highlights_query(): void {
		$excerpt = $this->invoke_generate_excerpt( 'This is about WordPress plugins and themes.', 'wordpress' );

		$this->assertStringContainsString( '<mark>', $excerpt );
		$this->assertStringContainsString( 'WordPress', $excerpt );
	}

	public function test_generate_excerpt_returns_beginning_when_query_not_found(): void {
		$content = str_repeat( 'Lorem ipsum dolor sit amet. ', 20 );
		$excerpt = $this->invoke_generate_excerpt( $content, 'nonexistent' );

		$this->assertStringStartsWith( 'Lorem', $excerpt );
		$this->assertStringEndsWith( '...', $excerpt );
	}

	/*
	|----------------------------------------------------------------------
	| Reflection helpers
	|----------------------------------------------------------------------
	*/

	private function invoke_matches_query( string $content, string $title, string $query ): bool {
		$method = new ReflectionMethod( SearchService::class, 'matches_query' );

		return $method->invoke( $this->service, $content, $title, $query );
	}

	private function invoke_calculate_relevance( string $content, string $title, string $query ): int {
		$method = new ReflectionMethod( SearchService::class, 'calculate_relevance' );

		return $method->invoke( $this->service, $content, $title, $query );
	}

	private function invoke_generate_excerpt( string $content, string $query ): string {
		$method = new ReflectionMethod( SearchService::class, 'generate_excerpt' );

		return $method->invoke( $this->service, $content, $query );
	}
}
