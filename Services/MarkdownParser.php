<?php
namespace Morntag\WpDocsManager\Services;

use Parsedown;
use ParsedownExtra;
use Symfony\Component\Yaml\Yaml;

/**
 * Markdown Parser Service
 *
 * Handles markdown parsing and frontmatter extraction.
 *
 * @package Morntag\WpDocsManager\Services
 */
class MarkdownParser {

	/**
	 * ParsedownExtra instance
	 *
	 * @var ParsedownExtra
	 */
	private ParsedownExtra $parsedown;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->parsedown = new ParsedownExtra();
		$this->parsedown->setMarkupEscaped( false );
	}

	/**
	 * Parse frontmatter from markdown content
	 *
	 * @param string $content Markdown content.
	 * @return array{frontmatter:array<string,mixed>,content:string} Array with 'frontmatter' and 'content' keys.
	 */
	public function parse_frontmatter( string $content ): array {
		$pattern = '/^---\s*\n(.*?)\n---\s*\n(.*)$/s';

		if ( preg_match( $pattern, $content, $matches ) ) {
			try {
				$frontmatter = Yaml::parse( $matches[1] );
			} catch ( \Exception $e ) {
				$frontmatter = array();
			}

			return array(
				'frontmatter' => $frontmatter,
				'content'     => $matches[2],
			);
		}

		return array(
			'frontmatter' => array(),
			'content'     => $content,
		);
	}

	/**
	 * Parse markdown to HTML
	 *
	 * @param string $content Markdown content.
	 * @return string HTML content.
	 */
	public function parse_markdown( string $content ): string {
		// Remove frontmatter if present.
		$parsed   = $this->parse_frontmatter( $content );
		$markdown = $parsed['content'];

		// Decode HTML entities in link URLs to prevent double-encoding by Parsedown.
		$markdown = $this->normalize_link_urls( $markdown );

		// Convert to HTML.
		$html = $this->parsedown->text( $markdown );

		// Add classes for styling.
		$html = $this->add_syntax_highlighting_classes( $html );

		return $html;
	}

	/**
	 * Decode HTML entities in markdown link URLs to prevent double-encoding by Parsedown
	 *
	 * Finds all markdown links (e.g. `[text](url)` and `![alt](url)`) and runs
	 * `html_entity_decode()` on the URL portion so that entities like `&amp;` are
	 * converted back to `&` before Parsedown processes the content, avoiding
	 * double-encoded output such as `&amp;amp;` in the rendered HTML.
	 *
	 * @param string $markdown Markdown content.
	 * @return string Markdown with decoded link URLs.
	 */
	private function normalize_link_urls( string $markdown ): string {
		$result = preg_replace_callback(
			'/(!?\[[^\]]*\])\(([^)]+)\)/',
			function ( $matches ) {
				return $matches[1] . '(' . html_entity_decode( $matches[2] ) . ')';
			},
			$markdown
		);

		return is_string( $result ) ? $result : $markdown;
	}

	/**
	 * Add Prism.js compatible classes to code blocks
	 *
	 * @param string $html HTML content.
	 * @return string HTML with syntax highlighting classes added.
	 */
	private function add_syntax_highlighting_classes( string $html ): string {
		// Add language-* class to code blocks.
		$html_with_lang = preg_replace_callback(
			'/<pre><code class="language-(\w+)">(.*?)<\/code><\/pre>/s',
			function ( $matches ) {
				$language = $matches[1];
				$code     = $matches[2];
				return sprintf(
					'<pre class="language-%s"><code class="language-%s">%s</code></pre>',
					$language,
					$language,
					$code
				);
			},
			$html
		);

		// Fallback if preg_replace_callback fails.
		if ( null === $html_with_lang ) {
			return $html;
		}

		// Handle code blocks without language.
		$html_result = preg_replace(
			'/<pre><code>(.*?)<\/code><\/pre>/s',
			'<pre class="language-none"><code class="language-none">$1</code></pre>',
			$html_with_lang
		);

		return is_string( $html_result ) ? $html_result : $html_with_lang;
	}

	/**
	 * Generate table of contents from markdown
	 *
	 * @param string $content Markdown content.
	 * @return array<int,array{level:int,title:string,id:string}> TOC entries.
	 */
	public function generate_toc( string $content ): array {
		// Strip frontmatter to avoid YAML comments (e.g. "# comment") being parsed as headings.
		$parsed  = $this->parse_frontmatter( $content );
		$content = $parsed['content'];

		$toc     = array();
		$pattern = '/^(#{1,6})\s+(.+)$/m';

		if ( preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$level = strlen( $match[1] );
				$title = $match[2];
				$id    = sanitize_title( $title );

				$toc[] = array(
					'level' => $level,
					'title' => $title,
					'id'    => $id,
				);
			}
		}

		return $toc;
	}
}
