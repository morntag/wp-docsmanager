<?php
/**
 * PHPUnit bootstrap — loads Composer autoloader and stubs WordPress functions
 * so unit tests can run without a full WordPress environment.
 */

require_once __DIR__ . '/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| WordPress constants
|--------------------------------------------------------------------------
*/
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wp/' );
}

/*
|--------------------------------------------------------------------------
| WordPress function stubs
|--------------------------------------------------------------------------
| Minimal stubs so classes that call WP functions can be instantiated and
| exercised without a running WordPress installation.
*/

if ( ! function_exists( 'sanitize_title' ) ) {
	/**
	 * Simplified sanitize_title stub — lowercases, strips non-alphanumeric, collapses dashes.
	 */
	function sanitize_title( string $title ): string {
		$title = strtolower( $title );
		$title = preg_replace( '/[^a-z0-9\s-]/', '', $title );
		$title = preg_replace( '/[\s-]+/', '-', $title );
		return trim( $title, '-' );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( string $text ): string {
		return strip_tags( $text );
	}
}

/*
|--------------------------------------------------------------------------
| Transient stubs (always cache-miss)
|--------------------------------------------------------------------------
*/
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( string $transient ) {
		return false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( string $transient, $value, int $expiration = 0 ): bool {
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( string $transient ): bool {
		return true;
	}
}

/*
|--------------------------------------------------------------------------
| Query stubs
|--------------------------------------------------------------------------
*/
if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( array $args = array() ): array {
		return array();
	}
}

/*
|--------------------------------------------------------------------------
| Hook / filter stubs — record calls for assertions
|--------------------------------------------------------------------------
*/
$GLOBALS['wp_test_actions'] = array();
$GLOBALS['wp_test_filters'] = array();

if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$GLOBALS['wp_test_actions'][] = array(
			'hook'          => $hook,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $filter, $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$GLOBALS['wp_test_filters'][] = array(
			'filter'        => $filter,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
	}
}
