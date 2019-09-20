<?php

namespace WP\Helper\Ajax;

class DashboardHelper {
	/**
	 * Handles AJAX requests for community events.
	 */
	public static function getCommunityEvents() {
		check_ajax_referer( 'community_events' );

		$search         = isset( $_POST['location'] ) ? wp_unslash( $_POST['location'] ) : '';
		$timezone       = isset( $_POST['timezone'] ) ? wp_unslash( $_POST['timezone'] ) : '';
		$user_id        = get_current_user_id();
		$saved_location = get_user_option( 'community-events-location', $user_id );
		$events_client  = new \WP_Community_Events( $user_id, $saved_location );
		$events         = $events_client->get_events( $search, $timezone );
		$ip_changed     = false;

		if ( is_wp_error( $events ) ) {
			wp_send_json_error(
				array(
					'error' => $events->get_error_message(),
				)
			);
		} else {
			if ( empty( $saved_location['ip'] ) && ! empty( $events['location']['ip'] ) ) {
				$ip_changed = true;
			} elseif ( isset( $saved_location['ip'] ) && ! empty( $events['location']['ip'] ) && $saved_location['ip'] !== $events['location']['ip'] ) {
				$ip_changed = true;
			}

			/*
			 * The location should only be updated when it changes. The API doesn't always return
			 * a full location; sometimes it's missing the description or country. The location
			 * that was saved during the initial request is known to be good and complete, though.
			 * It should be left intact until the user explicitly changes it (either by manually
			 * searching for a new location, or by changing their IP address).
			 *
			 * If the location was updated with an incomplete response from the API, then it could
			 * break assumptions that the UI makes (e.g., that there will always be a description
			 * that corresponds to a latitude/longitude location).
			 *
			 * The location is stored network-wide, so that the user doesn't have to set it on each site.
			 */
			if ( $ip_changed || $search ) {
				update_user_option( $user_id, 'community-events-location', $events['location'], true );
			}

			wp_send_json_success( $events );
		}
	}

	/**
	 * Ajax handler for dashboard widgets.
	 */
	public static function dashboardWidgets() {
		$pagenow = $_GET['pagenow'];
		if ( $pagenow === 'dashboard-user' || $pagenow === 'dashboard-network' || $pagenow === 'dashboard' ) {
			set_current_screen( $pagenow );
		}

		switch ( $_GET['widget'] ) {
			case 'dashboard_primary':
				wp_dashboard_primary();
				break;
		}
		wp_die();
	}
}
