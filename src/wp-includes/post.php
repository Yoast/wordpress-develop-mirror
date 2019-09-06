<?php


//
// Comment, trackback, and pingback functions.
//

/**
 * Add a URL to those already pinged.
 *
 * @since 1.5.0
 * @since 4.7.0 `$post_id` can be a WP_Post object.
 * @since 4.7.0 `$uri` can be an array of URIs.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int|WP_Post  $post_id Post object or ID.
 * @param string|array $uri     Ping URI or array of URIs.
 * @return int|false How many rows were updated.
 */
function add_ping( $post_id, $uri ) {
	global $wpdb;

	$post = get_post( $post_id );
	if ( ! $post ) {
		return false;
	}

	$pung = trim( $post->pinged );
	$pung = preg_split( '/\s/', $pung );

	if ( is_array( $uri ) ) {
		$pung = array_merge( $pung, $uri );
	} else {
		$pung[] = $uri;
	}
	$new = implode( "\n", $pung );

	/**
	 * Filters the new ping URL to add for the given post.
	 *
	 * @since 2.0.0
	 *
	 * @param string $new New ping URL to add.
	 */
	$new = apply_filters( 'add_ping', $new );

	$return = $wpdb->update( $wpdb->posts, array( 'pinged' => $new ), array( 'ID' => $post->ID ) );
	clean_post_cache( $post->ID );
	return $return;
}

/**
 * Retrieve enclosures already enclosed for a post.
 *
 * @since 1.5.0
 *
 * @param int $post_id Post ID.
 * @return array List of enclosures.
 */
function get_enclosed( $post_id ) {
	$custom_fields = get_post_custom( $post_id );
	$pung          = array();
	if ( ! is_array( $custom_fields ) ) {
		return $pung;
	}

	foreach ( $custom_fields as $key => $val ) {
		if ( 'enclosure' != $key || ! is_array( $val ) ) {
			continue;
		}
		foreach ( $val as $enc ) {
			$enclosure = explode( "\n", $enc );
			$pung[]    = trim( $enclosure[0] );
		}
	}

	/**
	 * Filters the list of enclosures already enclosed for the given post.
	 *
	 * @since 2.0.0
	 *
	 * @param array $pung    Array of enclosures for the given post.
	 * @param int   $post_id Post ID.
	 */
	return apply_filters( 'get_enclosed', $pung, $post_id );
}

/**
 * Retrieve URLs already pinged for a post.
 *
 * @since 1.5.0
 *
 * @since 4.7.0 `$post_id` can be a WP_Post object.
 *
 * @param int|WP_Post $post_id Post ID or object.
 * @return bool|string[] Array of URLs already pinged for the given post, false if the post is not found.
 */
function get_pung( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return false;
	}

	$pung = trim( $post->pinged );
	$pung = preg_split( '/\s/', $pung );

	/**
	 * Filters the list of already-pinged URLs for the given post.
	 *
	 * @since 2.0.0
	 *
	 * @param string[] $pung Array of URLs already pinged for the given post.
	 */
	return apply_filters( 'get_pung', $pung );
}

/**
 * Retrieve URLs that need to be pinged.
 *
 * @since 1.5.0
 * @since 4.7.0 `$post_id` can be a WP_Post object.
 *
 * @param int|WP_Post $post_id Post Object or ID
 * @return array
 */
function get_to_ping( $post_id ) {
	$post = get_post( $post_id );

	if ( ! $post ) {
		return false;
	}

	$to_ping = sanitize_trackback_urls( $post->to_ping );
	$to_ping = preg_split( '/\s/', $to_ping, -1, PREG_SPLIT_NO_EMPTY );

	/**
	 * Filters the list of URLs yet to ping for the given post.
	 *
	 * @since 2.0.0
	 *
	 * @param array $to_ping List of URLs yet to ping.
	 */
	return apply_filters( 'get_to_ping', $to_ping );
}

/**
 * Do trackbacks for a list of URLs.
 *
 * @since 1.0.0
 *
 * @param string $tb_list Comma separated list of URLs.
 * @param int    $post_id Post ID.
 */
function trackback_url_list( $tb_list, $post_id ) {
	if ( ! empty( $tb_list ) ) {
		// Get post data.
		$postdata = get_post( $post_id, ARRAY_A );

		// Form an excerpt.
		$excerpt = strip_tags( $postdata['post_excerpt'] ? $postdata['post_excerpt'] : $postdata['post_content'] );

		if ( strlen( $excerpt ) > 255 ) {
			$excerpt = substr( $excerpt, 0, 252 ) . '&hellip;';
		}

		$trackback_urls = explode( ',', $tb_list );
		foreach ( (array) $trackback_urls as $tb_url ) {
			$tb_url = trim( $tb_url );
			trackback( $tb_url, wp_unslash( $postdata['post_title'] ), $excerpt, $post_id );
		}
	}
}

//
// Page functions
//

/**
 * Get a list of page IDs.
 *
 * @since 2.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @return array List of page IDs.
 */
function get_all_page_ids() {
	global $wpdb;

	$page_ids = wp_cache_get( 'all_page_ids', 'posts' );
	if ( ! is_array( $page_ids ) ) {
		$page_ids = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_type = 'page'" );
		wp_cache_add( 'all_page_ids', $page_ids, 'posts' );
	}

	return $page_ids;
}

/**
 * Retrieves page data given a page ID or page object.
 *
 * Use get_post() instead of get_page().
 *
 * @since 1.5.1
 * @deprecated 3.5.0 Use get_post()
 *
 * @param mixed  $page   Page object or page ID. Passed by reference.
 * @param string $output Optional. The required return type. One of OBJECT, ARRAY_A, or ARRAY_N, which correspond to
 *                       a WP_Post object, an associative array, or a numeric array, respectively. Default OBJECT.
 * @param string $filter Optional. How the return value should be filtered. Accepts 'raw',
 *                       'edit', 'db', 'display'. Default 'raw'.
 * @return WP_Post|array|null WP_Post (or array) on success, or null on failure.
 */
function get_page( $page, $output = OBJECT, $filter = 'raw' ) {
	return get_post( $page, $output, $filter );
}

/**
 * Retrieves a page given its path.
 *
 * @since 2.1.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string       $page_path Page path.
 * @param string       $output    Optional. The required return type. One of OBJECT, ARRAY_A, or ARRAY_N, which correspond to
 *                                a WP_Post object, an associative array, or a numeric array, respectively. Default OBJECT.
 * @param string|array $post_type Optional. Post type or array of post types. Default 'page'.
 * @return WP_Post|array|null WP_Post (or array) on success, or null on failure.
 */
function get_page_by_path( $page_path, $output = OBJECT, $post_type = 'page' ) {
	global $wpdb;

	$last_changed = wp_cache_get_last_changed( 'posts' );

	$hash      = md5( $page_path . serialize( $post_type ) );
	$cache_key = "get_page_by_path:$hash:$last_changed";
	$cached    = wp_cache_get( $cache_key, 'posts' );
	if ( false !== $cached ) {
		// Special case: '0' is a bad `$page_path`.
		if ( '0' === $cached || 0 === $cached ) {
			return;
		} else {
			return get_post( $cached, $output );
		}
	}

	$page_path     = rawurlencode( urldecode( $page_path ) );
	$page_path     = str_replace( '%2F', '/', $page_path );
	$page_path     = str_replace( '%20', ' ', $page_path );
	$parts         = explode( '/', trim( $page_path, '/' ) );
	$parts         = array_map( 'sanitize_title_for_query', $parts );
	$escaped_parts = esc_sql( $parts );

	$in_string = "'" . implode( "','", $escaped_parts ) . "'";

	if ( is_array( $post_type ) ) {
		$post_types = $post_type;
	} else {
		$post_types = array( $post_type, 'attachment' );
	}

	$post_types          = esc_sql( $post_types );
	$post_type_in_string = "'" . implode( "','", $post_types ) . "'";
	$sql                 = "
		SELECT ID, post_name, post_parent, post_type
		FROM $wpdb->posts
		WHERE post_name IN ($in_string)
		AND post_type IN ($post_type_in_string)
	";

	$pages = $wpdb->get_results( $sql, OBJECT_K );

	$revparts = array_reverse( $parts );

	$foundid = 0;
	foreach ( (array) $pages as $page ) {
		if ( $page->post_name == $revparts[0] ) {
			$count = 0;
			$p     = $page;

			/*
			 * Loop through the given path parts from right to left,
			 * ensuring each matches the post ancestry.
			 */
			while ( $p->post_parent != 0 && isset( $pages[ $p->post_parent ] ) ) {
				$count++;
				$parent = $pages[ $p->post_parent ];
				if ( ! isset( $revparts[ $count ] ) || $parent->post_name != $revparts[ $count ] ) {
					break;
				}
				$p = $parent;
			}

			if ( $p->post_parent == 0 && $count + 1 == count( $revparts ) && $p->post_name == $revparts[ $count ] ) {
				$foundid = $page->ID;
				if ( $page->post_type == $post_type ) {
					break;
				}
			}
		}
	}

	// We cache misses as well as hits.
	wp_cache_set( $cache_key, $foundid, 'posts' );

	if ( $foundid ) {
		return get_post( $foundid, $output );
	}
}

/**
 * Retrieve a page given its title.
 *
 * If more than one post uses the same title, the post with the smallest ID will be returned.
 * Be careful: in case of more than one post having the same title, it will check the oldest
 * publication date, not the smallest ID.
 *
 * Because this function uses the MySQL '=' comparison, $page_title will usually be matched
 * as case-insensitive with default collation.
 *
 * @since 2.1.0
 * @since 3.0.0 The `$post_type` parameter was added.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string       $page_title Page title.
 * @param string       $output     Optional. The required return type. One of OBJECT, ARRAY_A, or ARRAY_N, which correspond to
 *                                 a WP_Post object, an associative array, or a numeric array, respectively. Default OBJECT.
 * @param string|array $post_type  Optional. Post type or array of post types. Default 'page'.
 * @return WP_Post|array|null WP_Post (or array) on success, or null on failure.
 */
function get_page_by_title( $page_title, $output = OBJECT, $post_type = 'page' ) {
	global $wpdb;

	if ( is_array( $post_type ) ) {
		$post_type           = esc_sql( $post_type );
		$post_type_in_string = "'" . implode( "','", $post_type ) . "'";
		$sql                 = $wpdb->prepare(
			"
			SELECT ID
			FROM $wpdb->posts
			WHERE post_title = %s
			AND post_type IN ($post_type_in_string)
		",
			$page_title
		);
	} else {
		$sql = $wpdb->prepare(
			"
			SELECT ID
			FROM $wpdb->posts
			WHERE post_title = %s
			AND post_type = %s
		",
			$page_title,
			$post_type
		);
	}

	$page = $wpdb->get_var( $sql );

	if ( $page ) {
		return get_post( $page, $output );
	}
}

/**
 * Identify descendants of a given page ID in a list of page objects.
 *
 * Descendants are identified from the `$pages` array passed to the function. No database queries are performed.
 *
 * @since 1.5.1
 *
 * @param int   $page_id Page ID.
 * @param array $pages   List of page objects from which descendants should be identified.
 * @return array List of page children.
 */
function get_page_children( $page_id, $pages ) {
	// Build a hash of ID -> children.
	$children = array();
	foreach ( (array) $pages as $page ) {
		$children[ intval( $page->post_parent ) ][] = $page;
	}

	$page_list = array();

	// Start the search by looking at immediate children.
	if ( isset( $children[ $page_id ] ) ) {
		// Always start at the end of the stack in order to preserve original `$pages` order.
		$to_look = array_reverse( $children[ $page_id ] );

		while ( $to_look ) {
			$p           = array_pop( $to_look );
			$page_list[] = $p;
			if ( isset( $children[ $p->ID ] ) ) {
				foreach ( array_reverse( $children[ $p->ID ] ) as $child ) {
					// Append to the `$to_look` stack to descend the tree.
					$to_look[] = $child;
				}
			}
		}
	}

	return $page_list;
}

/**
 * Order the pages with children under parents in a flat list.
 *
 * It uses auxiliary structure to hold parent-children relationships and
 * runs in O(N) complexity
 *
 * @since 2.0.0
 *
 * @param array $pages   Posts array (passed by reference).
 * @param int   $page_id Optional. Parent page ID. Default 0.
 * @return array A list arranged by hierarchy. Children immediately follow their parents.
 */
function get_page_hierarchy( &$pages, $page_id = 0 ) {
	if ( empty( $pages ) ) {
		return array();
	}

	$children = array();
	foreach ( (array) $pages as $p ) {
		$parent_id                = intval( $p->post_parent );
		$children[ $parent_id ][] = $p;
	}

	$result = array();
	_page_traverse_name( $page_id, $children, $result );

	return $result;
}

/**
 * Traverse and return all the nested children post names of a root page.
 *
 * $children contains parent-children relations
 *
 * @since 2.9.0
 * @access private
 *
 * @see _page_traverse_name()
 *
 * @param int   $page_id   Page ID.
 * @param array $children  Parent-children relations (passed by reference).
 * @param array $result    Result (passed by reference).
 */
function _page_traverse_name( $page_id, &$children, &$result ) {
	if ( isset( $children[ $page_id ] ) ) {
		foreach ( (array) $children[ $page_id ] as $child ) {
			$result[ $child->ID ] = $child->post_name;
			_page_traverse_name( $child->ID, $children, $result );
		}
	}
}

/**
 * Build the URI path for a page.
 *
 * Sub pages will be in the "directory" under the parent page post name.
 *
 * @since 1.5.0
 * @since 4.6.0 Converted the `$page` parameter to optional.
 *
 * @param WP_Post|object|int $page Optional. Page ID or WP_Post object. Default is global $post.
 * @return string|false Page URI, false on error.
 */
function get_page_uri( $page = 0 ) {
	if ( ! $page instanceof WP_Post ) {
		$page = get_post( $page );
	}

	if ( ! $page ) {
		return false;
	}

	$uri = $page->post_name;

	foreach ( $page->ancestors as $parent ) {
		$parent = get_post( $parent );
		if ( $parent && $parent->post_name ) {
			$uri = $parent->post_name . '/' . $uri;
		}
	}

	/**
	 * Filters the URI for a page.
	 *
	 * @since 4.4.0
	 *
	 * @param string  $uri  Page URI.
	 * @param WP_Post $page Page object.
	 */
	return apply_filters( 'get_page_uri', $uri, $page );
}

/**
 * Retrieve a list of pages (or hierarchical post type items).
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @since 1.5.0
 *
 * @param array|string $args {
 *     Optional. Array or string of arguments to retrieve pages.
 *
 *     @type int          $child_of     Page ID to return child and grandchild pages of. Note: The value
 *                                      of `$hierarchical` has no bearing on whether `$child_of` returns
 *                                      hierarchical results. Default 0, or no restriction.
 *     @type string       $sort_order   How to sort retrieved pages. Accepts 'ASC', 'DESC'. Default 'ASC'.
 *     @type string       $sort_column  What columns to sort pages by, comma-separated. Accepts 'post_author',
 *                                      'post_date', 'post_title', 'post_name', 'post_modified', 'menu_order',
 *                                      'post_modified_gmt', 'post_parent', 'ID', 'rand', 'comment_count'.
 *                                      'post_' can be omitted for any values that start with it.
 *                                      Default 'post_title'.
 *     @type bool         $hierarchical Whether to return pages hierarchically. If false in conjunction with
 *                                      `$child_of` also being false, both arguments will be disregarded.
 *                                      Default true.
 *     @type array        $exclude      Array of page IDs to exclude. Default empty array.
 *     @type array        $include      Array of page IDs to include. Cannot be used with `$child_of`,
 *                                      `$parent`, `$exclude`, `$meta_key`, `$meta_value`, or `$hierarchical`.
 *                                      Default empty array.
 *     @type string       $meta_key     Only include pages with this meta key. Default empty.
 *     @type string       $meta_value   Only include pages with this meta value. Requires `$meta_key`.
 *                                      Default empty.
 *     @type string       $authors      A comma-separated list of author IDs. Default empty.
 *     @type int          $parent       Page ID to return direct children of. Default -1, or no restriction.
 *     @type string|array $exclude_tree Comma-separated string or array of page IDs to exclude.
 *                                      Default empty array.
 *     @type int          $number       The number of pages to return. Default 0, or all pages.
 *     @type int          $offset       The number of pages to skip before returning. Requires `$number`.
 *                                      Default 0.
 *     @type string       $post_type    The post type to query. Default 'page'.
 *     @type string|array $post_status  A comma-separated list or array of post statuses to include.
 *                                      Default 'publish'.
 * }
 * @return array|false List of pages matching defaults or `$args`.
 */
function get_pages( $args = array() ) {
	global $wpdb;

	$defaults = array(
		'child_of'     => 0,
		'sort_order'   => 'ASC',
		'sort_column'  => 'post_title',
		'hierarchical' => 1,
		'exclude'      => array(),
		'include'      => array(),
		'meta_key'     => '',
		'meta_value'   => '',
		'authors'      => '',
		'parent'       => -1,
		'exclude_tree' => array(),
		'number'       => '',
		'offset'       => 0,
		'post_type'    => 'page',
		'post_status'  => 'publish',
	);

	$parsed_args = wp_parse_args( $args, $defaults );

	$number       = (int) $parsed_args['number'];
	$offset       = (int) $parsed_args['offset'];
	$child_of     = (int) $parsed_args['child_of'];
	$hierarchical = $parsed_args['hierarchical'];
	$exclude      = $parsed_args['exclude'];
	$meta_key     = $parsed_args['meta_key'];
	$meta_value   = $parsed_args['meta_value'];
	$parent       = $parsed_args['parent'];
	$post_status  = $parsed_args['post_status'];

	// Make sure the post type is hierarchical.
	$hierarchical_post_types = get_post_types( array( 'hierarchical' => true ) );
	if ( ! in_array( $parsed_args['post_type'], $hierarchical_post_types ) ) {
		return false;
	}

	if ( $parent > 0 && ! $child_of ) {
		$hierarchical = false;
	}

	// Make sure we have a valid post status.
	if ( ! is_array( $post_status ) ) {
		$post_status = explode( ',', $post_status );
	}
	if ( array_diff( $post_status, get_post_stati() ) ) {
		return false;
	}

	// $args can be whatever, only use the args defined in defaults to compute the key.
	$key          = md5( serialize( wp_array_slice_assoc( $parsed_args, array_keys( $defaults ) ) ) );
	$last_changed = wp_cache_get_last_changed( 'posts' );

	$cache_key = "get_pages:$key:$last_changed";
	$cache     = wp_cache_get( $cache_key, 'posts' );
	if ( false !== $cache ) {
		// Convert to WP_Post instances.
		$pages = array_map( 'get_post', $cache );
		/** This filter is documented in wp-includes/post.php */
		$pages = apply_filters( 'get_pages', $pages, $parsed_args );
		return $pages;
	}

	$inclusions = '';
	if ( ! empty( $parsed_args['include'] ) ) {
		$child_of     = 0; //ignore child_of, parent, exclude, meta_key, and meta_value params if using include
		$parent       = -1;
		$exclude      = '';
		$meta_key     = '';
		$meta_value   = '';
		$hierarchical = false;
		$incpages     = wp_parse_id_list( $parsed_args['include'] );
		if ( ! empty( $incpages ) ) {
			$inclusions = ' AND ID IN (' . implode( ',', $incpages ) . ')';
		}
	}

	$exclusions = '';
	if ( ! empty( $exclude ) ) {
		$expages = wp_parse_id_list( $exclude );
		if ( ! empty( $expages ) ) {
			$exclusions = ' AND ID NOT IN (' . implode( ',', $expages ) . ')';
		}
	}

	$author_query = '';
	if ( ! empty( $parsed_args['authors'] ) ) {
		$post_authors = wp_parse_list( $parsed_args['authors'] );

		if ( ! empty( $post_authors ) ) {
			foreach ( $post_authors as $post_author ) {
				//Do we have an author id or an author login?
				if ( 0 == intval( $post_author ) ) {
					$post_author = get_user_by( 'login', $post_author );
					if ( empty( $post_author ) ) {
						continue;
					}
					if ( empty( $post_author->ID ) ) {
						continue;
					}
					$post_author = $post_author->ID;
				}

				if ( '' == $author_query ) {
					$author_query = $wpdb->prepare( ' post_author = %d ', $post_author );
				} else {
					$author_query .= $wpdb->prepare( ' OR post_author = %d ', $post_author );
				}
			}
			if ( '' != $author_query ) {
				$author_query = " AND ($author_query)";
			}
		}
	}

	$join  = '';
	$where = "$exclusions $inclusions ";
	if ( '' !== $meta_key || '' !== $meta_value ) {
		$join = " LEFT JOIN $wpdb->postmeta ON ( $wpdb->posts.ID = $wpdb->postmeta.post_id )";

		// meta_key and meta_value might be slashed
		$meta_key   = wp_unslash( $meta_key );
		$meta_value = wp_unslash( $meta_value );
		if ( '' !== $meta_key ) {
			$where .= $wpdb->prepare( " AND $wpdb->postmeta.meta_key = %s", $meta_key );
		}
		if ( '' !== $meta_value ) {
			$where .= $wpdb->prepare( " AND $wpdb->postmeta.meta_value = %s", $meta_value );
		}
	}

	if ( is_array( $parent ) ) {
		$post_parent__in = implode( ',', array_map( 'absint', (array) $parent ) );
		if ( ! empty( $post_parent__in ) ) {
			$where .= " AND post_parent IN ($post_parent__in)";
		}
	} elseif ( $parent >= 0 ) {
		$where .= $wpdb->prepare( ' AND post_parent = %d ', $parent );
	}

	if ( 1 == count( $post_status ) ) {
		$where_post_type = $wpdb->prepare( 'post_type = %s AND post_status = %s', $parsed_args['post_type'], reset( $post_status ) );
	} else {
		$post_status     = implode( "', '", $post_status );
		$where_post_type = $wpdb->prepare( "post_type = %s AND post_status IN ('$post_status')", $parsed_args['post_type'] );
	}

	$orderby_array = array();
	$allowed_keys  = array(
		'author',
		'post_author',
		'date',
		'post_date',
		'title',
		'post_title',
		'name',
		'post_name',
		'modified',
		'post_modified',
		'modified_gmt',
		'post_modified_gmt',
		'menu_order',
		'parent',
		'post_parent',
		'ID',
		'rand',
		'comment_count',
	);

	foreach ( explode( ',', $parsed_args['sort_column'] ) as $orderby ) {
		$orderby = trim( $orderby );
		if ( ! in_array( $orderby, $allowed_keys ) ) {
			continue;
		}

		switch ( $orderby ) {
			case 'menu_order':
				break;
			case 'ID':
				$orderby = "$wpdb->posts.ID";
				break;
			case 'rand':
				$orderby = 'RAND()';
				break;
			case 'comment_count':
				$orderby = "$wpdb->posts.comment_count";
				break;
			default:
				if ( 0 === strpos( $orderby, 'post_' ) ) {
					$orderby = "$wpdb->posts." . $orderby;
				} else {
					$orderby = "$wpdb->posts.post_" . $orderby;
				}
		}

		$orderby_array[] = $orderby;

	}
	$sort_column = ! empty( $orderby_array ) ? implode( ',', $orderby_array ) : "$wpdb->posts.post_title";

	$sort_order = strtoupper( $parsed_args['sort_order'] );
	if ( '' !== $sort_order && ! in_array( $sort_order, array( 'ASC', 'DESC' ) ) ) {
		$sort_order = 'ASC';
	}

	$query  = "SELECT * FROM $wpdb->posts $join WHERE ($where_post_type) $where ";
	$query .= $author_query;
	$query .= ' ORDER BY ' . $sort_column . ' ' . $sort_order;

	if ( ! empty( $number ) ) {
		$query .= ' LIMIT ' . $offset . ',' . $number;
	}

	$pages = $wpdb->get_results( $query );

	if ( empty( $pages ) ) {
		wp_cache_set( $cache_key, array(), 'posts' );

		/** This filter is documented in wp-includes/post.php */
		$pages = apply_filters( 'get_pages', array(), $parsed_args );
		return $pages;
	}

	// Sanitize before caching so it'll only get done once.
	$num_pages = count( $pages );
	for ( $i = 0; $i < $num_pages; $i++ ) {
		$pages[ $i ] = sanitize_post( $pages[ $i ], 'raw' );
	}

	// Update cache.
	update_post_cache( $pages );

	if ( $child_of || $hierarchical ) {
		$pages = get_page_children( $child_of, $pages );
	}

	if ( ! empty( $parsed_args['exclude_tree'] ) ) {
		$exclude = wp_parse_id_list( $parsed_args['exclude_tree'] );
		foreach ( $exclude as $id ) {
			$children = get_page_children( $id, $pages );
			foreach ( $children as $child ) {
				$exclude[] = $child->ID;
			}
		}

		$num_pages = count( $pages );
		for ( $i = 0; $i < $num_pages; $i++ ) {
			if ( in_array( $pages[ $i ]->ID, $exclude ) ) {
				unset( $pages[ $i ] );
			}
		}
	}

	$page_structure = array();
	foreach ( $pages as $page ) {
		$page_structure[] = $page->ID;
	}

	wp_cache_set( $cache_key, $page_structure, 'posts' );

	// Convert to WP_Post instances
	$pages = array_map( 'get_post', $pages );

	/**
	 * Filters the retrieved list of pages.
	 *
	 * @since 2.1.0
	 *
	 * @param array $pages List of pages to retrieve.
	 * @param array $parsed_args     Array of get_pages() arguments.
	 */
	return apply_filters( 'get_pages', $pages, $parsed_args );
}

//
// Attachment functions
//

/**
 * Determines whether an attachment URI is local and really an attachment.
 *
 * For more information on this and similar theme functions, check out
 * the {@link https://developer.wordpress.org/themes/basics/conditional-tags/
 * Conditional Tags} article in the Theme Developer Handbook.
 *
 * @since 2.0.0
 *
 * @param string $url URL to check
 * @return bool True on success, false on failure.
 */
function is_local_attachment( $url ) {
	if ( strpos( $url, home_url() ) === false ) {
		return false;
	}
	if ( strpos( $url, home_url( '/?attachment_id=' ) ) !== false ) {
		return true;
	}

	$id = url_to_postid( $url );
	if ( $id ) {
		$post = get_post( $id );
		if ( 'attachment' == $post->post_type ) {
			return true;
		}
	}
	return false;
}

/**
 * Insert an attachment.
 *
 * If you set the 'ID' in the $args parameter, it will mean that you are
 * updating and attempt to update the attachment. You can also set the
 * attachment name or title by setting the key 'post_name' or 'post_title'.
 *
 * You can set the dates for the attachment manually by setting the 'post_date'
 * and 'post_date_gmt' keys' values.
 *
 * By default, the comments will use the default settings for whether the
 * comments are allowed. You can close them manually or keep them open by
 * setting the value for the 'comment_status' key.
 *
 * @since 2.0.0
 * @since 4.7.0 Added the `$wp_error` parameter to allow a WP_Error to be returned on failure.
 *
 * @see wp_insert_post()
 *
 * @param string|array $args     Arguments for inserting an attachment.
 * @param string       $file     Optional. Filename.
 * @param int          $parent   Optional. Parent post ID.
 * @param bool         $wp_error Optional. Whether to return a WP_Error on failure. Default false.
 * @return int|WP_Error The attachment ID on success. The value 0 or WP_Error on failure.
 */
function wp_insert_attachment( $args, $file = false, $parent = 0, $wp_error = false ) {
	$defaults = array(
		'file'        => $file,
		'post_parent' => 0,
	);

	$data = wp_parse_args( $args, $defaults );

	if ( ! empty( $parent ) ) {
		$data['post_parent'] = $parent;
	}

	$data['post_type'] = 'attachment';

	return wp_insert_post( $data, $wp_error );
}

/**
 * Trash or delete an attachment.
 *
 * When an attachment is permanently deleted, the file will also be removed.
 * Deletion removes all post meta fields, taxonomy, comments, etc. associated
 * with the attachment (except the main post).
 *
 * The attachment is moved to the trash instead of permanently deleted unless trash
 * for media is disabled, item is already in the trash, or $force_delete is true.
 *
 * @since 2.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int  $post_id      Attachment ID.
 * @param bool $force_delete Optional. Whether to bypass trash and force deletion.
 *                           Default false.
 * @return WP_Post|false|null Post data on success, false or null on failure.
 */
function wp_delete_attachment( $post_id, $force_delete = false ) {
	global $wpdb;

	$post = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE ID = %d", $post_id ) );

	if ( ! $post ) {
		return $post;
	}

	$post = get_post( $post );

	if ( 'attachment' !== $post->post_type ) {
		return false;
	}

	if ( ! $force_delete && EMPTY_TRASH_DAYS && MEDIA_TRASH && 'trash' !== $post->post_status ) {
		return wp_trash_post( $post_id );
	}

	delete_post_meta( $post_id, '_wp_trash_meta_status' );
	delete_post_meta( $post_id, '_wp_trash_meta_time' );

	$meta         = wp_get_attachment_metadata( $post_id );
	$backup_sizes = get_post_meta( $post->ID, '_wp_attachment_backup_sizes', true );
	$file         = get_attached_file( $post_id );

	if ( is_multisite() ) {
		delete_transient( 'dirsize_cache' );
	}

	/**
	 * Fires before an attachment is deleted, at the start of wp_delete_attachment().
	 *
	 * @since 2.0.0
	 *
	 * @param int $post_id Attachment ID.
	 */
	do_action( 'delete_attachment', $post_id );

	wp_delete_object_term_relationships( $post_id, array( 'category', 'post_tag' ) );
	wp_delete_object_term_relationships( $post_id, get_object_taxonomies( $post->post_type ) );

	// Delete all for any posts.
	delete_metadata( 'post', null, '_thumbnail_id', $post_id, true );

	wp_defer_comment_counting( true );

	$comment_ids = $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM $wpdb->comments WHERE comment_post_ID = %d", $post_id ) );
	foreach ( $comment_ids as $comment_id ) {
		wp_delete_comment( $comment_id, true );
	}

	wp_defer_comment_counting( false );

	$post_meta_ids = $wpdb->get_col( $wpdb->prepare( "SELECT meta_id FROM $wpdb->postmeta WHERE post_id = %d ", $post_id ) );
	foreach ( $post_meta_ids as $mid ) {
		delete_metadata_by_mid( 'post', $mid );
	}

	/** This action is documented in wp-includes/post.php */
	do_action( 'delete_post', $post_id );
	$result = $wpdb->delete( $wpdb->posts, array( 'ID' => $post_id ) );
	if ( ! $result ) {
		return false;
	}
	/** This action is documented in wp-includes/post.php */
	do_action( 'deleted_post', $post_id );

	wp_delete_attachment_files( $post_id, $meta, $backup_sizes, $file );

	clean_post_cache( $post );

	return $post;
}

/**
 * Deletes all files that belong to the given attachment.
 *
 * @since 4.9.7
 *
 * @param int    $post_id      Attachment ID.
 * @param array  $meta         The attachment's meta data.
 * @param array  $backup_sizes The meta data for the attachment's backup images.
 * @param string $file         Absolute path to the attachment's file.
 * @return bool True on success, false on failure.
 */
function wp_delete_attachment_files( $post_id, $meta, $backup_sizes, $file ) {
	global $wpdb;

	$uploadpath = wp_get_upload_dir();
	$deleted    = true;

	if ( ! empty( $meta['thumb'] ) ) {
		// Don't delete the thumb if another attachment uses it.
		if ( ! $wpdb->get_row( $wpdb->prepare( "SELECT meta_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attachment_metadata' AND meta_value LIKE %s AND post_id <> %d", '%' . $wpdb->esc_like( $meta['thumb'] ) . '%', $post_id ) ) ) {
			$thumbfile = str_replace( wp_basename( $file ), $meta['thumb'], $file );
			if ( ! empty( $thumbfile ) ) {
				$thumbfile = path_join( $uploadpath['basedir'], $thumbfile );
				$thumbdir  = path_join( $uploadpath['basedir'], dirname( $file ) );

				if ( ! wp_delete_file_from_directory( $thumbfile, $thumbdir ) ) {
					$deleted = false;
				}
			}
		}
	}

	// Remove intermediate and backup images if there are any.
	if ( isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
		$intermediate_dir = path_join( $uploadpath['basedir'], dirname( $file ) );
		foreach ( $meta['sizes'] as $size => $sizeinfo ) {
			$intermediate_file = str_replace( wp_basename( $file ), $sizeinfo['file'], $file );
			if ( ! empty( $intermediate_file ) ) {
				$intermediate_file = path_join( $uploadpath['basedir'], $intermediate_file );

				if ( ! wp_delete_file_from_directory( $intermediate_file, $intermediate_dir ) ) {
					$deleted = false;
				}
			}
		}
	}

	if ( is_array( $backup_sizes ) ) {
		$del_dir = path_join( $uploadpath['basedir'], dirname( $meta['file'] ) );
		foreach ( $backup_sizes as $size ) {
			$del_file = path_join( dirname( $meta['file'] ), $size['file'] );
			if ( ! empty( $del_file ) ) {
				$del_file = path_join( $uploadpath['basedir'], $del_file );

				if ( ! wp_delete_file_from_directory( $del_file, $del_dir ) ) {
					$deleted = false;
				}
			}
		}
	}

	if ( ! wp_delete_file_from_directory( $file, $uploadpath['basedir'] ) ) {
		$deleted = false;
	}

	return $deleted;
}

/**
 * Retrieve attachment meta field for attachment ID.
 *
 * @since 2.1.0
 *
 * @param int  $attachment_id Attachment post ID. Defaults to global $post.
 * @param bool $unfiltered    Optional. If true, filters are not run. Default false.
 * @return mixed Attachment meta field. False on failure.
 */
function wp_get_attachment_metadata( $attachment_id = 0, $unfiltered = false ) {
	$attachment_id = (int) $attachment_id;
	$post          = get_post( $attachment_id );
	if ( ! $post ) {
		return false;
	}

	$data = get_post_meta( $post->ID, '_wp_attachment_metadata', true );

	if ( $unfiltered ) {
		return $data;
	}

	/**
	 * Filters the attachment meta data.
	 *
	 * @since 2.1.0
	 *
	 * @param array|bool $data          Array of meta data for the given attachment, or false
	 *                                  if the object does not exist.
	 * @param int        $attachment_id Attachment post ID.
	 */
	return apply_filters( 'wp_get_attachment_metadata', $data, $post->ID );
}

/**
 * Update metadata for an attachment.
 *
 * @since 2.1.0
 *
 * @param int   $attachment_id Attachment post ID.
 * @param array $data          Attachment meta data.
 * @return int|bool False if $post is invalid.
 */
function wp_update_attachment_metadata( $attachment_id, $data ) {
	$attachment_id = (int) $attachment_id;
	$post          = get_post( $attachment_id );
	if ( ! $post ) {
		return false;
	}

	/**
	 * Filters the updated attachment meta data.
	 *
	 * @since 2.1.0
	 *
	 * @param array $data          Array of updated attachment meta data.
	 * @param int   $attachment_id Attachment post ID.
	 */
	$data = apply_filters( 'wp_update_attachment_metadata', $data, $post->ID );
	if ( $data ) {
		return update_post_meta( $post->ID, '_wp_attachment_metadata', $data );
	} else {
		return delete_post_meta( $post->ID, '_wp_attachment_metadata' );
	}
}

/**
 * Retrieve the URL for an attachment.
 *
 * @since 2.1.0
 *
 * @global string $pagenow
 *
 * @param int $attachment_id Optional. Attachment post ID. Defaults to global $post.
 * @return string|false Attachment URL, otherwise false.
 */
function wp_get_attachment_url( $attachment_id = 0 ) {
	$attachment_id = (int) $attachment_id;
	$post          = get_post( $attachment_id );
	if ( ! $post ) {
		return false;
	}

	if ( 'attachment' != $post->post_type ) {
		return false;
	}

	$url = '';
	// Get attached file.
	$file = get_post_meta( $post->ID, '_wp_attached_file', true );
	if ( $file ) {
		// Get upload directory.
		$uploads = wp_get_upload_dir();
		if ( $uploads && false === $uploads['error'] ) {
			// Check that the upload base exists in the file location.
			if ( 0 === strpos( $file, $uploads['basedir'] ) ) {
				// Replace file location with url location.
				$url = str_replace( $uploads['basedir'], $uploads['baseurl'], $file );
			} elseif ( false !== strpos( $file, 'wp-content/uploads' ) ) {
				// Get the directory name relative to the basedir (back compat for pre-2.7 uploads)
				$url = trailingslashit( $uploads['baseurl'] . '/' . _wp_get_attachment_relative_path( $file ) ) . wp_basename( $file );
			} else {
				// It's a newly-uploaded file, therefore $file is relative to the basedir.
				$url = $uploads['baseurl'] . "/$file";
			}
		}
	}

	/*
	 * If any of the above options failed, Fallback on the GUID as used pre-2.7,
	 * not recommended to rely upon this.
	 */
	if ( empty( $url ) ) {
		$url = get_the_guid( $post->ID );
	}

	// On SSL front end, URLs should be HTTPS.
	if ( is_ssl() && ! is_admin() && 'wp-login.php' !== $GLOBALS['pagenow'] ) {
		$url = set_url_scheme( $url );
	}

	/**
	 * Filters the attachment URL.
	 *
	 * @since 2.1.0
	 *
	 * @param string $url           URL for the given attachment.
	 * @param int    $attachment_id Attachment post ID.
	 */
	$url = apply_filters( 'wp_get_attachment_url', $url, $post->ID );

	if ( empty( $url ) ) {
		return false;
	}

	return $url;
}

/**
 * Retrieves the caption for an attachment.
 *
 * @since 4.6.0
 *
 * @param int $post_id Optional. Attachment ID. Default is the ID of the global `$post`.
 * @return string|false False on failure. Attachment caption on success.
 */
function wp_get_attachment_caption( $post_id = 0 ) {
	$post_id = (int) $post_id;
	$post    = get_post( $post_id );
	if ( ! $post ) {
		return false;
	}

	if ( 'attachment' !== $post->post_type ) {
		return false;
	}

	$caption = $post->post_excerpt;

	/**
	 * Filters the attachment caption.
	 *
	 * @since 4.6.0
	 *
	 * @param string $caption Caption for the given attachment.
	 * @param int    $post_id Attachment ID.
	 */
	return apply_filters( 'wp_get_attachment_caption', $caption, $post->ID );
}

/**
 * Retrieve thumbnail for an attachment.
 *
 * @since 2.1.0
 *
 * @param int $post_id Optional. Attachment ID. Default 0.
 * @return string|false False on failure. Thumbnail file path on success.
 */
function wp_get_attachment_thumb_file( $post_id = 0 ) {
	$post_id = (int) $post_id;
	$post    = get_post( $post_id );
	if ( ! $post ) {
		return false;
	}

	$imagedata = wp_get_attachment_metadata( $post->ID );
	if ( ! is_array( $imagedata ) ) {
		return false;
	}

	$file = get_attached_file( $post->ID );

	if ( ! empty( $imagedata['thumb'] ) ) {
		$thumbfile = str_replace( wp_basename( $file ), $imagedata['thumb'], $file );
		if ( file_exists( $thumbfile ) ) {
			/**
			 * Filters the attachment thumbnail file path.
			 *
			 * @since 2.1.0
			 *
			 * @param string $thumbfile File path to the attachment thumbnail.
			 * @param int    $post_id   Attachment ID.
			 */
			return apply_filters( 'wp_get_attachment_thumb_file', $thumbfile, $post->ID );
		}
	}
	return false;
}

/**
 * Retrieve URL for an attachment thumbnail.
 *
 * @since 2.1.0
 *
 * @param int $post_id Optional. Attachment ID. Default 0.
 * @return string|false False on failure. Thumbnail URL on success.
 */
function wp_get_attachment_thumb_url( $post_id = 0 ) {
	$post_id = (int) $post_id;
	$post    = get_post( $post_id );
	if ( ! $post ) {
		return false;
	}

	$url = wp_get_attachment_url( $post->ID );
	if ( ! $url ) {
		return false;
	}

	$sized = image_downsize( $post_id, 'thumbnail' );
	if ( $sized ) {
		return $sized[0];
	}

	$thumb = wp_get_attachment_thumb_file( $post->ID );
	if ( ! $thumb ) {
		return false;
	}

	$url = str_replace( wp_basename( $url ), wp_basename( $thumb ), $url );

	/**
	 * Filters the attachment thumbnail URL.
	 *
	 * @since 2.1.0
	 *
	 * @param string $url     URL for the attachment thumbnail.
	 * @param int    $post_id Attachment ID.
	 */
	return apply_filters( 'wp_get_attachment_thumb_url', $url, $post->ID );
}

/**
 * Verifies an attachment is of a given type.
 *
 * @since 4.2.0
 *
 * @param string      $type Attachment type. Accepts 'image', 'audio', or 'video'.
 * @param int|WP_Post $post Optional. Attachment ID or object. Default is global $post.
 * @return bool True if one of the accepted types, false otherwise.
 */
function wp_attachment_is( $type, $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return false;
	}

	$file = get_attached_file( $post->ID );
	if ( ! $file ) {
		return false;
	}

	if ( 0 === strpos( $post->post_mime_type, $type . '/' ) ) {
		return true;
	}

	$check = wp_check_filetype( $file );
	if ( empty( $check['ext'] ) ) {
		return false;
	}

	$ext = $check['ext'];

	if ( 'import' !== $post->post_mime_type ) {
		return $type === $ext;
	}

	switch ( $type ) {
		case 'image':
			$image_exts = array( 'jpg', 'jpeg', 'jpe', 'gif', 'png' );
			return in_array( $ext, $image_exts );

		case 'audio':
			return in_array( $ext, wp_get_audio_extensions() );

		case 'video':
			return in_array( $ext, wp_get_video_extensions() );

		default:
			return $type === $ext;
	}
}

/**
 * Determines whether an attachment is an image.
 *
 * For more information on this and similar theme functions, check out
 * the {@link https://developer.wordpress.org/themes/basics/conditional-tags/
 * Conditional Tags} article in the Theme Developer Handbook.
 *
 * @since 2.1.0
 * @since 4.2.0 Modified into wrapper for wp_attachment_is() and
 *              allowed WP_Post object to be passed.
 *
 * @param int|WP_Post $post Optional. Attachment ID or object. Default is global $post.
 * @return bool Whether the attachment is an image.
 */
function wp_attachment_is_image( $post = null ) {
	return wp_attachment_is( 'image', $post );
}

/**
 * Retrieve the icon for a MIME type.
 *
 * @since 2.1.0
 *
 * @param string|int $mime MIME type or attachment ID.
 * @return string|false Icon, false otherwise.
 */
function wp_mime_type_icon( $mime = 0 ) {
	if ( ! is_numeric( $mime ) ) {
		$icon = wp_cache_get( "mime_type_icon_$mime" );
	}

	$post_id = 0;
	if ( empty( $icon ) ) {
		$post_mimes = array();
		if ( is_numeric( $mime ) ) {
			$mime = (int) $mime;
			$post = get_post( $mime );
			if ( $post ) {
				$post_id = (int) $post->ID;
				$file    = get_attached_file( $post_id );
				$ext     = preg_replace( '/^.+?\.([^.]+)$/', '$1', $file );
				if ( ! empty( $ext ) ) {
					$post_mimes[] = $ext;
					$ext_type     = wp_ext2type( $ext );
					if ( $ext_type ) {
						$post_mimes[] = $ext_type;
					}
				}
				$mime = $post->post_mime_type;
			} else {
				$mime = 0;
			}
		} else {
			$post_mimes[] = $mime;
		}

		$icon_files = wp_cache_get( 'icon_files' );

		if ( ! is_array( $icon_files ) ) {
			/**
			 * Filters the icon directory path.
			 *
			 * @since 2.0.0
			 *
			 * @param string $path Icon directory absolute path.
			 */
			$icon_dir = apply_filters( 'icon_dir', ABSPATH . WPINC . '/images/media' );

			/**
			 * Filters the icon directory URI.
			 *
			 * @since 2.0.0
			 *
			 * @param string $uri Icon directory URI.
			 */
			$icon_dir_uri = apply_filters( 'icon_dir_uri', includes_url( 'images/media' ) );

			/**
			 * Filters the list of icon directory URIs.
			 *
			 * @since 2.5.0
			 *
			 * @param array $uris List of icon directory URIs.
			 */
			$dirs       = apply_filters( 'icon_dirs', array( $icon_dir => $icon_dir_uri ) );
			$icon_files = array();
			while ( $dirs ) {
				$keys = array_keys( $dirs );
				$dir  = array_shift( $keys );
				$uri  = array_shift( $dirs );
				$dh   = opendir( $dir );
				if ( $dh ) {
					while ( false !== $file = readdir( $dh ) ) {
						$file = wp_basename( $file );
						if ( substr( $file, 0, 1 ) == '.' ) {
							continue;
						}
						if ( ! in_array( strtolower( substr( $file, -4 ) ), array( '.png', '.gif', '.jpg' ) ) ) {
							if ( is_dir( "$dir/$file" ) ) {
								$dirs[ "$dir/$file" ] = "$uri/$file";
							}
							continue;
						}
						$icon_files[ "$dir/$file" ] = "$uri/$file";
					}
					closedir( $dh );
				}
			}
			wp_cache_add( 'icon_files', $icon_files, 'default', 600 );
		}

		$types = array();
		// Icon wp_basename - extension = MIME wildcard.
		foreach ( $icon_files as $file => $uri ) {
			$types[ preg_replace( '/^([^.]*).*$/', '$1', wp_basename( $file ) ) ] =& $icon_files[ $file ];
		}

		if ( ! empty( $mime ) ) {
			$post_mimes[] = substr( $mime, 0, strpos( $mime, '/' ) );
			$post_mimes[] = substr( $mime, strpos( $mime, '/' ) + 1 );
			$post_mimes[] = str_replace( '/', '_', $mime );
		}

		$matches            = wp_match_mime_types( array_keys( $types ), $post_mimes );
		$matches['default'] = array( 'default' );

		foreach ( $matches as $match => $wilds ) {
			foreach ( $wilds as $wild ) {
				if ( ! isset( $types[ $wild ] ) ) {
					continue;
				}

				$icon = $types[ $wild ];
				if ( ! is_numeric( $mime ) ) {
					wp_cache_add( "mime_type_icon_$mime", $icon );
				}
				break 2;
			}
		}
	}

	/**
	 * Filters the mime type icon.
	 *
	 * @since 2.1.0
	 *
	 * @param string $icon    Path to the mime type icon.
	 * @param string $mime    Mime type.
	 * @param int    $post_id Attachment ID. Will equal 0 if the function passed
	 *                        the mime type.
	 */
	return apply_filters( 'wp_mime_type_icon', $icon, $mime, $post_id );
}

/**
 * Check for changed slugs for published post objects and save the old slug.
 *
 * The function is used when a post object of any type is updated,
 * by comparing the current and previous post objects.
 *
 * If the slug was changed and not already part of the old slugs then it will be
 * added to the post meta field ('_wp_old_slug') for storing old slugs for that
 * post.
 *
 * The most logically usage of this function is redirecting changed post objects, so
 * that those that linked to an changed post will be redirected to the new post.
 *
 * @since 2.1.0
 *
 * @param int     $post_id     Post ID.
 * @param WP_Post $post        The Post Object
 * @param WP_Post $post_before The Previous Post Object
 */
function wp_check_for_changed_slugs( $post_id, $post, $post_before ) {
	// Don't bother if it hasn't changed.
	if ( $post->post_name == $post_before->post_name ) {
		return;
	}

	// We're only concerned with published, non-hierarchical objects.
	if ( ! ( 'publish' === $post->post_status || ( 'attachment' === get_post_type( $post ) && 'inherit' === $post->post_status ) ) || is_post_type_hierarchical( $post->post_type ) ) {
		return;
	}

	$old_slugs = (array) get_post_meta( $post_id, '_wp_old_slug' );

	// If we haven't added this old slug before, add it now.
	if ( ! empty( $post_before->post_name ) && ! in_array( $post_before->post_name, $old_slugs ) ) {
		add_post_meta( $post_id, '_wp_old_slug', $post_before->post_name );
	}

	// If the new slug was used previously, delete it from the list.
	if ( in_array( $post->post_name, $old_slugs ) ) {
		delete_post_meta( $post_id, '_wp_old_slug', $post->post_name );
	}
}

/**
 * Check for changed dates for published post objects and save the old date.
 *
 * The function is used when a post object of any type is updated,
 * by comparing the current and previous post objects.
 *
 * If the date was changed and not already part of the old dates then it will be
 * added to the post meta field ('_wp_old_date') for storing old dates for that
 * post.
 *
 * The most logically usage of this function is redirecting changed post objects, so
 * that those that linked to an changed post will be redirected to the new post.
 *
 * @since 4.9.3
 *
 * @param int     $post_id     Post ID.
 * @param WP_Post $post        The Post Object
 * @param WP_Post $post_before The Previous Post Object
 */
function wp_check_for_changed_dates( $post_id, $post, $post_before ) {
	$previous_date = gmdate( 'Y-m-d', strtotime( $post_before->post_date ) );
	$new_date      = gmdate( 'Y-m-d', strtotime( $post->post_date ) );
	// Don't bother if it hasn't changed.
	if ( $new_date == $previous_date ) {
		return;
	}
	// We're only concerned with published, non-hierarchical objects.
	if ( ! ( 'publish' === $post->post_status || ( 'attachment' === get_post_type( $post ) && 'inherit' === $post->post_status ) ) || is_post_type_hierarchical( $post->post_type ) ) {
		return;
	}
	$old_dates = (array) get_post_meta( $post_id, '_wp_old_date' );
	// If we haven't added this old date before, add it now.
	if ( ! empty( $previous_date ) && ! in_array( $previous_date, $old_dates ) ) {
		add_post_meta( $post_id, '_wp_old_date', $previous_date );
	}
	// If the new slug was used previously, delete it from the list.
	if ( in_array( $new_date, $old_dates ) ) {
		delete_post_meta( $post_id, '_wp_old_date', $new_date );
	}
}

/**
 * Retrieve the private post SQL based on capability.
 *
 * This function provides a standardized way to appropriately select on the
 * post_status of a post type. The function will return a piece of SQL code
 * that can be added to a WHERE clause; this SQL is constructed to allow all
 * published posts, and all private posts to which the user has access.
 *
 * @since 2.2.0
 * @since 4.3.0 Added the ability to pass an array to `$post_type`.
 *
 * @param string|array $post_type Single post type or an array of post types. Currently only supports 'post' or 'page'.
 * @return string SQL code that can be added to a where clause.
 */
function get_private_posts_cap_sql( $post_type ) {
	return get_posts_by_author_sql( $post_type, false );
}

/**
 * Retrieve the post SQL based on capability, author, and type.
 *
 * @since 3.0.0
 * @since 4.3.0 Introduced the ability to pass an array of post types to `$post_type`.
 *
 * @see get_private_posts_cap_sql()
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param array|string   $post_type   Single post type or an array of post types.
 * @param bool           $full        Optional. Returns a full WHERE statement instead of just
 *                                    an 'andalso' term. Default true.
 * @param int            $post_author Optional. Query posts having a single author ID. Default null.
 * @param bool           $public_only Optional. Only return public posts. Skips cap checks for
 *                                    $current_user.  Default false.
 * @return string SQL WHERE code that can be added to a query.
 */
function get_posts_by_author_sql( $post_type, $full = true, $post_author = null, $public_only = false ) {
	global $wpdb;

	if ( is_array( $post_type ) ) {
		$post_types = $post_type;
	} else {
		$post_types = array( $post_type );
	}

	$post_type_clauses = array();
	foreach ( $post_types as $post_type ) {
		$post_type_obj = get_post_type_object( $post_type );
		if ( ! $post_type_obj ) {
			continue;
		}

		/**
		 * Filters the capability to read private posts for a custom post type
		 * when generating SQL for getting posts by author.
		 *
		 * @since 2.2.0
		 * @deprecated 3.2.0 The hook transitioned from "somewhat useless" to "totally useless".
		 *
		 * @param string $cap Capability.
		 */
		$cap = apply_filters( 'pub_priv_sql_capability', '' );
		if ( ! $cap ) {
			$cap = current_user_can( $post_type_obj->cap->read_private_posts );
		}

		// Only need to check the cap if $public_only is false.
		$post_status_sql = "post_status = 'publish'";
		if ( false === $public_only ) {
			if ( $cap ) {
				// Does the user have the capability to view private posts? Guess so.
				$post_status_sql .= " OR post_status = 'private'";
			} elseif ( is_user_logged_in() ) {
				// Users can view their own private posts.
				$id = get_current_user_id();
				if ( null === $post_author || ! $full ) {
					$post_status_sql .= " OR post_status = 'private' AND post_author = $id";
				} elseif ( $id == (int) $post_author ) {
					$post_status_sql .= " OR post_status = 'private'";
				} // else none
			} // else none
		}

		$post_type_clauses[] = "( post_type = '" . $post_type . "' AND ( $post_status_sql ) )";
	}

	if ( empty( $post_type_clauses ) ) {
		return $full ? 'WHERE 1 = 0' : '1 = 0';
	}

	$sql = '( ' . implode( ' OR ', $post_type_clauses ) . ' )';

	if ( null !== $post_author ) {
		$sql .= $wpdb->prepare( ' AND post_author = %d', $post_author );
	}

	if ( $full ) {
		$sql = 'WHERE ' . $sql;
	}

	return $sql;
}

/**
 * Retrieve the date that the last post was published.
 *
 * The server timezone is the default and is the difference between GMT and
 * server time. The 'blog' value is the date when the last post was posted. The
 * 'gmt' is when the last post was posted in GMT formatted date.
 *
 * @since 0.71
 * @since 4.4.0 The `$post_type` argument was added.
 *
 * @param string $timezone  Optional. The timezone for the timestamp. Accepts 'server', 'blog', or 'gmt'.
 *                          'server' uses the server's internal timezone.
 *                          'blog' uses the `post_modified` field, which proxies to the timezone set for the site.
 *                          'gmt' uses the `post_modified_gmt` field.
 *                          Default 'server'.
 * @param string $post_type Optional. The post type to check. Default 'any'.
 * @return string The date of the last post.
 */
function get_lastpostdate( $timezone = 'server', $post_type = 'any' ) {
	/**
	 * Filters the date the last post was published.
	 *
	 * @since 2.3.0
	 *
	 * @param string $date     Date the last post was published.
	 * @param string $timezone Location to use for getting the post published date.
	 *                         See get_lastpostdate() for accepted `$timezone` values.
	 */
	return apply_filters( 'get_lastpostdate', _get_last_post_time( $timezone, 'date', $post_type ), $timezone );
}

/**
 * Get the timestamp of the last time any post was modified.
 *
 * The server timezone is the default and is the difference between GMT and
 * server time. The 'blog' value is just when the last post was modified. The
 * 'gmt' is when the last post was modified in GMT time.
 *
 * @since 1.2.0
 * @since 4.4.0 The `$post_type` argument was added.
 *
 * @param string $timezone  Optional. The timezone for the timestamp. See get_lastpostdate()
 *                          for information on accepted values.
 *                          Default 'server'.
 * @param string $post_type Optional. The post type to check. Default 'any'.
 * @return string The timestamp.
 */
function get_lastpostmodified( $timezone = 'server', $post_type = 'any' ) {
	/**
	 * Pre-filter the return value of get_lastpostmodified() before the query is run.
	 *
	 * @since 4.4.0
	 *
	 * @param string $lastpostmodified Date the last post was modified.
	 *                                 Returning anything other than false will short-circuit the function.
	 * @param string $timezone         Location to use for getting the post modified date.
	 *                                 See get_lastpostdate() for accepted `$timezone` values.
	 * @param string $post_type        The post type to check.
	 */
	$lastpostmodified = apply_filters( 'pre_get_lastpostmodified', false, $timezone, $post_type );
	if ( false !== $lastpostmodified ) {
		return $lastpostmodified;
	}

	$lastpostmodified = _get_last_post_time( $timezone, 'modified', $post_type );

	$lastpostdate = get_lastpostdate( $timezone );
	if ( $lastpostdate > $lastpostmodified ) {
		$lastpostmodified = $lastpostdate;
	}

	/**
	 * Filters the date the last post was modified.
	 *
	 * @since 2.3.0
	 *
	 * @param string $lastpostmodified Date the last post was modified.
	 * @param string $timezone         Location to use for getting the post modified date.
	 *                                 See get_lastpostdate() for accepted `$timezone` values.
	 */
	return apply_filters( 'get_lastpostmodified', $lastpostmodified, $timezone );
}

/**
 * Get the timestamp of the last time any post was modified or published.
 *
 * @since 3.1.0
 * @since 4.4.0 The `$post_type` argument was added.
 * @access private
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string $timezone  The timezone for the timestamp. See get_lastpostdate().
 *                          for information on accepted values.
 * @param string $field     Post field to check. Accepts 'date' or 'modified'.
 * @param string $post_type Optional. The post type to check. Default 'any'.
 * @return string|false The timestamp.
 */
function _get_last_post_time( $timezone, $field, $post_type = 'any' ) {
	global $wpdb;

	if ( ! in_array( $field, array( 'date', 'modified' ) ) ) {
		return false;
	}

	$timezone = strtolower( $timezone );

	$key = "lastpost{$field}:$timezone";
	if ( 'any' !== $post_type ) {
		$key .= ':' . sanitize_key( $post_type );
	}

	$date = wp_cache_get( $key, 'timeinfo' );
	if ( false !== $date ) {
		return $date;
	}

	if ( 'any' === $post_type ) {
		$post_types = get_post_types( array( 'public' => true ) );
		array_walk( $post_types, array( $wpdb, 'escape_by_ref' ) );
		$post_types = "'" . implode( "', '", $post_types ) . "'";
	} else {
		$post_types = "'" . sanitize_key( $post_type ) . "'";
	}

	switch ( $timezone ) {
		case 'gmt':
			$date = $wpdb->get_var( "SELECT post_{$field}_gmt FROM $wpdb->posts WHERE post_status = 'publish' AND post_type IN ({$post_types}) ORDER BY post_{$field}_gmt DESC LIMIT 1" );
			break;
		case 'blog':
			$date = $wpdb->get_var( "SELECT post_{$field} FROM $wpdb->posts WHERE post_status = 'publish' AND post_type IN ({$post_types}) ORDER BY post_{$field}_gmt DESC LIMIT 1" );
			break;
		case 'server':
			$add_seconds_server = gmdate( 'Z' );
			$date               = $wpdb->get_var( "SELECT DATE_ADD(post_{$field}_gmt, INTERVAL '$add_seconds_server' SECOND) FROM $wpdb->posts WHERE post_status = 'publish' AND post_type IN ({$post_types}) ORDER BY post_{$field}_gmt DESC LIMIT 1" );
			break;
	}

	if ( $date ) {
		wp_cache_set( $key, $date, 'timeinfo' );

		return $date;
	}

	return false;
}

/**
 * Updates posts in cache.
 *
 * @since 1.5.1
 *
 * @param WP_Post[] $posts Array of post objects (passed by reference).
 */
function update_post_cache( &$posts ) {
	if ( ! $posts ) {
		return;
	}

	foreach ( $posts as $post ) {
		wp_cache_add( $post->ID, $post, 'posts' );
	}
}

/**
 * Will clean the post in the cache.
 *
 * Cleaning means delete from the cache of the post. Will call to clean the term
 * object cache associated with the post ID.
 *
 * This function not run if $_wp_suspend_cache_invalidation is not empty. See
 * wp_suspend_cache_invalidation().
 *
 * @since 2.0.0
 *
 * @global bool $_wp_suspend_cache_invalidation
 *
 * @param int|WP_Post $post Post ID or post object to remove from the cache.
 */
function clean_post_cache( $post ) {
	global $_wp_suspend_cache_invalidation;

	if ( ! empty( $_wp_suspend_cache_invalidation ) ) {
		return;
	}

	$post = get_post( $post );
	if ( empty( $post ) ) {
		return;
	}

	wp_cache_delete( $post->ID, 'posts' );
	wp_cache_delete( $post->ID, 'post_meta' );

	clean_object_term_cache( $post->ID, $post->post_type );

	wp_cache_delete( 'wp_get_archives', 'general' );

	/**
	 * Fires immediately after the given post's cache is cleaned.
	 *
	 * @since 2.5.0
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	do_action( 'clean_post_cache', $post->ID, $post );

	if ( 'page' == $post->post_type ) {
		wp_cache_delete( 'all_page_ids', 'posts' );

		/**
		 * Fires immediately after the given page's cache is cleaned.
		 *
		 * @since 2.5.0
		 *
		 * @param int $post_id Post ID.
		 */
		do_action( 'clean_page_cache', $post->ID );
	}

	wp_cache_set( 'last_changed', microtime(), 'posts' );
}

/**
 * Call major cache updating functions for list of Post objects.
 *
 * @since 1.5.0
 *
 * @param WP_Post[] $posts             Array of Post objects
 * @param string    $post_type         Optional. Post type. Default 'post'.
 * @param bool      $update_term_cache Optional. Whether to update the term cache. Default true.
 * @param bool      $update_meta_cache Optional. Whether to update the meta cache. Default true.
 */
function update_post_caches( &$posts, $post_type = 'post', $update_term_cache = true, $update_meta_cache = true ) {
	// No point in doing all this work if we didn't match any posts.
	if ( ! $posts ) {
		return;
	}

	update_post_cache( $posts );

	$post_ids = array();
	foreach ( $posts as $post ) {
		$post_ids[] = $post->ID;
	}

	if ( ! $post_type ) {
		$post_type = 'any';
	}

	if ( $update_term_cache ) {
		if ( is_array( $post_type ) ) {
			$ptypes = $post_type;
		} elseif ( 'any' == $post_type ) {
			$ptypes = array();
			// Just use the post_types in the supplied posts.
			foreach ( $posts as $post ) {
				$ptypes[] = $post->post_type;
			}
			$ptypes = array_unique( $ptypes );
		} else {
			$ptypes = array( $post_type );
		}

		if ( ! empty( $ptypes ) ) {
			update_object_term_cache( $post_ids, $ptypes );
		}
	}

	if ( $update_meta_cache ) {
		update_postmeta_cache( $post_ids );
	}
}

/**
 * Updates metadata cache for list of post IDs.
 *
 * Performs SQL query to retrieve the metadata for the post IDs and updates the
 * metadata cache for the posts. Therefore, the functions, which call this
 * function, do not need to perform SQL queries on their own.
 *
 * @since 2.1.0
 *
 * @param int[] $post_ids Array of post IDs.
 * @return array|false Returns false if there is nothing to update or an array
 *                     of metadata.
 */
function update_postmeta_cache( $post_ids ) {
	return update_meta_cache( 'post', $post_ids );
}

/**
 * Will clean the attachment in the cache.
 *
 * Cleaning means delete from the cache. Optionally will clean the term
 * object cache associated with the attachment ID.
 *
 * This function will not run if $_wp_suspend_cache_invalidation is not empty.
 *
 * @since 3.0.0
 *
 * @global bool $_wp_suspend_cache_invalidation
 *
 * @param int  $id          The attachment ID in the cache to clean.
 * @param bool $clean_terms Optional. Whether to clean terms cache. Default false.
 */
function clean_attachment_cache( $id, $clean_terms = false ) {
	global $_wp_suspend_cache_invalidation;

	if ( ! empty( $_wp_suspend_cache_invalidation ) ) {
		return;
	}

	$id = (int) $id;

	wp_cache_delete( $id, 'posts' );
	wp_cache_delete( $id, 'post_meta' );

	if ( $clean_terms ) {
		clean_object_term_cache( $id, 'attachment' );
	}

	/**
	 * Fires after the given attachment's cache is cleaned.
	 *
	 * @since 3.0.0
	 *
	 * @param int $id Attachment ID.
	 */
	do_action( 'clean_attachment_cache', $id );
}

//
// Hooks
//

/**
 * Hook for managing future post transitions to published.
 *
 * @since 2.3.0
 * @access private
 *
 * @see wp_clear_scheduled_hook()
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string  $new_status New post status.
 * @param string  $old_status Previous post status.
 * @param WP_Post $post       Post object.
 */
function _transition_post_status( $new_status, $old_status, $post ) {
	global $wpdb;

	if ( $old_status != 'publish' && $new_status == 'publish' ) {
		// Reset GUID if transitioning to publish and it is empty.
		if ( '' == get_the_guid( $post->ID ) ) {
			$wpdb->update( $wpdb->posts, array( 'guid' => get_permalink( $post->ID ) ), array( 'ID' => $post->ID ) );
		}

		/**
		 * Fires when a post's status is transitioned from private to published.
		 *
		 * @since 1.5.0
		 * @deprecated 2.3.0 Use 'private_to_publish' instead.
		 *
		 * @param int $post_id Post ID.
		 */
		do_action( 'private_to_published', $post->ID );
	}

	// If published posts changed clear the lastpostmodified cache.
	if ( 'publish' == $new_status || 'publish' == $old_status ) {
		foreach ( array( 'server', 'gmt', 'blog' ) as $timezone ) {
			wp_cache_delete( "lastpostmodified:$timezone", 'timeinfo' );
			wp_cache_delete( "lastpostdate:$timezone", 'timeinfo' );
			wp_cache_delete( "lastpostdate:$timezone:{$post->post_type}", 'timeinfo' );
		}
	}

	if ( $new_status !== $old_status ) {
		wp_cache_delete( _count_posts_cache_key( $post->post_type ), 'counts' );
		wp_cache_delete( _count_posts_cache_key( $post->post_type, 'readable' ), 'counts' );
	}

	// Always clears the hook in case the post status bounced from future to draft.
	wp_clear_scheduled_hook( 'publish_future_post', array( $post->ID ) );
}

/**
 * Hook used to schedule publication for a post marked for the future.
 *
 * The $post properties used and must exist are 'ID' and 'post_date_gmt'.
 *
 * @since 2.3.0
 * @access private
 *
 * @param int     $deprecated Not used. Can be set to null. Never implemented. Not marked
 *                            as deprecated with _deprecated_argument() as it conflicts with
 *                            wp_transition_post_status() and the default filter for _future_post_hook().
 * @param WP_Post $post       Post object.
 */
function _future_post_hook( $deprecated, $post ) {
	wp_clear_scheduled_hook( 'publish_future_post', array( $post->ID ) );
	wp_schedule_single_event( strtotime( get_gmt_from_date( $post->post_date ) . ' GMT' ), 'publish_future_post', array( $post->ID ) );
}

/**
 * Hook to schedule pings and enclosures when a post is published.
 *
 * Uses XMLRPC_REQUEST and WP_IMPORTING constants.
 *
 * @since 2.3.0
 * @access private
 *
 * @param int $post_id The ID in the database table of the post being published.
 */
function _publish_post_hook( $post_id ) {
	if ( defined( 'XMLRPC_REQUEST' ) ) {
		/**
		 * Fires when _publish_post_hook() is called during an XML-RPC request.
		 *
		 * @since 2.1.0
		 *
		 * @param int $post_id Post ID.
		 */
		do_action( 'xmlrpc_publish_post', $post_id );
	}

	if ( defined( 'WP_IMPORTING' ) ) {
		return;
	}

	if ( get_option( 'default_pingback_flag' ) ) {
		add_post_meta( $post_id, '_pingme', '1' );
	}
	add_post_meta( $post_id, '_encloseme', '1' );

	if ( ! wp_next_scheduled( 'do_pings' ) ) {
		wp_schedule_single_event( time(), 'do_pings' );
	}
}

/**
 * Returns the ID of the post's parent.
 *
 * @since 3.1.0
 *
 * @param int|WP_Post $post Post ID or post object. Defaults to global $post.
 * @return int|false Post parent ID (which can be 0 if there is no parent), or false if the post does not exist.
 */
function wp_get_post_parent_id( $post ) {
	$post = get_post( $post );
	if ( ! $post || is_wp_error( $post ) ) {
		return false;
	}
	return (int) $post->post_parent;
}

/**
 * Check the given subset of the post hierarchy for hierarchy loops.
 *
 * Prevents loops from forming and breaks those that it finds. Attached
 * to the {@see 'wp_insert_post_parent'} filter.
 *
 * @since 3.1.0
 *
 * @see wp_find_hierarchy_loop()
 *
 * @param int $post_parent ID of the parent for the post we're checking.
 * @param int $post_ID     ID of the post we're checking.
 * @return int The new post_parent for the post, 0 otherwise.
 */
function wp_check_post_hierarchy_for_loops( $post_parent, $post_ID ) {
	// Nothing fancy here - bail.
	if ( ! $post_parent ) {
		return 0;
	}

	// New post can't cause a loop.
	if ( empty( $post_ID ) ) {
		return $post_parent;
	}

	// Can't be its own parent.
	if ( $post_parent == $post_ID ) {
		return 0;
	}

	// Now look for larger loops.
	$loop = wp_find_hierarchy_loop( 'wp_get_post_parent_id', $post_ID, $post_parent );
	if ( ! $loop ) {
		return $post_parent; // No loop
	}

	// Setting $post_parent to the given value causes a loop.
	if ( isset( $loop[ $post_ID ] ) ) {
		return 0;
	}

	// There's a loop, but it doesn't contain $post_ID. Break the loop.
	foreach ( array_keys( $loop ) as $loop_member ) {
		wp_update_post(
			array(
				'ID'          => $loop_member,
				'post_parent' => 0,
			)
		);
	}

	return $post_parent;
}

/**
 * Sets the post thumbnail (featured image) for the given post.
 *
 * @since 3.1.0
 *
 * @param int|WP_Post $post         Post ID or post object where thumbnail should be attached.
 * @param int         $thumbnail_id Thumbnail to attach.
 * @return int|bool True on success, false on failure.
 */
function set_post_thumbnail( $post, $thumbnail_id ) {
	$post         = get_post( $post );
	$thumbnail_id = absint( $thumbnail_id );
	if ( $post && $thumbnail_id && get_post( $thumbnail_id ) ) {
		if ( wp_get_attachment_image( $thumbnail_id, 'thumbnail' ) ) {
			return update_post_meta( $post->ID, '_thumbnail_id', $thumbnail_id );
		} else {
			return delete_post_meta( $post->ID, '_thumbnail_id' );
		}
	}
	return false;
}

/**
 * Removes the thumbnail (featured image) from the given post.
 *
 * @since 3.3.0
 *
 * @param int|WP_Post $post Post ID or post object from which the thumbnail should be removed.
 * @return bool True on success, false on failure.
 */
function delete_post_thumbnail( $post ) {
	$post = get_post( $post );
	if ( $post ) {
		return delete_post_meta( $post->ID, '_thumbnail_id' );
	}
	return false;
}

/**
 * Delete auto-drafts for new posts that are > 7 days old.
 *
 * @since 3.4.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 */
function wp_delete_auto_drafts() {
	global $wpdb;

	// Cleanup old auto-drafts more than 7 days old.
	$old_posts = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_status = 'auto-draft' AND DATE_SUB( NOW(), INTERVAL 7 DAY ) > post_date" );
	foreach ( (array) $old_posts as $delete ) {
		// Force delete.
		wp_delete_post( $delete, true );
	}
}

/**
 * Queues posts for lazy-loading of term meta.
 *
 * @since 4.5.0
 *
 * @param array $posts Array of WP_Post objects.
 */
function wp_queue_posts_for_term_meta_lazyload( $posts ) {
	$post_type_taxonomies = array();
	$term_ids             = array();
	foreach ( $posts as $post ) {
		if ( ! ( $post instanceof WP_Post ) ) {
			continue;
		}

		if ( ! isset( $post_type_taxonomies[ $post->post_type ] ) ) {
			$post_type_taxonomies[ $post->post_type ] = get_object_taxonomies( $post->post_type );
		}

		foreach ( $post_type_taxonomies[ $post->post_type ] as $taxonomy ) {
			// Term cache should already be primed by `update_post_term_cache()`.
			$terms = get_object_term_cache( $post->ID, $taxonomy );
			if ( false !== $terms ) {
				foreach ( $terms as $term ) {
					if ( ! isset( $term_ids[ $term->term_id ] ) ) {
						$term_ids[] = $term->term_id;
					}
				}
			}
		}
	}

	if ( $term_ids ) {
		$lazyloader = wp_metadata_lazyloader();
		$lazyloader->queue_objects( 'term', $term_ids );
	}
}

/**
 * Update the custom taxonomies' term counts when a post's status is changed.
 *
 * For example, default posts term counts (for custom taxonomies) don't include
 * private / draft posts.
 *
 * @since 3.3.0
 * @access private
 *
 * @param string  $new_status New post status.
 * @param string  $old_status Old post status.
 * @param WP_Post $post       Post object.
 */
function _update_term_count_on_transition_post_status( $new_status, $old_status, $post ) {
	// Update counts for the post's terms.
	foreach ( (array) get_object_taxonomies( $post->post_type ) as $taxonomy ) {
		$tt_ids = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'tt_ids' ) );
		wp_update_term_count( $tt_ids, $taxonomy );
	}
}

/**
 * Adds any posts from the given ids to the cache that do not already exist in cache
 *
 * @since 3.4.0
 * @access private
 *
 * @see update_post_caches()
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param array $ids               ID list.
 * @param bool  $update_term_cache Optional. Whether to update the term cache. Default true.
 * @param bool  $update_meta_cache Optional. Whether to update the meta cache. Default true.
 */
function _prime_post_caches( $ids, $update_term_cache = true, $update_meta_cache = true ) {
	global $wpdb;

	$non_cached_ids = _get_non_cached_ids( $ids, 'posts' );
	if ( ! empty( $non_cached_ids ) ) {
		$fresh_posts = $wpdb->get_results( sprintf( "SELECT $wpdb->posts.* FROM $wpdb->posts WHERE ID IN (%s)", join( ',', $non_cached_ids ) ) );

		update_post_caches( $fresh_posts, 'any', $update_term_cache, $update_meta_cache );
	}
}

/**
 * Adds a suffix if any trashed posts have a given slug.
 *
 * Store its desired (i.e. current) slug so it can try to reclaim it
 * if the post is untrashed.
 *
 * For internal use.
 *
 * @since 4.5.0
 * @access private
 *
 * @param string $post_name Slug.
 * @param string $post_ID   Optional. Post ID that should be ignored. Default 0.
 */
function wp_add_trashed_suffix_to_post_name_for_trashed_posts( $post_name, $post_ID = 0 ) {
	$trashed_posts_with_desired_slug = get_posts(
		array(
			'name'         => $post_name,
			'post_status'  => 'trash',
			'post_type'    => 'any',
			'nopaging'     => true,
			'post__not_in' => array( $post_ID ),
		)
	);

	if ( ! empty( $trashed_posts_with_desired_slug ) ) {
		foreach ( $trashed_posts_with_desired_slug as $_post ) {
			wp_add_trashed_suffix_to_post_name_for_post( $_post );
		}
	}
}

/**
 * Adds a trashed suffix for a given post.
 *
 * Store its desired (i.e. current) slug so it can try to reclaim it
 * if the post is untrashed.
 *
 * For internal use.
 *
 * @since 4.5.0
 * @access private
 *
 * @param WP_Post $post The post.
 * @return string New slug for the post.
 */
function wp_add_trashed_suffix_to_post_name_for_post( $post ) {
	global $wpdb;

	$post = get_post( $post );

	if ( '__trashed' === substr( $post->post_name, -9 ) ) {
		return $post->post_name;
	}
	add_post_meta( $post->ID, '_wp_desired_post_slug', $post->post_name );
	$post_name = _truncate_post_slug( $post->post_name, 191 ) . '__trashed';
	$wpdb->update( $wpdb->posts, array( 'post_name' => $post_name ), array( 'ID' => $post->ID ) );
	clean_post_cache( $post->ID );
	return $post_name;
}

/**
 * Filter the SQL clauses of an attachment query to include filenames.
 *
 * @since 4.7.0
 * @access private
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param array $clauses An array including WHERE, GROUP BY, JOIN, ORDER BY,
 *                       DISTINCT, fields (SELECT), and LIMITS clauses.
 * @return array The modified clauses.
 */
function _filter_query_attachment_filenames( $clauses ) {
	global $wpdb;
	remove_filter( 'posts_clauses', __FUNCTION__ );

	// Add a LEFT JOIN of the postmeta table so we don't trample existing JOINs.
	$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS sq1 ON ( {$wpdb->posts}.ID = sq1.post_id AND sq1.meta_key = '_wp_attached_file' )";

	$clauses['groupby'] = "{$wpdb->posts}.ID";

	$clauses['where'] = preg_replace(
		"/\({$wpdb->posts}.post_content (NOT LIKE|LIKE) (\'[^']+\')\)/",
		'$0 OR ( sq1.meta_value $1 $2 )',
		$clauses['where']
	);

	return $clauses;
}

/**
 * Sets the last changed time for the 'posts' cache group.
 *
 * @since 5.0.0
 */
function wp_cache_set_posts_last_changed() {
	wp_cache_set( 'last_changed', microtime(), 'posts' );
}

/**
 * Get all available post MIME types for a given post type.
 *
 * @since 2.5.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string $type
 * @return mixed
 */
function get_available_post_mime_types( $type = 'attachment' ) {
	global $wpdb;

	$types = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT post_mime_type FROM $wpdb->posts WHERE post_type = %s", $type ) );
	return $types;
}
