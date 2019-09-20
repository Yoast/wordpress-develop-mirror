<?php

namespace WP\Helper\Ajax;

class PrivacyHelper {
	/**
	 * Ajax handler for exporting a user's personal data.
	 *
	 * @since 4.9.6
	 */
	public static function exportPersonalData() {
		if ( empty( $_POST['id'] ) ) {
			wp_send_json_error( __( 'Missing request ID.' ) );
		}

		$request_id = (int) $_POST['id'];

		if ( $request_id < 1 ) {
			wp_send_json_error( __( 'Invalid request ID.' ) );
		}

		if ( ! current_user_can( 'export_others_personal_data' ) ) {
			wp_send_json_error( __( 'Sorry, you are not allowed to perform this action.' ) );
		}

		check_ajax_referer( 'wp-privacy-export-personal-data-' . $request_id, 'security' );

		// Get the request data.
		$request = wp_get_user_request_data( $request_id );

		if ( ! $request || 'export_personal_data' !== $request->action_name ) {
			wp_send_json_error( __( 'Invalid request type.' ) );
		}

		$email_address = $request->email;
		if ( ! is_email( $email_address ) ) {
			wp_send_json_error( __( 'A valid email address must be given.' ) );
		}

		if ( ! isset( $_POST['exporter'] ) ) {
			wp_send_json_error( __( 'Missing exporter index.' ) );
		}

		$exporter_index = (int) $_POST['exporter'];

		if ( ! isset( $_POST['page'] ) ) {
			wp_send_json_error( __( 'Missing page index.' ) );
		}

		$page = (int) $_POST['page'];

		$send_as_email = isset( $_POST['sendAsEmail'] ) ? ( 'true' === $_POST['sendAsEmail'] ) : false;

		/**
		 * Filters the array of exporter callbacks.
		 *
		 * @since 4.9.6
		 *
		 * @param array $args {
		 *     An array of callable exporters of personal data. Default empty array.
		 *
		 *     @type array {
		 *         Array of personal data exporters.
		 *
		 *         @type string $callback               Callable exporter function that accepts an
		 *                                              email address and a page and returns an array
		 *                                              of name => value pairs of personal data.
		 *         @type string $exporter_friendly_name Translated user facing friendly name for the
		 *                                              exporter.
		 *     }
		 * }
		 */
		$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );

		if ( ! is_array( $exporters ) ) {
			wp_send_json_error( __( 'An exporter has improperly used the registration filter.' ) );
		}

		// Do we have any registered exporters?
		if ( 0 < count( $exporters ) ) {
			if ( $exporter_index < 1 ) {
				wp_send_json_error( __( 'Exporter index cannot be negative.' ) );
			}

			if ( $exporter_index > count( $exporters ) ) {
				wp_send_json_error( __( 'Exporter index is out of range.' ) );
			}

			if ( $page < 1 ) {
				wp_send_json_error( __( 'Page index cannot be less than one.' ) );
			}

			$exporter_keys = array_keys( $exporters );
			$exporter_key  = $exporter_keys[ $exporter_index - 1 ];
			$exporter      = $exporters[ $exporter_key ];

			if ( ! is_array( $exporter ) ) {
				wp_send_json_error(
				/* translators: %s: Exporter array index. */
					sprintf( __( 'Expected an array describing the exporter at index %s.' ), $exporter_key )
				);
			}

			if ( ! array_key_exists( 'exporter_friendly_name', $exporter ) ) {
				wp_send_json_error(
				/* translators: %s: Exporter array index. */
					sprintf( __( 'Exporter array at index %s does not include a friendly name.' ), $exporter_key )
				);
			}

			$exporter_friendly_name = $exporter['exporter_friendly_name'];

			if ( ! array_key_exists( 'callback', $exporter ) ) {
				wp_send_json_error(
				/* translators: %s: Exporter friendly name. */
					sprintf( __( 'Exporter does not include a callback: %s.' ), esc_html( $exporter_friendly_name ) )
				);
			}

			if ( ! is_callable( $exporter['callback'] ) ) {
				wp_send_json_error(
				/* translators: %s: Exporter friendly name. */
					sprintf( __( 'Exporter callback is not a valid callback: %s.' ), esc_html( $exporter_friendly_name ) )
				);
			}

			$callback = $exporter['callback'];
			$response = call_user_func( $callback, $email_address, $page );

			if ( is_wp_error( $response ) ) {
				wp_send_json_error( $response );
			}

			if ( ! is_array( $response ) ) {
				wp_send_json_error(
				/* translators: %s: Exporter friendly name. */
					sprintf( __( 'Expected response as an array from exporter: %s.' ), esc_html( $exporter_friendly_name ) )
				);
			}

			if ( ! array_key_exists( 'data', $response ) ) {
				wp_send_json_error(
				/* translators: %s: Exporter friendly name. */
					sprintf( __( 'Expected data in response array from exporter: %s.' ), esc_html( $exporter_friendly_name ) )
				);
			}

			if ( ! is_array( $response['data'] ) ) {
				wp_send_json_error(
				/* translators: %s: Exporter friendly name. */
					sprintf( __( 'Expected data array in response array from exporter: %s.' ), esc_html( $exporter_friendly_name ) )
				);
			}

			if ( ! array_key_exists( 'done', $response ) ) {
				wp_send_json_error(
				/* translators: %s: Exporter friendly name. */
					sprintf( __( 'Expected done (boolean) in response array from exporter: %s.' ), esc_html( $exporter_friendly_name ) )
				);
			}
		} else {
			// No exporters, so we're done.
			$exporter_key = '';

			$response = array(
				'data' => array(),
				'done' => true,
			);
		}

		/**
		 * Filters a page of personal data exporter data. Used to build the export report.
		 *
		 * Allows the export response to be consumed by destinations in addition to Ajax.
		 *
		 * @since 4.9.6
		 *
		 * @param array  $response        The personal data for the given exporter and page.
		 * @param int    $exporter_index  The index of the exporter that provided this data.
		 * @param string $email_address   The email address associated with this personal data.
		 * @param int    $page            The page for this response.
		 * @param int    $request_id      The privacy request post ID associated with this request.
		 * @param bool   $send_as_email   Whether the final results of the export should be emailed to the user.
		 * @param string $exporter_key    The key (slug) of the exporter that provided this data.
		 */
		$response = apply_filters( 'wp_privacy_personal_data_export_page', $response, $exporter_index, $email_address, $page, $request_id, $send_as_email, $exporter_key );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( $response );
		}

		wp_send_json_success( $response );
	}

	/**
	 * Ajax handler for erasing personal data.
	 *
	 * @since 4.9.6
	 */
	public static function erasePersonalData() {
		if ( empty( $_POST['id'] ) ) {
			wp_send_json_error( __( 'Missing request ID.' ) );
		}

		$request_id = (int) $_POST['id'];

		if ( $request_id < 1 ) {
			wp_send_json_error( __( 'Invalid request ID.' ) );
		}

		// Both capabilities are required to avoid confusion, see `_wp_personal_data_removal_page()`.
		if ( ! current_user_can( 'erase_others_personal_data' ) || ! current_user_can( 'delete_users' ) ) {
			wp_send_json_error( __( 'Sorry, you are not allowed to perform this action.' ) );
		}

		check_ajax_referer( 'wp-privacy-erase-personal-data-' . $request_id, 'security' );

		// Get the request data.
		$request = wp_get_user_request_data( $request_id );

		if ( ! $request || 'remove_personal_data' !== $request->action_name ) {
			wp_send_json_error( __( 'Invalid request type.' ) );
		}

		$email_address = $request->email;

		if ( ! is_email( $email_address ) ) {
			wp_send_json_error( __( 'Invalid email address in request.' ) );
		}

		if ( ! isset( $_POST['eraser'] ) ) {
			wp_send_json_error( __( 'Missing eraser index.' ) );
		}

		$eraser_index = (int) $_POST['eraser'];

		if ( ! isset( $_POST['page'] ) ) {
			wp_send_json_error( __( 'Missing page index.' ) );
		}

		$page = (int) $_POST['page'];

		/**
		 * Filters the array of personal data eraser callbacks.
		 *
		 * @since 4.9.6
		 *
		 * @param array $args {
		 *     An array of callable erasers of personal data. Default empty array.
		 *
		 *     @type array {
		 *         Array of personal data exporters.
		 *
		 *         @type string $callback               Callable eraser that accepts an email address and
		 *                                              a page and returns an array with boolean values for
		 *                                              whether items were removed or retained and any messages
		 *                                              from the eraser, as well as if additional pages are
		 *                                              available.
		 *         @type string $exporter_friendly_name Translated user facing friendly name for the eraser.
		 *     }
		 * }
		 */
		$erasers = apply_filters( 'wp_privacy_personal_data_erasers', array() );

		// Do we have any registered erasers?
		if ( 0 < count( $erasers ) ) {

			if ( $eraser_index < 1 ) {
				wp_send_json_error( __( 'Eraser index cannot be less than one.' ) );
			}

			if ( $eraser_index > count( $erasers ) ) {
				wp_send_json_error( __( 'Eraser index is out of range.' ) );
			}

			if ( $page < 1 ) {
				wp_send_json_error( __( 'Page index cannot be less than one.' ) );
			}

			$eraser_keys = array_keys( $erasers );
			$eraser_key  = $eraser_keys[ $eraser_index - 1 ];
			$eraser      = $erasers[ $eraser_key ];

			if ( ! is_array( $eraser ) ) {
				/* translators: %d: Eraser array index. */
				wp_send_json_error( sprintf( __( 'Expected an array describing the eraser at index %d.' ), $eraser_index ) );
			}

			if ( ! array_key_exists( 'eraser_friendly_name', $eraser ) ) {
				/* translators: %d: Eraser array index. */
				wp_send_json_error( sprintf( __( 'Eraser array at index %d does not include a friendly name.' ), $eraser_index ) );
			}

			$eraser_friendly_name = $eraser['eraser_friendly_name'];

			if ( ! array_key_exists( 'callback', $eraser ) ) {
				wp_send_json_error(
					sprintf(
					/* translators: %s: Eraser friendly name. */
						__( 'Eraser does not include a callback: %s.' ),
						esc_html( $eraser_friendly_name )
					)
				);
			}

			if ( ! is_callable( $eraser['callback'] ) ) {
				wp_send_json_error(
					sprintf(
					/* translators: %s: Eraser friendly name. */
						__( 'Eraser callback is not valid: %s.' ),
						esc_html( $eraser_friendly_name )
					)
				);
			}

			$callback = $eraser['callback'];
			$response = call_user_func( $callback, $email_address, $page );

			if ( is_wp_error( $response ) ) {
				wp_send_json_error( $response );
			}

			if ( ! is_array( $response ) ) {
				wp_send_json_error(
					sprintf(
					/* translators: 1: Eraser friendly name, 2: Eraser array index. */
						__( 'Did not receive array from %1$s eraser (index %2$d).' ),
						esc_html( $eraser_friendly_name ),
						$eraser_index
					)
				);
			}

			if ( ! array_key_exists( 'items_removed', $response ) ) {
				wp_send_json_error(
					sprintf(
					/* translators: 1: Eraser friendly name, 2: Eraser array index. */
						__( 'Expected items_removed key in response array from %1$s eraser (index %2$d).' ),
						esc_html( $eraser_friendly_name ),
						$eraser_index
					)
				);
			}

			if ( ! array_key_exists( 'items_retained', $response ) ) {
				wp_send_json_error(
					sprintf(
					/* translators: 1: Eraser friendly name, 2: Eraser array index. */
						__( 'Expected items_retained key in response array from %1$s eraser (index %2$d).' ),
						esc_html( $eraser_friendly_name ),
						$eraser_index
					)
				);
			}

			if ( ! array_key_exists( 'messages', $response ) ) {
				wp_send_json_error(
					sprintf(
					/* translators: 1: Eraser friendly name, 2: Eraser array index. */
						__( 'Expected messages key in response array from %1$s eraser (index %2$d).' ),
						esc_html( $eraser_friendly_name ),
						$eraser_index
					)
				);
			}

			if ( ! is_array( $response['messages'] ) ) {
				wp_send_json_error(
					sprintf(
					/* translators: 1: Eraser friendly name, 2: Eraser array index. */
						__( 'Expected messages key to reference an array in response array from %1$s eraser (index %2$d).' ),
						esc_html( $eraser_friendly_name ),
						$eraser_index
					)
				);
			}

			if ( ! array_key_exists( 'done', $response ) ) {
				wp_send_json_error(
					sprintf(
					/* translators: 1: Eraser friendly name, 2: Eraser array index. */
						__( 'Expected done flag in response array from %1$s eraser (index %2$d).' ),
						esc_html( $eraser_friendly_name ),
						$eraser_index
					)
				);
			}
		} else {
			// No erasers, so we're done.
			$eraser_key = '';

			$response = array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		/**
		 * Filters a page of personal data eraser data.
		 *
		 * Allows the erasure response to be consumed by destinations in addition to Ajax.
		 *
		 * @since 4.9.6
		 *
		 * @param array  $response        The personal data for the given exporter and page.
		 * @param int    $eraser_index    The index of the eraser that provided this data.
		 * @param string $email_address   The email address associated with this personal data.
		 * @param int    $page            The page for this response.
		 * @param int    $request_id      The privacy request post ID associated with this request.
		 * @param string $eraser_key      The key (slug) of the eraser that provided this data.
		 */
		$response = apply_filters( 'wp_privacy_personal_data_erasure_page', $response, $eraser_index, $email_address, $page, $request_id, $eraser_key );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( $response );
		}

		wp_send_json_success( $response );
	}
}
