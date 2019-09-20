<?php

namespace WP\Helper\Ajax;

class ThemeHelper {
	/**
	 * Ajax handler for getting themes from themes_api().
	 *
	 * @global array $themes_allowedtags
	 * @global array $theme_field_defaults
	 */
	public static function query() {
		global $themes_allowedtags, $theme_field_defaults;

		if ( ! current_user_can( 'install_themes' ) ) {
			wp_send_json_error();
		}

		$args = wp_parse_args(
			wp_unslash( $_REQUEST['request'] ),
			array(
				'per_page' => 20,
				'fields'   => array_merge(
					(array) $theme_field_defaults,
					array(
						'reviews_url' => true, // Explicitly request the reviews URL to be linked from the Add Themes screen.
					)
				),
			)
		);

		if ( isset( $args['browse'] ) && 'favorites' === $args['browse'] && ! isset( $args['user'] ) ) {
			$user = get_user_option( 'wporg_favorites' );
			if ( $user ) {
				$args['user'] = $user;
			}
		}

		$old_filter = isset( $args['browse'] ) ? $args['browse'] : 'search';

		/** This filter is documented in wp-admin/includes/class-wp-theme-install-list-table.php */
		$args = apply_filters( 'install_themes_table_api_args_' . $old_filter, $args );

		$api = themes_api( 'query_themes', $args );

		if ( is_wp_error( $api ) ) {
			wp_send_json_error();
		}

		$update_php = network_admin_url( 'update.php?action=install-theme' );

		foreach ( $api->themes as &$theme ) {
			$theme->install_url = add_query_arg(
				array(
					'theme'    => $theme->slug,
					'_wpnonce' => wp_create_nonce( 'install-theme_' . $theme->slug ),
				),
				$update_php
			);

			if ( current_user_can( 'switch_themes' ) ) {
				if ( is_multisite() ) {
					$theme->activate_url = add_query_arg(
						array(
							'action'   => 'enable',
							'_wpnonce' => wp_create_nonce( 'enable-theme_' . $theme->slug ),
							'theme'    => $theme->slug,
						),
						network_admin_url( 'themes.php' )
					);
				} else {
					$theme->activate_url = add_query_arg(
						array(
							'action'     => 'activate',
							'_wpnonce'   => wp_create_nonce( 'switch-theme_' . $theme->slug ),
							'stylesheet' => $theme->slug,
						),
						admin_url( 'themes.php' )
					);
				}
			}

			if ( ! is_multisite() && current_user_can( 'edit_theme_options' ) && current_user_can( 'customize' ) ) {
				$theme->customize_url = add_query_arg(
					array(
						'return' => urlencode( network_admin_url( 'theme-install.php', 'relative' ) ),
					),
					wp_customize_url( $theme->slug )
				);
			}

			$theme->name        = wp_kses( $theme->name, $themes_allowedtags );
			$theme->author      = wp_kses( $theme->author['display_name'], $themes_allowedtags );
			$theme->version     = wp_kses( $theme->version, $themes_allowedtags );
			$theme->description = wp_kses( $theme->description, $themes_allowedtags );

			$theme->stars = wp_star_rating(
				array(
					'rating' => $theme->rating,
					'type'   => 'percent',
					'number' => $theme->num_ratings,
					'echo'   => false,
				)
			);

			$theme->num_ratings = number_format_i18n( $theme->num_ratings );
			$theme->preview_url = set_url_scheme( $theme->preview_url );
		}

		wp_send_json_success( $api );
	}

	/**
	 * Ajax handler for installing a theme.
	 *
	 * @see Theme_Upgrader
	 *
	 * @global \WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
	 */
	public static function install() {
		check_ajax_referer( 'updates' );

		if ( empty( $_POST['slug'] ) ) {
			wp_send_json_error(
				array(
					'slug'         => '',
					'errorCode'    => 'no_theme_specified',
					'errorMessage' => __( 'No theme specified.' ),
				)
			);
		}

		$slug = sanitize_key( wp_unslash( $_POST['slug'] ) );

		$status = array(
			'install' => 'theme',
			'slug'    => $slug,
		);

		if ( ! current_user_can( 'install_themes' ) ) {
			$status['errorMessage'] = __( 'Sorry, you are not allowed to install themes on this site.' );
			wp_send_json_error( $status );
		}

		include_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );
		include_once( ABSPATH . 'wp-admin/includes/theme.php' );

		$api = themes_api(
			'theme_information',
			array(
				'slug'   => $slug,
				'fields' => array( 'sections' => false ),
			)
		);

		if ( is_wp_error( $api ) ) {
			$status['errorMessage'] = $api->get_error_message();
			wp_send_json_error( $status );
		}

		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Theme_Upgrader( $skin );
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
			if ( $wp_filesystem instanceof \WP_Filesystem_Base && is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->has_errors() ) {
				$status['errorMessage'] = esc_html( $wp_filesystem->errors->get_error_message() );
			}

			wp_send_json_error( $status );
		}

		$status['themeName'] = wp_get_theme( $slug )->get( 'Name' );

		if ( current_user_can( 'switch_themes' ) ) {
			if ( is_multisite() ) {
				$status['activateUrl'] = add_query_arg(
					array(
						'action'   => 'enable',
						'_wpnonce' => wp_create_nonce( 'enable-theme_' . $slug ),
						'theme'    => $slug,
					),
					network_admin_url( 'themes.php' )
				);
			} else {
				$status['activateUrl'] = add_query_arg(
					array(
						'action'     => 'activate',
						'_wpnonce'   => wp_create_nonce( 'switch-theme_' . $slug ),
						'stylesheet' => $slug,
					),
					admin_url( 'themes.php' )
				);
			}
		}

		if ( ! is_multisite() && current_user_can( 'edit_theme_options' ) && current_user_can( 'customize' ) ) {
			$status['customizeUrl'] = add_query_arg(
				array(
					'return' => urlencode( network_admin_url( 'theme-install.php', 'relative' ) ),
				),
				wp_customize_url( $slug )
			);
		}

		/*
		 * See WP_Theme_Install_List_Table::_get_theme_status() if we wanted to check
		 * on post-installation status.
		 */
		wp_send_json_success( $status );
	}

	/**
	 * Ajax handler for updating a theme.
	 *
	 * @see Theme_Upgrader
	 *
	 * @global \WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
	 */
	public static function update() {
		check_ajax_referer( 'updates' );

		if ( empty( $_POST['slug'] ) ) {
			wp_send_json_error(
				array(
					'slug'         => '',
					'errorCode'    => 'no_theme_specified',
					'errorMessage' => __( 'No theme specified.' ),
				)
			);
		}

		$stylesheet = preg_replace( '/[^A-z0-9_\-]/', '', wp_unslash( $_POST['slug'] ) );
		$status     = array(
			'update'     => 'theme',
			'slug'       => $stylesheet,
			'oldVersion' => '',
			'newVersion' => '',
		);

		if ( ! current_user_can( 'update_themes' ) ) {
			$status['errorMessage'] = __( 'Sorry, you are not allowed to update themes for this site.' );
			wp_send_json_error( $status );
		}

		$theme = wp_get_theme( $stylesheet );
		if ( $theme->exists() ) {
			$status['oldVersion'] = $theme->get( 'Version' );
		}

		include_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );

		$current = get_site_transient( 'update_themes' );
		if ( empty( $current ) ) {
			wp_update_themes();
		}

		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Theme_Upgrader( $skin );
		$result   = $upgrader->bulk_upgrade( array( $stylesheet ) );

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
		} elseif ( is_array( $result ) && ! empty( $result[ $stylesheet ] ) ) {

			// Theme is already at the latest version.
			if ( true === $result[ $stylesheet ] ) {
				$status['errorMessage'] = $upgrader->strings['up_to_date'];
				wp_send_json_error( $status );
			}

			$theme = wp_get_theme( $stylesheet );
			if ( $theme->exists() ) {
				$status['newVersion'] = $theme->get( 'Version' );
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
		$status['errorMessage'] = __( 'Update failed.' );
		wp_send_json_error( $status );
	}

	/**
	 * Ajax handler for deleting a theme.
	 *
	 * @see delete_theme()
	 *
	 * @global \WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
	 */
	public static function delete() {
		check_ajax_referer( 'updates' );

		if ( empty( $_POST['slug'] ) ) {
			wp_send_json_error(
				array(
					'slug'         => '',
					'errorCode'    => 'no_theme_specified',
					'errorMessage' => __( 'No theme specified.' ),
				)
			);
		}

		$stylesheet = preg_replace( '/[^A-z0-9_\-]/', '', wp_unslash( $_POST['slug'] ) );
		$status     = array(
			'delete' => 'theme',
			'slug'   => $stylesheet,
		);

		if ( ! current_user_can( 'delete_themes' ) ) {
			$status['errorMessage'] = __( 'Sorry, you are not allowed to delete themes on this site.' );
			wp_send_json_error( $status );
		}

		if ( ! wp_get_theme( $stylesheet )->exists() ) {
			$status['errorMessage'] = __( 'The requested theme does not exist.' );
			wp_send_json_error( $status );
		}

		// Check filesystem credentials. `delete_theme()` will bail otherwise.
		$url = wp_nonce_url( 'themes.php?action=delete&stylesheet=' . urlencode( $stylesheet ), 'delete-theme_' . $stylesheet );

		ob_start();
		$credentials = request_filesystem_credentials( $url );
		ob_end_clean();

		if ( false === $credentials || ! WP_Filesystem( $credentials ) ) {
			global $wp_filesystem;

			$status['errorCode']    = 'unable_to_connect_to_filesystem';
			$status['errorMessage'] = __( 'Unable to connect to the filesystem. Please confirm your credentials.' );

			// Pass through the error from WP_Filesystem if one was raised.
			if ( $wp_filesystem instanceof WP_Filesystem_Base && is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->has_errors() ) {
				$status['errorMessage'] = esc_html( $wp_filesystem->errors->get_error_message() );
			}

			wp_send_json_error( $status );
		}

		include_once( ABSPATH . 'wp-admin/includes/theme.php' );

		$result = delete_theme( $stylesheet );

		if ( is_wp_error( $result ) ) {
			$status['errorMessage'] = $result->get_error_message();
			wp_send_json_error( $status );
		} elseif ( false === $result ) {
			$status['errorMessage'] = __( 'Theme could not be deleted.' );
			wp_send_json_error( $status );
		}

		wp_send_json_success( $status );
	}
}
