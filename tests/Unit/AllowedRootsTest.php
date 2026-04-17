<?php

namespace Morntag\WpDocsManager\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Phase 2 acceptance: `allowed_roots` is auto-derived from whichever scans
 * are enabled. Both enabled -> 2 roots; only one enabled -> 1 root; neither
 * -> empty array. Used by the viewer's `realpath()` check to reject
 * out-of-scope file paths.
 *
 * ASSUMPTION: the implementer places this logic on
 *   Morntag\WpDocsManager\Services\PathResolver::allowed_roots(array $settings): array
 * If the implementer decides to live this on DocsManager (e.g. expose via
 * DocsManager::instance()->get_allowed_roots() after init()), flag and
 * update — the DocsManager angle is covered by DocsManagerConfigHydrationTest.
 */
class AllowedRootsTest extends TestCase {

	private const CLASS_FQN = 'Morntag\\WpDocsManager\\Services\\PathResolver';

	protected function setUp(): void {
		wp_test_reset_recorders();
	}

	public function test_allowed_roots_method_exists(): void {
		$this->assertTrue(
			class_exists( self::CLASS_FQN ),
			'PathResolver class missing — see PathResolverTest for context.'
		);
		$this->assertTrue(
			method_exists( self::CLASS_FQN, 'allowed_roots' ),
			'PathResolver::allowed_roots(array $settings): array must exist.'
		);
	}

	/**
	 * @dataProvider scan_combination_provider
	 *
	 * @param array<string,mixed> $settings
	 * @param string[]            $expected_roots
	 */
	public function test_allowed_roots_derived_from_enabled_scans( array $settings, array $expected_roots, string $label ): void {
		$this->assertTrue( class_exists( self::CLASS_FQN ) );

		$resolver = new ( self::CLASS_FQN )();
		$roots    = $resolver->allowed_roots( $settings );

		$this->assertIsArray( $roots );
		// Normalise order — "allowed_roots" is a set, order not contractually fixed.
		sort( $roots );
		sort( $expected_roots );

		$this->assertSame(
			$expected_roots,
			$roots,
			"allowed_roots mismatch for case '{$label}'."
		);
	}

	/**
	 * @return array<string, array{0:array<string,mixed>,1:string[],2:string}>
	 */
	public static function scan_combination_provider(): array {
		$modules_path = WP_PLUGIN_DIR . '/mcc-baspo/includes/Modules';
		$docs_path    = WP_PLUGIN_DIR . '/mcc-baspo/.docs';

		$base = array(
			'plugin_slug'     => 'mcc-baspo',
			'modules_subpath' => 'includes/Modules',
			'docs_subpath'    => '.docs',
		);

		return array(
			'both scans enabled -> two roots' => array(
				$base + array(
					'modules_scan_enabled' => true,
					'docs_scan_enabled'    => true,
				),
				array( $modules_path, $docs_path ),
				'both enabled',
			),
			'only modules enabled -> modules root' => array(
				$base + array(
					'modules_scan_enabled' => true,
					'docs_scan_enabled'    => false,
				),
				array( $modules_path ),
				'only modules',
			),
			'only docs enabled -> docs root' => array(
				$base + array(
					'modules_scan_enabled' => false,
					'docs_scan_enabled'    => true,
				),
				array( $docs_path ),
				'only docs',
			),
			'neither enabled -> empty array' => array(
				$base + array(
					'modules_scan_enabled' => false,
					'docs_scan_enabled'    => false,
				),
				array(),
				'neither enabled',
			),
		);
	}
}
