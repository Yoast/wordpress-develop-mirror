<?php

namespace WP\Helper\Ajax;

class LinkHelper {
	/**
	 * Ajax handler for deleting a link.
	 */
	public static function deleteLink() {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

		check_ajax_referer( "delete-bookmark_$id" );

		if ( ! current_user_can( 'manage_links' ) ) {
			wp_die( -1 );
		}

		$link = get_bookmark( $id );
		if ( ! $link || is_wp_error( $link ) ) {
			wp_die( 1 );
		}

		if ( wp_delete_link( $id ) ) {
			wp_die( 1 );
		} else {
			wp_die( 0 );
		}
	}

	/**
	 * Ajax handler for adding a link category.
	 *
	 * @param string $action Action to perform.
	 */
	public static function addLinkCategory( $action ) {
		if ( empty( $action ) ) {
			$action = 'add-link-category';
		}

		check_ajax_referer( $action );
		$tax = get_taxonomy( 'link_category' );

		if ( ! current_user_can( $tax->cap->manage_terms ) ) {
			wp_die( - 1 );
		}

		$names = explode( ',', wp_unslash( $_POST['newcat'] ) );
		$x     = new \WP_Ajax_Response();

		foreach ( $names as $cat_name ) {
			$cat_name = trim( $cat_name );
			$slug     = sanitize_title( $cat_name );

			if ( '' === $slug ) {
				continue;
			}

			$cat_id = wp_insert_term( $cat_name, 'link_category' );

			if ( ! $cat_id || is_wp_error( $cat_id ) ) {
				continue;
			} else {
				$cat_id = $cat_id['term_id'];
			}

			$cat_name = esc_html( $cat_name );

			$x->add(
				array(
					'what'     => 'link-category',
					'id'       => $cat_id,
					'data'     => "<li id='link-category-$cat_id'><label for='in-link-category-$cat_id' class='selectit'><input value='" . esc_attr( $cat_id ) . "' type='checkbox' checked='checked' name='link_category[]' id='in-link-category-$cat_id'/> $cat_name</label></li>",
					'position' => - 1,
				)
			);
		}
		$x->send();
	}

	/**
	 * Ajax handler for sending a link to the editor.
	 *
	 * Generates the HTML to send a non-image embed link to the editor.
	 *
	 * Backward compatible with the following filters:
	 * - file_send_to_editor_url
	 * - audio_send_to_editor_url
	 * - video_send_to_editor_url
	 *
	 * @global \WP_Post  $post     Global post object.
	 * @global \WP_Embed $wp_embed
	 */
	public static function sendToEditor() {
		global $post, $wp_embed;

		check_ajax_referer( 'media-send-to-editor', 'nonce' );

		$src = wp_unslash( $_POST['src'] );
		if ( ! $src ) {
			wp_send_json_error();
		}

		if ( ! strpos( $src, '://' ) ) {
			$src = 'http://' . $src;
		}

		$src = esc_url_raw( $src );
		if ( ! $src ) {
			wp_send_json_error();
		}

		$link_text = trim( wp_unslash( $_POST['link_text'] ) );
		if ( ! $link_text ) {
			$link_text = wp_basename( $src );
		}

		$post = get_post( isset( $_POST['post_id'] ) ? $_POST['post_id'] : 0 );

		// Ping WordPress for an embed.
		$check_embed = $wp_embed->run_shortcode( '[embed]' . $src . '[/embed]' );

		// Fallback that WordPress creates when no oEmbed was found.
		$fallback = $wp_embed->maybe_make_link( $src );

		if ( $check_embed !== $fallback ) {
			// TinyMCE view for [embed] will parse this
			$html = '[embed]' . $src . '[/embed]';
		} elseif ( $link_text ) {
			$html = '<a href="' . esc_url( $src ) . '">' . $link_text . '</a>';
		} else {
			$html = '';
		}

		// Figure out what filter to run:
		$type = 'file';
		$ext  = preg_replace( '/^.+?\.([^.]+)$/', '$1', $src );
		if ( $ext ) {
			$ext_type = wp_ext2type( $ext );
			if ( 'audio' == $ext_type || 'video' == $ext_type ) {
				$type = $ext_type;
			}
		}

		/** This filter is documented in wp-admin/includes/media.php */
		$html = apply_filters( "{$type}_send_to_editor_url", $html, $src, $link_text );

		wp_send_json_success( $html );
	}

	/**
	 * Ajax handler for internal linking.
	 */
	public static function query() {
		check_ajax_referer( 'internal-linking', '_ajax_linking_nonce' );

		$args = array();

		if ( isset( $_POST['search'] ) ) {
			$args['s'] = wp_unslash( $_POST['search'] );
		}

		if ( isset( $_POST['term'] ) ) {
			$args['s'] = wp_unslash( $_POST['term'] );
		}

		$args['pagenum'] = ! empty( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;

		$results = \_WP_Editors::wp_link_query( $args );

		if ( ! isset( $results ) ) {
			wp_die( 0 );
		}

		echo wp_json_encode( $results );
		echo "\n";

		wp_die();
	}
}
