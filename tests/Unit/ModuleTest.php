<?php

namespace Morntag\WpDocsManager\Tests\Unit;

use Morntag\WpDocsManager\Module;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Concrete Module subclass with various hook/filter configurations for testing.
 */
class TestableModule extends Module {

	protected $hooks = array(
		// Simple format.
		'init'       => 'handle_init',
		// Detailed format (single handler).
		'admin_init' => array(
			'method'        => 'handle_admin_init',
			'prio'          => 20,
			'accepted_args' => 2,
		),
		// Multiple handlers on one hook.
		'wp_loaded'  => array(
			array(
				'method' => 'handle_loaded_a',
				'prio'   => 5,
			),
			array(
				'method' => 'handle_loaded_b',
				'prio'   => 15,
			),
		),
		// Invalid: no 'method' key — should be skipped.
		'save_post'  => array(
			'prio' => 10,
		),
	);

	protected $filters = array(
		// Valid filter.
		'the_content' => array(
			'method'        => 'filter_content',
			'prio'          => 10,
			'accepted_args' => 1,
		),
		// Invalid: missing 'method' — should be skipped.
		'the_title'   => array(
			'prio' => 5,
		),
	);

	protected function init(): void {}

	public function handle_init(): void {}
	public function handle_admin_init(): void {}
	public function handle_loaded_a(): void {}
	public function handle_loaded_b(): void {}

	public function filter_content( string $content ): string {
		return $content;
	}
}

class ModuleTest extends TestCase {

	protected function setUp(): void {
		// Reset the singleton instances and the global hook/filter trackers.
		$prop = new ReflectionProperty( Module::class, 'instances' );
		$prop->setValue( null, array() );

		$GLOBALS['wp_test_actions'] = array();
		$GLOBALS['wp_test_filters'] = array();
	}

	/*
	|----------------------------------------------------------------------
	| register_hooks()
	|----------------------------------------------------------------------
	*/

	public function test_simple_hook_format_registers_action(): void {
		TestableModule::instance();

		$found = $this->find_action( 'init' );
		$this->assertNotNull( $found, 'Expected action "init" to be registered.' );
		$this->assertSame( 'handle_init', $found['callback'][1] );
		$this->assertSame( 10, $found['priority'] );          // default priority.
		$this->assertSame( 1, $found['accepted_args'] );      // default accepted_args.
	}

	public function test_detailed_hook_format_registers_with_custom_priority(): void {
		TestableModule::instance();

		$found = $this->find_action( 'admin_init' );
		$this->assertNotNull( $found );
		$this->assertSame( 'handle_admin_init', $found['callback'][1] );
		$this->assertSame( 20, $found['priority'] );
		$this->assertSame( 2, $found['accepted_args'] );
	}

	public function test_multiple_handlers_on_same_hook(): void {
		TestableModule::instance();

		$actions = $this->find_all_actions( 'wp_loaded' );
		$this->assertCount( 2, $actions );

		$methods = array_map( fn( $a ) => $a['callback'][1], $actions );
		$this->assertContains( 'handle_loaded_a', $methods );
		$this->assertContains( 'handle_loaded_b', $methods );
	}

	public function test_invalid_hook_config_is_skipped(): void {
		TestableModule::instance();

		$found = $this->find_action( 'save_post' );
		$this->assertNull( $found, 'Hook with no "method" key should be skipped.' );
	}

	/*
	|----------------------------------------------------------------------
	| register_filters()
	|----------------------------------------------------------------------
	*/

	public function test_valid_filter_is_registered(): void {
		TestableModule::instance();

		$found = $this->find_filter( 'the_content' );
		$this->assertNotNull( $found );
		$this->assertSame( 'filter_content', $found['callback'][1] );
		$this->assertSame( 10, $found['priority'] );
	}

	public function test_invalid_filter_is_skipped(): void {
		TestableModule::instance();

		$found = $this->find_filter( 'the_title' );
		$this->assertNull( $found, 'Filter with no "method" key should be skipped.' );
	}

	/*
	|----------------------------------------------------------------------
	| Singleton behaviour
	|----------------------------------------------------------------------
	*/

	public function test_instance_returns_same_object(): void {
		$a = TestableModule::instance();
		$b = TestableModule::instance();

		$this->assertSame( $a, $b );
	}

	/*
	|----------------------------------------------------------------------
	| Helpers
	|----------------------------------------------------------------------
	*/

	private function find_action( string $hook ): ?array {
		foreach ( $GLOBALS['wp_test_actions'] as $action ) {
			if ( $action['hook'] === $hook ) {
				return $action;
			}
		}
		return null;
	}

	private function find_all_actions( string $hook ): array {
		return array_values(
			array_filter(
				$GLOBALS['wp_test_actions'],
				fn( $a ) => $a['hook'] === $hook
			)
		);
	}

	private function find_filter( string $filter ): ?array {
		foreach ( $GLOBALS['wp_test_filters'] as $f ) {
			if ( $f['filter'] === $filter ) {
				return $f;
			}
		}
		return null;
	}
}
