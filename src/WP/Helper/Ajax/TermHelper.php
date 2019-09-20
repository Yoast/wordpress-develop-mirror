<?php

namespace WP\Helper\Ajax;

class TermHelper {
	/**
	 * Ajax handler for adding a hierarchical term.
	 *
	 * @access private
	 */
	public static function addHierarchicalTerm() {
		$action   = $_POST['action'];
		$taxonomy = get_taxonomy( substr( $action, 4 ) );
		check_ajax_referer( $action, '_ajax_nonce-add-' . $taxonomy->name );

		if ( ! current_user_can( $taxonomy->cap->edit_terms ) ) {
			wp_die( - 1 );
		}

		$names  = explode( ',', $_POST[ 'new' . $taxonomy->name ] );
		$parent = isset( $_POST[ 'new' . $taxonomy->name . '_parent' ] ) ? (int) $_POST[ 'new' . $taxonomy->name . '_parent' ] : 0;

		if ( 0 > $parent ) {
			$parent = 0;
		}

		if ( $taxonomy->name == 'category' ) {
			$post_category = isset( $_POST['post_category'] ) ? (array) $_POST['post_category'] : array();
		} else {
			$post_category = ( isset( $_POST['tax_input'] ) && isset( $_POST['tax_input'][ $taxonomy->name ] ) ) ? (array) $_POST['tax_input'][ $taxonomy->name ] : array();
		}

		$checked_categories = array_map( 'absint', (array) $post_category );
		$popular_ids        = wp_popular_terms_checklist( $taxonomy->name, 0, 10, false );

		foreach ( $names as $cat_name ) {
			$cat_name          = trim( $cat_name );
			$category_nicename = sanitize_title( $cat_name );

			if ( '' === $category_nicename ) {
				continue;
			}

			$cat_id = wp_insert_term( $cat_name, $taxonomy->name, array( 'parent' => $parent ) );

			if ( ! $cat_id || is_wp_error( $cat_id ) ) {
				continue;
			} else {
				$cat_id = $cat_id['term_id'];
			}

			$checked_categories[] = $cat_id;

			if ( $parent ) { // Do these all at once in a second
				continue;
			}

			ob_start();

			wp_terms_checklist(
				0,
				array(
					'taxonomy'             => $taxonomy->name,
					'descendants_and_self' => $cat_id,
					'selected_cats'        => $checked_categories,
					'popular_cats'         => $popular_ids,
				)
			);

			$data = ob_get_clean();

			$add = array(
				'what'     => $taxonomy->name,
				'id'       => $cat_id,
				'data'     => str_replace( array( "\n", "\t" ), '', $data ),
				'position' => - 1,
			);
		}

		if ( $parent ) { // Foncy - replace the parent and all its children
			$parent  = get_term( $parent, $taxonomy->name );
			$term_id = $parent->term_id;

			while ( $parent->parent ) { // get the top parent
				$parent = get_term( $parent->parent, $taxonomy->name );
				if ( is_wp_error( $parent ) ) {
					break;
				}
				$term_id = $parent->term_id;
			}

			ob_start();

			wp_terms_checklist(
				0,
				array(
					'taxonomy'             => $taxonomy->name,
					'descendants_and_self' => $term_id,
					'selected_cats'        => $checked_categories,
					'popular_cats'         => $popular_ids,
				)
			);

			$data = ob_get_clean();

			$add = array(
				'what'     => $taxonomy->name,
				'id'       => $term_id,
				'data'     => str_replace( array( "\n", "\t" ), '', $data ),
				'position' => - 1,
			);
		}

		ob_start();

		wp_dropdown_categories(
			array(
				'taxonomy'         => $taxonomy->name,
				'hide_empty'       => 0,
				'name'             => 'new' . $taxonomy->name . '_parent',
				'orderby'          => 'name',
				'hierarchical'     => 1,
				'show_option_none' => '&mdash; ' . $taxonomy->labels->parent_item . ' &mdash;',
			)
		);

		$sup = ob_get_clean();

		$add['supplemental'] = array( 'newcat_parent' => $sup );

		$x = new \WP_Ajax_Response( $add );
		$x->send();
	}

	/**
	 * Ajax handler for deleting a tag.
	 */
	public static function deleteTag() {
		$tag_id = (int) $_POST['tag_ID'];
		check_ajax_referer( "delete-tag_$tag_id" );

		if ( ! current_user_can( 'delete_term', $tag_id ) ) {
			wp_die( -1 );
		}

		$taxonomy = ! empty( $_POST['taxonomy'] ) ? $_POST['taxonomy'] : 'post_tag';
		$tag      = get_term( $tag_id, $taxonomy );

		if ( ! $tag || is_wp_error( $tag ) ) {
			wp_die( 1 );
		}

		if ( wp_delete_term( $tag_id, $taxonomy ) ) {
			wp_die( 1 );
		} else {
			wp_die( 0 );
		}
	}

	/**
	 * Ajax handler for tag search.
	 */
	public static function tagSearch() {
		if ( ! isset( $_GET['tax'] ) ) {
			wp_die( 0 );
		}

		$taxonomy = sanitize_key( $_GET['tax'] );
		$tax      = get_taxonomy( $taxonomy );

		if ( ! $tax ) {
			wp_die( 0 );
		}

		if ( ! current_user_can( $tax->cap->assign_terms ) ) {
			wp_die( -1 );
		}

		$s = wp_unslash( $_GET['q'] );

		$comma = _x( ',', 'tag delimiter' );
		if ( ',' !== $comma ) {
			$s = str_replace( $comma, ',', $s );
		}

		if ( false !== strpos( $s, ',' ) ) {
			$s = explode( ',', $s );
			$s = $s[ count( $s ) - 1 ];
		}

		$s = trim( $s );

		/**
		 * Filters the minimum number of characters required to fire a tag search via Ajax.
		 *
		 * @since 4.0.0
		 *
		 * @param int          $characters The minimum number of characters required. Default 2.
		 * @param \WP_Taxonomy $tax        The taxonomy object.
		 * @param string       $s          The search term.
		 */
		$term_search_min_chars = (int) apply_filters( 'term_search_min_chars', 2, $tax, $s );

		/*
		 * Require $term_search_min_chars chars for matching (default: 2)
		 * ensure it's a non-negative, non-zero integer.
		 */
		if ( ( $term_search_min_chars == 0 ) || ( strlen( $s ) < $term_search_min_chars ) ) {
			wp_die();
		}

		$results = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'name__like' => $s,
				'fields'     => 'names',
				'hide_empty' => false,
			)
		);

		echo join( $results, "\n" );
		wp_die();
	}

	/**
	 * Ajax handler to add a tag.
	 */
	public static function addTag() {
		check_ajax_referer( 'add-tag', '_wpnonce_add-tag' );
		$taxonomy = ! empty( $_POST['taxonomy'] ) ? $_POST['taxonomy'] : 'post_tag';
		$tax      = get_taxonomy( $taxonomy );

		if ( ! current_user_can( $tax->cap->edit_terms ) ) {
			wp_die( -1 );
		}

		$x = new \WP_Ajax_Response();

		$tag = wp_insert_term( $_POST['tag-name'], $taxonomy, $_POST );

		if ( $tag && ! is_wp_error( $tag ) ) {
			$tag = get_term( $tag['term_id'], $taxonomy );
		}

		if ( ! $tag || is_wp_error( $tag ) ) {
			$message = __( 'An error has occurred. Please reload the page and try again.' );

			if ( is_wp_error( $tag ) && $tag->get_error_message() ) {
				$message = $tag->get_error_message();
			}

			$x->add(
				array(
					'what' => 'taxonomy',
					'data' => new \WP_Error( 'error', $message ),
				)
			);
			$x->send();
		}

		$wp_list_table = _get_list_table( 'WP_Terms_List_Table', array( 'screen' => $_POST['screen'] ) );

		$level     = 0;
		$noparents = '';

		if ( is_taxonomy_hierarchical( $taxonomy ) ) {
			$level = count( get_ancestors( $tag->term_id, $taxonomy, 'taxonomy' ) );
			ob_start();
			$wp_list_table->single_row( $tag, $level );
			$noparents = ob_get_clean();
		}

		ob_start();
		$wp_list_table->single_row( $tag );
		$parents = ob_get_clean();

		$x->add(
			array(
				'what'         => 'taxonomy',
				'supplemental' => compact( 'parents', 'noparents' ),
			)
		);

		$x->add(
			array(
				'what'         => 'term',
				'position'     => $level,
				'supplemental' => (array) $tag,
			)
		);

		$x->send();
	}

	/**
	 * Ajax handler for getting a tagcloud.
	 */
	public static function getTagcloud() {
		if ( ! isset( $_POST['tax'] ) ) {
			wp_die( 0 );
		}

		$taxonomy = sanitize_key( $_POST['tax'] );
		$tax      = get_taxonomy( $taxonomy );

		if ( ! $tax ) {
			wp_die( 0 );
		}

		if ( ! current_user_can( $tax->cap->assign_terms ) ) {
			wp_die( -1 );
		}

		$tags = get_terms(
			array(
				'taxonomy' => $taxonomy,
				'number'   => 45,
				'orderby'  => 'count',
				'order'    => 'DESC',
			)
		);

		if ( empty( $tags ) ) {
			wp_die( $tax->labels->not_found );
		}

		if ( is_wp_error( $tags ) ) {
			wp_die( $tags->get_error_message() );
		}

		foreach ( $tags as $key => $tag ) {
			$tags[ $key ]->link = '#';
			$tags[ $key ]->id   = $tag->term_id;
		}

		// We need raw tag names here, so don't filter the output
		$return = wp_generate_tag_cloud(
			$tags,
			array(
				'filter' => 0,
				'format' => 'list',
			)
		);

		if ( empty( $return ) ) {
			wp_die( 0 );
		}

		echo $return;
		wp_die();
	}

	public static function inlineSaveTax() {
		check_ajax_referer( 'taxinlineeditnonce', '_inline_edit' );

		$taxonomy = sanitize_key( $_POST['taxonomy'] );
		$tax      = get_taxonomy( $taxonomy );

		if ( ! $tax ) {
			wp_die( 0 );
		}

		if ( ! isset( $_POST['tax_ID'] ) || ! (int) $_POST['tax_ID'] ) {
			wp_die( -1 );
		}

		$id = (int) $_POST['tax_ID'];

		if ( ! current_user_can( 'edit_term', $id ) ) {
			wp_die( -1 );
		}

		$wp_list_table = _get_list_table( 'WP_Terms_List_Table', array( 'screen' => 'edit-' . $taxonomy ) );

		$tag                  = get_term( $id, $taxonomy );
		$_POST['description'] = $tag->description;

		$updated = wp_update_term( $id, $taxonomy, $_POST );

		if ( $updated && ! is_wp_error( $updated ) ) {
			$tag = get_term( $updated['term_id'], $taxonomy );
			if ( ! $tag || is_wp_error( $tag ) ) {
				if ( is_wp_error( $tag ) && $tag->get_error_message() ) {
					wp_die( $tag->get_error_message() );
				}
				wp_die( __( 'Item not updated.' ) );
			}
		} else {
			if ( is_wp_error( $updated ) && $updated->get_error_message() ) {
				wp_die( $updated->get_error_message() );
			}
			wp_die( __( 'Item not updated.' ) );
		}

		$level  = 0;
		$parent = $tag->parent;

		while ( $parent > 0 ) {
			$parent_tag = get_term( $parent, $taxonomy );
			$parent     = $parent_tag->parent;
			$level++;
		}

		$wp_list_table->single_row( $tag, $level );
		wp_die();
	}
}
