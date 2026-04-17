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

if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', '/tmp/wp/plugins' );
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
		$GLOBALS['wp_test_deleted_transients'][] = $transient;
		return true;
	}
}

if ( ! isset( $GLOBALS['wp_test_deleted_transients'] ) ) {
	$GLOBALS['wp_test_deleted_transients'] = array();
}

/*
|--------------------------------------------------------------------------
| Option stubs — record deletes, reads, and writes
|--------------------------------------------------------------------------
*/
$GLOBALS['wp_test_deleted_options'] = array();
$GLOBALS['wp_test_options']         = array();

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( string $option ): bool {
		$GLOBALS['wp_test_deleted_options'][] = $option;
		unset( $GLOBALS['wp_test_options'][ $option ] );
		return true;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $option, $default_value = false ) {
		if ( array_key_exists( $option, $GLOBALS['wp_test_options'] ) ) {
			return $GLOBALS['wp_test_options'][ $option ];
		}
		return $default_value;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $option, $value ): bool {
		$GLOBALS['wp_test_options'][ $option ] = $value;
		return true;
	}
}

/*
|--------------------------------------------------------------------------
| Plugin API stubs
|--------------------------------------------------------------------------
*/
$GLOBALS['wp_test_active_plugins']    = array();
$GLOBALS['wp_test_installed_plugins'] = array();
$GLOBALS['wp_test_submenu_pages']     = array();

if ( ! function_exists( 'is_plugin_active' ) ) {
	function is_plugin_active( string $plugin_file ): bool {
		return in_array( $plugin_file, $GLOBALS['wp_test_active_plugins'], true );
	}
}

if ( ! function_exists( 'get_plugins' ) ) {
	function get_plugins(): array {
		return $GLOBALS['wp_test_installed_plugins'];
	}
}

if ( ! function_exists( 'add_submenu_page' ) ) {
	function add_submenu_page(
		string $parent_slug,
		string $page_title,
		string $menu_title,
		string $capability,
		string $menu_slug,
		$callback = ''
	) {
		$GLOBALS['wp_test_submenu_pages'][] = array(
			'parent_slug' => $parent_slug,
			'page_title'  => $page_title,
			'menu_title'  => $menu_title,
			'capability'  => $capability,
			'menu_slug'   => $menu_slug,
			'callback'    => $callback,
		);
		return $parent_slug . '_page_' . $menu_slug;
	}
}

/*
|--------------------------------------------------------------------------
| Role stubs — record add_cap / remove_cap calls
|--------------------------------------------------------------------------
|
| Tests can populate $GLOBALS['wp_test_roles'] before invoking activation or
| uninstall handlers. Each entry is a WP_Test_Role instance whose add_cap()
| and remove_cap() calls are recorded on the object itself. wp_roles() wraps
| that dictionary in an object exposing ->roles and ->get_role(), matching
| the shape of the real WP_Roles API used by uninstall code.
*/

if ( ! class_exists( 'WP_Test_Role' ) ) {
	class WP_Test_Role {
		/** @var string */
		public $name;
		/** @var array<string,bool> */
		public $capabilities = array();
		/** @var array<int,array{cap:string,grant:bool}> */
		public $added_caps = array();
		/** @var string[] */
		public $removed_caps = array();

		public function __construct( string $name, array $capabilities = array() ) {
			$this->name         = $name;
			$this->capabilities = $capabilities;
		}

		public function add_cap( string $cap, bool $grant = true ): void {
			$this->added_caps[]         = array(
				'cap'   => $cap,
				'grant' => $grant,
			);
			$this->capabilities[ $cap ] = $grant;
		}

		public function remove_cap( string $cap ): void {
			$this->removed_caps[] = $cap;
			unset( $this->capabilities[ $cap ] );
		}

		public function has_cap( string $cap ): bool {
			return ! empty( $this->capabilities[ $cap ] );
		}
	}
}

if ( ! class_exists( 'WP_Test_Roles' ) ) {
	class WP_Test_Roles {
		/** @var array<string,WP_Test_Role> */
		public $roles = array();

		public function __construct( array $roles = array() ) {
			$this->roles = $roles;
		}

		public function get_role( string $role ): ?WP_Test_Role {
			return $this->roles[ $role ] ?? null;
		}
	}
}

/**
 * Reset role/option/transient recorders for a fresh test.
 */
if ( ! function_exists( 'wp_test_reset_recorders' ) ) {
	function wp_test_reset_recorders(): void {
		$GLOBALS['wp_test_roles']              = array();
		$GLOBALS['wp_test_deleted_transients'] = array();
		$GLOBALS['wp_test_deleted_options']    = array();
		$GLOBALS['wp_test_options']            = array();
		$GLOBALS['wp_test_active_plugins']     = array();
		$GLOBALS['wp_test_installed_plugins']  = array();
		$GLOBALS['wp_test_submenu_pages']      = array();
		$GLOBALS['wp_test_actions']            = array();
		$GLOBALS['wp_test_filters']            = array();
		$GLOBALS['wp_test_settings_errors']    = array();
	}
}

$GLOBALS['wp_test_roles'] = array();

if ( ! function_exists( 'get_role' ) ) {
	function get_role( string $role ): ?WP_Test_Role {
		return $GLOBALS['wp_test_roles'][ $role ] ?? null;
	}
}

if ( ! function_exists( 'wp_roles' ) ) {
	function wp_roles(): WP_Test_Roles {
		return new WP_Test_Roles( $GLOBALS['wp_test_roles'] );
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

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Minimal apply_filters stub — iterates recorded filters, invokes each matching
	 * callback with (value, ...extra_args), chains the return value.
	 *
	 * @param string $filter Hook name.
	 * @param mixed  $value  Initial value.
	 * @param mixed  ...$args Additional args passed to callbacks.
	 * @return mixed Filtered value.
	 */
	function apply_filters( string $filter, $value, ...$args ) {
		$filters = $GLOBALS['wp_test_filters'] ?? array();
		foreach ( $filters as $entry ) {
			if ( ! is_array( $entry ) || ( $entry['filter'] ?? null ) !== $filter ) {
				continue;
			}
			$callback = $entry['callback'] ?? null;
			if ( ! is_callable( $callback ) ) {
				continue;
			}
			$value = call_user_func( $callback, $value, ...$args );
		}
		return $value;
	}
}

if ( ! function_exists( 'has_filter' ) ) {
	/**
	 * Returns whether any callback has been registered for the given filter/hook.
	 *
	 * @param string $filter Filter or action name.
	 */
	function has_filter( string $filter ): bool {
		$filters = $GLOBALS['wp_test_filters'] ?? array();
		foreach ( $filters as $entry ) {
			if ( is_array( $entry ) && ( $entry['filter'] ?? null ) === $filter ) {
				return true;
			}
		}
		return false;
	}
}

/*
|--------------------------------------------------------------------------
| Settings-error stubs
|--------------------------------------------------------------------------
*/
$GLOBALS['wp_test_settings_errors'] = array();

if ( ! function_exists( 'add_settings_error' ) ) {
	function add_settings_error( string $setting, string $code, string $message, string $type = 'error' ): void {
		$GLOBALS['wp_test_settings_errors'][] = array(
			'setting' => $setting,
			'code'    => $code,
			'message' => $message,
			'type'    => $type,
		);
	}
}

/*
|--------------------------------------------------------------------------
| View / escaping stubs
|--------------------------------------------------------------------------
*/

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( string $url ): string {
		return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = '' ): string {
		return esc_html( $text );
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( string $text, string $domain = '' ): string {
		return esc_attr( $text );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = '' ): string {
		return $text;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( string $path = '' ): string {
		return 'http://example.test/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $cap ): bool {
		return (bool) ( $GLOBALS['wp_test_current_user_can'] ?? true );
	}
}

if ( ! function_exists( 'settings_fields' ) ) {
	function settings_fields( string $option_group ): void {
		echo '<input type="hidden" name="option_page" value="' . esc_attr( $option_group ) . '" />';
	}
}

if ( ! function_exists( 'submit_button' ) ) {
	function submit_button( string $text = 'Save Changes' ): void {
		echo '<p class="submit"><button type="submit">' . esc_html( $text ) . '</button></p>';
	}
}

if ( ! function_exists( 'checked' ) ) {
	function checked( $checked, $current = true, bool $echo = true ): string {
		$result = ( (string) $checked === (string) $current ) ? ' checked="checked"' : '';
		if ( $echo ) {
			echo $result;
		}
		return $result;
	}
}

if ( ! function_exists( 'selected' ) ) {
	function selected( $selected, $current = true, bool $echo = true ): string {
		$result = ( (string) $selected === (string) $current ) ? ' selected="selected"' : '';
		if ( $echo ) {
			echo $result;
		}
		return $result;
	}
}
