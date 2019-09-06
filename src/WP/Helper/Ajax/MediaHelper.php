<?php

namespace WP\Helper\Ajax;

class MediaHelper {
	/**
	 * Ajax handler for image editor previews.
	 */
	public static function imageEditPreview() {
		$post_id = intval( $_GET['postid'] );
		if ( empty( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( -1 );
		}

		check_ajax_referer( "image_editor-$post_id" );

		include_once( ABSPATH . 'wp-admin/includes/image-edit.php' );

		if ( ! stream_preview_image( $post_id ) ) {
			wp_die( -1 );
		}

		wp_die();
	}

	/**
	 * Ajax handler for creating missing image sub-sizes for just uploaded images.
	 */
	public static function createImageSubsizes() {
		check_ajax_referer( 'media-form' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, you are not allowed to upload files.' ) ) );
		}

		// Using Plupload `file.id` as ref.
		if ( ! empty( $_POST['_wp_temp_image_ref'] ) ) {
			$image_ref = preg_replace( '/[^a-zA-Z0-9_]/', '', $_POST['_wp_temp_image_ref'] );
		} else {
			wp_send_json_error( array( 'message' => __( 'Invalid file reference.' ) ) );
		}

		// Uploading of images usually fails while creating the sub-sizes, either because of a timeout or out of memory.
		// At this point the file has been uploaded and an attachment post created, but because of the PHP fatal error
		// the cliend doesn't know the attachment ID yet.
		// To be able to find the new attachment_id in these cases we temporarily store an upload reference sent by the client
		// in the original upload request. It is used to save a transient with the attachment_id as value.
		// That reference currently is Plupload's `file.id` but can be any sufficiently random alpha-numeric string.
		$attachment_id = get_transient( '_wp_temp_image_ref:' . $image_ref );

		if ( empty( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Upload failed. Please reload and try again.' ) ) );
		}

		if ( ! empty( $_POST['_wp_upload_failed_cleanup'] ) ) {
			// Upload failed. Cleanup.
			if ( wp_attachment_is_image( $attachment_id ) ) {
				$attachment = get_post( $attachment_id );

				// Posted at most 10 min ago.
				if ( $attachment && ( time() - strtotime( $attachment->post_date_gmt ) < 600 ) ) {
					/**
					 * Runs when an image upload fails during the post-processing phase,
					 * and the newly created attachment post is about to be deleted.
					 *
					 * @since 5.3.0
					 *
					 * @param int $attachment_id The attachment post ID.
					 */
					do_action( 'wp_upload_failed_cleanup', $attachment_id );

					wp_delete_attachment( $attachment_id, true );
					wp_send_json_success();
				}
			}
		}

		// This can still be pretty slow and cause timeout or out of memory errors.
		// The js that handles the response would need to also handle HTTP 500 errors.
		wp_update_image_subsizes( $attachment_id );

		if ( ! empty( $_POST['_legasy_support'] ) ) {
			// The old (inline) uploader. Only needs the attachment_id.
			$response = array( 'id' => $attachment_id );
		} else {
			// Media modal and Media Library grid view.
			$response = wp_prepare_attachment_for_js( $attachment_id );

			if ( ! $response ) {
				wp_send_json_error( array( 'message' => __( 'Upload failed.' ) ) );
			}
		}

		// At this point the image has been uploaded successfully.
		delete_transient( '_wp_temp_image_ref:' . $image_ref );

		wp_send_json_success( $response );
	}

	/**
	 * Ajax handler for image editing.
	 */
	public static function imageEditor() {
		$attachment_id = intval( $_POST['postid'] );

		if ( empty( $attachment_id ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
			wp_die( -1 );
		}

		check_ajax_referer( "image_editor-$attachment_id" );
		include_once( ABSPATH . 'wp-admin/includes/image-edit.php' );

		$msg = false;
		switch ( $_POST['do'] ) {
			case 'save':
				$msg = wp_save_image( $attachment_id );
				$msg = wp_json_encode( $msg );
				wp_die( $msg );
				break;
			case 'scale':
				$msg = wp_save_image( $attachment_id );
				break;
			case 'restore':
				$msg = wp_restore_image( $attachment_id );
				break;
		}

		wp_image_editor( $attachment_id, $msg );
		wp_die();
	}

	/**
	 * @global \WP_Post    $post       Global post object.
	 * @global \WP_Scripts $wp_scripts
	 */
	public static function parseShortcode() {
		global $post, $wp_scripts;

		if ( empty( $_POST['shortcode'] ) ) {
			wp_send_json_error();
		}

		$shortcode = wp_unslash( $_POST['shortcode'] );

		if ( ! empty( $_POST['post_ID'] ) ) {
			$post = get_post( (int) $_POST['post_ID'] );
		}

		// the embed shortcode requires a post
		if ( ! $post || ! current_user_can( 'edit_post', $post->ID ) ) {
			if ( 'embed' === $shortcode ) {
				wp_send_json_error();
			}
		} else {
			setup_postdata( $post );
		}

		$parsed = do_shortcode( $shortcode );

		if ( empty( $parsed ) ) {
			wp_send_json_error(
				array(
					'type'    => 'no-items',
					'message' => __( 'No items found.' ),
				)
			);
		}

		$head   = '';
		$styles = wpview_media_sandbox_styles();

		foreach ( $styles as $style ) {
			$head .= '<link type="text/css" rel="stylesheet" href="' . $style . '">';
		}

		if ( ! empty( $wp_scripts ) ) {
			$wp_scripts->done = array();
		}

		ob_start();

		echo $parsed;

		if ( 'playlist' === $_REQUEST['type'] ) {
			wp_underscore_playlist_templates();

			wp_print_scripts( 'wp-playlist' );
		} else {
			wp_print_scripts( array( 'mediaelement-vimeo', 'wp-mediaelement' ) );
		}

		wp_send_json_success(
			array(
				'head' => $head,
				'body' => ob_get_clean(),
			)
		);
	}

	/**
	 * Ajax handler for cropping an image.
	 */
	public static function cropImage() {
		$attachment_id = absint( $_POST['id'] );

		check_ajax_referer( 'image_editor-' . $attachment_id, 'nonce' );

		if ( empty( $attachment_id ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
			wp_send_json_error();
		}

		$context = str_replace( '_', '-', $_POST['context'] );
		$data    = array_map( 'absint', $_POST['cropDetails'] );
		$cropped = wp_crop_image( $attachment_id, $data['x1'], $data['y1'], $data['width'], $data['height'], $data['dst_width'], $data['dst_height'] );

		if ( ! $cropped || is_wp_error( $cropped ) ) {
			wp_send_json_error( array( 'message' => __( 'Image could not be processed.' ) ) );
		}

		switch ( $context ) {
			case 'site-icon':
				require_once ABSPATH . 'wp-admin/includes/class-wp-site-icon.php';
				$wp_site_icon = new WP_Site_Icon();

				// Skip creating a new attachment if the attachment is a Site Icon.
				if ( get_post_meta( $attachment_id, '_wp_attachment_context', true ) == $context ) {

					// Delete the temporary cropped file, we don't need it.
					wp_delete_file( $cropped );

					// Additional sizes in wp_prepare_attachment_for_js().
					add_filter( 'image_size_names_choose', array( $wp_site_icon, 'additional_sizes' ) );
					break;
				}

				/** This filter is documented in wp-admin/includes/class-custom-image-header.php */
				$cropped = apply_filters( 'wp_create_file_in_uploads', $cropped, $attachment_id ); // For replication.
				$object  = $wp_site_icon->create_attachment_object( $cropped, $attachment_id );
				unset( $object['ID'] );

				// Update the attachment.
				add_filter( 'intermediate_image_sizes_advanced', array( $wp_site_icon, 'additional_sizes' ) );
				$attachment_id = $wp_site_icon->insert_attachment( $object, $cropped );
				remove_filter( 'intermediate_image_sizes_advanced', array( $wp_site_icon, 'additional_sizes' ) );

				// Additional sizes in wp_prepare_attachment_for_js().
				add_filter( 'image_size_names_choose', array( $wp_site_icon, 'additional_sizes' ) );
				break;

			default:
				/**
				 * Fires before a cropped image is saved.
				 *
				 * Allows to add filters to modify the way a cropped image is saved.
				 *
				 * @since 4.3.0
				 *
				 * @param string $context       The Customizer control requesting the cropped image.
				 * @param int    $attachment_id The attachment ID of the original image.
				 * @param string $cropped       Path to the cropped image file.
				 */
				do_action( 'wp_ajax_crop_image_pre_save', $context, $attachment_id, $cropped );

				/** This filter is documented in wp-admin/includes/class-custom-image-header.php */
				$cropped = apply_filters( 'wp_create_file_in_uploads', $cropped, $attachment_id ); // For replication.

				$parent_url = wp_get_attachment_url( $attachment_id );
				$url        = str_replace( wp_basename( $parent_url ), wp_basename( $cropped ), $parent_url );

				$size       = @getimagesize( $cropped );
				$image_type = ( $size ) ? $size['mime'] : 'image/jpeg';

				$object = array(
					'post_title'     => wp_basename( $cropped ),
					'post_content'   => $url,
					'post_mime_type' => $image_type,
					'guid'           => $url,
					'context'        => $context,
				);

				$attachment_id = wp_insert_attachment( $object, $cropped );
				$metadata      = wp_generate_attachment_metadata( $attachment_id, $cropped );

				/**
				 * Filters the cropped image attachment metadata.
				 *
				 * @since 4.3.0
				 *
				 * @see wp_generate_attachment_metadata()
				 *
				 * @param array $metadata Attachment metadata.
				 */
				$metadata = apply_filters( 'wp_ajax_cropped_attachment_metadata', $metadata );
				wp_update_attachment_metadata( $attachment_id, $metadata );

				/**
				 * Filters the attachment ID for a cropped image.
				 *
				 * @since 4.3.0
				 *
				 * @param int    $attachment_id The attachment ID of the cropped image.
				 * @param string $context       The Customizer control requesting the cropped image.
				 */
				$attachment_id = apply_filters( 'wp_ajax_cropped_attachment_id', $attachment_id, $context );
		}

		wp_send_json_success( wp_prepare_attachment_for_js( $attachment_id ) );
	}
}
