<?php

namespace WP\Helper\Ajax;

class CommentHelper {
	/**
	 * Sends back current comment total and new page links if they need to be updated.
	 *
	 * Contrary to normal success Ajax response ("1"), die with time() on success.
	 *
	 * @access private
	 *
	 * @param int $comment_id
	 * @param int $delta
	 */
	public static function deleteCommentResponse( $comment_id, $delta = -1 ) {
		$total    = isset( $_POST['_total'] ) ? (int) $_POST['_total'] : 0;
		$per_page = isset( $_POST['_per_page'] ) ? (int) $_POST['_per_page'] : 0;
		$page     = isset( $_POST['_page'] ) ? (int) $_POST['_page'] : 0;
		$url      = isset( $_POST['_url'] ) ? esc_url_raw( $_POST['_url'] ) : '';

		// JS didn't send us everything we need to know. Just die with success message
		if ( ! $total || ! $per_page || ! $page || ! $url ) {
			$time           = time();
			$comment        = get_comment( $comment_id );
			$comment_status = '';
			$comment_link   = '';

			if ( $comment ) {
				$comment_status = $comment->comment_approved;
			}

			if ( 1 === (int) $comment_status ) {
				$comment_link = get_comment_link( $comment );
			}

			$counts = wp_count_comments();

			$x = new \WP_Ajax_Response(
				array(
					'what'         => 'comment',
					// Here for completeness - not used.
					'id'           => $comment_id,
					'supplemental' => array(
						'status'               => $comment_status,
						'postId'               => $comment ? $comment->comment_post_ID : '',
						'time'                 => $time,
						'in_moderation'        => $counts->moderated,
						'i18n_comments_text'   => sprintf(
						/* translators: %s: Number of comments. */
							_n( '%s Comment', '%s Comments', $counts->approved ),
							number_format_i18n( $counts->approved )
						),
						'i18n_moderation_text' => sprintf(
						/* translators: %s: Number of comments. */
							_n( '%s Comment in moderation', '%s Comments in moderation', $counts->moderated ),
							number_format_i18n( $counts->moderated )
						),
						'comment_link'         => $comment_link,
					),
				)
			);
			$x->send();
		}

		$total += $delta;
		if ( $total < 0 ) {
			$total = 0;
		}

		// Only do the expensive stuff on a page-break, and about 1 other time per page
		if ( 0 == $total % $per_page || 1 == mt_rand( 1, $per_page ) ) {
			$post_id = 0;
			// What type of comment count are we looking for?
			$status = 'all';
			$parsed = parse_url( $url );

			if ( isset( $parsed['query'] ) ) {
				parse_str( $parsed['query'], $query_vars );

				if ( ! empty( $query_vars['comment_status'] ) ) {
					$status = $query_vars['comment_status'];
				}

				if ( ! empty( $query_vars['p'] ) ) {
					$post_id = (int) $query_vars['p'];
				}

				if ( ! empty( $query_vars['comment_type'] ) ) {
					$type = $query_vars['comment_type'];
				}
			}

			if ( empty( $type ) ) {
				// Only use the comment count if not filtering by a comment_type.
				$comment_count = wp_count_comments( $post_id );

				// We're looking for a known type of comment count.
				if ( isset( $comment_count->$status ) ) {
					$total = $comment_count->$status;
				}
			}
			// Else use the decremented value from above.
		}

		// The time since the last comment count.
		$time    = time();
		$comment = get_comment( $comment_id );
		$counts  = wp_count_comments();

		$x = new \WP_Ajax_Response(
			array(
				'what'         => 'comment',
				'id'           => $comment_id,
				'supplemental' => array(
					'status'               => $comment ? $comment->comment_approved : '',
					'postId'               => $comment ? $comment->comment_post_ID : '',
					/* translators: %s: Number of comments. */
					'total_items_i18n'     => sprintf( _n( '%s item', '%s items', $total ), number_format_i18n( $total ) ),
					'total_pages'          => ceil( $total / $per_page ),
					'total_pages_i18n'     => number_format_i18n( ceil( $total / $per_page ) ),
					'total'                => $total,
					'time'                 => $time,
					'in_moderation'        => $counts->moderated,
					'i18n_moderation_text' => sprintf(
					/* translators: %s: Number of comments. */
						_n( '%s Comment in moderation', '%s Comments in moderation', $counts->moderated ),
						number_format_i18n( $counts->moderated )
					),
				),
			)
		);
		$x->send();
	}

	/**
	 * Ajax handler for deleting a comment.
	 */
	public static function deleteComment() {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

		$comment = get_comment( $id );

		if ( ! $comment ) {
			wp_die( time() );
		}

		if ( ! current_user_can( 'edit_comment', $comment->comment_ID ) ) {
			wp_die( -1 );
		}

		check_ajax_referer( "delete-comment_$id" );
		$status = wp_get_comment_status( $comment );
		$delta  = -1;

		if ( isset( $_POST['trash'] ) && 1 == $_POST['trash'] ) {
			if ( 'trash' == $status ) {
				wp_die( time() );
			}

			$r = wp_trash_comment( $comment );
		} elseif ( isset( $_POST['untrash'] ) && 1 == $_POST['untrash'] ) {
			if ( 'trash' != $status ) {
				wp_die( time() );
			}

			$r = wp_untrash_comment( $comment );

			if ( ! isset( $_POST['comment_status'] ) || $_POST['comment_status'] != 'trash' ) { // undo trash, not in trash
				$delta = 1;
			}
		} elseif ( isset( $_POST['spam'] ) && 1 == $_POST['spam'] ) {
			if ( 'spam' == $status ) {
				wp_die( time() );
			}

			$r = wp_spam_comment( $comment );
		} elseif ( isset( $_POST['unspam'] ) && 1 == $_POST['unspam'] ) {
			if ( 'spam' != $status ) {
				wp_die( time() );
			}

			$r = wp_unspam_comment( $comment );

			if ( ! isset( $_POST['comment_status'] ) || $_POST['comment_status'] != 'spam' ) { // undo spam, not in spam
				$delta = 1;
			}
		} elseif ( isset( $_POST['delete'] ) && 1 == $_POST['delete'] ) {
			$r = wp_delete_comment( $comment );
		} else {
			wp_die( -1 );
		}

		if ( $r ) { // Decide if we need to send back '1' or a more complicated response including page links and comment counts
			_wp_ajax_delete_comment_response( $comment->comment_ID, $delta );
		}

		wp_die( 0 );
	}

	/**
	 * Ajax handler to dim a comment.
	 *
	 * @since 3.1.0
	 */
	public static function dimComment() {
		$id      = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$comment = get_comment( $id );

		if ( ! $comment ) {
			$x = new \WP_Ajax_Response(
				array(
					'what' => 'comment',
					'id'   => new \WP_Error(
						'invalid_comment',
						/* translators: %d: Comment ID. */
						sprintf( __( 'Comment %d does not exist' ), $id )
					),
				)
			);
			$x->send();
		}

		if ( ! current_user_can( 'edit_comment', $comment->comment_ID ) && ! current_user_can( 'moderate_comments' ) ) {
			wp_die( -1 );
		}

		$current = wp_get_comment_status( $comment );

		if ( isset( $_POST['new'] ) && $_POST['new'] == $current ) {
			wp_die( time() );
		}

		check_ajax_referer( "approve-comment_$id" );

		if ( in_array( $current, array( 'unapproved', 'spam' ) ) ) {
			$result = wp_set_comment_status( $comment, 'approve', true );
		} else {
			$result = wp_set_comment_status( $comment, 'hold', true );
		}

		if ( is_wp_error( $result ) ) {
			$x = new \WP_Ajax_Response(
				array(
					'what' => 'comment',
					'id'   => $result,
				)
			);
			$x->send();
		}

		// Decide if we need to send back '1' or a more complicated response including page links and comment counts
		self::deleteCommentResponse( $comment->comment_ID );
		wp_die( 0 );
	}

	/**
	 * Ajax handler for getting comments.
	 *
	 * @global int           $post_id
	 *
	 * @param string $action Action to perform.
	 */
	public static function getComments( $action ) {
		global $post_id;

		if ( empty( $action ) ) {
			$action = 'get-comments';
		}

		check_ajax_referer( $action );

		if ( empty( $post_id ) && ! empty( $_REQUEST['p'] ) ) {
			$id = absint( $_REQUEST['p'] );
			if ( ! empty( $id ) ) {
				$post_id = $id;
			}
		}

		if ( empty( $post_id ) ) {
			wp_die( -1 );
		}

		$wp_list_table = _get_list_table( 'WP_Post_Comments_List_Table', array( 'screen' => 'edit-comments' ) );

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( -1 );
		}

		$wp_list_table->prepare_items();

		if ( ! $wp_list_table->has_items() ) {
			wp_die( 1 );
		}

		$x = new \WP_Ajax_Response();

		ob_start();
		foreach ( $wp_list_table->items as $comment ) {
			if ( ! current_user_can( 'edit_comment', $comment->comment_ID ) && 0 === $comment->comment_approved ) {
				continue;
			}
			get_comment( $comment );
			$wp_list_table->single_row( $comment );
		}
		$comment_list_item = ob_get_clean();

		$x->add(
			array(
				'what' => 'comments',
				'data' => $comment_list_item,
			)
		);

		$x->send();
	}

	/**
	 * Ajax handler for replying to a comment.
	 *
	 * @param string $action Action to perform.
	 */
	public static function replyToComment( $action ) {
		if ( empty( $action ) ) {
			$action = 'replyto-comment';
		}

		check_ajax_referer( $action, '_ajax_nonce-replyto-comment' );

		$comment_post_ID = (int) $_POST['comment_post_ID'];
		$post            = get_post( $comment_post_ID );

		if ( ! $post ) {
			wp_die( -1 );
		}

		if ( ! current_user_can( 'edit_post', $comment_post_ID ) ) {
			wp_die( -1 );
		}

		if ( empty( $post->post_status ) ) {
			wp_die( 1 );
		} elseif ( in_array( $post->post_status, array( 'draft', 'pending', 'trash' ) ) ) {
			wp_die( __( 'ERROR: you are replying to a comment on a draft post.' ) );
		}

		$user = wp_get_current_user();

		if ( $user->exists() ) {
			$user_ID              = $user->ID;
			$comment_author       = wp_slash( $user->display_name );
			$comment_author_email = wp_slash( $user->user_email );
			$comment_author_url   = wp_slash( $user->user_url );
			$comment_content      = trim( $_POST['content'] );
			$comment_type         = isset( $_POST['comment_type'] ) ? trim( $_POST['comment_type'] ) : '';

			if ( current_user_can( 'unfiltered_html' ) ) {
				if ( ! isset( $_POST['_wp_unfiltered_html_comment'] ) ) {
					$_POST['_wp_unfiltered_html_comment'] = '';
				}

				if ( wp_create_nonce( 'unfiltered-html-comment' ) != $_POST['_wp_unfiltered_html_comment'] ) {
					kses_remove_filters(); // start with a clean slate
					kses_init_filters(); // set up the filters
					remove_filter( 'pre_comment_content', 'wp_filter_post_kses' );
					add_filter( 'pre_comment_content', 'wp_filter_kses' );
				}
			}
		} else {
			wp_die( __( 'Sorry, you must be logged in to reply to a comment.' ) );
		}

		if ( '' == $comment_content ) {
			wp_die( __( 'ERROR: please type a comment.' ) );
		}

		$comment_parent = 0;

		if ( isset( $_POST['comment_ID'] ) ) {
			$comment_parent = absint( $_POST['comment_ID'] );
		}

		$comment_auto_approved = false;
		$commentdata           = compact( 'comment_post_ID', 'comment_author', 'comment_author_email', 'comment_author_url', 'comment_content', 'comment_type', 'comment_parent', 'user_ID' );

		// Automatically approve parent comment.
		if ( ! empty( $_POST['approve_parent'] ) ) {
			$parent = get_comment( $comment_parent );

			if ( $parent && $parent->comment_approved === '0' && $parent->comment_post_ID == $comment_post_ID ) {
				if ( ! current_user_can( 'edit_comment', $parent->comment_ID ) ) {
					wp_die( -1 );
				}

				if ( wp_set_comment_status( $parent, 'approve' ) ) {
					$comment_auto_approved = true;
				}
			}
		}

		$comment_id = wp_new_comment( $commentdata );

		if ( is_wp_error( $comment_id ) ) {
			wp_die( $comment_id->get_error_message() );
		}

		$comment = get_comment( $comment_id );

		if ( ! $comment ) {
			wp_die( 1 );
		}

		$position = ( isset( $_POST['position'] ) && (int) $_POST['position'] ) ? (int) $_POST['position'] : '-1';

		ob_start();
		if ( isset( $_REQUEST['mode'] ) && 'dashboard' == $_REQUEST['mode'] ) {
			require_once( ABSPATH . 'wp-admin/includes/dashboard.php' );
			_wp_dashboard_recent_comments_row( $comment );
		} else {
			if ( isset( $_REQUEST['mode'] ) && 'single' == $_REQUEST['mode'] ) {
				$wp_list_table = _get_list_table( 'WP_Post_Comments_List_Table', array( 'screen' => 'edit-comments' ) );
			} else {
				$wp_list_table = _get_list_table( 'WP_Comments_List_Table', array( 'screen' => 'edit-comments' ) );
			}
			$wp_list_table->single_row( $comment );
		}
		$comment_list_item = ob_get_clean();

		$response = array(
			'what'     => 'comment',
			'id'       => $comment->comment_ID,
			'data'     => $comment_list_item,
			'position' => $position,
		);

		$counts                   = wp_count_comments();
		$response['supplemental'] = array(
			'in_moderation'        => $counts->moderated,
			'i18n_comments_text'   => sprintf(
			/* translators: %s: Number of comments. */
				_n( '%s Comment', '%s Comments', $counts->approved ),
				number_format_i18n( $counts->approved )
			),
			'i18n_moderation_text' => sprintf(
			/* translators: %s: Number of comments. */
				_n( '%s Comment in moderation', '%s Comments in moderation', $counts->moderated ),
				number_format_i18n( $counts->moderated )
			),
		);

		if ( $comment_auto_approved ) {
			$response['supplemental']['parent_approved'] = $parent->comment_ID;
			$response['supplemental']['parent_post_id']  = $parent->comment_post_ID;
		}

		$x = new \WP_Ajax_Response();
		$x->add( $response );
		$x->send();
	}

	/**
	 * Ajax handler for editing a comment.
	 */
	public static function editComment() {
		check_ajax_referer( 'replyto-comment', '_ajax_nonce-replyto-comment' );

		$comment_id = (int) $_POST['comment_ID'];

		if ( ! current_user_can( 'edit_comment', $comment_id ) ) {
			wp_die( -1 );
		}

		if ( '' == $_POST['content'] ) {
			wp_die( __( 'ERROR: please type a comment.' ) );
		}

		if ( isset( $_POST['status'] ) ) {
			$_POST['comment_status'] = $_POST['status'];
		}
		edit_comment();

		$position      = ( isset( $_POST['position'] ) && (int) $_POST['position'] ) ? (int) $_POST['position'] : '-1';
		$checkbox      = ( isset( $_POST['checkbox'] ) && true == $_POST['checkbox'] ) ? 1 : 0;
		$wp_list_table = _get_list_table( $checkbox ? 'WP_Comments_List_Table' : 'WP_Post_Comments_List_Table', array( 'screen' => 'edit-comments' ) );

		$comment = get_comment( $comment_id );

		if ( empty( $comment->comment_ID ) ) {
			wp_die( -1 );
		}

		ob_start();
		$wp_list_table->single_row( $comment );
		$comment_list_item = ob_get_clean();

		$x = new \WP_Ajax_Response();

		$x->add(
			array(
				'what'     => 'edit_comment',
				'id'       => $comment->comment_ID,
				'data'     => $comment_list_item,
				'position' => $position,
			)
		);

		$x->send();
	}
}
