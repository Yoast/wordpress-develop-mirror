<?php
/**
 * WordPress Administration Template Header
 *
 * @package WordPress
 * @subpackage Administration
 */

header( 'Content-Type: ' . get_option( 'html_type' ) . '; charset=' . get_option( 'blog_charset' ) );
if ( ! defined( 'WP_ADMIN' ) ) {
	require_once( dirname( dirname( __FILE__ ) ). '/wp-load.php' );
}
