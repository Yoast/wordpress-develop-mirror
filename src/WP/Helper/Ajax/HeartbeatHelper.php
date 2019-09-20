<?php

namespace WP\Helper\Ajax;

class HeartbeatHelper {
	/**
	 * Ajax handler for the Heartbeat API in
	 * the no-privilege context.
	 *
	 * Runs when the user is not logged in.
	 */
	public static function sendNoPriv() {
		$response = array();

		// screen_id is the same as $current_screen->id and the JS global 'pagenow'.
		if ( ! empty( $_POST['screen_id'] ) ) {
			$screen_id = sanitize_key( $_POST['screen_id'] );
		} else {
			$screen_id = 'front';
		}

		if ( ! empty( $_POST['data'] ) ) {
			$data = wp_unslash( (array) $_POST['data'] );

			/**
			 * Filters Heartbeat Ajax response in no-privilege environments.
			 *
			 * @since 3.6.0
			 *
			 * @param array  $response  The no-priv Heartbeat response.
			 * @param array  $data      The $_POST data sent.
			 * @param string $screen_id The screen id.
			 */
			$response = apply_filters( 'heartbeat_nopriv_received', $response, $data, $screen_id );
		}

		/**
		 * Filters Heartbeat Ajax response in no-privilege environments when no data is passed.
		 *
		 * @since 3.6.0
		 *
		 * @param array  $response  The no-priv Heartbeat response.
		 * @param string $screen_id The screen id.
		 */
		$response = apply_filters( 'heartbeat_nopriv_send', $response, $screen_id );

		/**
		 * Fires when Heartbeat ticks in no-privilege environments.
		 *
		 * Allows the transport to be easily replaced with long-polling.
		 *
		 * @since 3.6.0
		 *
		 * @param array  $response  The no-priv Heartbeat response.
		 * @param string $screen_id The screen id.
		 */
		do_action( 'heartbeat_nopriv_tick', $response, $screen_id );

		// Send the current time according to the server.
		$response['server_time'] = time();

		wp_send_json( $response );
	}

	/**
	 * Ajax handler for the Heartbeat API.
	 *
	 * Runs when the user is logged in.
	 */
	public static function send() {
		if ( empty( $_POST['_nonce'] ) ) {
			wp_send_json_error();
		}

		$response    = array();
		$data        = array();
		$nonce_state = wp_verify_nonce( $_POST['_nonce'], 'heartbeat-nonce' );

		// screen_id is the same as $current_screen->id and the JS global 'pagenow'.
		if ( ! empty( $_POST['screen_id'] ) ) {
			$screen_id = sanitize_key( $_POST['screen_id'] );
		} else {
			$screen_id = 'front';
		}

		if ( ! empty( $_POST['data'] ) ) {
			$data = wp_unslash( (array) $_POST['data'] );
		}

		if ( 1 !== $nonce_state ) {
			/**
			 * Filters the nonces to send to the New/Edit Post screen.
			 *
			 * @since 4.3.0
			 *
			 * @param array  $response  The Heartbeat response.
			 * @param array  $data      The $_POST data sent.
			 * @param string $screen_id The screen id.
			 */
			$response = apply_filters( 'wp_refresh_nonces', $response, $data, $screen_id );

			if ( false === $nonce_state ) {
				// User is logged in but nonces have expired.
				$response['nonces_expired'] = true;
				wp_send_json( $response );
			}
		}

		if ( ! empty( $data ) ) {
			/**
			 * Filters the Heartbeat response received.
			 *
			 * @since 3.6.0
			 *
			 * @param array  $response  The Heartbeat response.
			 * @param array  $data      The $_POST data sent.
			 * @param string $screen_id The screen id.
			 */
			$response = apply_filters( 'heartbeat_received', $response, $data, $screen_id );
		}

		/**
		 * Filters the Heartbeat response sent.
		 *
		 * @since 3.6.0
		 *
		 * @param array  $response  The Heartbeat response.
		 * @param string $screen_id The screen id.
		 */
		$response = apply_filters( 'heartbeat_send', $response, $screen_id );

		/**
		 * Fires when Heartbeat ticks in logged-in environments.
		 *
		 * Allows the transport to be easily replaced with long-polling.
		 *
		 * @since 3.6.0
		 *
		 * @param array  $response  The Heartbeat response.
		 * @param string $screen_id The screen id.
		 */
		do_action( 'heartbeat_tick', $response, $screen_id );

		// Send the current time according to the server
		$response['server_time'] = time();

		wp_send_json( $response );
	}
}
