<?php

namespace WP\Helper\Ajax;

class SiteHealthHelper {
	/**
	 * Ajax handler for compression testing.
	 */
	public static function compressionTest() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( -1 );
		}

		if ( ini_get( 'zlib.output_compression' ) || 'ob_gzhandler' == ini_get( 'output_handler' ) ) {
			update_site_option( 'can_compress_scripts', 0 );
			wp_die( 0 );
		}

		if ( isset( $_GET['test'] ) ) {
			header( 'Expires: Wed, 11 Jan 1984 05:00:00 GMT' );
			header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
			header( 'Cache-Control: no-cache, must-revalidate, max-age=0' );
			header( 'Content-Type: application/javascript; charset=UTF-8' );
			$force_gzip = ( defined( 'ENFORCE_GZIP' ) && ENFORCE_GZIP );
			$test_str   = '"wpCompressionTest Lorem ipsum dolor sit amet consectetuer mollis sapien urna ut a. Eu nonummy condimentum fringilla tempor pretium platea vel nibh netus Maecenas. Hac molestie amet justo quis pellentesque est ultrices interdum nibh Morbi. Cras mattis pretium Phasellus ante ipsum ipsum ut sociis Suspendisse Lorem. Ante et non molestie. Porta urna Vestibulum egestas id congue nibh eu risus gravida sit. Ac augue auctor Ut et non a elit massa id sodales. Elit eu Nulla at nibh adipiscing mattis lacus mauris at tempus. Netus nibh quis suscipit nec feugiat eget sed lorem et urna. Pellentesque lacus at ut massa consectetuer ligula ut auctor semper Pellentesque. Ut metus massa nibh quam Curabitur molestie nec mauris congue. Volutpat molestie elit justo facilisis neque ac risus Ut nascetur tristique. Vitae sit lorem tellus et quis Phasellus lacus tincidunt nunc Fusce. Pharetra wisi Suspendisse mus sagittis libero lacinia Integer consequat ac Phasellus. Et urna ac cursus tortor aliquam Aliquam amet tellus volutpat Vestibulum. Justo interdum condimentum In augue congue tellus sollicitudin Quisque quis nibh."';

			if ( 1 == $_GET['test'] ) {
				echo $test_str;
				wp_die();
			} elseif ( 2 == $_GET['test'] ) {
				if ( ! isset( $_SERVER['HTTP_ACCEPT_ENCODING'] ) ) {
					wp_die( -1 );
				}

				if ( false !== stripos( $_SERVER['HTTP_ACCEPT_ENCODING'], 'deflate' ) && function_exists( 'gzdeflate' ) && ! $force_gzip ) {
					header( 'Content-Encoding: deflate' );
					$out = \gzdeflate( $test_str, 1 );
				} elseif ( false !== stripos( $_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip' ) && function_exists( 'gzencode' ) ) {
					header( 'Content-Encoding: gzip' );
					$out = \gzencode( $test_str, 1 );
				} else {
					wp_die( -1 );
				}

				echo $out;
				wp_die();
			} elseif ( 'no' == $_GET['test'] ) {
				check_ajax_referer( 'update_can_compress_scripts' );
				update_site_option( 'can_compress_scripts', 0 );
			} elseif ( 'yes' == $_GET['test'] ) {
				check_ajax_referer( 'update_can_compress_scripts' );
				update_site_option( 'can_compress_scripts', 1 );
			}
		}

		wp_die( 0 );
	}

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
