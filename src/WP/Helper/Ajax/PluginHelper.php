<?php

namespace WP\Helper\Ajax;

class PluginHelper {
	/**
	 * Ajax handler for installing a plugin.
	 *
	 * @see Plugin_Upgrader
	 *
	 * @global \WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
	 */
	public static function install() {
		check_ajax_referer( 'updates' );

		if ( empty( $_POST['slug'] ) ) {
			wp_send_json_error(
				array(
					'slug'         => '',
					'errorCode'    => 'no_plugin_specified',
					'errorMessage' => __( 'No plugin specified.' ),
				)
			);
		}

		$status = array(
			'install' => 'plugin',
			'slug'    => sanitize_key( wp_unslash( $_POST['slug'] ) ),
		);

		if ( ! current_user_can( 'install_plugins' ) ) {
			$status['errorMessage'] = __( 'Sorry, you are not allowed to install plugins on this site.' );
			wp_send_json_error( $status );
		}

		include_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );
		include_once( ABSPATH . 'wp-admin/includes/plugin-install.php' );

		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => sanitize_key( wp_unslash( $_POST['slug'] ) ),
				'fields' => array(
					'sections' => false,
				),
			)
		);

		if ( is_wp_error( $api ) ) {
			$status['errorMessage'] = $api->get_error_message();
			wp_send_json_error( $status );
		}

		$status['pluginName'] = $api->name;

		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $api->download_link );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$status['debug'] = $skin->get_upgrade_messages();
		}

		if ( is_wp_error( $result ) ) {
			$status['errorCode']    = $result->get_error_code();
			$status['errorMessage'] = $result->get_error_message();
			wp_send_json_error( $status );
		} elseif ( is_wp_error( $skin->result ) ) {
			$status['errorCode']    = $skin->result->get_error_code();
			$status['errorMessage'] = $skin->result->get_error_message();
			wp_send_json_error( $status );
		} elseif ( $skin->get_errors()->has_errors() ) {
			$status['errorMessage'] = $skin->get_error_messages();
			wp_send_json_error( $status );
		} elseif ( is_null( $result ) ) {
			global $wp_filesystem;

			$status['errorCode']    = 'unable_to_connect_to_filesystem';
			$status['errorMessage'] = __( 'Unable to connect to the filesystem. Please confirm your credentials.' );

			// Pass through the error from WP_Filesystem if one was raised.
			if ( $wp_filesystem instanceof WP_Filesystem_Base && is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->has_errors() ) {
				$status['errorMessage'] = esc_html( $wp_filesystem->errors->get_error_message() );
			}

			wp_send_json_error( $status );
		}

		$install_status = install_plugin_install_status( $api );
		$pagenow        = isset( $_POST['pagenow'] ) ? sanitize_key( $_POST['pagenow'] ) : '';

		// If installation request is coming from import page, do not return network activation link.
		$plugins_url = ( 'import' === $pagenow ) ? admin_url( 'plugins.php' ) : network_admin_url( 'plugins.php' );

		if ( current_user_can( 'activate_plugin', $install_status['file'] ) && is_plugin_inactive( $install_status['file'] ) ) {
			$status['activateUrl'] = add_query_arg(
				array(
					'_wpnonce' => wp_create_nonce( 'activate-plugin_' . $install_status['file'] ),
					'action'   => 'activate',
					'plugin'   => $install_status['file'],
				),
				$plugins_url
			);
		}

		if ( is_multisite() && current_user_can( 'manage_network_plugins' ) && 'import' !== $pagenow ) {
			$status['activateUrl'] = add_query_arg( array( 'networkwide' => 1 ), $status['activateUrl'] );
		}

		wp_send_json_success( $status );
	}

	/**
	 * Ajax handler for updating a plugin.
	 *
	 * @see Plugin_Upgrader
	 *
	 * @global \WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
	 */
	public static function update() {
		check_ajax_referer( 'updates' );

		if ( empty( $_POST['plugin'] ) || empty( $_POST['slug'] ) ) {
			wp_send_json_error(
				array(
					'slug'         => '',
					'errorCode'    => 'no_plugin_specified',
					'errorMessage' => __( 'No plugin specified.' ),
				)
			);
		}

		$plugin = plugin_basename( sanitize_text_field( wp_unslash( $_POST['plugin'] ) ) );

		$status = array(
			'update'     => 'plugin',
			'slug'       => sanitize_key( wp_unslash( $_POST['slug'] ) ),
			'oldVersion' => '',
			'newVersion' => '',
		);

		if ( ! current_user_can( 'update_plugins' ) || 0 !== validate_file( $plugin ) ) {
			$status['errorMessage'] = __( 'Sorry, you are not allowed to update plugins for this site.' );
			wp_send_json_error( $status );
		}

		$plugin_data          = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );
		$status['plugin']     = $plugin;
		$status['pluginName'] = $plugin_data['Name'];

		if ( $plugin_data['Version'] ) {
			/* translators: %s: Plugin version. */
			$status['oldVersion'] = sprintf( __( 'Version %s' ), $plugin_data['Version'] );
		}

		include_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );

		wp_update_plugins();

		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$result   = $upgrader->bulk_upgrade( array( $plugin ) );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$status['debug'] = $skin->get_upgrade_messages();
		}

		if ( is_wp_error( $skin->result ) ) {
			$status['errorCode']    = $skin->result->get_error_code();
			$status['errorMessage'] = $skin->result->get_error_message();
			wp_send_json_error( $status );
		} elseif ( $skin->get_errors()->has_errors() ) {
			$status['errorMessage'] = $skin->get_error_messages();
			wp_send_json_error( $status );
		} elseif ( is_array( $result ) && ! empty( $result[ $plugin ] ) ) {
			$plugin_update_data = current( $result );

			/*
			 * If the `update_plugins` site transient is empty (e.g. when you update
			 * two plugins in quick succession before the transient repopulates),
			 * this may be the return.
			 *
			 * Preferably something can be done to ensure `update_plugins` isn't empty.
			 * For now, surface some sort of error here.
			 */
			if ( true === $plugin_update_data ) {
				$status['errorMessage'] = __( 'Plugin update failed.' );
				wp_send_json_error( $status );
			}

			$plugin_data = get_plugins( '/' . $result[ $plugin ]['destination_name'] );
			$plugin_data = reset( $plugin_data );

			if ( $plugin_data['Version'] ) {
				/* translators: %s: Plugin version. */
				$status['newVersion'] = sprintf( __( 'Version %s' ), $plugin_data['Version'] );
			}
			wp_send_json_success( $status );
		} elseif ( false === $result ) {
			global $wp_filesystem;

			$status['errorCode']    = 'unable_to_connect_to_filesystem';
			$status['errorMessage'] = __( 'Unable to connect to the filesystem. Please confirm your credentials.' );

			// Pass through the error from WP_Filesystem if one was raised.
			if ( $wp_filesystem instanceof \WP_Filesystem_Base && is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->has_errors() ) {
				$status['errorMessage'] = esc_html( $wp_filesystem->errors->get_error_message() );
			}

			wp_send_json_error( $status );
		}

		// An unhandled error occurred.
		$status['errorMessage'] = __( 'Plugin update failed.' );
		wp_send_json_error( $status );
	}

	/**
	 * Ajax handler for deleting a plugin.
	 *
	 * @see delete_plugins()
	 *
	 * @global \WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
	 */
	public static function delete() {
		check_ajax_referer( 'updates' );

		if ( empty( $_POST['slug'] ) || empty( $_POST['plugin'] ) ) {
			wp_send_json_error(
				array(
					'slug'         => '',
					'errorCode'    => 'no_plugin_specified',
					'errorMessage' => __( 'No plugin specified.' ),
				)
			);
		}

		$plugin = plugin_basename( sanitize_text_field( wp_unslash( $_POST['plugin'] ) ) );

		$status = array(
			'delete' => 'plugin',
			'slug'   => sanitize_key( wp_unslash( $_POST['slug'] ) ),
		);

		if ( ! current_user_can( 'delete_plugins' ) || 0 !== validate_file( $plugin ) ) {
			$status['errorMessage'] = __( 'Sorry, you are not allowed to delete plugins for this site.' );
			wp_send_json_error( $status );
		}

		$plugin_data          = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );
		$status['plugin']     = $plugin;
		$status['pluginName'] = $plugin_data['Name'];

		if ( is_plugin_active( $plugin ) ) {
			$status['errorMessage'] = __( 'You cannot delete a plugin while it is active on the main site.' );
			wp_send_json_error( $status );
		}

		// Check filesystem credentials. `delete_plugins()` will bail otherwise.
		$url = wp_nonce_url( 'plugins.php?action=delete-selected&verify-delete=1&checked[]=' . $plugin, 'bulk-plugins' );

		ob_start();
		$credentials = request_filesystem_credentials( $url );
		ob_end_clean();

		if ( false === $credentials || ! WP_Filesystem( $credentials ) ) {
			global $wp_filesystem;

			$status['errorCode']    = 'unable_to_connect_to_filesystem';
			$status['errorMessage'] = __( 'Unable to connect to the filesystem. Please confirm your credentials.' );

			// Pass through the error from WP_Filesystem if one was raised.
			if ( $wp_filesystem instanceof \WP_Filesystem_Base && is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->has_errors() ) {
				$status['errorMessage'] = esc_html( $wp_filesystem->errors->get_error_message() );
			}

			wp_send_json_error( $status );
		}

		$result = delete_plugins( array( $plugin ) );

		if ( is_wp_error( $result ) ) {
			$status['errorMessage'] = $result->get_error_message();
			wp_send_json_error( $status );
		} elseif ( false === $result ) {
			$status['errorMessage'] = __( 'Plugin could not be deleted.' );
			wp_send_json_error( $status );
		}

		wp_send_json_success( $status );
	}

	/**
	 * Ajax handler for searching plugins.
	 *
	 * @global string $s Search term.
	 */
	public static function search() {
		check_ajax_referer( 'updates' );

		$pagenow = isset( $_POST['pagenow'] ) ? sanitize_key( $_POST['pagenow'] ) : '';
		if ( 'plugins-network' === $pagenow || 'plugins' === $pagenow ) {
			set_current_screen( $pagenow );
		}

		/** @var \WP_Plugins_List_Table $wp_list_table */
		$wp_list_table = _get_list_table(
			'WP_Plugins_List_Table',
			array(
				'screen' => get_current_screen(),
			)
		);

		$status = array();

		if ( ! $wp_list_table->ajax_user_can() ) {
			$status['errorMessage'] = __( 'Sorry, you are not allowed to manage plugins for this site.' );
			wp_send_json_error( $status );
		}

		// Set the correct requester, so pagination works.
		$_SERVER['REQUEST_URI'] = add_query_arg(
			array_diff_key(
				$_POST,
				array(
					'_ajax_nonce' => null,
					'action'      => null,
				)
			),
			network_admin_url( 'plugins.php', 'relative' )
		);

		$GLOBALS['s'] = wp_unslash( $_POST['s'] );

		$wp_list_table->prepare_items();

		ob_start();
		$wp_list_table->display();
		$status['count'] = count( $wp_list_table->items );
		$status['items'] = ob_get_clean();

		wp_send_json_success( $status );
	}

	/**
	 * Ajax handler for searching plugins to install.
	 *
	 * @since 4.6.0
	 */
	public static function install_search() {
		check_ajax_referer( 'updates' );

		$pagenow = isset( $_POST['pagenow'] ) ? sanitize_key( $_POST['pagenow'] ) : '';
		if ( 'plugin-install-network' === $pagenow || 'plugin-install' === $pagenow ) {
			set_current_screen( $pagenow );
		}

		/** @var \WP_Plugin_Install_List_Table $wp_list_table */
		$wp_list_table = _get_list_table(
			'WP_Plugin_Install_List_Table',
			array(
				'screen' => get_current_screen(),
			)
		);

		$status = array();

		if ( ! $wp_list_table->ajax_user_can() ) {
			$status['errorMessage'] = __( 'Sorry, you are not allowed to manage plugins for this site.' );
			wp_send_json_error( $status );
		}

		// Set the correct requester, so pagination works.
		$_SERVER['REQUEST_URI'] = add_query_arg(
			array_diff_key(
				$_POST,
				array(
					'_ajax_nonce' => null,
					'action'      => null,
				)
			),
			network_admin_url( 'plugin-install.php', 'relative' )
		);

		$wp_list_table->prepare_items();

		ob_start();
		$wp_list_table->display();
		$status['count'] = (int) $wp_list_table->get_pagination_arg( 'total_items' );
		$status['items'] = ob_get_clean();

		wp_send_json_success( $status );
	}
}
