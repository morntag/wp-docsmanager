<?php
/**
 * Settings page service.
 *
 * @package Morntag\WpDocsManager\Services
 */

namespace Morntag\WpDocsManager\Services;

/**
 * Registers the `Documentation → Settings` submenu and drives the form
 * round-trip through the WP Settings API.
 *
 * The constructor wires `admin_menu` and `admin_init` hooks. Actual menu
 * registration lives in `register()` so unit tests can invoke it directly
 * without firing the full action pipeline.
 */
class SettingsPage {

	public const PARENT_SLUG     = 'mcc-documentation';
	public const MENU_SLUG       = 'mcc-documentation-settings';
	public const CAPABILITY      = 'manage_options';
	public const OPTION_GROUP    = 'docsmanager_settings_group';
	public const SETTINGS_FIELDS = 'docsmanager_settings_fields';

	/**
	 * Repository used to read and persist settings.
	 *
	 * @var SettingsRepository
	 */
	private SettingsRepository $repository;

	/**
	 * Resolves stored settings to absolute filesystem paths, with filter overrides.
	 *
	 * @var PathResolver
	 */
	private PathResolver $path_resolver;

	/**
	 * Constructor — binds WP admin hooks.
	 *
	 * @param SettingsRepository|null $repository    Optional repository override (primarily for tests).
	 * @param PathResolver|null       $path_resolver Optional path resolver override.
	 */
	public function __construct( ?SettingsRepository $repository = null, ?PathResolver $path_resolver = null ) {
		$this->repository    = $repository ?? new SettingsRepository();
		$this->path_resolver = $path_resolver ?? new PathResolver();

		if ( function_exists( 'add_action' ) ) {
			add_action( 'admin_menu', array( $this, 'register' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
		}
	}

	/**
	 * Register the `Documentation → Settings` submenu.
	 */
	public function register(): void {
		$title = function_exists( '__' ) ? __( 'Settings', 'wp-docsmanager' ) : 'Settings';

		add_submenu_page(
			self::PARENT_SLUG,
			$title,
			$title,
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Register the option with the WP Settings API.
	 */
	public function register_settings(): void {
		if ( ! function_exists( 'register_setting' ) ) {
			return;
		}

		register_setting(
			self::OPTION_GROUP,
			SettingsRepository::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => SettingsRepository::defaults(),
			)
		);
	}

	/**
	 * Sanitisation callback invoked by the Settings API on form submit.
	 *
	 * Delegates the actual work to SettingsRepository::save() so the same
	 * logic runs whether the option is written programmatically or via the
	 * admin form.
	 *
	 * @param mixed $input Raw form payload.
	 * @return array<string,mixed> Cleaned settings array.
	 */
	public function sanitize( $input ): array {
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$this->repository->save( $input );
		return $this->repository->get();
	}

	/**
	 * Render the settings form.
	 *
	 * Minimal functional markup — fields submit through the WP Settings API
	 * so the save round-trip exercises SettingsRepository / SubpathSanitizer.
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$settings = $this->repository->get();
		$plugins  = function_exists( 'get_plugins' ) ? get_plugins() : array();

		$modules_filter_overridden = function_exists( 'has_filter' ) && has_filter( 'morntag_docs_modules_dir' );
		$docs_filter_overridden    = function_exists( 'has_filter' ) && has_filter( 'morntag_docs_docs_dir' );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Documentation Settings', 'wp-docsmanager' ) . '</h1>';

		$this->render_warnings( $settings, $plugins );

		echo '<form method="post" action="options.php">';

		if ( function_exists( 'settings_fields' ) ) {
			settings_fields( self::OPTION_GROUP );
		}

		echo '<table class="form-table" role="presentation">';

		// Plugin slug dropdown.
		echo '<tr><th scope="row"><label for="docsmanager_plugin_slug">' . esc_html__( 'Plugin', 'wp-docsmanager' ) . '</label></th><td>';
		echo '<select id="docsmanager_plugin_slug" name="' . esc_attr( SettingsRepository::OPTION_NAME ) . '[plugin_slug]">';
		echo '<option value="">' . esc_html__( '— Select a plugin —', 'wp-docsmanager' ) . '</option>';
		foreach ( $plugins as $plugin_file => $plugin_data ) {
			$slug = is_string( $plugin_file ) ? strtok( $plugin_file, '/' ) : '';
			if ( ! is_string( $slug ) || '' === $slug ) {
				continue;
			}
			$name = isset( $plugin_data['Name'] ) ? (string) $plugin_data['Name'] : $slug;
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $slug ),
				selected( $settings['plugin_slug'], $slug, false ),
				esc_html( $name )
			);
		}
		echo '</select>';
		echo '</td></tr>';

		// Module READMEs scan.
		echo '<tr><th scope="row">' . esc_html__( 'Module READMEs scan', 'wp-docsmanager' ) . '</th><td>';
		echo '<label><input type="checkbox" name="' . esc_attr( SettingsRepository::OPTION_NAME ) . '[modules_scan_enabled]" value="1"' . checked( $settings['modules_scan_enabled'], true, false ) . '> ' . esc_html__( 'Enable module READMEs scan', 'wp-docsmanager' ) . '</label><br>';
		if ( $modules_filter_overridden ) {
			$resolved_modules = $this->path_resolver->resolve_modules_path( $settings );
			echo '<input type="text" class="regular-text" value="' . esc_attr( $resolved_modules ) . '" disabled readonly><br>';
			echo '<p class="description">' . esc_html__( 'Overridden by `morntag_docs_modules_dir` filter — settings-page value ignored.', 'wp-docsmanager' ) . '</p>';
		} else {
			echo '<input type="text" class="regular-text" name="' . esc_attr( SettingsRepository::OPTION_NAME ) . '[modules_subpath]" value="' . esc_attr( $settings['modules_subpath'] ) . '">';
		}
		echo '</td></tr>';

		// Docs tree scan.
		echo '<tr><th scope="row">' . esc_html__( 'Docs tree scan', 'wp-docsmanager' ) . '</th><td>';
		echo '<label><input type="checkbox" name="' . esc_attr( SettingsRepository::OPTION_NAME ) . '[docs_scan_enabled]" value="1"' . checked( $settings['docs_scan_enabled'], true, false ) . '> ' . esc_html__( 'Enable docs tree scan', 'wp-docsmanager' ) . '</label><br>';
		if ( $docs_filter_overridden ) {
			$resolved_docs = $this->path_resolver->resolve_docs_path( $settings );
			echo '<input type="text" class="regular-text" value="' . esc_attr( $resolved_docs ) . '" disabled readonly><br>';
			echo '<p class="description">' . esc_html__( 'Overridden by `morntag_docs_docs_dir` filter — settings-page value ignored.', 'wp-docsmanager' ) . '</p>';
		} else {
			echo '<input type="text" class="regular-text" name="' . esc_attr( SettingsRepository::OPTION_NAME ) . '[docs_subpath]" value="' . esc_attr( $settings['docs_subpath'] ) . '">';
		}
		echo '</td></tr>';

		echo '</table>';

		if ( function_exists( 'submit_button' ) ) {
			submit_button();
		}

		echo '</form></div>';
	}

	/**
	 * Emit non-blocking warnings about the current configuration.
	 *
	 * @param array<string,mixed>                      $settings Current settings.
	 * @param array<string,array<string,mixed>|string> $plugins  `get_plugins()` result.
	 */
	private function render_warnings( array $settings, array $plugins ): void {
		$slug = isset( $settings['plugin_slug'] ) && is_string( $settings['plugin_slug'] ) ? $settings['plugin_slug'] : '';

		// Inactive-plugin warning.
		if ( '' !== $slug ) {
			$plugin_file = $this->find_plugin_file_for_slug( $slug, $plugins );
			if ( '' !== $plugin_file && function_exists( 'is_plugin_active' ) && ! is_plugin_active( $plugin_file ) ) {
				printf(
					'<div class="notice notice-warning inline"><p>%s</p></div>',
					esc_html(
						sprintf(
							/* translators: %s: plugin folder slug */
							'Plugin `%s` is not active — scanning is disabled until it is activated.',
							$slug
						)
					)
				);
			}
		}

		// Missing-subpath warnings (only when scan is enabled).
		if ( ! empty( $settings['modules_scan_enabled'] ) ) {
			$resolved = $this->path_resolver->resolve_modules_path( $settings );
			if ( '' !== $resolved && ! is_dir( $resolved ) ) {
				printf(
					'<div class="notice notice-warning inline"><p>%s</p></div>',
					esc_html(
						sprintf(
							/* translators: %s: resolved absolute path */
							'Path `%s` does not exist on disk.',
							$resolved
						)
					)
				);
			}
		}

		if ( ! empty( $settings['docs_scan_enabled'] ) ) {
			$resolved = $this->path_resolver->resolve_docs_path( $settings );
			if ( '' !== $resolved && ! is_dir( $resolved ) ) {
				printf(
					'<div class="notice notice-warning inline"><p>%s</p></div>',
					esc_html(
						sprintf(
							/* translators: %s: resolved absolute path */
							'Path `%s` does not exist on disk.',
							$resolved
						)
					)
				);
			}
		}
	}

	/**
	 * Look up the `folder/file.php` basename for a folder slug.
	 *
	 * @param string                                   $slug    Folder slug (e.g. `mcc-baspo`).
	 * @param array<string,array<string,mixed>|string> $plugins `get_plugins()` result.
	 */
	private function find_plugin_file_for_slug( string $slug, array $plugins ): string {
		foreach ( $plugins as $plugin_file => $_data ) {
			$plugin_file = (string) $plugin_file;
			if ( str_starts_with( $plugin_file, $slug . '/' ) ) {
				return $plugin_file;
			}
		}
		return '';
	}
}
