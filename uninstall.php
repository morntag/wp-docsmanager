<?php
/**
 * WordPress uninstall dispatcher.
 *
 * Included directly by WordPress when the plugin is deleted. Delegates to
 * the namespaced UninstallHandler class so the cleanup logic stays unit
 * testable.
 *
 * @package Morntag\WpDocsManager
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require __DIR__ . '/vendor/autoload.php';

\Morntag\WpDocsManager\UninstallHandler::run();
