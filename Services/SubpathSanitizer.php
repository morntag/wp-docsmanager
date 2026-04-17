<?php
/**
 * Subpath input sanitiser.
 *
 * @package Morntag\WpDocsManager\Services
 */

namespace Morntag\WpDocsManager\Services;

/**
 * Validates and cleans the per-plugin subpath inputs stored in
 * `docsmanager_settings` (e.g. `includes/Modules`, `.docs`).
 *
 * Accepts only forward-slash relative paths that stay within the selected
 * plugin's directory. Rejects traversal (`..`), absolute paths (leading
 * `/`), and Windows-style backslashes. Strips leading/trailing slashes from
 * otherwise-valid input. An empty string is a valid "no subpath" signal and
 * passes through unchanged.
 */
class SubpathSanitizer {

	/**
	 * Sanitise a subpath input.
	 *
	 * @param string $input Raw subpath from the settings form.
	 * @return string|null Cleaned subpath, empty string, or null if invalid.
	 */
	public static function sanitize( string $input ): ?string {
		if ( '' === $input ) {
			return '';
		}

		if ( str_contains( $input, '\\' ) ) {
			return null;
		}

		if ( str_starts_with( $input, '/' ) ) {
			return null;
		}

		// Reject any `..` path segment (covers `..`, `../foo`, `foo/../bar`).
		$segments = explode( '/', $input );
		foreach ( $segments as $segment ) {
			if ( '..' === $segment ) {
				return null;
			}
		}

		return trim( $input, '/' );
	}
}
