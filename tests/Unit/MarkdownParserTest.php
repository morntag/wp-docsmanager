<?php

namespace Morntag\WpDocsManager\Tests\Unit;

use Morntag\WpDocsManager\Services\MarkdownParser;
use PHPUnit\Framework\TestCase;

class MarkdownParserTest extends TestCase {

	private MarkdownParser $parser;

	protected function setUp(): void {
		$this->parser = new MarkdownParser();
	}

	/*
	|----------------------------------------------------------------------
	| parse_frontmatter()
	|----------------------------------------------------------------------
	*/

	public function test_parse_frontmatter_extracts_yaml(): void {
		$content = "---\ntitle: My Doc\nauthor: Jane\n---\n# Hello\nBody text.";

		$result = $this->parser->parse_frontmatter( $content );

		$this->assertSame( 'My Doc', $result['frontmatter']['title'] );
		$this->assertSame( 'Jane', $result['frontmatter']['author'] );
		$this->assertStringContainsString( '# Hello', $result['content'] );
		$this->assertStringContainsString( 'Body text.', $result['content'] );
	}

	public function test_parse_frontmatter_returns_empty_when_absent(): void {
		$content = "# Just a heading\nSome text.";

		$result = $this->parser->parse_frontmatter( $content );

		$this->assertSame( array(), $result['frontmatter'] );
		$this->assertSame( $content, $result['content'] );
	}

	public function test_parse_frontmatter_handles_invalid_yaml(): void {
		$content = "---\n[{unclosed\n---\nBody.";

		$result = $this->parser->parse_frontmatter( $content );

		// Should not throw — returns empty frontmatter array.
		$this->assertSame( array(), $result['frontmatter'] );
		$this->assertSame( 'Body.', $result['content'] );
	}

	public function test_parse_frontmatter_requires_content_between_delimiters(): void {
		// Regex requires content between --- delimiters, so adjacent
		// delimiters are treated as plain content (no match).
		$content = "---\n---\nBody.";

		$result = $this->parser->parse_frontmatter( $content );

		$this->assertSame( array(), $result['frontmatter'] );
		$this->assertSame( $content, $result['content'] );
	}

	/*
	|----------------------------------------------------------------------
	| parse_markdown()
	|----------------------------------------------------------------------
	*/

	public function test_parse_markdown_converts_basic_elements(): void {
		$md = "# Heading\n\nA **bold** paragraph.\n\n- item one\n- item two";

		$html = $this->parser->parse_markdown( $md );

		$this->assertStringContainsString( '<h1>Heading</h1>', $html );
		$this->assertStringContainsString( '<strong>bold</strong>', $html );
		$this->assertStringContainsString( '<li>item one</li>', $html );
	}

	public function test_parse_markdown_strips_frontmatter_before_rendering(): void {
		$md = "---\ntitle: Test\n---\n# Visible Heading";

		$html = $this->parser->parse_markdown( $md );

		$this->assertStringContainsString( '<h1>Visible Heading</h1>', $html );
		$this->assertStringNotContainsString( 'title: Test', $html );
	}

	public function test_parse_markdown_adds_language_class_to_fenced_code(): void {
		$md = "```php\necho 'hello';\n```";

		$html = $this->parser->parse_markdown( $md );

		$this->assertStringContainsString( 'class="language-php"', $html );
		$this->assertStringContainsString( '<pre class="language-php">', $html );
	}

	public function test_parse_markdown_adds_language_none_to_untagged_code(): void {
		$md = "```\nplain code\n```";

		$html = $this->parser->parse_markdown( $md );

		$this->assertStringContainsString( 'class="language-none"', $html );
	}

	public function test_parse_markdown_decodes_html_entities_in_links(): void {
		// Simulate a link whose URL contains &amp; (common after copy-paste from HTML).
		$md = '[Click](https://example.com?a=1&amp;b=2)';

		$html = $this->parser->parse_markdown( $md );

		// The rendered href should have & not &amp;amp;.
		$this->assertStringContainsString( 'href="https://example.com?a=1&amp;b=2"', $html );
		$this->assertStringNotContainsString( '&amp;amp;', $html );
	}

	/*
	|----------------------------------------------------------------------
	| generate_toc()
	|----------------------------------------------------------------------
	*/

	public function test_generate_toc_extracts_all_heading_levels(): void {
		$md = "# H1\n## H2\n### H3\nBody\n#### H4";

		$toc = $this->parser->generate_toc( $md );

		$this->assertCount( 4, $toc );
		$this->assertSame( 1, $toc[0]['level'] );
		$this->assertSame( 'H1', $toc[0]['title'] );
		$this->assertSame( 2, $toc[1]['level'] );
		$this->assertSame( 3, $toc[2]['level'] );
		$this->assertSame( 4, $toc[3]['level'] );
	}

	public function test_generate_toc_creates_slug_ids(): void {
		$md = "# Getting Started\n## Installation Guide";

		$toc = $this->parser->generate_toc( $md );

		$this->assertSame( 'getting-started', $toc[0]['id'] );
		$this->assertSame( 'installation-guide', $toc[1]['id'] );
	}

	public function test_generate_toc_returns_empty_for_no_headings(): void {
		$md = "Just a paragraph with no headings.";

		$toc = $this->parser->generate_toc( $md );

		$this->assertSame( array(), $toc );
	}
}
