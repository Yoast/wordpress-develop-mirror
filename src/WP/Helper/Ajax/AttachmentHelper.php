<?php

namespace WP\Helper\Ajax;

class AttachmentHelper {
	/**
	 * Ajax handler for uploading attachments.
	 */
	public static function upload() {
		check_ajax_referer( 'media-form' );
		/*
		 * This function does not use wp_send_json_success() / wp_send_json_error()
		 * as the html4 Plupload handler requires a text/html content-type for older IE.
		 * See https://core.trac.wordpress.org/ticket/31037
		 */

		if ( ! current_user_can( 'upload_files' ) ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'data'    => array(
						'message'  => __( 'Sorry, you are not allowed to upload files.' ),
						'filename' => esc_html( $_FILES['async-upload']['name'] ),
					),
				)
			);

			wp_die();
		}

		if ( isset( $_REQUEST['post_id'] ) ) {
			$post_id = $_REQUEST['post_id'];

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				echo wp_json_encode(
					array(
						'success' => false,
						'data'    => array(
							'message'  => __( 'Sorry, you are not allowed to attach files to this post.' ),
							'filename' => esc_html( $_FILES['async-upload']['name'] ),
						),
					)
				);

				wp_die();
			}
		} else {
			$post_id = null;
		}

		$post_data = ! empty( $_REQUEST['post_data'] ) ? _wp_get_allowed_postdata( _wp_translate_postdata( false, (array) $_REQUEST['post_data'] ) ) : array();

		if ( is_wp_error( $post_data ) ) {
			wp_die( $post_data->get_error_message() );
		}

		// If the context is custom header or background, make sure the uploaded file is an image.
		if ( isset( $post_data['context'] ) && in_array( $post_data['context'], array( 'custom-header', 'custom-background' ) ) ) {
			$wp_filetype = wp_check_filetype_and_ext( $_FILES['async-upload']['tmp_name'], $_FILES['async-upload']['name'] );

			if ( ! wp_match_mime_types( 'image', $wp_filetype['type'] ) ) {
				echo wp_json_encode(
					array(
						'success' => false,
						'data'    => array(
							'message'  => __( 'The uploaded file is not a valid image. Please try again.' ),
							'filename' => esc_html( $_FILES['async-upload']['name'] ),
						),
					)
				);

				wp_die();
			}
		}

		$attachment_id = media_handle_upload( 'async-upload', $post_id, $post_data );

		if ( is_wp_error( $attachment_id ) ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'data'    => array(
						'message'  => $attachment_id->get_error_message(),
						'filename' => esc_html( $_FILES['async-upload']['name'] ),
					),
				)
			);

			wp_die();
		}

		if ( isset( $post_data['context'] ) && isset( $post_data['theme'] ) ) {
			if ( 'custom-background' === $post_data['context'] ) {
				update_post_meta( $attachment_id, '_wp_attachment_is_custom_background', $post_data['theme'] );
			}

			if ( 'custom-header' === $post_data['context'] ) {
				update_post_meta( $attachment_id, '_wp_attachment_is_custom_header', $post_data['theme'] );
			}
		}

		$attachment = wp_prepare_attachment_for_js( $attachment_id );
		if ( ! $attachment ) {
			wp_die();
		}

		echo wp_json_encode(
			array(
				'success' => true,
				'data'    => $attachment,
			)
		);

		wp_die();
	}

	/**
	 * Ajax handler for setting the featured image for an attachment.
	 *
	 * @see set_post_thumbnail()
	 */
	public static function setThumbnail() {
		if ( empty( $_POST['urls'] ) || ! is_array( $_POST['urls'] ) ) {
			wp_send_json_error();
		}

		$thumbnail_id = (int) $_POST['thumbnail_id'];
		if ( empty( $thumbnail_id ) ) {
			wp_send_json_error();
		}

		$post_ids = array();
		// For each URL, try to find its corresponding post ID.
		foreach ( $_POST['urls'] as $url ) {
			$post_id = attachment_url_to_postid( $url );
			if ( ! empty( $post_id ) ) {
				$post_ids[] = $post_id;
			}
		}

		if ( empty( $post_ids ) ) {
			wp_send_json_error();
		}

		$success = 0;
		// For each found attachment, set its thumbnail.
		foreach ( $post_ids as $post_id ) {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}

			if ( set_post_thumbnail( $post_id, $thumbnail_id ) ) {
				$success++;
			}
		}

		if ( 0 === $success ) {
			wp_send_json_error();
		} else {
			wp_send_json_success();
		}

		wp_send_json_error();
	}

	/**
	 * Ajax handler for getting an attachment.
	 */
	public static function getOne() {
		if ( ! isset( $_REQUEST['id'] ) ) {
			wp_send_json_error();
		}

		$id = absint( $_REQUEST['id'] );
		if ( ! $id ) {
			wp_send_json_error();
		}

		$post = get_post( $id );
		if ( ! $post ) {
			wp_send_json_error();
		}

		if ( 'attachment' != $post->post_type ) {
			wp_send_json_error();
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error();
		}

		$attachment = wp_prepare_attachment_for_js( $id );
		if ( ! $attachment ) {
			wp_send_json_error();
		}

		wp_send_json_success( $attachment );
	}

	/**
	 * Ajax handler for querying attachments.
	 */
	public static function queryAll() {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error();
		}

		$query = isset( $_REQUEST['query'] ) ? (array) $_REQUEST['query'] : array();
		$keys  = array(
			's',
			'order',
			'orderby',
			'posts_per_page',
			'paged',
			'post_mime_type',
			'post_parent',
			'author',
			'post__in',
			'post__not_in',
			'year',
			'monthnum',
		);

		foreach ( get_taxonomies_for_attachments( 'objects' ) as $t ) {
			if ( $t->query_var && isset( $query[ $t->query_var ] ) ) {
				$keys[] = $t->query_var;
			}
		}

		$query              = array_intersect_key( $query, array_flip( $keys ) );
		$query['post_type'] = 'attachment';

		if (
			MEDIA_TRASH &&
			! empty( $_REQUEST['query']['post_status'] ) &&
			'trash' === $_REQUEST['query']['post_status']
		) {
			$query['post_status'] = 'trash';
		} else {
			$query['post_status'] = 'inherit';
		}

		if ( current_user_can( get_post_type_object( 'attachment' )->cap->read_private_posts ) ) {
			$query['post_status'] .= ',private';
		}

		// Filter query clauses to include filenames.
		if ( isset( $query['s'] ) ) {
			add_filter( 'posts_clauses', '_filter_query_attachment_filenames' );
		}

		/**
		 * Filters the arguments passed to WP_Query during an Ajax
		 * call for querying attachments.
		 *
		 * @since 3.7.0
		 *
		 * @see WP_Query::parse_query()
		 *
		 * @param array $query An array of query variables.
		 */
		$query = apply_filters( 'ajax_query_attachments_args', $query );
		$query = new WP_Query( $query );

		$posts = array_map( 'wp_prepare_attachment_for_js', $query->posts );
		$posts = array_filter( $posts );

		wp_send_json_success( $posts );
	}

	/**
	 * Ajax handler for updating attachment attributes.
	 */
	public static function save() {
		if ( ! isset( $_REQUEST['id'] ) || ! isset( $_REQUEST['changes'] ) ) {
			wp_send_json_error();
		}

		$id = absint( $_REQUEST['id'] );
		if ( ! $id ) {
			wp_send_json_error();
		}

		check_ajax_referer( 'update-post_' . $id, 'nonce' );

		if ( ! current_user_can( 'edit_post', $id ) ) {
			wp_send_json_error();
		}

		$changes = $_REQUEST['changes'];
		$post    = get_post( $id, ARRAY_A );

		if ( 'attachment' != $post['post_type'] ) {
			wp_send_json_error();
		}

		if ( isset( $changes['parent'] ) ) {
			$post['post_parent'] = $changes['parent'];
		}

		if ( isset( $changes['title'] ) ) {
			$post['post_title'] = $changes['title'];
		}

		if ( isset( $changes['caption'] ) ) {
			$post['post_excerpt'] = $changes['caption'];
		}

		if ( isset( $changes['description'] ) ) {
			$post['post_content'] = $changes['description'];
		}

		if ( MEDIA_TRASH && isset( $changes['status'] ) ) {
			$post['post_status'] = $changes['status'];
		}

		if ( isset( $changes['alt'] ) ) {
			$alt = wp_unslash( $changes['alt'] );
			if ( $alt != get_post_meta( $id, '_wp_attachment_image_alt', true ) ) {
				$alt = wp_strip_all_tags( $alt, true );
				update_post_meta( $id, '_wp_attachment_image_alt', wp_slash( $alt ) );
			}
		}

		if ( wp_attachment_is( 'audio', $post['ID'] ) ) {
			$changed = false;
			$id3data = wp_get_attachment_metadata( $post['ID'] );

			if ( ! is_array( $id3data ) ) {
				$changed = true;
				$id3data = array();
			}

			foreach ( wp_get_attachment_id3_keys( (object) $post, 'edit' ) as $key => $label ) {
				if ( isset( $changes[ $key ] ) ) {
					$changed         = true;
					$id3data[ $key ] = sanitize_text_field( wp_unslash( $changes[ $key ] ) );
				}
			}

			if ( $changed ) {
				wp_update_attachment_metadata( $id, $id3data );
			}
		}

		if ( MEDIA_TRASH && isset( $changes['status'] ) && 'trash' === $changes['status'] ) {
			wp_delete_post( $id );
		} else {
			wp_update_post( $post );
		}

		wp_send_json_success();
	}

	/**
	 * Ajax handler for saving backward compatible attachment attributes.
	 */
	public static function saveCompat() {
		if ( ! isset( $_REQUEST['id'] ) ) {
			wp_send_json_error();
		}

		$id = absint( $_REQUEST['id'] );
		if ( ! $id ) {
			wp_send_json_error();
		}

		if ( empty( $_REQUEST['attachments'] ) || empty( $_REQUEST['attachments'][ $id ] ) ) {
			wp_send_json_error();
		}

		$attachment_data = $_REQUEST['attachments'][ $id ];

		check_ajax_referer( 'update-post_' . $id, 'nonce' );

		if ( ! current_user_can( 'edit_post', $id ) ) {
			wp_send_json_error();
		}

		$post = get_post( $id, ARRAY_A );

		if ( 'attachment' != $post['post_type'] ) {
			wp_send_json_error();
		}

		/** This filter is documented in wp-admin/includes/media.php */
		$post = apply_filters( 'attachment_fields_to_save', $post, $attachment_data );

		if ( isset( $post['errors'] ) ) {
			$errors = $post['errors']; // @todo return me and display me!
			unset( $post['errors'] );
		}

		wp_update_post( $post );

		foreach ( get_attachment_taxonomies( $post ) as $taxonomy ) {
			if ( isset( $attachment_data[ $taxonomy ] ) ) {
				wp_set_object_terms( $id, array_map( 'trim', preg_split( '/,+/', $attachment_data[ $taxonomy ] ) ), $taxonomy, false );
			}
		}

		$attachment = wp_prepare_attachment_for_js( $id );

		if ( ! $attachment ) {
			wp_send_json_error();
		}

		wp_send_json_success( $attachment );
	}

	/**
	 * Ajax handler for saving the attachment order.
	 */
	public static function saveOrder() {
		if ( ! isset( $_REQUEST['post_id'] ) ) {
			wp_send_json_error();
		}

		$post_id = absint( $_REQUEST['post_id'] );
		if ( ! $post_id ) {
			wp_send_json_error();
		}

		if ( empty( $_REQUEST['attachments'] ) ) {
			wp_send_json_error();
		}

		check_ajax_referer( 'update-post_' . $post_id, 'nonce' );

		$attachments = $_REQUEST['attachments'];

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error();
		}

		foreach ( $attachments as $attachment_id => $menu_order ) {
			if ( ! current_user_can( 'edit_post', $attachment_id ) ) {
				continue;
			}

			$attachment = get_post( $attachment_id );

			if ( ! $attachment ) {
				continue;
			}

			if ( 'attachment' != $attachment->post_type ) {
				continue;
			}

			wp_update_post(
				array(
					'ID'         => $attachment_id,
					'menu_order' => $menu_order,
				)
			);
		}

		wp_send_json_success();
	}

	/**
	 * Ajax handler for sending an attachment to the editor.
	 *
	 * Generates the HTML to send an attachment to the editor.
	 * Backward compatible with the {@see 'media_send_to_editor'} filter
	 * and the chain of filters that follow.
	 */
	public static function sendToEditor() {
		check_ajax_referer( 'media-send-to-editor', 'nonce' );

		$attachment = wp_unslash( $_POST['attachment'] );

		$id = intval( $attachment['id'] );

		$post = get_post( $id );
		if ( ! $post ) {
			wp_send_json_error();
		}

		if ( 'attachment' != $post->post_type ) {
			wp_send_json_error();
		}

		if ( current_user_can( 'edit_post', $id ) ) {
			// If this attachment is unattached, attach it. Primarily a back compat thing.
			$insert_into_post_id = intval( $_POST['post_id'] );

			if ( 0 == $post->post_parent && $insert_into_post_id ) {
				wp_update_post(
					array(
						'ID'          => $id,
						'post_parent' => $insert_into_post_id,
					)
				);
			}
		}

		$url = empty( $attachment['url'] ) ? '' : $attachment['url'];
		$rel = ( strpos( $url, 'attachment_id' ) || get_attachment_link( $id ) == $url );

		remove_filter( 'media_send_to_editor', 'image_media_send_to_editor' );

		if ( 'image' === substr( $post->post_mime_type, 0, 5 ) ) {
			$align = isset( $attachment['align'] ) ? $attachment['align'] : 'none';
			$size  = isset( $attachment['image-size'] ) ? $attachment['image-size'] : 'medium';
			$alt   = isset( $attachment['image_alt'] ) ? $attachment['image_alt'] : '';

			// No whitespace-only captions.
			$caption = isset( $attachment['post_excerpt'] ) ? $attachment['post_excerpt'] : '';
			if ( '' === trim( $caption ) ) {
				$caption = '';
			}

			$title = ''; // We no longer insert title tags into <img> tags, as they are redundant.
			$html  = get_image_send_to_editor( $id, $caption, $title, $align, $url, $rel, $size, $alt );
		} elseif ( wp_attachment_is( 'video', $post ) || wp_attachment_is( 'audio', $post ) ) {
			$html = stripslashes_deep( $_POST['html'] );
		} else {
			$html = isset( $attachment['post_title'] ) ? $attachment['post_title'] : '';
			$rel  = $rel ? ' rel="attachment wp-att-' . $id . '"' : ''; // Hard-coded string, $id is already sanitized

			if ( ! empty( $url ) ) {
				$html = '<a href="' . esc_url( $url ) . '"' . $rel . '>' . $html . '</a>';
			}
		}

		/** This filter is documented in wp-admin/includes/media.php */
		$html = apply_filters( 'media_send_to_editor', $html, $id, $attachment );

		wp_send_json_success( $html );
	}
}
