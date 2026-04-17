<?php
/**
 * PHPStan bootstrap file
 *
 * Defines necessary constants and loads dependencies for static analysis.
 *
 * @package Morntag\WpDocsManager
 */

// Define WordPress constants that are checked in the library code.
if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

// Load Composer autoloader.
require_once __DIR__ . '/vendor/autoload.php';
