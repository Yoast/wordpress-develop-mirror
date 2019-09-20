<?php

namespace WP\Helper\Ajax;

class PostHelper {
	/**
	 * Ajax handler for deleting a post.
	 *
	 * @param string $action Action to perform.
	 */
	public static function deletePost( $action ) {
		if ( empty( $action ) ) {
			$action = 'delete-post';
		}

		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		check_ajax_referer( "{$action}_$id" );

		if ( ! current_user_can( 'delete_post', $id ) ) {
			wp_die( -1 );
		}

		if ( ! get_post( $id ) ) {
			wp_die( 1 );
		}

		if ( wp_delete_post( $id ) ) {
			wp_die( 1 );
		} else {
			wp_die( 0 );
		}
	}

	/**
	 * Ajax handler for sending a post to the trash.
	 *
	 * @param string $action Action to perform.
	 */
	public static function trashPost( $action ) {
		if ( empty( $action ) ) {
			$action = 'trash-post';
		}

		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		check_ajax_referer( "{$action}_$id" );

		if ( ! current_user_can( 'delete_post', $id ) ) {
			wp_die( -1 );
		}

		if ( ! get_post( $id ) ) {
			wp_die( 1 );
		}

		if ( 'trash-post' == $action ) {
			$done = wp_trash_post( $id );
		} else {
			$done = wp_untrash_post( $id );
		}

		if ( $done ) {
			wp_die( 1 );
		}

		wp_die( 0 );
	}

	/**
	 * Ajax handler to restore a post from the trash.
	 *
	 * @param string $action Action to perform.
	 */
	public static function untrashPost( $action ) {
		if ( empty( $action ) ) {
			$action = 'untrash-post';
		}

		self::trashPost( $action );
	}

	/**
	 * Ajax handler to delete a page.
	 *
	 * @param string $action Action to perform.
	 */
	public static function deletePage( $action ) {
		if ( empty( $action ) ) {
			$action = 'delete-page';
		}

		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		check_ajax_referer( "{$action}_$id" );

		if ( ! current_user_can( 'delete_page', $id ) ) {
			wp_die( -1 );
		}

		if ( ! get_post( $id ) ) {
			wp_die( 1 );
		}

		if ( wp_delete_post( $id ) ) {
			wp_die( 1 );
		} else {
			wp_die( 0 );
		}
	}

	/**
	 * Ajax handler for querying posts for the Find Posts modal.
	 *
	 * @see window.findPosts
	 */
	public static function findPosts() {
		check_ajax_referer( 'find-posts' );

		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $post_types['attachment'] );

		$s    = wp_unslash( $_POST['ps'] );
		$args = array(
			'post_type'      => array_keys( $post_types ),
			'post_status'    => 'any',
			'posts_per_page' => 50,
		);

		if ( '' !== $s ) {
			$args['s'] = $s;
		}

		$posts = get_posts( $args );

		if ( ! $posts ) {
			wp_send_json_error( __( 'No items found.' ) );
		}

		$html = '<table class="widefat"><thead><tr><th class="found-radio"><br /></th><th>' . __( 'Title' ) . '</th><th class="no-break">' . __( 'Type' ) . '</th><th class="no-break">' . __( 'Date' ) . '</th><th class="no-break">' . __( 'Status' ) . '</th></tr></thead><tbody>';
		$alt  = '';
		foreach ( $posts as $post ) {
			$title = trim( $post->post_title ) ? $post->post_title : __( '(no title)' );
			$alt   = ( 'alternate' == $alt ) ? '' : 'alternate';

			switch ( $post->post_status ) {
				case 'publish':
				case 'private':
					$stat = __( 'Published' );
					break;
				case 'future':
					$stat = __( 'Scheduled' );
					break;
				case 'pending':
					$stat = __( 'Pending Review' );
					break;
				case 'draft':
					$stat = __( 'Draft' );
					break;
			}

			if ( '0000-00-00 00:00:00' == $post->post_date ) {
				$time = '';
			} else {
				/* translators: Date format in table columns, see https://secure.php.net/date */
				$time = mysql2date( __( 'Y/m/d' ), $post->post_date );
			}

			$html .= '<tr class="' . trim( 'found-posts ' . $alt ) . '"><td class="found-radio"><input type="radio" id="found-' . $post->ID . '" name="found_post_id" value="' . esc_attr( $post->ID ) . '"></td>';
			$html .= '<td><label for="found-' . $post->ID . '">' . esc_html( $title ) . '</label></td><td class="no-break">' . esc_html( $post_types[ $post->post_type ]->labels->singular_name ) . '</td><td class="no-break">' . esc_html( $time ) . '</td><td class="no-break">' . esc_html( $stat ) . ' </td></tr>' . "\n\n";
		}

		$html .= '</tbody></table>';

		wp_send_json_success( $html );
	}

	/**
	 * Ajax handler to retrieve a permalink.
	 */
	public static function getPermalink() {
		check_ajax_referer( 'getpermalink', 'getpermalinknonce' );
		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		wp_die( get_preview_post_link( $post_id ) );
	}

	/**
	 * Ajax handler to retrieve a sample permalink.
	 */
	public static function samplePermalink() {
		check_ajax_referer( 'samplepermalink', 'samplepermalinknonce' );
		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$title   = isset( $_POST['new_title'] ) ? $_POST['new_title'] : '';
		$slug    = isset( $_POST['new_slug'] ) ? $_POST['new_slug'] : null;
		wp_die( get_sample_permalink_html( $post_id, $title, $slug ) );
	}

	public static function inlineSave() {
		global $mode;

		check_ajax_referer( 'inlineeditnonce', '_inline_edit' );

		if ( ! isset( $_POST['post_ID'] ) || ! (int) $_POST['post_ID'] ) {
			wp_die();
		}

		$post_ID = (int) $_POST['post_ID'];

		if ( 'page' == $_POST['post_type'] ) {
			if ( ! current_user_can( 'edit_page', $post_ID ) ) {
				wp_die( __( 'Sorry, you are not allowed to edit this page.' ) );
			}
		} else {
			if ( ! current_user_can( 'edit_post', $post_ID ) ) {
				wp_die( __( 'Sorry, you are not allowed to edit this post.' ) );
			}
		}

		$last = wp_check_post_lock( $post_ID );
		if ( $last ) {
			$last_user      = get_userdata( $last );
			$last_user_name = $last_user ? $last_user->display_name : __( 'Someone' );

			/* translators: %s: User's display name. */
			$msg_template = __( 'Saving is disabled: %s is currently editing this post.' );

			if ( $_POST['post_type'] == 'page' ) {
				/* translators: %s: User's display name. */
				$msg_template = __( 'Saving is disabled: %s is currently editing this page.' );
			}

			printf( $msg_template, esc_html( $last_user_name ) );
			wp_die();
		}

		$data = &$_POST;

		$post = get_post( $post_ID, ARRAY_A );

		// Since it's coming from the database.
		$post = wp_slash( $post );

		$data['content'] = $post['post_content'];
		$data['excerpt'] = $post['post_excerpt'];

		// Rename.
		$data['user_ID'] = get_current_user_id();

		if ( isset( $data['post_parent'] ) ) {
			$data['parent_id'] = $data['post_parent'];
		}

		// Status.
		if ( isset( $data['keep_private'] ) && 'private' == $data['keep_private'] ) {
			$data['visibility']  = 'private';
			$data['post_status'] = 'private';
		} else {
			$data['post_status'] = $data['_status'];
		}

		if ( empty( $data['comment_status'] ) ) {
			$data['comment_status'] = 'closed';
		}

		if ( empty( $data['ping_status'] ) ) {
			$data['ping_status'] = 'closed';
		}

		// Exclude terms from taxonomies that are not supposed to appear in Quick Edit.
		if ( ! empty( $data['tax_input'] ) ) {
			foreach ( $data['tax_input'] as $taxonomy => $terms ) {
				$tax_object = get_taxonomy( $taxonomy );
				/** This filter is documented in wp-admin/includes/class-wp-posts-list-table.php */
				if ( ! apply_filters( 'quick_edit_show_taxonomy', $tax_object->show_in_quick_edit, $taxonomy, $post['post_type'] ) ) {
					unset( $data['tax_input'][ $taxonomy ] );
				}
			}
		}

		// Hack: wp_unique_post_slug() doesn't work for drafts, so we will fake that our post is published.
		if ( ! empty( $data['post_name'] ) && in_array( $post['post_status'], array( 'draft', 'pending' ) ) ) {
			$post['post_status'] = 'publish';
			$data['post_name']   = wp_unique_post_slug( $data['post_name'], $post['ID'], $post['post_status'], $post['post_type'], $post['post_parent'] );
		}

		// Update the post.
		edit_post();

		$wp_list_table = _get_list_table( 'WP_Posts_List_Table', array( 'screen' => $_POST['screen'] ) );

		$mode = $_POST['post_view'] === 'excerpt' ? 'excerpt' : 'list';

		$level = 0;
		if ( is_post_type_hierarchical( $wp_list_table->screen->post_type ) ) {
			$request_post = array( get_post( $_POST['post_ID'] ) );
			$parent       = $request_post[0]->post_parent;

			while ( $parent > 0 ) {
				$parent_post = get_post( $parent );
				$parent      = $parent_post->post_parent;
				$level++;
			}
		}

		$wp_list_table->display_rows( array( get_post( $_POST['post_ID'] ) ), $level );

		wp_die();
	}

	/**
	 * Ajax handler for setting the featured image.
	 */
	public static function setThumbnail() {
		$json = ! empty( $_REQUEST['json'] ); // New-style request

		$post_ID = intval( $_POST['post_id'] );
		if ( ! current_user_can( 'edit_post', $post_ID ) ) {
			wp_die( -1 );
		}

		$thumbnail_id = intval( $_POST['thumbnail_id'] );

		if ( $json ) {
			check_ajax_referer( "update-post_$post_ID" );
		} else {
			check_ajax_referer( "set_post_thumbnail-$post_ID" );
		}

		if ( $thumbnail_id == '-1' ) {
			if ( delete_post_thumbnail( $post_ID ) ) {
				$return = _wp_post_thumbnail_html( null, $post_ID );
				$json ? wp_send_json_success( $return ) : wp_die( $return );
			} else {
				wp_die( 0 );
			}
		}

		if ( set_post_thumbnail( $post_ID, $thumbnail_id ) ) {
			$return = _wp_post_thumbnail_html( $thumbnail_id, $post_ID );
			$json ? wp_send_json_success( $return ) : wp_die( $return );
		}

		wp_die( 0 );
	}

	/**
	 * Ajax handler for retrieving HTML for the featured image.
	 */
	public static function getThumbnailHtml() {
		$post_ID = intval( $_POST['post_id'] );

		check_ajax_referer( "update-post_$post_ID" );

		if ( ! current_user_can( 'edit_post', $post_ID ) ) {
			wp_die( -1 );
		}

		$thumbnail_id = intval( $_POST['thumbnail_id'] );

		// For backward compatibility, -1 refers to no featured image.
		if ( -1 === $thumbnail_id ) {
			$thumbnail_id = null;
		}

		$return = _wp_post_thumbnail_html( $thumbnail_id, $post_ID );
		wp_send_json_success( $return );
	}

	/**
	 * Ajax handler for saving posts from the fullscreen editor.
	 *
	 * @deprecated 4.3.0
	 */
	public static function fullscreenSave() {
		$post_id = isset( $_POST['post_ID'] ) ? (int) $_POST['post_ID'] : 0;

		$post = null;

		if ( $post_id ) {
			$post = get_post( $post_id );
		}

		check_ajax_referer( 'update-post_' . $post_id, '_wpnonce' );

		$post_id = edit_post();

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error();
		}

		if ( $post ) {
			$last_date = mysql2date( __( 'F j, Y' ), $post->post_modified );
			$last_time = mysql2date( __( 'g:i a' ), $post->post_modified );
		} else {
			$last_date = date_i18n( __( 'F j, Y' ) );
			$last_time = date_i18n( __( 'g:i a' ) );
		}

		$last_id = get_post_meta( $post_id, '_edit_last', true );
		if ( $last_id ) {
			$last_user = get_userdata( $last_id );
			/* translators: 1: User's display name, 2: Date of last edit, 3: Time of last edit. */
			$last_edited = sprintf( __( 'Last edited by %1$s on %2$s at %3$s' ), esc_html( $last_user->display_name ), $last_date, $last_time );
		} else {
			/* translators: 1: Date of last edit, 2: Time of last edit. */
			$last_edited = sprintf( __( 'Last edited on %1$s at %2$s' ), $last_date, $last_time );
		}

		wp_send_json_success( array( 'last_edited' => $last_edited ) );
	}

	/**
	 * Ajax handler for removing a post lock.
	 */
	public static function removeLock() {
		if ( empty( $_POST['post_ID'] ) || empty( $_POST['active_post_lock'] ) ) {
			wp_die( 0 );
		}

		$post_id = (int) $_POST['post_ID'];
		$post    = get_post( $post_id );

		if ( ! $post ) {
			wp_die( 0 );
		}

		check_ajax_referer( 'update-post_' . $post_id );

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( -1 );
		}

		$active_lock = array_map( 'absint', explode( ':', $_POST['active_post_lock'] ) );

		if ( $active_lock[1] != get_current_user_id() ) {
			wp_die( 0 );
		}

		/**
		 * Filters the post lock window duration.
		 *
		 * @since 3.3.0
		 *
		 * @param int $interval The interval in seconds the post lock duration
		 *                      should last, plus 5 seconds. Default 150.
		 */
		$new_lock = ( time() - apply_filters( 'wp_check_post_lock_window', 150 ) + 5 ) . ':' . $active_lock[1];
		update_post_meta( $post_id, '_edit_lock', $new_lock, implode( ':', $active_lock ) );
		wp_die( 1 );
	}

	/**
	 * Ajax handler for getting revision diffs.
	 *
	 * @since 3.6.0
	 */
	public static function getRevisionDiffs() {
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
}
