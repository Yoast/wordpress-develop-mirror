<?php

namespace WP\Helper\Ajax;

class ScreenHelper {

	/**
	 * Ajax handler for fetching a list table.
	 */
	public static function fetchList() {
		$list_class = $_GET['list_args']['class'];
		check_ajax_referer( "fetch-list-$list_class", '_ajax_fetch_list_nonce' );

		$wp_list_table = _get_list_table( $list_class, array( 'screen' => $_GET['list_args']['screen']['id'] ) );
		if ( ! $wp_list_table ) {
			wp_die( 0 );
		}

		if ( ! $wp_list_table->ajax_user_can() ) {
			wp_die( -1 );
		}

		$wp_list_table->ajax_response();

		wp_die( 0 );
	}

	/**
	 * Ajax handler for closed post boxes.
	 */
	public static function closedPostboxes() {
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
	public static function hiddenColumns() {
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
	public static function updateWelcomePanel() {
		check_ajax_referer( 'welcome-panel-nonce', 'welcomepanelnonce' );

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( -1 );
		}

		update_user_meta( get_current_user_id(), 'show_welcome_panel', empty( $_POST['visible'] ) ? 0 : 1 );

		wp_die( 1 );
	}

	/**
	 * Ajax handler for saving the meta box order.
	 */
	public static function metaBoxOrder() {
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
	 * Ajax handler for dismissing a WordPress pointer.
	 */
	public static function dismissWPPointer() {
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
}
