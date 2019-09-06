<?php
/**
 * The plugin API is located in this file, which allows for creating actions
 * and filters and hooking functions, and methods. The functions or methods will
 * then be run when the action or filter is called.
 *
 * The API callback examples reference functions, but can be methods of classes.
 * To hook methods, you'll need to pass an array one of two ways.
 *
 * Any of the syntaxes explained in the PHP documentation for the
 * {@link https://secure.php.net/manual/en/language.pseudo-types.php#language.types.callback 'callback'}
 * type are valid.
 *
 * Also see the {@link https://developer.wordpress.org/plugins/ Plugin API} for
 * more information and examples on how to use a lot of these functions.
 *
 * This file should have no external dependencies.
 *
 * @package WordPress
 * @subpackage Plugin
 * @since 1.5.0
 */

// Initialize the filter globals.
require( dirname( __FILE__ ) . '/class-wp-hook.php' );

/** @var WP_Hook[] $wp_filter */
global $wp_filter, $wp_actions, $wp_current_filter;

if ( $wp_filter ) {
	$wp_filter = WP_Hook::build_preinitialized_hooks( $wp_filter );
} else {
	$wp_filter = array();
}

if ( ! isset( $wp_actions ) ) {
	$wp_actions = array();
}

if ( ! isset( $wp_current_filter ) ) {
	$wp_current_filter = array();
}
