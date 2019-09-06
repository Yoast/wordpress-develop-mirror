<?php

namespace WP\Helper;

class AjaxHelper {
	/**
	 * Ajax handler for fetching a list table.
	 */
	public static function fetchList() {
		$list_class = $_GET['list_args']['class'];
		check_ajax_referer( "fetch-list-$list_class", '_ajax_fetch_list_nonce' );

		$wp_list_table = _get_list_table( $list_class, array( 'screen' => $_GET['list_args']['screen']['id'] ) );
		if ( ! $wp_list_table ) {
			wp_die( 0 );
		}

		if ( ! $wp_list_table->ajax_user_can() ) {
			wp_die( -1 );
		}

		$wp_list_table->ajax_response();

		wp_die( 0 );
	}

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
}
