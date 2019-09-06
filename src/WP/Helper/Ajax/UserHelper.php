<?php

namespace WP\Helper\Ajax;

class UserHelper {
	/**
	 * Ajax handler for user autocomplete.
	 */
	public static function autocompleteUser() {
		if ( ! is_multisite() || ! current_user_can( 'promote_users' ) || wp_is_large_network( 'users' ) ) {
			wp_die( -1 );
		}

		/** This filter is documented in wp-admin/user-new.php */
		if ( ! current_user_can( 'manage_network_users' ) && ! apply_filters( 'autocomplete_users_for_site_admins', false ) ) {
			wp_die( -1 );
		}

		$return = array();

		// Check the type of request
		// Current allowed values are `add` and `search`
		if ( isset( $_REQUEST['autocomplete_type'] ) && 'search' === $_REQUEST['autocomplete_type'] ) {
			$type = $_REQUEST['autocomplete_type'];
		} else {
			$type = 'add';
		}

		// Check the desired field for value
		// Current allowed values are `user_email` and `user_login`
		if ( isset( $_REQUEST['autocomplete_field'] ) && 'user_email' === $_REQUEST['autocomplete_field'] ) {
			$field = $_REQUEST['autocomplete_field'];
		} else {
			$field = 'user_login';
		}

		// Exclude current users of this blog
		if ( isset( $_REQUEST['site_id'] ) ) {
			$id = absint( $_REQUEST['site_id'] );
		} else {
			$id = get_current_blog_id();
		}

		$include_blog_users = ( $type == 'search' ? get_users(
			array(
				'blog_id' => $id,
				'fields'  => 'ID',
			)
		) : array() );

		$exclude_blog_users = ( $type == 'add' ? get_users(
			array(
				'blog_id' => $id,
				'fields'  => 'ID',
			)
		) : array() );

		$users = get_users(
			array(
				'blog_id'        => false,
				'search'         => '*' . $_REQUEST['term'] . '*',
				'include'        => $include_blog_users,
				'exclude'        => $exclude_blog_users,
				'search_columns' => array( 'user_login', 'user_nicename', 'user_email' ),
			)
		);

		foreach ( $users as $user ) {
			$return[] = array(
				/* translators: 1: User login, 2: User email address. */
				'label' => sprintf( _x( '%1$s (%2$s)', 'user autocomplete result' ), $user->user_login, $user->user_email ),
				'value' => $user->$field,
			);
		}

		wp_die( wp_json_encode( $return ) );
	}

	/**
	 * Ajax handler for Customizer preview logged-in status.
	 */
	public static function loggedIn() {
		wp_die( 1 );
	}

	/**
	 * Ajax handler for adding a user.
	 *
	 * @param string $action Action to perform.
	 */
	public static function addUser( $action ) {
		if ( empty( $action ) ) {
			$action = 'add-user';
		}

		check_ajax_referer( $action );

		if ( ! current_user_can( 'create_users' ) ) {
			wp_die( -1 );
		}

		$user_id = edit_user();

		if ( ! $user_id ) {
			wp_die( 0 );
		} elseif ( is_wp_error( $user_id ) ) {
			$x = new \WP_Ajax_Response(
				array(
					'what' => 'user',
					'id'   => $user_id,
				)
			);
			$x->send();
		}

		$user_object   = get_userdata( $user_id );
		$wp_list_table = _get_list_table( 'WP_Users_List_Table' );

		$role = current( $user_object->roles );

		$x = new \WP_Ajax_Response(
			array(
				'what'         => 'user',
				'id'           => $user_id,
				'data'         => $wp_list_table->single_row( $user_object, '', $role ),
				'supplemental' => array(
					'show-link' => sprintf(
					/* translators: %s: The new user. */
						__( 'User %s added' ),
						'<a href="#user-' . $user_id . '">' . $user_object->user_login . '</a>'
					),
					'role'      => $role,
				),
			)
		);
		$x->send();
	}

	/**
	 * Ajax handler for auto-saving the selected color scheme for
	 * a user's own profile.
	 *
	 * @global array $_wp_admin_css_colors
	 */
	public static function saveColorScheme() {
		global $_wp_admin_css_colors;

		check_ajax_referer( 'save-color-scheme', 'nonce' );

		$color_scheme = sanitize_key( $_POST['color_scheme'] );

		if ( ! isset( $_wp_admin_css_colors[ $color_scheme ] ) ) {
			wp_send_json_error();
		}

		$previous_color_scheme = get_user_meta( get_current_user_id(), 'admin_color', true );
		update_user_meta( get_current_user_id(), 'admin_color', $color_scheme );

		wp_send_json_success(
			array(
				'previousScheme' => 'admin-color-' . $previous_color_scheme,
				'currentScheme'  => 'admin-color-' . $color_scheme,
			)
		);
	}

	/**
	 * Ajax handler for generating a password.
	 */
	public static function generatePassword() {
		wp_send_json_success( wp_generate_password( 24 ) );
	}

	/**
	 * Ajax handler for saving the user's WordPress.org username.
	 */
	public static function saveWPOrgUsername() {
		if ( ! current_user_can( 'install_themes' ) && ! current_user_can( 'install_plugins' ) ) {
			wp_send_json_error();
		}

		check_ajax_referer( 'save_wporg_username_' . get_current_user_id() );

		$username = isset( $_REQUEST['username'] ) ? wp_unslash( $_REQUEST['username'] ) : false;

		if ( ! $username ) {
			wp_send_json_error();
		}

		wp_send_json_success( update_user_meta( get_current_user_id(), 'wporg_favorites', $username ) );
	}
}
