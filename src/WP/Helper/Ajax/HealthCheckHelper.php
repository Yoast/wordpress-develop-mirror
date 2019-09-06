<?php

namespace WP\Helper\Ajax;

class HealthCheckHelper {
	public static function dotOrgCommunication() {
		check_ajax_referer( 'health-check-site-status' );

		if ( ! current_user_can( 'view_site_health_checks' ) ) {
			wp_send_json_error();
		}

		if ( ! class_exists( 'WP_Site_Health' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/class-wp-site-health.php' );
		}

		$site_health = new \WP_Site_Health();
		wp_send_json_success( $site_health->get_test_dotorg_communication() );
	}

	/**
	 * Ajax handler for site health checks on debug mode.
	 */
	public static function isInDebugMode() {
		wp_verify_nonce( 'health-check-site-status' );

		if ( ! current_user_can( 'view_site_health_checks' ) ) {
			wp_send_json_error();
		}

		if ( ! class_exists( 'WP_Site_Health' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/class-wp-site-health.php' );
		}

		$site_health = new \WP_Site_Health();
		wp_send_json_success( $site_health->get_test_is_in_debug_mode() );
	}

	/**
	 * Ajax handler for site health checks on background updates.
	 */
	public static function backgroundUpdates() {
		check_ajax_referer( 'health-check-site-status' );

		if ( ! current_user_can( 'view_site_health_checks' ) ) {
			wp_send_json_error();
		}

		if ( ! class_exists( 'WP_Site_Health' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/class-wp-site-health.php' );
		}

		$site_health = new \WP_Site_Health();
		wp_send_json_success( $site_health->get_test_background_updates() );
	}

	/**
	 * Ajax handler for site health checks on loopback requests.
	 */
	public static function loopbackRequests() {
		check_ajax_referer( 'health-check-site-status' );

		if ( ! current_user_can( 'view_site_health_checks' ) ) {
			wp_send_json_error();
		}

		if ( ! class_exists( 'WP_Site_Health' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/class-wp-site-health.php' );
		}

		$site_health = new \WP_Site_Health();
		wp_send_json_success( $site_health->get_test_loopback_requests() );
	}

	/**
	 * Ajax handler for site health check to update the result status.
	 */
	public static function statusResult() {
		check_ajax_referer( 'health-check-site-status-result' );

		if ( ! current_user_can( 'view_site_health_checks' ) ) {
			wp_send_json_error();
		}

		set_transient( 'health-check-site-status-result', wp_json_encode( $_POST['counts'] ) );

		wp_send_json_success();
	}

	/**
	 * Ajax handler for site health check to get directories and database sizes.
	 */
	public static function checkGetSizes() {
		check_ajax_referer( 'health-check-site-status-result' );

		if ( ! current_user_can( 'view_site_health_checks' ) || is_multisite() ) {
			wp_send_json_error();
		}

		if ( ! class_exists( 'WP_Debug_Data' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/class-wp-debug-data.php' );
		}

		$sizes_data = \WP_Debug_Data::get_sizes();
		$all_sizes  = array( 'raw' => 0 );

		foreach ( $sizes_data as $name => $value ) {
			$name = sanitize_text_field( $name );
			$data = array();

			if ( isset( $value['size'] ) ) {
				if ( is_string( $value['size'] ) ) {
					$data['size'] = sanitize_text_field( $value['size'] );
				} else {
					$data['size'] = (int) $value['size'];
				}
			}

			if ( isset( $value['debug'] ) ) {
				if ( is_string( $value['debug'] ) ) {
					$data['debug'] = sanitize_text_field( $value['debug'] );
				} else {
					$data['debug'] = (int) $value['debug'];
				}
			}

			if ( ! empty( $value['raw'] ) ) {
				$data['raw'] = (int) $value['raw'];
			}

			$all_sizes[ $name ] = $data;
		}

		if ( isset( $all_sizes['total_size']['debug'] ) && 'not available' === $all_sizes['total_size']['debug'] ) {
			wp_send_json_error( $all_sizes );
		}

		wp_send_json_success( $all_sizes );
	}
}
