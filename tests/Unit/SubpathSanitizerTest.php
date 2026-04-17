<?php

namespace Morntag\WpDocsManager\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Phase 2 acceptance: subpath input must reject traversal (`..`), absolute
 * paths (leading `/`), and Windows-style backslashes. Valid input has
 * leading/trailing slashes stripped. Empty string passes through unchanged
 * (empty means "no subpath configured").
 *
 * ASSUMPTION: the implementer exposes a sanitizer at
 *   Morntag\WpDocsManager\Services\SubpathSanitizer::sanitize(string $input): ?string
 * returning null for invalid input, the cleaned string for valid input, and
 * '' for an empty input. If the implementer inlines this logic into
 * SettingsPage::sanitize_settings() instead, flag and update these tests.
 */
class SubpathSanitizerTest extends TestCase {

	private const CLASS_FQN = 'Morntag\\WpDocsManager\\Services\\SubpathSanitizer';

	public function test_subpath_sanitizer_class_exists(): void {
		$this->assertTrue(
			class_exists( self::CLASS_FQN ),
			'Expected class Morntag\\WpDocsManager\\Services\\SubpathSanitizer::sanitize().'
		);
	}

	public function test_sanitize_method_exists(): void {
		$this->assertTrue( class_exists( self::CLASS_FQN ) );
		$this->assertTrue(
			method_exists( self::CLASS_FQN, 'sanitize' ),
			'SubpathSanitizer::sanitize() must exist.'
		);
	}

	/**
	 * @dataProvider valid_input_provider
	 */
	public function test_sanitize_returns_cleaned_string_for_valid_input( string $input, string $expected ): void {
		$this->assertTrue( class_exists( self::CLASS_FQN ) );
		$result = call_user_func( array( self::CLASS_FQN, 'sanitize' ), $input );
		$this->assertSame(
			$expected,
			$result,
			"sanitize('{$input}') expected '{$expected}'."
		);
	}

	/**
	 * @return array<string, array{0:string,1:string}>
	 */
	public static function valid_input_provider(): array {
		return array(
			'bare valid path'              => array( 'includes/Modules', 'includes/Modules' ),
			'trailing slash stripped'      => array( 'foo/bar/', 'foo/bar' ),
			'multiple trailing slashes'    => array( 'foo/bar///', 'foo/bar' ),
			'nested valid path'            => array( 'a/b/c', 'a/b/c' ),
			'docs dotfolder'               => array( '.docs', '.docs' ),
			'single segment'               => array( 'docs', 'docs' ),
			'empty string passthrough'     => array( '', '' ),
		);
	}

	/**
	 * @dataProvider invalid_input_provider
	 */
	public function test_sanitize_returns_null_for_invalid_input( string $input ): void {
		$this->assertTrue( class_exists( self::CLASS_FQN ) );
		$result = call_user_func( array( self::CLASS_FQN, 'sanitize' ), $input );
		$this->assertNull(
			$result,
			"sanitize('{$input}') must return null for invalid input."
		);
	}

	/**
	 * @return array<string, array{0:string}>
	 */
	public static function invalid_input_provider(): array {
		return array(
			'parent traversal'            => array( '../etc' ),
			'inline parent traversal'     => array( 'foo/../bar' ),
			'double dot segment'          => array( '..' ),
			'leading slash (absolute)'    => array( '/etc/passwd' ),
			'leading slash on relative'   => array( '/includes/Modules' ),
			'single backslash'            => array( 'a\\b' ),
			'backslash-only windows path' => array( 'a\\b\\c' ),
			'mixed separators'            => array( 'a/b\\c' ),
		);
	}
}
