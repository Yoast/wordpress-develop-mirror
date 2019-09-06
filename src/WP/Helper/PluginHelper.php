<?php namespace WP\Helper;

/**
 * Class PluginHelper
 * @package WP\Helper
 */
class PluginHelper {
	public static function basename( $file ) {
		global $wp_plugin_paths;

		// $wp_plugin_paths contains normalized paths.
		$file = wp_normalize_path( $file );

		arsort( $wp_plugin_paths );
		foreach ( $wp_plugin_paths as $dir => $realdir ) {
			if ( strpos( $file, $realdir ) === 0 ) {
				$file = $dir . substr( $file, strlen( $realdir ) );
			}
		}

		$plugin_dir    = wp_normalize_path( WP_PLUGIN_DIR );
		$mu_plugin_dir = wp_normalize_path( WPMU_PLUGIN_DIR );

		$file = preg_replace( '#^' . preg_quote( $plugin_dir, '#' ) . '/|^' . preg_quote( $mu_plugin_dir, '#' ) . '/#', '', $file ); // get relative path from plugins dir
		$file = trim( $file, '/' );
		return $file;
	}

	public static function registerRealpath( $file ) {
		global $wp_plugin_paths;

		// Normalize, but store as static to avoid recalculation of a constant value
		static $wp_plugin_path = null, $wpmu_plugin_path = null;
		if ( ! isset( $wp_plugin_path ) ) {
			$wp_plugin_path   = wp_normalize_path( WP_PLUGIN_DIR );
			$wpmu_plugin_path = wp_normalize_path( WPMU_PLUGIN_DIR );
		}

		$plugin_path     = wp_normalize_path( dirname( $file ) );
		$plugin_realpath = wp_normalize_path( dirname( realpath( $file ) ) );

		if ( $plugin_path === $wp_plugin_path || $plugin_path === $wpmu_plugin_path ) {
			return false;
		}

		if ( $plugin_path !== $plugin_realpath ) {
			$wp_plugin_paths[ $plugin_path ] = $plugin_realpath;
		}

		return true;
	}

	public static function dirPath( $file ) {
		return trailingslashit( dirname( $file ) );
	}

	public static function dirUrl( $file ) {
		return trailingslashit( plugins_url( '', $file ) );
	}
}
