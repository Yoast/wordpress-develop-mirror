<?php
/**
 * Administration API: Core Ajax handlers
 *
 * @package WordPress
 * @subpackage Administration
 * @since 2.1.0
 */

//
// No-privilege Ajax handlers.
//

//
// GET-based Ajax handlers.
//



















//
// Ajax helpers.
//



//
// POST-based Ajax handlers.
//



































/**
 * Ajax handler for closed post boxes.
 *
 * @since 3.1.0
 */
function wp_ajax_closed_postboxes() {
	check_ajax_referer( 'closedpostboxes', 'closedpostboxesnonce' );
	$closed = isset( $_POST['closed'] ) ? explode( ',', $_POST['closed'] ) : array();
	$closed = array_filter( $closed );

	$hidden = isset( $_POST['hidden'] ) ? explode( ',', $_POST['hidden'] ) : array();
	$hidden = array_filter( $hidden );

	$page = isset( $_POST['page'] ) ? $_POST['page'] : '';

	if ( $page != sanitize_key( $page ) ) {
		wp_die( 0 );
	}

	$user = wp_get_current_user();
	if ( ! $user ) {
		wp_die( -1 );
	}

	if ( is_array( $closed ) ) {
		update_user_option( $user->ID, "closedpostboxes_$page", $closed, true );
	}

	if ( is_array( $hidden ) ) {
		$hidden = array_diff( $hidden, array( 'submitdiv', 'linksubmitdiv', 'manage-menu', 'create-menu' ) ); // postboxes that are always shown
		update_user_option( $user->ID, "metaboxhidden_$page", $hidden, true );
	}

	wp_die( 1 );
}

/**
 * Ajax handler for hidden columns.
 *
 * @since 3.1.0
 */
function wp_ajax_hidden_columns() {
	check_ajax_referer( 'screen-options-nonce', 'screenoptionnonce' );
	$page = isset( $_POST['page'] ) ? $_POST['page'] : '';

	if ( $page != sanitize_key( $page ) ) {
		wp_die( 0 );
	}

	$user = wp_get_current_user();
	if ( ! $user ) {
		wp_die( -1 );
	}

	$hidden = ! empty( $_POST['hidden'] ) ? explode( ',', $_POST['hidden'] ) : array();
	update_user_option( $user->ID, "manage{$page}columnshidden", $hidden, true );

	wp_die( 1 );
}

/**
 * Ajax handler for updating whether to display the welcome panel.
 *
 * @since 3.1.0
 */
function wp_ajax_update_welcome_panel() {
	check_ajax_referer( 'welcome-panel-nonce', 'welcomepanelnonce' );

	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( -1 );
	}

	update_user_meta( get_current_user_id(), 'show_welcome_panel', empty( $_POST['visible'] ) ? 0 : 1 );

	wp_die( 1 );
}

/**
 * Ajax handler for retrieving menu meta boxes.
 *
 * @since 3.1.0
 */
function wp_ajax_menu_get_metabox() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( -1 );
	}

	require_once ABSPATH . 'wp-admin/includes/nav-menu.php';

	if ( isset( $_POST['item-type'] ) && 'post_type' == $_POST['item-type'] ) {
		$type     = 'posttype';
		$callback = 'wp_nav_menu_item_post_type_meta_box';
		$items    = (array) get_post_types( array( 'show_in_nav_menus' => true ), 'object' );
	} elseif ( isset( $_POST['item-type'] ) && 'taxonomy' == $_POST['item-type'] ) {
		$type     = 'taxonomy';
		$callback = 'wp_nav_menu_item_taxonomy_meta_box';
		$items    = (array) get_taxonomies( array( 'show_ui' => true ), 'object' );
	}

	if ( ! empty( $_POST['item-object'] ) && isset( $items[ $_POST['item-object'] ] ) ) {
		$menus_meta_box_object = $items[ $_POST['item-object'] ];

		/** This filter is documented in wp-admin/includes/nav-menu.php */
		$item = apply_filters( 'nav_menu_meta_box_object', $menus_meta_box_object );
		ob_start();
		call_user_func_array(
			$callback,
			array(
				null,
				array(
					'id'       => 'add-' . $item->name,
					'title'    => $item->labels->name,
					'callback' => $callback,
					'args'     => $item,
				),
			)
		);

		$markup = ob_get_clean();

		echo wp_json_encode(
			array(
				'replace-id' => $type . '-' . $item->name,
				'markup'     => $markup,
			)
		);
	}

	wp_die();
}

/**
 * Ajax handler for saving the meta box order.
 *
 * @since 3.1.0
 */
function wp_ajax_meta_box_order() {
	check_ajax_referer( 'meta-box-order' );
	$order        = isset( $_POST['order'] ) ? (array) $_POST['order'] : false;
	$page_columns = isset( $_POST['page_columns'] ) ? $_POST['page_columns'] : 'auto';

	if ( $page_columns != 'auto' ) {
		$page_columns = (int) $page_columns;
	}

	$page = isset( $_POST['page'] ) ? $_POST['page'] : '';

	if ( $page != sanitize_key( $page ) ) {
		wp_die( 0 );
	}

	$user = wp_get_current_user();
	if ( ! $user ) {
		wp_die( -1 );
	}

	if ( $order ) {
		update_user_option( $user->ID, "meta-box-order_$page", $order, true );
	}

	if ( $page_columns ) {
		update_user_option( $user->ID, "screen_layout_$page", $page_columns, true );
	}

	wp_die( 1 );
}

/**
 * Ajax handler for date formatting.
 *
 * @since 3.1.0
 */
function wp_ajax_date_format() {
	wp_die( date_i18n( sanitize_option( 'date_format', wp_unslash( $_POST['date'] ) ) ) );
}

/**
 * Ajax handler for time formatting.
 *
 * @since 3.1.0
 */
function wp_ajax_time_format() {
	wp_die( date_i18n( sanitize_option( 'time_format', wp_unslash( $_POST['date'] ) ) ) );
}

/**
 * Ajax handler for dismissing a WordPress pointer.
 *
 * @since 3.1.0
 */
function wp_ajax_dismiss_wp_pointer() {
	$pointer = $_POST['pointer'];

	if ( $pointer != sanitize_key( $pointer ) ) {
		wp_die( 0 );
	}

	//  check_ajax_referer( 'dismiss-pointer_' . $pointer );

	$dismissed = array_filter( explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) ) );

	if ( in_array( $pointer, $dismissed ) ) {
		wp_die( 0 );
	}

	$dismissed[] = $pointer;
	$dismissed   = implode( ',', $dismissed );

	update_user_meta( get_current_user_id(), 'dismissed_wp_pointers', $dismissed );
	wp_die( 1 );
}

/**
 * Ajax handler for getting revision diffs.
 *
 * @since 3.6.0
 */
function wp_ajax_get_revision_diffs() {
	require ABSPATH . 'wp-admin/includes/revision.php';

	$post = get_post( (int) $_REQUEST['post_id'] );
	if ( ! $post ) {
		wp_send_json_error();
	}

	if ( ! current_user_can( 'edit_post', $post->ID ) ) {
		wp_send_json_error();
	}

	// Really just pre-loading the cache here.
	$revisions = wp_get_post_revisions( $post->ID, array( 'check_enabled' => false ) );
	if ( ! $revisions ) {
		wp_send_json_error();
	}

	$return = array();
	set_time_limit( 0 );

	foreach ( $_REQUEST['compare'] as $compare_key ) {
		list( $compare_from, $compare_to ) = explode( ':', $compare_key ); // from:to

		$return[] = array(
			'id'     => $compare_key,
			'fields' => wp_get_revision_ui_diff( $post, $compare_from, $compare_to ),
		);
	}
	wp_send_json_success( $return );
}

/**
 * Ajax handler for destroying multiple open sessions for a user.
 *
 * @since 4.1.0
 */
function wp_ajax_destroy_sessions() {
	$user = get_userdata( (int) $_POST['user_id'] );

	if ( $user ) {
		if ( ! current_user_can( 'edit_user', $user->ID ) ) {
			$user = false;
		} elseif ( ! wp_verify_nonce( $_POST['nonce'], 'update-user_' . $user->ID ) ) {
			$user = false;
		}
	}

	if ( ! $user ) {
		wp_send_json_error(
			array(
				'message' => __( 'Could not log out user sessions. Please try again.' ),
			)
		);
	}

	$sessions = WP_Session_Tokens::get_instance( $user->ID );

	if ( $user->ID === get_current_user_id() ) {
		$sessions->destroy_others( wp_get_session_token() );
		$message = __( 'You are now logged out everywhere else.' );
	} else {
		$sessions->destroy_all();
		/* translators: %s: User's display name. */
		$message = sprintf( __( '%s has been logged out.' ), $user->display_name );
	}

	wp_send_json_success( array( 'message' => $message ) );
}

/**
 * Ajax handler for editing a theme or plugin file.
 *
 * @since 4.9.0
 * @see wp_edit_theme_plugin_file()
 */
function wp_ajax_edit_theme_plugin_file() {
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
