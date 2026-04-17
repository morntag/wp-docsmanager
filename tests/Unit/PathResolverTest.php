<?php

namespace Morntag\WpDocsManager\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Phase 2 acceptance: resolve (plugin_slug, subpath) -> absolute path via
 *   WP_PLUGIN_DIR . '/' . $slug . '/' . $subpath
 * and allow `morntag_docs_modules_dir` / `morntag_docs_docs_dir` filter
 * overrides to win over the stored setting.
 *
 * Scan-disabled / missing-slug => resolver returns ''.
 *
 * ASSUMPTION: the implementer exposes
 *   Morntag\WpDocsManager\Services\PathResolver
 * with instance methods:
 *   - resolve_modules_path(array $settings): string
 *   - resolve_docs_path(array $settings): string
 * Both must apply their respective `morntag_docs_modules_dir` /
 * `morntag_docs_docs_dir` filters on the resolved value.
 * If the implementer inlines this into DocsManager::init(), flag and update.
 */
class PathResolverTest extends TestCase {

	private const CLASS_FQN = 'Morntag\\WpDocsManager\\Services\\PathResolver';

	protected function setUp(): void {
		wp_test_reset_recorders();
	}

	public function test_path_resolver_class_exists(): void {
		$this->assertTrue(
			class_exists( self::CLASS_FQN ),
			'Expected class Morntag\\WpDocsManager\\Services\\PathResolver.'
		);
	}

	public function test_resolver_methods_exist(): void {
		$this->assertTrue( class_exists( self::CLASS_FQN ) );
		$this->assertTrue( method_exists( self::CLASS_FQN, 'resolve_modules_path' ) );
		$this->assertTrue( method_exists( self::CLASS_FQN, 'resolve_docs_path' ) );
	}

	public function test_modules_path_built_from_wp_plugin_dir_slug_and_subpath(): void {
		$this->assertTrue( class_exists( self::CLASS_FQN ) );

		$resolver = new ( self::CLASS_FQN )();
		$path     = $resolver->resolve_modules_path(
			array(
				'plugin_slug'          => 'mcc-baspo',
				'modules_scan_enabled' => true,
				'modules_subpath'      => 'includes/Modules',
				'docs_scan_enabled'    => false,
				'docs_subpath'         => '.docs',
			)
		);

		$this->assertSame( WP_PLUGIN_DIR . '/mcc-baspo/includes/Modules', $path );
	}

	public function test_docs_path_built_from_wp_plugin_dir_slug_and_subpath(): void {
		$this->assertTrue( class_exists( self::CLASS_FQN ) );

		$resolver = new ( self::CLASS_FQN )();
		$path     = $resolver->resolve_docs_path(
			array(
				'plugin_slug'          => 'mcc-baspo',
				'modules_scan_enabled' => false,
				'modules_subpath'      => 'includes/Modules',
				'docs_scan_enabled'    => true,
				'docs_subpath'         => '.docs',
			)
		);

		$this->assertSame( WP_PLUGIN_DIR . '/mcc-baspo/.docs', $path );
	}

	public function test_modules_path_empty_when_scan_disabled(): void {
		$this->assertTrue( class_exists( self::CLASS_FQN ) );

		$resolver = new ( self::CLASS_FQN )();
		$path     = $resolver->resolve_modules_path(
			array(
				'plugin_slug'          => 'mcc-baspo',
				'modules_scan_enabled' => false,
				'modules_subpath'      => 'includes/Modules',
				'docs_scan_enabled'    => true,
				'docs_subpath'         => '.docs',
			)
		);

		$this->assertSame( '', $path, 'Disabled modules scan must resolve to empty string.' );
	}

	public function test_docs_path_empty_when_scan_disabled(): void {
		$this->assertTrue( class_exists( self::CLASS_FQN ) );

		$resolver = new ( self::CLASS_FQN )();
		$path     = $resolver->resolve_docs_path(
			array(
				'plugin_slug'          => 'mcc-baspo',
				'modules_scan_enabled' => true,
				'modules_subpath'      => 'includes/Modules',
				'docs_scan_enabled'    => false,
				'docs_subpath'         => '.docs',
			)
		);

		$this->assertSame( '', $path, 'Disabled docs scan must resolve to empty string.' );
	}

	public function test_empty_plugin_slug_results_in_empty_path(): void {
		$this->assertTrue( class_exists( self::CLASS_FQN ) );

		$resolver = new ( self::CLASS_FQN )();
		$path     = $resolver->resolve_modules_path(
			array(
				'plugin_slug'          => '',
				'modules_scan_enabled' => true,
				'modules_subpath'      => 'includes/Modules',
				'docs_scan_enabled'    => false,
				'docs_subpath'         => '.docs',
			)
		);

		$this->assertSame( '', $path, 'Missing plugin_slug must resolve to empty string.' );
	}

	public function test_modules_dir_filter_overrides_resolved_path(): void {
		$this->assertTrue( class_exists( self::CLASS_FQN ) );

		add_filter(
			'morntag_docs_modules_dir',
			static fn( string $path ): string => '/custom/modules/override',
			10,
			1
		);

		$resolver = new ( self::CLASS_FQN )();
		$path     = $resolver->resolve_modules_path(
			array(
				'plugin_slug'          => 'mcc-baspo',
				'modules_scan_enabled' => true,
				'modules_subpath'      => 'includes/Modules',
				'docs_scan_enabled'    => false,
				'docs_subpath'         => '.docs',
			)
		);

		$this->assertSame(
			'/custom/modules/override',
			$path,
			'morntag_docs_modules_dir filter return value must win over settings-derived path.'
		);
	}

	public function test_docs_dir_filter_overrides_resolved_path(): void {
		$this->assertTrue( class_exists( self::CLASS_FQN ) );

		add_filter(
			'morntag_docs_docs_dir',
			static fn( string $path ): string => '/custom/docs/override',
			10,
			1
		);

		$resolver = new ( self::CLASS_FQN )();
		$path     = $resolver->resolve_docs_path(
			array(
				'plugin_slug'          => 'mcc-baspo',
				'modules_scan_enabled' => false,
				'modules_subpath'      => 'includes/Modules',
				'docs_scan_enabled'    => true,
				'docs_subpath'         => '.docs',
			)
		);

		$this->assertSame(
			'/custom/docs/override',
			$path,
			'morntag_docs_docs_dir filter return value must win over settings-derived path.'
		);
	}

	public function test_modules_filter_receives_resolved_absolute_path_as_value(): void {
		$this->assertTrue( class_exists( self::CLASS_FQN ) );

		$captured = null;
		add_filter(
			'morntag_docs_modules_dir',
			static function ( string $path ) use ( &$captured ): string {
				$captured = $path;
				return $path;
			},
			10,
			1
		);

		$resolver = new ( self::CLASS_FQN )();
		$resolver->resolve_modules_path(
			array(
				'plugin_slug'          => 'mcc-baspo',
				'modules_scan_enabled' => true,
				'modules_subpath'      => 'includes/Modules',
				'docs_scan_enabled'    => false,
				'docs_subpath'         => '.docs',
			)
		);

		$this->assertSame(
			WP_PLUGIN_DIR . '/mcc-baspo/includes/Modules',
			$captured,
			'Filter callbacks must receive the resolved absolute path as their $value argument.'
		);
	}
}
