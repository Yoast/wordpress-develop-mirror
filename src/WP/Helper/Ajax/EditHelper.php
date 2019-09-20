<?php

namespace WP\Helper\Ajax;

class EditHelper {
	public static function editThemePluginFile() {
		$r = wp_edit_theme_plugin_file( wp_unslash( $_POST ) ); // Validation of args is done in wp_edit_theme_plugin_file().

		if ( is_wp_error( $r ) ) {
			wp_send_json_error(
				array_merge(
					array(
						'code'    => $r->get_error_code(),
						'message' => $r->get_error_message(),
					),
					(array) $r->get_error_data()
				)
			);
		} else {
			wp_send_json_success(
				array(
					'message' => __( 'File edited successfully.' ),
				)
			);
		}
	}
}
