<?php
/**
 * Settings repository.
 *
 * @package Morntag\WpDocsManager\Services
 */

namespace Morntag\WpDocsManager\Services;

/**
 * Reads and writes the `docsmanager_settings` WP option.
 *
 * Get returns a shape-stable array with documented defaults merged in so
 * callers never have to do null-coalescing on individual keys. Save runs
 * subpath inputs through SubpathSanitizer; an invalid subpath falls back to
 * the currently-stored value so one bad field never zeroes out an existing
 * configuration.
 */
class SettingsRepository {

	/**
	 * Option name used to store the plugin's settings array.
	 */
	public const OPTION_NAME = 'docsmanager_settings';

	/**
	 * Documented defaults for a fresh install.
	 *
	 * @return array{plugin_slug:string,modules_scan_enabled:bool,modules_subpath:string,docs_scan_enabled:bool,docs_subpath:string}
	 */
	public static function defaults(): array {
		return array(
			'plugin_slug'          => '',
			'modules_scan_enabled' => false,
			'modules_subpath'      => 'includes/Modules',
			'docs_scan_enabled'    => false,
			'docs_subpath'         => '.docs',
		);
	}

	/**
	 * Read the stored settings, merged with defaults.
	 *
	 * @return array{plugin_slug:string,modules_scan_enabled:bool,modules_subpath:string,docs_scan_enabled:bool,docs_subpath:string}
	 */
	public function get(): array {
		$stored = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$defaults = self::defaults();
		$merged   = array_merge( $defaults, $stored );

		return array(
			'plugin_slug'          => is_string( $merged['plugin_slug'] ) ? $merged['plugin_slug'] : '',
			'modules_scan_enabled' => (bool) $merged['modules_scan_enabled'],
			'modules_subpath'      => is_string( $merged['modules_subpath'] ) ? $merged['modules_subpath'] : $defaults['modules_subpath'],
			'docs_scan_enabled'    => (bool) $merged['docs_scan_enabled'],
			'docs_subpath'         => is_string( $merged['docs_subpath'] ) ? $merged['docs_subpath'] : $defaults['docs_subpath'],
		);
	}

	/**
	 * Persist settings after sanitising subpath inputs.
	 *
	 * If ANY subpath field fails validation, the entire save is short-circuited
	 * (the option is left unchanged) and a settings error is raised so the
	 * admin UI can display the rejection. This matches the plan's AC: "settings
	 * are not saved" when the submission contains invalid data.
	 *
	 * @param array<string,mixed> $settings Raw settings payload.
	 */
	public function save( array $settings ): void {
		$current = $this->get();

		$plugin_slug = isset( $settings['plugin_slug'] ) && is_string( $settings['plugin_slug'] )
			? $this->sanitize_plugin_slug( $settings['plugin_slug'] )
			: $current['plugin_slug'];

		$modules_enabled = ! empty( $settings['modules_scan_enabled'] );
		$docs_enabled    = ! empty( $settings['docs_scan_enabled'] );

		$modules_subpath = $current['modules_subpath'];
		if ( array_key_exists( 'modules_subpath', $settings ) && is_string( $settings['modules_subpath'] ) ) {
			$cleaned = SubpathSanitizer::sanitize( $settings['modules_subpath'] );
			if ( null === $cleaned ) {
				$this->raise_invalid_subpath_error( (string) $settings['modules_subpath'] );
				return;
			}
			$modules_subpath = $cleaned;
		}

		$docs_subpath = $current['docs_subpath'];
		if ( array_key_exists( 'docs_subpath', $settings ) && is_string( $settings['docs_subpath'] ) ) {
			$cleaned = SubpathSanitizer::sanitize( $settings['docs_subpath'] );
			if ( null === $cleaned ) {
				$this->raise_invalid_subpath_error( (string) $settings['docs_subpath'] );
				return;
			}
			$docs_subpath = $cleaned;
		}

		$clean = array(
			'plugin_slug'          => $plugin_slug,
			'modules_scan_enabled' => $modules_enabled,
			'modules_subpath'      => $modules_subpath,
			'docs_scan_enabled'    => $docs_enabled,
			'docs_subpath'         => $docs_subpath,
		);

		update_option( self::OPTION_NAME, $clean );
	}

	/**
	 * Register a validation error for an invalid subpath input.
	 *
	 * @param string $input Offending raw input (echoed in the message).
	 */
	private function raise_invalid_subpath_error( string $input ): void {
		if ( function_exists( 'add_settings_error' ) ) {
			add_settings_error(
				self::OPTION_NAME,
				'invalid_subpath',
				sprintf(
					/* translators: %s: offending subpath string */
					'Subpath `%s` is invalid — paths cannot contain `..`, absolute paths, or backslashes.',
					$input
				)
			);
		}
	}

	/**
	 * Strip anything that isn't a safe plugin folder slug.
	 *
	 * @param string $slug Candidate slug.
	 */
	private function sanitize_plugin_slug( string $slug ): string {
		$slug = trim( $slug );
		if ( '' === $slug ) {
			return '';
		}

		// Folder slug only — strip path separators and any explicit ".php" reference.
		$slug = str_replace( array( '\\', '/' ), '', $slug );
		if ( '' === $slug || str_contains( $slug, '..' ) ) {
			return '';
		}

		return $slug;
	}
}
