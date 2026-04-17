<?php
/**
 * Plugin Name: WP Docs Manager
 * Plugin URI:  https://github.com/morntag/wp-docsmanager
 * Description: Admin UI, Tiptap-based Markdown editor, and filesystem scanner for documenting WordPress plugins — auto-discovers READMEs and .docs trees inside any active plugin.
 * Version:     0.4.0
 * Author:      morntag.com
 * Author URI:  https://morntag.com
 * License:     GPL-2.0-or-later
 * Text Domain: wp-docsmanager
 *
 * @package Morntag\WpDocsManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/vendor/autoload.php';

// Wire the WordPress auto-update checker against the public GitHub repo.
// The repo is public, so no authentication token is used — PUC polls the
// Releases API on the default cache window and downloads the attached ZIP.
if ( class_exists( '\\YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory' ) ) {
	$wp_docsmanager_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/morntag/wp-docsmanager',
		__FILE__,
		'wp-docsmanager'
	);
	$wp_docsmanager_update_checker->setBranch( 'main' );
	$wp_docsmanager_update_checker->getVcsApi()->enableReleaseAssets();
}

register_activation_hook( __FILE__, array( '\\Morntag\\WpDocsManager\\Activation', 'activate' ) );
register_uninstall_hook( __FILE__, array( '\\Morntag\\WpDocsManager\\UninstallHandler', 'run' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		\Morntag\WpDocsManager\DocsManager::instance();
	},
	20
);
