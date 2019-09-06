<?php

namespace WP\Helper\Ajax;

class MetaHelper {
	/**
	 * Ajax handler for deleting meta.
	 */
	public static function deleteMeta() {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

		check_ajax_referer( "delete-meta_$id" );
		$meta = get_metadata_by_mid( 'post', $id );

		if ( ! $meta ) {
			wp_die( 1 );
		}

		if ( is_protected_meta( $meta->meta_key, 'post' ) || ! current_user_can( 'delete_post_meta', $meta->post_id, $meta->meta_key ) ) {
			wp_die( -1 );
		}

		if ( delete_meta( $meta->meta_id ) ) {
			wp_die( 1 );
		}

		wp_die( 0 );
	}

	/**
	 * Ajax handler for adding meta.
	 */
	public static function addMeta() {
		check_ajax_referer( 'add-meta', '_ajax_nonce-add-meta' );
		$c    = 0;
		$pid  = (int) $_POST['post_id'];
		$post = get_post( $pid );

		if ( isset( $_POST['metakeyselect'] ) || isset( $_POST['metakeyinput'] ) ) {
			if ( ! current_user_can( 'edit_post', $pid ) ) {
				wp_die( -1 );
			}

			if ( isset( $_POST['metakeyselect'] ) && '#NONE#' == $_POST['metakeyselect'] && empty( $_POST['metakeyinput'] ) ) {
				wp_die( 1 );
			}

			// If the post is an autodraft, save the post as a draft and then attempt to save the meta.
			if ( $post->post_status == 'auto-draft' ) {
				$post_data                = array();
				$post_data['action']      = 'draft'; // Warning fix
				$post_data['post_ID']     = $pid;
				$post_data['post_type']   = $post->post_type;
				$post_data['post_status'] = 'draft';
				$now                      = time();
				/* translators: 1: Post creation date, 2: Post creation time. */
				$post_data['post_title'] = sprintf( __( 'Draft created on %1$s at %2$s' ), gmdate( __( 'F j, Y' ), $now ), gmdate( __( 'g:i a' ), $now ) );

				$pid = edit_post( $post_data );

				if ( $pid ) {
					if ( is_wp_error( $pid ) ) {
						$x = new \WP_Ajax_Response(
							array(
								'what' => 'meta',
								'data' => $pid,
							)
						);
						$x->send();
					}

					$mid = add_meta( $pid );
					if ( ! $mid ) {
						wp_die( __( 'Please provide a custom field value.' ) );
					}
				} else {
					wp_die( 0 );
				}
			} else {
				$mid = add_meta( $pid );
				if ( ! $mid ) {
					wp_die( __( 'Please provide a custom field value.' ) );
				}
			}

			$meta = get_metadata_by_mid( 'post', $mid );
			$pid  = (int) $meta->post_id;
			$meta = get_object_vars( $meta );

			$x = new \WP_Ajax_Response(
				array(
					'what'         => 'meta',
					'id'           => $mid,
					'data'         => _list_meta_row( $meta, $c ),
					'position'     => 1,
					'supplemental' => array( 'postid' => $pid ),
				)
			);
		} else { // Update?
			$mid   = (int) key( $_POST['meta'] );
			$key   = wp_unslash( $_POST['meta'][ $mid ]['key'] );
			$value = wp_unslash( $_POST['meta'][ $mid ]['value'] );

			if ( '' == trim( $key ) ) {
				wp_die( __( 'Please provide a custom field name.' ) );
			}

			$meta = get_metadata_by_mid( 'post', $mid );

			if ( ! $meta ) {
				wp_die( 0 ); // if meta doesn't exist
			}

			if (
				is_protected_meta( $meta->meta_key, 'post' ) || is_protected_meta( $key, 'post' ) ||
				! current_user_can( 'edit_post_meta', $meta->post_id, $meta->meta_key ) ||
				! current_user_can( 'edit_post_meta', $meta->post_id, $key )
			) {
				wp_die( -1 );
			}

			if ( $meta->meta_value != $value || $meta->meta_key != $key ) {
				$u = update_metadata_by_mid( 'post', $mid, $value, $key );
				if ( ! $u ) {
					wp_die( 0 ); // We know meta exists; we also know it's unchanged (or DB error, in which case there are bigger problems).
				}
			}

			$x = new \WP_Ajax_Response(
				array(
					'what'         => 'meta',
					'id'           => $mid,
					'old_id'       => $mid,
					'data'         => _list_meta_row(
						array(
							'meta_key'   => $key,
							'meta_value' => $value,
							'meta_id'    => $mid,
						),
						$c
					),
					'position'     => 0,
					'supplemental' => array( 'postid' => $meta->post_id ),
				)
			);
		}
		$x->send();
	}
}
