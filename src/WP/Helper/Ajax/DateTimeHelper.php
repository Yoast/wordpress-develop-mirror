<?php

namespace WP\Helper\Ajax;

class DateTimeHelper {
	/**
	 * Ajax handler for date formatting.
	 */
	public static function formatDate() {
		wp_die( date_i18n( sanitize_option( 'date_format', wp_unslash( $_POST['date'] ) ) ) );
	}

	/**
	 * Ajax handler for time formatting.
	 */
	public static function formatTime() {
		wp_die( date_i18n( sanitize_option( 'time_format', wp_unslash( $_POST['date'] ) ) ) );
	}
}
