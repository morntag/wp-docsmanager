<?php
/**
 * Abstract base class for DocsManager.
 *
 * Provides base functionality for the DocsManager package including
 * hooks registration and singleton pattern implementation.
 *
 * @package Morntag\WpDocsManager
 */

namespace Morntag\WpDocsManager;

/**
 * Abstract base class for DocsManager
 */
abstract class Module {

	/**
	 * Stores instances of all module classes
	 *
	 * @var array<string, static>
	 */
	private static $instances = array();

	/**
	 * WordPress hooks to register.
	 *
	 * The $hooks array can contain two types of structures:
	 * array( string $hook => string $method )
	 * OR
	 * array( string $hook => array( string $method => int $prio = 10, ...))
	 *
	 * @var array<string, string|array<string|int, string|int|array<string, string|int>>>
	 */
	protected $hooks = array();

	/**
	 * WordPress filters to register.
	 *
	 * The $filters array for all registerd filters.
	 *
	 * The structure looks like this:
	 * array(
	 *  string $filter => array(
	 *      'method' => string $method,
	 *      'prio' => int $prio = 10,
	 *      'accepted_args' => int $accepted_args = 1
	 *  )
	 * )
	 *
	 * @var array<string, array<string, mixed>>
	 * */
	protected $filters = array();


	/**
	 * Protected constructor to prevent direct creation.
	 */
	protected function __construct() {
		$this->register_hooks();
		$this->register_filters();
		$this->init();
	}

	/**
	 * Initializes the module. Replacement for __construct().
	 */
	abstract protected function init(): void;

	/**
	 * Gets the module instance.
	 *
	 * @return static
	 */
	public static function instance(): static {
		$class = static::class;

		if ( ! isset( self::$instances[ $class ] ) ) {
			// @phpstan-ignore-next-line new.static - Safe usage with protected constructor
			self::$instances[ $class ] = new static();
		}
		return self::$instances[ $class ];
	}

	/**
	 * Registers WordPress hooks based on the $hooks property.
	 *
	 * This version supports two clear formats:
	 * 1. Simple String: 'hook' => 'method'
	 * 2. Detailed Array: 'hook' => ['method' => 'name', 'prio' => 10, 'accepted_args' => 3]
	 * (Can also be an array of detailed arrays for multiple handlers)
	 */
	protected function register_hooks(): void {
		foreach ( $this->hooks as $hook => $config ) {
			// Case 1: Simple format -> 'hook' => 'method'.
			if ( is_string( $config ) ) {
				add_action( $hook, array( $this, $config ) );
				continue;
			}

			// Case 2: Detailed Array format(s)
			// At this point, $config must be an array
			// Check if this is a single handler definition or an array of multiple handlers.
			$handlers = isset( $config['method'] ) ? array( $config ) : $config;

			foreach ( $handlers as $params ) {
				// Ensure the handler is a properly formed array with a 'method' key.
				if ( ! is_array( $params ) || ! isset( $params['method'] ) || ! is_string( $params['method'] ) ) {
					continue;
				}

				$method        = $params['method'];
				$priority      = $params['prio'] ?? 10;
				$accepted_args = $params['accepted_args'] ?? 1;

				if ( ! is_int( $priority ) || ! is_int( $accepted_args ) ) {
					continue;
				}

				add_action(
					$hook,
					array( $this, $method ),
					$priority,
					$accepted_args
				);
			}
		}
	}

	/**
	 * Registers WordPress filters based on the $filters property.
	 */
	protected function register_filters(): void {
		foreach ( $this->filters as $filter => $params ) {
			if ( ! isset( $params['method'] ) || ! is_string( $params['method'] ) ) {
				continue;
			}

			$method        = $params['method'];
			$priority      = $params['prio'] ?? 10;
			$accepted_args = $params['accepted_args'] ?? 1;

			if ( ! is_int( $priority ) || ! is_int( $accepted_args ) ) {
				continue;
			}

			add_filter(
				$filter,
				array( $this, $method ),
				$priority,
				$accepted_args
			);
		}
	}

	/**
	 * Prevents cloning of singleton.
	 */
	private function __clone() {}

	/**
	 * Prevents unserializing of singleton.
	 */
	public function __wakeup() {}
}
