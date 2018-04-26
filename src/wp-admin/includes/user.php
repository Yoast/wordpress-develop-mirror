<?php
/**
 * WordPress user administration API.
 *
 * @package WordPress
 * @subpackage Administration
 */

/**
 * Creates a new user from the "Users" form using $_POST information.
 *
 * @since 2.0.0
 *
 * @return int|WP_Error WP_Error or User ID.
 */
function add_user() {
	return edit_user();
}

/**
 * Edit user settings based on contents of $_POST
 *
 * Used on user-edit.php and profile.php to manage and process user options, passwords etc.
 *
 * @since 2.0.0
 *
 * @param int $user_id Optional. User ID.
 * @return int|WP_Error user id of the updated user
 */
function edit_user( $user_id = 0 ) {
	$wp_roles = wp_roles();
	$user     = new stdClass;
	$user_id  = (int) $user_id;
	if ( $user_id ) {
		$update           = true;
		$user->ID         = $user_id;
		$userdata         = get_userdata( $user_id );
		$user->user_login = wp_slash( $userdata->user_login );
	} else {
		$update = false;
	}

	if ( ! $update && isset( $_POST['user_login'] ) ) {
		$user->user_login = sanitize_user( $_POST['user_login'], true );
	}

	$pass1 = $pass2 = '';
	if ( isset( $_POST['pass1'] ) ) {
		$pass1 = $_POST['pass1'];
	}
	if ( isset( $_POST['pass2'] ) ) {
		$pass2 = $_POST['pass2'];
	}

	if ( isset( $_POST['role'] ) && current_user_can( 'promote_users' ) && ( ! $user_id || current_user_can( 'promote_user', $user_id ) ) ) {
		$new_role = sanitize_text_field( $_POST['role'] );

		// If the new role isn't editable by the logged-in user die with error.
		$editable_roles = get_editable_roles();
		if ( ! empty( $new_role ) && empty( $editable_roles[ $new_role ] ) ) {
			wp_die( __( 'Sorry, you are not allowed to give users that role.' ), 403 );
		}

		$potential_role = isset( $wp_roles->role_objects[ $new_role ] ) ? $wp_roles->role_objects[ $new_role ] : false;

		/*
		 * Don't let anyone with 'promote_users' edit their own role to something without it.
		 * Multisite super admins can freely edit their roles, they possess all caps.
		 */
		if (
			( is_multisite() && current_user_can( 'manage_network_users' ) ) ||
			$user_id !== get_current_user_id() ||
			( $potential_role && $potential_role->has_cap( 'promote_users' ) )
		) {
			$user->role = $new_role;
		}
	}

	if ( isset( $_POST['email'] ) ) {
		$user->user_email = sanitize_text_field( wp_unslash( $_POST['email'] ) );
	}
	if ( isset( $_POST['url'] ) ) {
		if ( empty( $_POST['url'] ) || $_POST['url'] == 'http://' ) {
			$user->user_url = '';
		} else {
			$user->user_url = esc_url_raw( $_POST['url'] );
			$protocols      = implode( '|', array_map( 'preg_quote', wp_allowed_protocols() ) );
			$user->user_url = preg_match( '/^(' . $protocols . '):/is', $user->user_url ) ? $user->user_url : 'http://' . $user->user_url;
		}
	}
	if ( isset( $_POST['first_name'] ) ) {
		$user->first_name = sanitize_text_field( $_POST['first_name'] );
	}
	if ( isset( $_POST['last_name'] ) ) {
		$user->last_name = sanitize_text_field( $_POST['last_name'] );
	}
	if ( isset( $_POST['nickname'] ) ) {
		$user->nickname = sanitize_text_field( $_POST['nickname'] );
	}
	if ( isset( $_POST['display_name'] ) ) {
		$user->display_name = sanitize_text_field( $_POST['display_name'] );
	}

	if ( isset( $_POST['description'] ) ) {
		$user->description = trim( $_POST['description'] );
	}

	foreach ( wp_get_user_contact_methods( $user ) as $method => $name ) {
		if ( isset( $_POST[ $method ] ) ) {
			$user->$method = sanitize_text_field( $_POST[ $method ] );
		}
	}

	if ( $update ) {
		$user->rich_editing         = isset( $_POST['rich_editing'] ) && 'false' === $_POST['rich_editing'] ? 'false' : 'true';
		$user->syntax_highlighting  = isset( $_POST['syntax_highlighting'] ) && 'false' === $_POST['syntax_highlighting'] ? 'false' : 'true';
		$user->admin_color          = isset( $_POST['admin_color'] ) ? sanitize_text_field( $_POST['admin_color'] ) : 'fresh';
		$user->show_admin_bar_front = isset( $_POST['admin_bar_front'] ) ? 'true' : 'false';
		$user->locale               = '';

		if ( isset( $_POST['locale'] ) ) {
			$locale = sanitize_text_field( $_POST['locale'] );
			if ( 'site-default' === $locale ) {
				$locale = '';
			} elseif ( '' === $locale ) {
				$locale = 'en_US';
			} elseif ( ! in_array( $locale, get_available_languages(), true ) ) {
				$locale = '';
			}

			$user->locale = $locale;
		}
	}

	$user->comment_shortcuts = isset( $_POST['comment_shortcuts'] ) && 'true' == $_POST['comment_shortcuts'] ? 'true' : '';

	$user->use_ssl = 0;
	if ( ! empty( $_POST['use_ssl'] ) ) {
		$user->use_ssl = 1;
	}

	$errors = new WP_Error();

	/* checking that username has been typed */
	if ( $user->user_login == '' ) {
		$errors->add( 'user_login', __( '<strong>ERROR</strong>: Please enter a username.' ) );
	}

	/* checking that nickname has been typed */
	if ( $update && empty( $user->nickname ) ) {
		$errors->add( 'nickname', __( '<strong>ERROR</strong>: Please enter a nickname.' ) );
	}

	/**
	 * Fires before the password and confirm password fields are checked for congruity.
	 *
	 * @since 1.5.1
	 *
	 * @param string $user_login The username.
	 * @param string $pass1     The password (passed by reference).
	 * @param string $pass2     The confirmed password (passed by reference).
	 */
	do_action_ref_array( 'check_passwords', array( $user->user_login, &$pass1, &$pass2 ) );

	// Check for blank password when adding a user.
	if ( ! $update && empty( $pass1 ) ) {
		$errors->add( 'pass', __( '<strong>ERROR</strong>: Please enter a password.' ), array( 'form-field' => 'pass1' ) );
	}

	// Check for "\" in password.
	if ( false !== strpos( wp_unslash( $pass1 ), '\\' ) ) {
		$errors->add( 'pass', __( '<strong>ERROR</strong>: Passwords may not contain the character "\\".' ), array( 'form-field' => 'pass1' ) );
	}

	// Checking the password has been typed twice the same.
	if ( ( $update || ! empty( $pass1 ) ) && $pass1 != $pass2 ) {
		$errors->add( 'pass', __( '<strong>ERROR</strong>: Please enter the same password in both password fields.' ), array( 'form-field' => 'pass1' ) );
	}

	if ( ! empty( $pass1 ) ) {
		$user->user_pass = $pass1;
	}

	if ( ! $update && isset( $_POST['user_login'] ) && ! validate_username( $_POST['user_login'] ) ) {
		$errors->add( 'user_login', __( '<strong>ERROR</strong>: This username is invalid because it uses illegal characters. Please enter a valid username.' ) );
	}

	if ( ! $update && username_exists( $user->user_login ) ) {
		$errors->add( 'user_login', __( '<strong>ERROR</strong>: This username is already registered. Please choose another one.' ) );
	}

	/** This filter is documented in wp-includes/user.php */
	$illegal_logins = (array) apply_filters( 'illegal_user_logins', array() );

	if ( in_array( strtolower( $user->user_login ), array_map( 'strtolower', $illegal_logins ) ) ) {
		$errors->add( 'invalid_username', __( '<strong>ERROR</strong>: Sorry, that username is not allowed.' ) );
	}

	/* checking email address */
	if ( empty( $user->user_email ) ) {
		$errors->add( 'empty_email', __( '<strong>ERROR</strong>: Please enter an email address.' ), array( 'form-field' => 'email' ) );
	} elseif ( ! is_email( $user->user_email ) ) {
		$errors->add( 'invalid_email', __( '<strong>ERROR</strong>: The email address isn&#8217;t correct.' ), array( 'form-field' => 'email' ) );
	} elseif ( ( $owner_id = email_exists( $user->user_email ) ) && ( ! $update || ( $owner_id != $user->ID ) ) ) {
		$errors->add( 'email_exists', __( '<strong>ERROR</strong>: This email is already registered, please choose another one.' ), array( 'form-field' => 'email' ) );
	}

	/**
	 * Fires before user profile update errors are returned.
	 *
	 * @since 2.8.0
	 *
	 * @param WP_Error $errors WP_Error object (passed by reference).
	 * @param bool     $update  Whether this is a user update.
	 * @param stdClass $user   User object (passed by reference).
	 */
	do_action_ref_array( 'user_profile_update_errors', array( &$errors, $update, &$user ) );

	if ( $errors->has_errors() ) {
		return $errors;
	}

	if ( $update ) {
		$user_id = wp_update_user( $user );
	} else {
		$user_id = wp_insert_user( $user );
		$notify  = isset( $_POST['send_user_notification'] ) ? 'both' : 'admin';

		/**
		 * Fires after a new user has been created.
		 *
		 * @since 4.4.0
		 *
		 * @param int    $user_id ID of the newly created user.
		 * @param string $notify  Type of notification that should happen. See wp_send_new_user_notifications()
		 *                        for more information on possible values.
		 */
		do_action( 'edit_user_created_user', $user_id, $notify );
	}
	return $user_id;
}

/**
 * Fetch a filtered list of user roles that the current user is
 * allowed to edit.
 *
 * Simple function who's main purpose is to allow filtering of the
 * list of roles in the $wp_roles object so that plugins can remove
 * inappropriate ones depending on the situation or user making edits.
 * Specifically because without filtering anyone with the edit_users
 * capability can edit others to be administrators, even if they are
 * only editors or authors. This filter allows admins to delegate
 * user management.
 *
 * @since 2.8.0
 *
 * @return array[] Array of arrays containing role information.
 */
function get_editable_roles() {
	$all_roles = wp_roles()->roles;

	/**
	 * Filters the list of editable roles.
	 *
	 * @since 2.8.0
	 *
	 * @param array[] $all_roles Array of arrays containing role information.
	 */
	$editable_roles = apply_filters( 'editable_roles', $all_roles );

	return $editable_roles;
}

/**
 * Retrieve user data and filter it.
 *
 * @since 2.0.5
 *
 * @param int $user_id User ID.
 * @return WP_User|bool WP_User object on success, false on failure.
 */
function get_user_to_edit( $user_id ) {
	$user = get_userdata( $user_id );

	if ( $user ) {
		$user->filter = 'edit';
	}

	return $user;
}

/**
 * Retrieve the user's drafts.
 *
 * @since 2.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int $user_id User ID.
 * @return array
 */
function get_users_drafts( $user_id ) {
	global $wpdb;
	$query = $wpdb->prepare( "SELECT ID, post_title FROM $wpdb->posts WHERE post_type = 'post' AND post_status = 'draft' AND post_author = %d ORDER BY post_modified DESC", $user_id );

	/**
	 * Filters the user's drafts query string.
	 *
	 * @since 2.0.0
	 *
	 * @param string $query The user's drafts query string.
	 */
	$query = apply_filters( 'get_users_drafts', $query );
	return $wpdb->get_results( $query );
}

/**
 * Remove user and optionally reassign posts and links to another user.
 *
 * If the $reassign parameter is not assigned to a User ID, then all posts will
 * be deleted of that user. The action {@see 'delete_user'} that is passed the User ID
 * being deleted will be run after the posts are either reassigned or deleted.
 * The user meta will also be deleted that are for that User ID.
 *
 * @since 2.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int $id User ID.
 * @param int $reassign Optional. Reassign posts and links to new User ID.
 * @return bool True when finished.
 */
function wp_delete_user( $id, $reassign = null ) {
	global $wpdb;

	if ( ! is_numeric( $id ) ) {
		return false;
	}

	$id   = (int) $id;
	$user = new WP_User( $id );

	if ( ! $user->exists() ) {
		return false;
	}

	// Normalize $reassign to null or a user ID. 'novalue' was an older default.
	if ( 'novalue' === $reassign ) {
		$reassign = null;
	} elseif ( null !== $reassign ) {
		$reassign = (int) $reassign;
	}

	/**
	 * Fires immediately before a user is deleted from the database.
	 *
	 * @since 2.0.0
	 *
	 * @param int      $id       ID of the user to delete.
	 * @param int|null $reassign ID of the user to reassign posts and links to.
	 *                           Default null, for no reassignment.
	 */
	do_action( 'delete_user', $id, $reassign );

	if ( null === $reassign ) {
		$post_types_to_delete = array();
		foreach ( get_post_types( array(), 'objects' ) as $post_type ) {
			if ( $post_type->delete_with_user ) {
				$post_types_to_delete[] = $post_type->name;
			} elseif ( null === $post_type->delete_with_user && post_type_supports( $post_type->name, 'author' ) ) {
				$post_types_to_delete[] = $post_type->name;
			}
		}

		/**
		 * Filters the list of post types to delete with a user.
		 *
		 * @since 3.4.0
		 *
		 * @param string[] $post_types_to_delete Array of post types to delete.
		 * @param int      $id                   User ID.
		 */
		$post_types_to_delete = apply_filters( 'post_types_to_delete_with_user', $post_types_to_delete, $id );
		$post_types_to_delete = implode( "', '", $post_types_to_delete );
		$post_ids             = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_author = %d AND post_type IN ('$post_types_to_delete')", $id ) );
		if ( $post_ids ) {
			foreach ( $post_ids as $post_id ) {
				wp_delete_post( $post_id );
			}
		}

		// Clean links
		$link_ids = $wpdb->get_col( $wpdb->prepare( "SELECT link_id FROM $wpdb->links WHERE link_owner = %d", $id ) );

		if ( $link_ids ) {
			foreach ( $link_ids as $link_id ) {
				wp_delete_link( $link_id );
			}
		}
	} else {
		$post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_author = %d", $id ) );
		$wpdb->update( $wpdb->posts, array( 'post_author' => $reassign ), array( 'post_author' => $id ) );
		if ( ! empty( $post_ids ) ) {
			foreach ( $post_ids as $post_id ) {
				clean_post_cache( $post_id );
			}
		}
		$link_ids = $wpdb->get_col( $wpdb->prepare( "SELECT link_id FROM $wpdb->links WHERE link_owner = %d", $id ) );
		$wpdb->update( $wpdb->links, array( 'link_owner' => $reassign ), array( 'link_owner' => $id ) );
		if ( ! empty( $link_ids ) ) {
			foreach ( $link_ids as $link_id ) {
				clean_bookmark_cache( $link_id );
			}
		}
	}

	// FINALLY, delete user
	if ( is_multisite() ) {
		remove_user_from_blog( $id, get_current_blog_id() );
	} else {
		$meta = $wpdb->get_col( $wpdb->prepare( "SELECT umeta_id FROM $wpdb->usermeta WHERE user_id = %d", $id ) );
		foreach ( $meta as $mid ) {
			delete_metadata_by_mid( 'user', $mid );
		}

		$wpdb->delete( $wpdb->users, array( 'ID' => $id ) );
	}

	clean_user_cache( $user );

	/**
	 * Fires immediately after a user is deleted from the database.
	 *
	 * @since 2.9.0
	 *
	 * @param int      $id       ID of the deleted user.
	 * @param int|null $reassign ID of the user to reassign posts and links to.
	 *                           Default null, for no reassignment.
	 */
	do_action( 'deleted_user', $id, $reassign );

	return true;
}

/**
 * Remove all capabilities from user.
 *
 * @since 2.1.0
 *
 * @param int $id User ID.
 */
function wp_revoke_user( $id ) {
	$id = (int) $id;

	$user = new WP_User( $id );
	$user->remove_all_caps();
}

/**
 * @since 2.8.0
 *
 * @global int $user_ID
 *
 * @param false $errors Deprecated.
 */
function default_password_nag_handler( $errors = false ) {
	global $user_ID;
	// Short-circuit it.
	if ( ! get_user_option( 'default_password_nag' ) ) {
		return;
	}

	// get_user_setting = JS saved UI setting. else no-js-fallback code.
	if ( 'hide' == get_user_setting( 'default_password_nag' ) || isset( $_GET['default_password_nag'] ) && '0' == $_GET['default_password_nag'] ) {
		delete_user_setting( 'default_password_nag' );
		update_user_option( $user_ID, 'default_password_nag', false, true );
	}
}

/**
 * @since 2.8.0
 *
 * @param int    $user_ID
 * @param object $old_data
 */
function default_password_nag_edit_user( $user_ID, $old_data ) {
	// Short-circuit it.
	if ( ! get_user_option( 'default_password_nag', $user_ID ) ) {
		return;
	}

	$new_data = get_userdata( $user_ID );

	// Remove the nag if the password has been changed.
	if ( $new_data->user_pass != $old_data->user_pass ) {
		delete_user_setting( 'default_password_nag' );
		update_user_option( $user_ID, 'default_password_nag', false, true );
	}
}

/**
 * @since 2.8.0
 *
 * @global string $pagenow
 */
function default_password_nag() {
	global $pagenow;
	// Short-circuit it.
	if ( 'profile.php' == $pagenow || ! get_user_option( 'default_password_nag' ) ) {
		return;
	}

	echo '<div class="error default-password-nag">';
	echo '<p>';
	echo '<strong>' . __( 'Notice:' ) . '</strong> ';
	_e( 'You&rsquo;re using the auto-generated password for your account. Would you like to change it?' );
	echo '</p><p>';
	printf( '<a href="%s">' . __( 'Yes, take me to my profile page' ) . '</a> | ', get_edit_profile_url() . '#password' );
	printf( '<a href="%s" id="default-password-nag-no">' . __( 'No thanks, do not remind me again' ) . '</a>', '?default_password_nag=0' );
	echo '</p></div>';
}

/**
 * @since 3.5.0
 * @access private
 */
function delete_users_add_js() {
	?>
<script>
jQuery(document).ready( function($) {
	var submit = $('#submit').prop('disabled', true);
	$('input[name="delete_option"]').one('change', function() {
		submit.prop('disabled', false);
	});
	$('#reassign_user').focus( function() {
		$('#delete_option1').prop('checked', true).trigger('change');
	});
});
</script>
<?php
}

/**
 * Optional SSL preference that can be turned on by hooking to the 'personal_options' action.
 *
 * See the {@see 'personal_options'} action.
 *
 * @since 2.7.0
 *
 * @param object $user User data object
 */
function use_ssl_preference( $user ) {
?>
	<tr class="user-use-ssl-wrap">
		<th scope="row"><?php _e( 'Use https' ); ?></th>
		<td><label for="use_ssl"><input name="use_ssl" type="checkbox" id="use_ssl" value="1" <?php checked( '1', $user->use_ssl ); ?> /> <?php _e( 'Always use https when visiting the admin' ); ?></label></td>
	</tr>
<?php
}

/**
 * @param string $text
 * @return string
 */
function admin_created_user_email( $text ) {
	$roles = get_editable_roles();
	$role  = $roles[ $_REQUEST['role'] ];
	/* translators: 1: site name, 2: site URL, 3: role */
	return sprintf(
		__(
			'Hi,
You\'ve been invited to join \'%1$s\' at
%2$s with the role of %3$s.
If you do not want to join this site please ignore
this email. This invitation will expire in a few days.

Please click the following link to activate your user account:
%%s'
		), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), home_url(), wp_specialchars_decode( translate_user_role( $role['name'] ) )
	);
}

/**
 * Get action description from the name.
 *
 * @since 4.9.6
 * @access private
 *
 * @return string
 */
function _wp_privacy_action_description( $request_type ) {
	switch ( $request_type ) {
		case 'user_export_request':
			return __( 'Export Personal Data' );
		case 'user_remove_request':
			return __( 'Remove Personal Data' );
	}
}

/**
 * Log a request and send to the user.
 *
 * @since 4.9.6
 * @access private
 *
 * @param string $email_address Email address sending the request to.
 * @param string $action Action being requested.
 * @param string $description Description of request.
 * @return bool|WP_Error depending on success.
 */
function _wp_privacy_create_request( $email_address, $action, $description ) {
	$user_id = 0;
	$user    = get_user_by( 'email', $email_address );

	if ( $user ) {
		$user_id = $user->ID;
	}

	$privacy_request_id = wp_insert_post( array(
		'post_author'   => $user_id,
		'post_status'   => 'request-pending',
		'post_type'     => $action,
		'post_date'     => current_time( 'mysql', false ),
		'post_date_gmt' => current_time( 'mysql', true ),
	), true );

	if ( is_wp_error( $privacy_request_id ) ) {
		return $privacy_request_id;
	}

	update_post_meta( $privacy_request_id, '_user_email', $email_address );
	update_post_meta( $privacy_request_id, '_action_name', $action );
	update_post_meta( $privacy_request_id, '_confirmed_timestamp', false );

	return wp_send_account_verification_key( $email_address, $action, $description, array(
		'privacy_request_id' => $privacy_request_id,
	) );
}

/**
 * Resend an existing request and return the result.
 *
 * @since 4.9.6
 * @access private
 *
 * @param int $privacy_request_id Request ID.
 * @return bool|WP_Error
 */
function _wp_privacy_resend_request( $privacy_request_id ) {
	$privacy_request_id = absint( $privacy_request_id );
	$privacy_request    = get_post( $privacy_request_id );

	if ( ! $privacy_request || ! in_array( $privacy_request->post_type, _wp_privacy_action_request_types(), true ) ) {
		return new WP_Error( 'privacy_request_error', __( 'Invalid request.' ) );
	}

	$email_address = get_post_meta( $privacy_request_id, '_user_email', true );
	$action        = get_post_meta( $privacy_request_id, '_action_name', true );
	$description   = _wp_privacy_action_description( $action );
	$result        = wp_send_account_verification_key( $email_address, $action, $description, array(
		'privacy_request_id' => $privacy_request_id,
	) );

	if ( is_wp_error( $result ) ) {
		return $result;
	} elseif ( ! $result ) {
		return new WP_Error( 'privacy_request_error', __( 'Unable to initiate confirmation request.' ) );
	}

	wp_update_post( array(
		'ID'            => $privacy_request_id,
		'post_status'   => 'request-pending',
		'post_date'     => current_time( 'mysql', false ),
		'post_date_gmt' => current_time( 'mysql', true ),
	) );

	return true;
}

/**
 * Marks a request as completed by the admin and logs the datetime.
 *
 * @since 4.9.6
 * @access private
 *
 * @param int $privacy_request_id Request ID.
 * @return bool|WP_Error
 */
function _wp_privacy_completed_request( $privacy_request_id ) {
	$privacy_request_id = absint( $privacy_request_id );
	$privacy_request    = get_post( $privacy_request_id );

	if ( ! $privacy_request || ! in_array( $privacy_request->post_type, _wp_privacy_action_request_types(), true ) ) {
		return new WP_Error( 'privacy_request_error', __( 'Invalid request.' ) );
	}

	wp_update_post( array(
		'ID'          => $privacy_request_id,
		'post_status' => 'request-completed',
	) );

	update_post_meta( $privacy_request_id, '_completed_timestamp', time() );
}

/**
 * Handle list table actions.
 *
 * @since 4.9.6
 * @access private
 */
function _wp_personal_data_handle_actions() {
	if ( isset( $_POST['privacy_action_email_retry'] ) ) { // WPCS: input var ok.
		check_admin_referer( 'bulk-privacy_requests' );

		$request_id = absint( current( array_keys( (array) wp_unslash( $_POST['privacy_action_email_retry'] ) ) ) ); // WPCS: input var ok, sanitization ok.
		$result     = _wp_privacy_resend_request( $request_id );

		if ( is_wp_error( $result ) ) {
			add_settings_error(
				'privacy_action_email_retry',
				'privacy_action_email_retry',
				$result->get_error_message(),
				'error'
			);
		} else {
			add_settings_error(
				'privacy_action_email_retry',
				'privacy_action_email_retry',
				__( 'Confirmation request re-resent successfully.' ),
				'updated'
			);
		}

	} elseif ( isset( $_POST['export_personal_data_email_send'] ) ) { // WPCS: input var ok.
		check_admin_referer( 'bulk-privacy_requests' );

		$request_id = absint( current( array_keys( (array) wp_unslash( $_POST['export_personal_data_email_send'] ) ) ) ); // WPCS: input var ok, sanitization ok.
		$result     = false;

		/**
		 * TODO: Email the data to the user here.
		 */

		if ( is_wp_error( $result ) ) {
			add_settings_error(
				'export_personal_data_email_send',
				'export_personal_data_email_send',
				$result->get_error_message(),
				'error'
			);
		} else {
			_wp_privacy_completed_request( $request_id );
			add_settings_error(
				'export_personal_data_email_send',
				'export_personal_data_email_send',
				__( 'Personal data was sent to the user successfully.' ),
				'updated'
			);
		}

	} elseif ( isset( $_POST['action'] ) ) {
		$action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : ''; // WPCS: input var ok, CSRF ok.

		switch ( $action ) {
			case 'add_export_personal_data_request':
			case 'add_remove_personal_data_request':
				check_admin_referer( 'personal-data-request' );

				if ( ! isset( $_POST['type_of_action'], $_POST['username_or_email_to_export'] ) ) { // WPCS: input var ok.
					add_settings_error(
						'action_type',
						'action_type',
						__( 'Invalid action.' ),
						'error'
					);
				}
				$action_type               = sanitize_text_field( wp_unslash( $_POST['type_of_action'] ) ); // WPCS: input var ok.
				$username_or_email_address = sanitize_text_field( wp_unslash( $_POST['username_or_email_to_export'] ) ); // WPCS: input var ok.
				$email_address             = '';

				if ( ! in_array( $action_type, _wp_privacy_action_request_types(), true ) ) {
					add_settings_error(
						'action_type',
						'action_type',
						__( 'Invalid action.' ),
						'error'
					);
				}

				if ( ! is_email( $username_or_email_address ) ) {
					$user = get_user_by( 'login', $username_or_email_address );
					if ( ! $user instanceof WP_User ) {
						add_settings_error(
							'username_or_email_to_export',
							'username_or_email_to_export',
							__( 'Unable to add this request. A valid email address or username must be supplied.' ),
							'error'
						);
					} else {
						$email_address = $user->user_email;
					}
				} else {
					$email_address = $username_or_email_address;
				}

				if ( ! empty( $email_address ) ) {
					$result = _wp_privacy_create_request( $email_address, $action_type, _wp_privacy_action_description( $action_type ) );

					if ( is_wp_error( $result ) ) {
						add_settings_error(
							'username_or_email_to_export',
							'username_or_email_to_export',
							$result->get_error_message(),
							'error'
						);
					} elseif ( ! $result ) {
						add_settings_error(
							'username_or_email_to_export',
							'username_or_email_to_export',
							__( 'Unable to initiate confirmation request.' ),
							'error'
						);
					} else {
						add_settings_error(
							'username_or_email_to_export',
							'username_or_email_to_export',
							__( 'Confirmation request initiated successfully.' ),
							'updated'
						);
					}
				}
				break;
		}
	}
}

/**
 * Personal data export.
 *
 * @since 4.9.6
 * @access private
 */
function _wp_personal_data_export_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage privacy on this site.' ) );
	}

	_wp_personal_data_handle_actions();

	$requests_table = new WP_Privacy_Data_Export_Requests_Table( array(
		'plural'   => 'privacy_requests',
		'singular' => 'privacy_request',
	) );
	$requests_table->process_bulk_action();
	$requests_table->prepare_items();
	?>
	<div class="wrap nosubsub">
		<h1><?php esc_html_e( 'Export Personal Data' ); ?></h1>
		<hr class="wp-header-end" />

		<?php settings_errors(); ?>

		<form method="post" class="wp-privacy-request-form">
			<h2><?php esc_html_e( 'Add Data Export Request' ); ?></h2>
			<p><?php esc_html_e( 'An email will be sent to the user at this email address asking them to verify the request.' ); ?></p>

			<div class="wp-privacy-request-form-field">
				<label for="username_or_email_to_export"><?php esc_html_e( 'Username or email address' ); ?></label>
				<input type="text" required class="regular-text" id="username_or_email_to_export" name="username_or_email_to_export" />
				<?php submit_button( __( 'Send Request' ), 'secondary', 'submit', false ); ?>
			</div>
			<?php wp_nonce_field( 'personal-data-request' ); ?>
			<input type="hidden" name="action" value="add_export_personal_data_request" />
			<input type="hidden" name="type_of_action" value="user_export_request" />
		</form>
		<hr />

		<?php $requests_table->views(); ?>

		<form class="search-form wp-clearfix">
			<?php $requests_table->search_box( __( 'Search Requests' ), 'requests' ); ?>
			<input type="hidden" name="page" value="export_personal_data" />
			<input type="hidden" name="filter-status" value="<?php echo isset( $_REQUEST['filter-status'] ) ? esc_attr( sanitize_text_field( $_REQUEST['filter-status'] ) ) : ''; ?>" />
			<input type="hidden" name="orderby" value="<?php echo isset( $_REQUEST['orderby'] ) ? esc_attr( sanitize_text_field( $_REQUEST['orderby'] ) ) : ''; ?>" />
			<input type="hidden" name="order" value="<?php echo isset( $_REQUEST['order'] ) ? esc_attr( sanitize_text_field( $_REQUEST['order'] ) ) : ''; ?>" />
		</form>

		<form method="post">
			<?php
			$requests_table->display();
			$requests_table->embed_scripts();
			?>
		</form>
	</div>
	<?php
}

/**
 * Personal data anonymization.
 *
 * @since 4.9.6
 * @access private
 */
function _wp_personal_data_removal_page() {
	if ( ! current_user_can( 'delete_users' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage privacy on this site.' ) );
	}

	_wp_personal_data_handle_actions();

	// "Borrow" xfn.js for now so we don't have to create new files.
	wp_enqueue_script( 'xfn' );

	$requests_table = new WP_Privacy_Data_Removal_Requests_Table( array(
		'plural'   => 'privacy_requests',
		'singular' => 'privacy_request',
	) );

	$requests_table->process_bulk_action();
	$requests_table->prepare_items();

	?>
	<div class="wrap nosubsub">
		<h1><?php esc_html_e( 'Remove Personal Data' ); ?></h1>
		<hr class="wp-header-end" />

		<?php settings_errors(); ?>

		<form method="post" class="wp-privacy-request-form">
			<h2><?php esc_html_e( 'Add Data Removal Request' ); ?></h2>
			<p><?php esc_html_e( 'An email will be sent to the user at this email address asking them to verify the request.' ); ?></p>

			<div class="wp-privacy-request-form-field">
				<label for="username_or_email_to_export"><?php esc_html_e( 'Username or email address' ); ?></label>
				<input type="text" required class="regular-text" id="username_or_email_to_export" name="username_or_email_to_export" />
				<?php submit_button( __( 'Send Request' ), 'secondary', 'submit', false ); ?>
			</div>
			<?php wp_nonce_field( 'personal-data-request' ); ?>
			<input type="hidden" name="action" value="add_remove_personal_data_request" />
			<input type="hidden" name="type_of_action" value="user_remove_request" />
		</form>
		<hr />

		<?php $requests_table->views(); ?>

		<form class="search-form wp-clearfix">
			<?php $requests_table->search_box( __( 'Search Requests' ), 'requests' ); ?>
			<input type="hidden" name="page" value="export_personal_data" />
			<input type="hidden" name="filter-status" value="<?php echo isset( $_REQUEST['filter-status'] ) ? esc_attr( sanitize_text_field( $_REQUEST['filter-status'] ) ) : ''; ?>" />
			<input type="hidden" name="orderby" value="<?php echo isset( $_REQUEST['orderby'] ) ? esc_attr( sanitize_text_field( $_REQUEST['orderby'] ) ) : ''; ?>" />
			<input type="hidden" name="order" value="<?php echo isset( $_REQUEST['order'] ) ? esc_attr( sanitize_text_field( $_REQUEST['order'] ) ) : ''; ?>" />
		</form>

		<form method="post">
			<?php
			$requests_table->display();
			$requests_table->embed_scripts();
			?>
		</form>
	</div>
	<?php
}

/**
 * Add requests pages.
 *
 * @since 4.9.6
 * @access private
 */
function _wp_privacy_hook_requests_page() {
	add_submenu_page( 'tools.php', __( 'Export Personal Data' ), __( 'Export Personal Data' ), 'manage_options', 'export_personal_data', '_wp_personal_data_export_page' );
	add_submenu_page( 'tools.php', __( 'Remove Personal Data' ), __( 'Remove Personal Data' ), 'manage_options', 'remove_personal_data', '_wp_personal_data_removal_page' );
}

// TODO: move the following classes in new files.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * WP_Privacy_Requests_Table class.
 */
abstract class WP_Privacy_Requests_Table extends WP_List_Table {

	/**
	 * Action name for the requests this table will work with. Classes
	 * which inherit from WP_Privacy_Requests_Table should define this.
	 * e.g. 'export_personal_data'
	 *
	 * @since 4.9.6
	 *
	 * @var string $request_type Name of action.
	 */
	protected $request_type = 'INVALID';

	/**
	 * Post type to be used.
	 *
	 * @since 4.9.6
	 *
	 * @var string $post_type The post type.
	 */
	protected $post_type = 'INVALID';

	/**
	 * Get columns to show in the list table.
	 *
	 * @since 4.9.6
	 *
	 * @param array Array of columns.
	 */
	public function get_columns() {
		$columns = array(
			'cb'         => '<input type="checkbox" />',
			'email'      => __( 'Requester' ),
			'status'     => __( 'Status' ),
			'requested'  => __( 'Requested' ),
			'next_steps' => __( 'Next Steps' ),
		);
		return $columns;
	}

	/**
	 * Get a list of sortable columns.
	 *
	 * @since 4.9.6
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array();
	}

	/**
	 * Default primary column.
	 *
	 * @since 4.9.6
	 *
	 * @return string
	 */
	protected function get_default_primary_column_name() {
		return 'email';
	}

	/**
	 * Get an associative array ( id => link ) with the list
	 * of views available on this table.
	 *
	 * @since 4.9.6
	 *
	 * @return array
	 */
	protected function get_views() {
		$current_status = isset( $_REQUEST['filter-status'] ) ? sanitize_text_field( $_REQUEST['filter-status'] ): '';
		$statuses       = _wp_privacy_statuses();
		$views          = array();
		$admin_url      = admin_url( 'tools.php?page=' . $this->request_type );
		$counts         = wp_count_posts( $this->post_type );

		$current_link_attributes = empty( $current_status ) ? ' class="current" aria-current="page"' : '';
		$views['all']            = '<a href="' . esc_url( $admin_url ) . "\" $current_link_attributes>" . esc_html__( 'All' ) . ' <span class="count">(' . absint( array_sum( (array) $counts ) ) . ')</span></a>';

		foreach ( $statuses as $status => $label ) {
			$current_link_attributes = $status === $current_status ? ' class="current" aria-current="page"' : '';
			$views[ $status ]        = '<a href="' . esc_url( add_query_arg( 'filter-status', $status, $admin_url ) ) . "\" $current_link_attributes>" . esc_html( $label ) . ' <span class="count">(' . absint( $counts->$status ) . ')</span></a>';
		}

		return $views;
	}

	/**
	 * Get bulk actions.
	 *
	 * @since 4.9.6
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array(
			'delete' => __( 'Remove' ),
			'resend' => __( 'Resend email' ),
		);
	}

	/**
	 * Process bulk actions.
	 *
	 * @since 4.9.6
	 */
	public function process_bulk_action() {
		$action      = $this->current_action();
		$request_ids = isset( $_REQUEST['request_id'] ) ? wp_parse_id_list( wp_unslash( $_REQUEST['request_id'] ) ) : array(); // WPCS: input var ok, CSRF ok.

		if ( $request_ids ) {
			check_admin_referer( 'bulk-privacy_requests' );
		}

		switch ( $action ) {
			case 'delete':
				$count = 0;

				foreach ( $request_ids as $request_id ) {
					if ( wp_delete_post( $request_id, true ) ) {
						$count ++;
					}
				}

				add_settings_error(
					'bulk_action',
					'bulk_action',
					sprintf( _n( 'Deleted %d request', 'Deleted %d requests', $count ), $count ),
					'updated'
				);
				break;
			case 'resend':
				$count = 0;

				foreach ( $request_ids as $request_id ) {
					if ( _wp_privacy_resend_request( $request_id ) ) {
						$count ++;
					}
				}

				add_settings_error(
					'bulk_action',
					'bulk_action',
					sprintf( _n( 'Re-sent %d request', 'Re-sent %d requests', $count ), $count ),
					'updated'
				);
				break;
		}
	}

	/**
	 * Prepare items to output.
	 *
	 * @since 4.9.6
	 */
	public function prepare_items() {
		global $wpdb;

		$primary               = $this->get_primary_column_name();
		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
			$primary,
		);

		$this->items    = array();
		$posts_per_page = 20;
		$args           = array(
			'post_type'      => $this->post_type,
			'posts_per_page' => $posts_per_page,
			'offset'         => isset( $_REQUEST['paged'] ) ? max( 0, absint( $_REQUEST['paged'] ) - 1 ) * $posts_per_page: 0,
			'post_status'    => 'any',
		);

		if ( ! empty( $_REQUEST['filter-status'] ) ) {
			$filter_status       = isset( $_REQUEST['filter-status'] ) ? sanitize_text_field( $_REQUEST['filter-status'] ) : '';
			$args['post_status'] = $filter_status;
		}

		if ( ! empty( $_REQUEST['s'] ) ) {
			$args['meta_query'] = array(
				$name_query,
				'relation'  => 'AND',
				array(
					'key'     => '_user_email',
					'value'   => isset( $_REQUEST['s'] ) ? sanitize_text_field( $_REQUEST['s'] ): '',
					'compare' => 'LIKE'
				),
			);
		}

		$privacy_requests_query = new WP_Query( $args );
		$privacy_requests       = $privacy_requests_query->posts;

		foreach ( $privacy_requests as $privacy_request ) {
			$this->items[] = array(
				'request_id' => $privacy_request->ID,
				'user_id'    => $privacy_request->post_author,
				'email'      => get_post_meta( $privacy_request->ID, '_user_email', true ),
				'action'     => get_post_meta( $privacy_request->ID, '_action_name', true ),
				'requested'  => strtotime( $privacy_request->post_date_gmt ),
				'confirmed'  => get_post_meta( $privacy_request->ID, '_confirmed_timestamp', true ),
				'completed'  => get_post_meta( $privacy_request->ID, '_completed_timestamp', true ),
			);
		}

		$this->set_pagination_args(
			array(
				'total_items' => $privacy_requests_query->found_posts,
				'per_page'    => $posts_per_page,
			)
		);
	}

	/**
	 * Checkbox column.
	 *
	 * @since 4.9.6
	 *
	 * @param array $item Item being shown.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="request_id[]" value="%1$s" /><span class="spinner"></span>', esc_attr( $item['request_id'] ) );
	}

	/**
	 * Status column.
	 *
	 * @since 4.9.6
	 *
	 * @param array $item Item being shown.
	 * @return string
	 */
	public function column_status( $item ) {
		$status        = get_post_status( $item['request_id'] );
		$status_object = get_post_status_object( $status );

		if ( ! $status_object || empty( $status_object->label ) ) {
			return '-';
		}

		$timestamp = false;

		switch ( $status ) {
			case 'request-confirmed':
				$timestamp = $item['confirmed'];
				break;
			case 'request-completed':
				$timestamp = $item['completed'];
				break;
		}

		echo '<span class="status-label status-' . esc_attr( $status ) . '">';
		echo esc_html( $status_object->label );

		if ( $timestamp ) {
			echo ' (' . $this->get_timestamp_as_date( $timestamp ) . ')';
		}

		echo '</span>';
	}

	/**
	 * Convert timestamp for display.
	 *
	 * @since 4.9.6
	 *
	 * @param int $timestamp Event timestamp.
	 * @return string
	 */
	protected function get_timestamp_as_date( $timestamp ) {
		if ( empty( $timestamp ) ) {
			return '';
		}

		$time_diff = current_time( 'timestamp', true ) - $timestamp;

		if ( $time_diff >= 0 && $time_diff < DAY_IN_SECONDS ) {
			return sprintf( __( '%s ago' ), human_time_diff( $timestamp ) );
		}

		return date_i18n( get_option( 'date_format' ), $timestamp );
	}

	/**
	 * Default column handler.
	 *
	 * @since 4.9.6
	 *
	 * @param array $item         Item being shown.
	 * @param string $column_name Name of column being shown.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		$cell_value = $item[ $column_name ];

		if ( in_array( $column_name, array( 'requested' ), true ) ) {
			return $this->get_timestamp_as_date( $cell_value );
		}

		return $cell_value;
	}

	/**
	 * Actions column. Overriden by children.
	 *
	 * @since 4.9.6
	 *
	 * @param array $item Item being shown.
	 * @return string
	 */
	public function column_email( $item ) {
		return sprintf( '%1$s %2$s', $item['email'], $this->row_actions( array() ) );
	}

	/**
	 * Next steps column. Overriden by children.
	 *
	 * @since 4.9.6
	 *
	 * @param array $item Item being shown.
	 */
	public function column_next_steps( $item ) {}

	/**
	 * Generates content for a single row of the table
	 *
	 * @since 4.9.6
	 *
	 * @param object $item The current item
	 */
	public function single_row( $item ) {
		$status = get_post_status( $item['request_id'] );

		echo '<tr class="status-' . esc_attr( $status ) . '">';
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	/**
	 * Embed scripts used to perform actions. Overriden by children.
	 *
	 * @since 4.9.6
	 */
	public function embed_scripts() {}
}

/**
 * WP_Privacy_Data_Export_Requests_Table class.
 *
 * @since 4.9.6
 */
class WP_Privacy_Data_Export_Requests_Table extends WP_Privacy_Requests_Table {
	/**
	 * Action name for the requests this table will work with.
	 *
	 * @since 4.9.6
	 *
	 * @var string $request_type Name of action.
	 */
	protected $request_type = 'export_personal_data';

	/**
	 * Post type for the requests.
	 *
	 * @since 4.9.6
	 *
	 * @var string $post_type The post type.
	 */
	protected $post_type = 'user_export_request';

	/**
	 * Actions column.
	 *
	 * @since 4.9.6
	 *
	 * @param array $item Item being shown.
	 * @return string
	 */
	public function column_email( $item ) {
		$exporters       = apply_filters( 'wp_privacy_personal_data_exporters', array() );
		$exporters_count = count( $exporters );
		$request_id      = $item['request_id'];
		$nonce           = wp_create_nonce( 'wp-privacy-export-personal-data-' . $request_id );

		$download_data_markup = '<div class="download_personal_data" ' .
			'data-exporters-count="' . esc_attr( $exporters_count ) . '" ' .
			'data-request-id="' . esc_attr( $request_id ) . '" ' .
			'data-nonce="' . esc_attr( $nonce ) .
			'">';

		$download_data_markup .= '<span class="download_personal_data_idle"><a href="#" >' . __( 'Download Personal Data' ) . '</a></span>' .
			'<span style="display:none" class="download_personal_data_processing" >' . __( 'Downloading Data...' ) . '</span>' .
			'<span style="display:none" class="download_personal_data_failed">' . __( 'Download Failed!' ) . ' <a href="#" >' . __( 'Retry' ) . '</a></span>';

		$row_actions = array(
			'download_data' => $download_data_markup,
		);

		return sprintf( '%1$s %2$s', $item['email'], $this->row_actions( $row_actions ) );
	}

	/**
	 * Next steps column.
	 *
	 * @since 4.9.6
	 *
	 * @param array $item Item being shown.
	 */
	public function column_next_steps( $item ) {
		$status = get_post_status( $item['request_id'] );

		switch ( $status ) {
			case 'request-pending':
				esc_html_e( 'Waiting for confirmation' );
				break;
			case 'request-confirmed':
				// TODO Complete in follow on patch.
				break;
			case 'request-failed':
				submit_button( __( 'Retry' ), 'secondary', 'privacy_action_email_retry[' . $item['request_id'] . ']', false );
				break;
			case 'request-completed':
				echo '<a href="' . esc_url( wp_nonce_url( add_query_arg( array(
					'action' => 'delete',
					'request_id' => array( $item['request_id'] )
				), admin_url( 'tools.php?page=export_personal_data' ) ), 'bulk-privacy_requests' ) ) . '">' . esc_html__( 'Remove request' ) . '</a>';
				break;
		}
	}
}

/**
 * WP_Privacy_Data_Removal_Requests_Table class.
 *
	 * @since 4.9.6
 */
class WP_Privacy_Data_Removal_Requests_Table extends WP_Privacy_Requests_Table {
	/**
	 * Action name for the requests this table will work with.
	 *
	 * @since 4.9.6
	 *
	 * @var string $request_type Name of action.
	 */
	protected $request_type = 'remove_personal_data';

	/**
	 * Post type for the requests.
	 *
	 * @since 4.9.6
	 *
	 * @var string $post_type The post type.
	 */
	protected $post_type = 'user_remove_request';

	/**
	 * Actions column.
	 *
	 * @since 4.9.6
	 *
	 * @param array $item Item being shown.
	 * @return string
	 */
	public function column_email( $item ) {
		$row_actions = array();

		// Allow the administrator to "force remove" the personal data even if confirmation has not yet been received
		$status = get_post_status( $item['request_id'] );
		if ( 'request-confirmed' !== $status ) {
			$erasers       = apply_filters( 'wp_privacy_personal_data_erasers', array() );
			$erasers_count = count( $erasers );
			$request_id    = $item['request_id'];
			$nonce         = wp_create_nonce( 'wp-privacy-erase-personal-data-' . $request_id );

			$remove_data_markup = '<div class="remove_personal_data force_remove_personal_data" ' .
				'data-erasers-count="' . esc_attr( $erasers_count ) . '" ' .
				'data-request-id="' . esc_attr( $request_id ) . '" ' .
				'data-nonce="' . esc_attr( $nonce ) .
				'">';

			$remove_data_markup .= '<span class="remove_personal_data_idle"><a href="#" >' . __( 'Force Remove Personal Data' ) . '</a></span>' .
				'<span style="display:none" class="remove_personal_data_processing" >' . __( 'Removing Data...' ) . '</span>' .
				'<span style="display:none" class="remove_personal_data_failed">' . __( 'Force Remove Failed!' ) . ' <a href="#" >' . __( 'Retry' ) . '</a></span>';

			$row_actions = array(
				'remove_data' => $remove_data_markup,
			);
		}

		return sprintf( '%1$s %2$s', $item['email'], $this->row_actions( $row_actions ) );
	}

	/**
	 * Next steps column.
	 *
	 * @since 4.9.6
	 *
	 * @param array $item Item being shown.
	 */
	public function column_next_steps( $item ) {
		$status = get_post_status( $item['request_id'] );

		switch ( $status ) {
			case 'request-pending':
				esc_html_e( 'Waiting for confirmation' );
				break;
			case 'request-confirmed':
				$erasers       = apply_filters( 'wp_privacy_personal_data_erasers', array() );
				$erasers_count = count( $erasers );
				$request_id    = $item['request_id'];
				$nonce         = wp_create_nonce( 'wp-privacy-erase-personal-data-' . $request_id );

				echo '<div class="remove_personal_data" ' .
					'data-force-erase="1" ' .
					'data-erasers-count="' . esc_attr( $erasers_count ) . '" ' .
					'data-request-id="' . esc_attr( $request_id ) . '" ' .
					'data-nonce="' . esc_attr( $nonce ) .
					'">';

				?>
				<span class="remove_personal_data_idle"><a class="button" href="#" ><?php _e( 'Remove Personal Data' ); ?></a></span>
				<span style="display:none" class="remove_personal_data_processing button updating-message" ><?php _e( 'Removing Data...' ); ?></span>
				<span style="display:none" class="remove_personal_data_failed"><?php _e( 'Removing Data Failed!' ); ?> <a class="button" href="#" ><?php _e( 'Retry' ); ?></a></span>
				<?php

				break;
			case 'request-failed':
				submit_button( __( 'Retry' ), 'secondary', 'privacy_action_email_retry[' . $item['request_id'] . ']', false );
				break;
			case 'request-completed':
				echo '<a href="' . esc_url( wp_nonce_url( add_query_arg( array(
					'action' => 'delete',
					'request_id' => array( $item['request_id'] ),
				), admin_url( 'tools.php?page=remove_personal_data' ) ), 'bulk-privacy_requests' ) ) . '">' . esc_html__( 'Remove request' ) . '</a>';
				break;
		}
	}

}
