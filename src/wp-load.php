<?php
/**
 * Bootstrap file for setting the ABSPATH constant
 * and loading the wp-config.php file. The wp-config.php
 * file will then load the wp-settings.php file, which
 * will then set up the WordPress environment.
 *
 * If the wp-config.php file is not found then an error
 * will be displayed asking the visitor to set up the
 * wp-config.php file.
 *
 * Will also search for wp-config.php in WordPress' parent
 * directory to allow the WordPress directory to remain
 * untouched.
 *
 * @package WordPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __FILE__ ) . '/' );
}

if ( is_readable( __DIR__ . '/../vendor/autoload.php' ) ) {
	require __DIR__ . '/../vendor/autoload.php';
} elseif ( is_readable( __DIR__ . '/../vendor/autoload.php' ) ) {
	require __DIR__ . '/vendor/autoload.php';
}

static $wp_initialized;

if ( $wp_initialized !== true ) {
	$initializer = new WP\Initializer\Main();
	$initializer->initialize();
	$wp_initialized = true;
	$router = new WP\Router( WP\Config\Routes::ROUTES );
	$router->route();
}
