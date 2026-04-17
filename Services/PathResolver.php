<?php
/**
 * Path resolver for scan directories.
 *
 * @package Morntag\WpDocsManager\Services
 */

namespace Morntag\WpDocsManager\Services;

/**
 * Turns the stored settings array into absolute filesystem paths.
 *
 * Composes paths from `WP_PLUGIN_DIR . '/' . $slug . '/' . $subpath` and
 * then runs them through the `morntag_docs_modules_dir` /
 * `morntag_docs_docs_dir` filters so programmatic setups can override.
 * Returns an empty string when the scan is disabled or the slug is missing.
 */
class PathResolver {

	/**
	 * Resolve the absolute module READMEs scan path.
	 *
	 * @param array<string,mixed> $settings Settings array (see SettingsRepository::get()).
	 */
	public function resolve_modules_path( array $settings ): string {
		$base = $this->compose_path(
			$settings,
			'modules_scan_enabled',
			'modules_subpath'
		);

		$filtered = apply_filters( 'morntag_docs_modules_dir', $base );
		return is_string( $filtered ) ? $filtered : '';
	}

	/**
	 * Resolve the absolute docs-tree scan path.
	 *
	 * @param array<string,mixed> $settings Settings array (see SettingsRepository::get()).
	 */
	public function resolve_docs_path( array $settings ): string {
		$base = $this->compose_path(
			$settings,
			'docs_scan_enabled',
			'docs_subpath'
		);

		$filtered = apply_filters( 'morntag_docs_docs_dir', $base );
		return is_string( $filtered ) ? $filtered : '';
	}

	/**
	 * Build the list of filesystem roots the viewer is allowed to read from.
	 *
	 * @param array<string,mixed> $settings Settings array.
	 * @return string[]
	 */
	public function allowed_roots( array $settings ): array {
		$roots = array();

		$modules = $this->resolve_modules_path( $settings );
		if ( '' !== $modules ) {
			$roots[] = $modules;
		}

		$docs = $this->resolve_docs_path( $settings );
		if ( '' !== $docs ) {
			$roots[] = $docs;
		}

		return $roots;
	}

	/**
	 * Compose the pre-filter absolute path for a given scan.
	 *
	 * @param array<string,mixed> $settings     Settings array.
	 * @param string              $enabled_key  Key that toggles the scan.
	 * @param string              $subpath_key  Key holding the subpath.
	 */
	private function compose_path( array $settings, string $enabled_key, string $subpath_key ): string {
		if ( empty( $settings[ $enabled_key ] ) ) {
			return '';
		}

		$slug    = isset( $settings['plugin_slug'] ) && is_string( $settings['plugin_slug'] ) ? trim( $settings['plugin_slug'] ) : '';
		$subpath = isset( $settings[ $subpath_key ] ) && is_string( $settings[ $subpath_key ] ) ? trim( $settings[ $subpath_key ], '/' ) : '';

		if ( '' === $slug ) {
			return '';
		}

		$path = WP_PLUGIN_DIR . '/' . $slug;
		if ( '' !== $subpath ) {
			$path .= '/' . $subpath;
		}

		return $path;
	}
}
