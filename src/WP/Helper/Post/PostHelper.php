<?php

namespace WP\Helper\Post;

class PostHelper{



    /**
     * Retrieves post data given a post ID or post object.
     */
    public static function get( $post = null, $output = OBJECT, $filter = 'raw' ) {
        if ( empty( $post ) && isset( $GLOBALS['post'] ) ) {
            $post = $GLOBALS['post'];
        }

        if ( $post instanceof WP_Post ) {
            $_post = $post;
        } elseif ( is_object( $post ) ) {
            if ( empty( $post->filter ) ) {
                $_post = sanitize_post( $post, 'raw' );
                $_post = new \WP_Post( $_post );
            } elseif ( 'raw' == $post->filter ) {
                $_post = new \WP_Post( $post );
            } else {
                $_post = \WP_Post::get_instance( $post->ID );
            }
        } else {
            $_post = \WP_Post::get_instance( $post );
        }

        if ( ! $_post ) {
            return null;
        }

        $_post = $_post->filter( $filter );

        if ( $output == ARRAY_A ) {
            return $_post->to_array();
        } elseif ( $output == ARRAY_N ) {
            return array_values( $_post->to_array() );
        }

        return $_post;
    }

    /**
     * Get multiple posts
     */
    public static function getPosts( $args ) {
        $defaults = array(
            'numberposts'      => 5,
            'category'         => 0,
            'orderby'          => 'date',
            'order'            => 'DESC',
            'include'          => array(),
            'exclude'          => array(),
            'meta_key'         => '',
            'meta_value'       => '',
            'post_type'        => 'post',
            'suppress_filters' => true,
        );

        $parsed_args = wp_parse_args( $args, $defaults );
        if ( empty( $parsed_args['post_status'] ) ) {
            $parsed_args['post_status'] = ( 'attachment' == $parsed_args['post_type'] ) ? 'inherit' : 'publish';
        }
        if ( ! empty( $parsed_args['numberposts'] ) && empty( $parsed_args['posts_per_page'] ) ) {
            $parsed_args['posts_per_page'] = $parsed_args['numberposts'];
        }
        if ( ! empty( $parsed_args['category'] ) ) {
            $parsed_args['cat'] = $parsed_args['category'];
        }
        if ( ! empty( $parsed_args['include'] ) ) {
            $incposts                      = wp_parse_id_list( $parsed_args['include'] );
            $parsed_args['posts_per_page'] = count( $incposts );  // only the number of posts included
            $parsed_args['post__in']       = $incposts;
        } elseif ( ! empty( $parsed_args['exclude'] ) ) {
            $parsed_args['post__not_in'] = wp_parse_id_list( $parsed_args['exclude'] );
        }

        $parsed_args['ignore_sticky_posts'] = true;
        $parsed_args['no_found_rows']       = true;

        $get_posts = new \WP_Query;
        return $get_posts->query( $parsed_args );
    }


    public static function getRecentPosts( $args = array(), $output = ARRAY_A ) {

        if ( is_numeric( $args ) ) {
            _deprecated_argument( __FUNCTION__, '3.1.0', __( 'Passing an integer number of posts is deprecated. Pass an array of arguments instead.' ) );
            $args = array( 'numberposts' => absint( $args ) );
        }

        // Set default arguments.
        $defaults = array(
            'numberposts'      => 10,
            'offset'           => 0,
            'category'         => 0,
            'orderby'          => 'post_date',
            'order'            => 'DESC',
            'include'          => '',
            'exclude'          => '',
            'meta_key'         => '',
            'meta_value'       => '',
            'post_type'        => 'post',
            'post_status'      => 'draft, publish, future, pending, private',
            'suppress_filters' => true,
        );

        $parsed_args = wp_parse_args( $args, $defaults );

        $results = static::getPosts( $parsed_args );

        // Backward compatibility. Prior to 3.1 expected posts to be returned in array.
        if ( ARRAY_A == $output ) {
            foreach ( $results as $key => $result ) {
                $results[ $key ] = get_object_vars( $result );
            }
            return $results ? $results : array();
        }

        return $results ? $results : false;
    }

    /**
     * Retrieve ancestors of a post.
     */
    public static function getAncestors( $post ) {
        $post = get_post( $post );

        if ( ! $post || empty( $post->post_parent ) || $post->post_parent == $post->ID ) {
            return array();
        }

        $ancestors = array();

        $id          = $post->post_parent;
        $ancestors[] = $id;

        while ( $ancestor = get_post( $id ) ) {
            // Loop detection: If the ancestor has been seen before, break.
            if ( empty( $ancestor->post_parent ) || ( $ancestor->post_parent == $post->ID ) || in_array( $ancestor->post_parent, $ancestors ) ) {
                break;
            }

            $id          = $ancestor->post_parent;
            $ancestors[] = $id;
        }

        return $ancestors;
    }


    /**
     * Retrieve all children of the post parent ID.
     */
    public static function getChildren( $args = '', $output = OBJECT ) {
        $kids = array();
        if ( empty( $args ) ) {
            if ( isset( $GLOBALS['post'] ) ) {
                $args = array( 'post_parent' => (int) $GLOBALS['post']->post_parent );
            } else {
                return $kids;
            }
        } elseif ( is_object( $args ) ) {
            $args = array( 'post_parent' => (int) $args->post_parent );
        } elseif ( is_numeric( $args ) ) {
            $args = array( 'post_parent' => (int) $args );
        }

        $defaults = array(
            'numberposts' => -1,
            'post_type'   => 'any',
            'post_status' => 'any',
            'post_parent' => 0,
        );

        $parsed_args = wp_parse_args( $args, $defaults );

        $children = get_posts( $parsed_args );

        if ( ! $children ) {
            return $kids;
        }

        if ( ! empty( $parsed_args['fields'] ) ) {
            return $children;
        }

        update_post_cache( $children );

        foreach ( $children as $key => $child ) {
            $kids[ $child->ID ] = $children[ $key ];
        }

        if ( $output == OBJECT ) {
            return $kids;
        } elseif ( $output == ARRAY_A ) {
            $weeuns = array();
            foreach ( (array) $kids as $kid ) {
                $weeuns[ $kid->ID ] = get_object_vars( $kids[ $kid->ID ] );
            }
            return $weeuns;
        } elseif ( $output == ARRAY_N ) {
            $babes = array();
            foreach ( (array) $kids as $kid ) {
                $babes[ $kid->ID ] = array_values( get_object_vars( $kids[ $kid->ID ] ) );
            }
            return $babes;
        } else {
            return $kids;
        }
    }


    /**
     * Add a post
     */
    public static function add( $postarr, $wp_error = false ) {
        global $wpdb;

        $user_id = get_current_user_id();

        $defaults = array(
            'post_author'           => $user_id,
            'post_content'          => '',
            'post_content_filtered' => '',
            'post_title'            => '',
            'post_excerpt'          => '',
            'post_status'           => 'draft',
            'post_type'             => 'post',
            'comment_status'        => '',
            'ping_status'           => '',
            'post_password'         => '',
            'to_ping'               => '',
            'pinged'                => '',
            'post_parent'           => 0,
            'menu_order'            => 0,
            'guid'                  => '',
            'import_id'             => 0,
            'context'               => '',
        );

        $postarr = wp_parse_args( $postarr, $defaults );

        unset( $postarr['filter'] );

        $postarr = static::sanitize( $postarr, 'db' );

        // Are we updating or creating?
        $post_ID = 0;
        $update  = false;
        $guid    = $postarr['guid'];

        if ( ! empty( $postarr['ID'] ) ) {
            $update = true;

            // Get the post ID and GUID.
            $post_ID     = $postarr['ID'];
            $post_before = static::get( $post_ID );
            if ( is_null( $post_before ) ) {
                if ( $wp_error ) {
                    return new WP_Error( 'invalid_post', __( 'Invalid post ID.' ) );
                }
                return 0;
            }

            $guid            = static::getField( 'guid', $post_ID );
            $previous_status = static::getField( 'post_status', $post_ID );
        } else {
            $previous_status = 'new';
        }

        $post_type = empty( $postarr['post_type'] ) ? 'post' : $postarr['post_type'];

        $post_title   = $postarr['post_title'];
        $post_content = $postarr['post_content'];
        $post_excerpt = $postarr['post_excerpt'];
        if ( isset( $postarr['post_name'] ) ) {
            $post_name = $postarr['post_name'];
        } elseif ( $update ) {
            // For an update, don't modify the post_name if it wasn't supplied as an argument.
            $post_name = $post_before->post_name;
        }

        $maybe_empty = 'attachment' !== $post_type
            && ! $post_content && ! $post_title && ! $post_excerpt
            && post_type_supports( $post_type, 'editor' )
            && post_type_supports( $post_type, 'title' )
            && post_type_supports( $post_type, 'excerpt' );

        /**
         * Filters whether the post should be considered "empty".
         *
         * The post is considered "empty" if both:
         * 1. The post type supports the title, editor, and excerpt fields
         * 2. The title, editor, and excerpt fields are all empty
         *
         * Returning a truthy value to the filter will effectively short-circuit
         * the new post being inserted, returning 0. If $wp_error is true, a WP_Error
         * will be returned instead.
         *
         * @since 3.3.0
         *
         * @param bool  $maybe_empty Whether the post should be considered "empty".
         * @param array $postarr     Array of post data.
         */
        if ( apply_filters( 'wp_insert_post_empty_content', $maybe_empty, $postarr ) ) {
            if ( $wp_error ) {
                return new WP_Error( 'empty_content', __( 'Content, title, and excerpt are empty.' ) );
            } else {
                return 0;
            }
        }

        $post_status = empty( $postarr['post_status'] ) ? 'draft' : $postarr['post_status'];
        if ( 'attachment' === $post_type && ! in_array( $post_status, array( 'inherit', 'private', 'trash', 'auto-draft' ), true ) ) {
            $post_status = 'inherit';
        }

        if ( ! empty( $postarr['post_category'] ) ) {
            // Filter out empty terms.
            $post_category = array_filter( $postarr['post_category'] );
        }

        // Make sure we set a valid category.
        if ( empty( $post_category ) || 0 == count( $post_category ) || ! is_array( $post_category ) ) {
            // 'post' requires at least one category.
            if ( 'post' == $post_type && 'auto-draft' != $post_status ) {
                $post_category = array( get_option( 'default_category' ) );
            } else {
                $post_category = array();
            }
        }

        /*
        * Don't allow contributors to set the post slug for pending review posts.
        *
        * For new posts check the primitive capability, for updates check the meta capability.
        */
        $post_type_object = get_post_type_object( $post_type );

        if ( ! $update && 'pending' === $post_status && ! current_user_can( $post_type_object->cap->publish_posts ) ) {
            $post_name = '';
        } elseif ( $update && 'pending' === $post_status && ! current_user_can( 'publish_post', $post_ID ) ) {
            $post_name = '';
        }

        /*
        * Create a valid post name. Drafts and pending posts are allowed to have
        * an empty post name.
        */
        if ( empty( $post_name ) ) {
            if ( ! in_array( $post_status, array( 'draft', 'pending', 'auto-draft' ) ) ) {
                $post_name = sanitize_title( $post_title );
            } else {
                $post_name = '';
            }
        } else {
            // On updates, we need to check to see if it's using the old, fixed sanitization context.
            $check_name = sanitize_title( $post_name, '', 'old-save' );
            if ( $update && strtolower( urlencode( $post_name ) ) == $check_name && get_post_field( 'post_name', $post_ID ) == $check_name ) {
                $post_name = $check_name;
            } else { // new post, or slug has changed.
                $post_name = sanitize_title( $post_name );
            }
        }

        /*
        * If the post date is empty (due to having been new or a draft) and status
        * is not 'draft' or 'pending', set date to now.
        */
        if ( empty( $postarr['post_date'] ) || '0000-00-00 00:00:00' == $postarr['post_date'] ) {
            if ( empty( $postarr['post_date_gmt'] ) || '0000-00-00 00:00:00' == $postarr['post_date_gmt'] ) {
                $post_date = current_time( 'mysql' );
            } else {
                $post_date = get_date_from_gmt( $postarr['post_date_gmt'] );
            }
        } else {
            $post_date = $postarr['post_date'];
        }

        // Validate the date.
        $mm         = substr( $post_date, 5, 2 );
        $jj         = substr( $post_date, 8, 2 );
        $aa         = substr( $post_date, 0, 4 );
        $valid_date = wp_checkdate( $mm, $jj, $aa, $post_date );
        if ( ! $valid_date ) {
            if ( $wp_error ) {
                return new WP_Error( 'invalid_date', __( 'Invalid date.' ) );
            } else {
                return 0;
            }
        }

        if ( empty( $postarr['post_date_gmt'] ) || '0000-00-00 00:00:00' == $postarr['post_date_gmt'] ) {
            if ( ! in_array( $post_status, array( 'draft', 'pending', 'auto-draft' ) ) ) {
                $post_date_gmt = get_gmt_from_date( $post_date );
            } else {
                $post_date_gmt = '0000-00-00 00:00:00';
            }
        } else {
            $post_date_gmt = $postarr['post_date_gmt'];
        }

        if ( $update || '0000-00-00 00:00:00' == $post_date ) {
            $post_modified     = current_time( 'mysql' );
            $post_modified_gmt = current_time( 'mysql', 1 );
        } else {
            $post_modified     = $post_date;
            $post_modified_gmt = $post_date_gmt;
        }

        if ( 'attachment' !== $post_type ) {
            if ( 'publish' === $post_status ) {
                // String comparison to work around far future dates (year 2038+) on 32-bit systems.
                if ( $post_date_gmt > gmdate( 'Y-m-d H:i:59' ) ) {
                    $post_status = 'future';
                }
            } elseif ( 'future' === $post_status ) {
                if ( $post_date_gmt <= gmdate( 'Y-m-d H:i:59' ) ) {
                    $post_status = 'publish';
                }
            }
        }

        // Comment status.
        if ( empty( $postarr['comment_status'] ) ) {
            if ( $update ) {
                $comment_status = 'closed';
            } else {
                $comment_status = get_default_comment_status( $post_type );
            }
        } else {
            $comment_status = $postarr['comment_status'];
        }

        // These variables are needed by compact() later.
        $post_content_filtered = $postarr['post_content_filtered'];
        $post_author           = isset( $postarr['post_author'] ) ? $postarr['post_author'] : $user_id;
        $ping_status           = empty( $postarr['ping_status'] ) ? get_default_comment_status( $post_type, 'pingback' ) : $postarr['ping_status'];
        $to_ping               = isset( $postarr['to_ping'] ) ? sanitize_trackback_urls( $postarr['to_ping'] ) : '';
        $pinged                = isset( $postarr['pinged'] ) ? $postarr['pinged'] : '';
        $import_id             = isset( $postarr['import_id'] ) ? $postarr['import_id'] : 0;

        /*
        * The 'wp_insert_post_parent' filter expects all variables to be present.
        * Previously, these variables would have already been extracted
        */
        if ( isset( $postarr['menu_order'] ) ) {
            $menu_order = (int) $postarr['menu_order'];
        } else {
            $menu_order = 0;
        }

        $post_password = isset( $postarr['post_password'] ) ? $postarr['post_password'] : '';
        if ( 'private' == $post_status ) {
            $post_password = '';
        }

        if ( isset( $postarr['post_parent'] ) ) {
            $post_parent = (int) $postarr['post_parent'];
        } else {
            $post_parent = 0;
        }

        $new_postarr = array_merge(
            array(
                'ID' => $post_ID,
            ),
            compact( array_diff( array_keys( $defaults ), array( 'context', 'filter' ) ) )
        );

        /**
         * Filters the post parent -- used to check for and prevent hierarchy loops.
         *
         * @since 3.1.0
         *
         * @param int   $post_parent Post parent ID.
         * @param int   $post_ID     Post ID.
         * @param array $new_postarr Array of parsed post data.
         * @param array $postarr     Array of sanitized, but otherwise unmodified post data.
         */
        $post_parent = apply_filters( 'wp_insert_post_parent', $post_parent, $post_ID, $new_postarr, $postarr );

        /*
        * If the post is being untrashed and it has a desired slug stored in post meta,
        * reassign it.
        */
        if ( 'trash' === $previous_status && 'trash' !== $post_status ) {
            $desired_post_slug = get_post_meta( $post_ID, '_wp_desired_post_slug', true );
            if ( $desired_post_slug ) {
                delete_post_meta( $post_ID, '_wp_desired_post_slug' );
                $post_name = $desired_post_slug;
            }
        }

        // If a trashed post has the desired slug, change it and let this post have it.
        if ( 'trash' !== $post_status && $post_name ) {
            wp_add_trashed_suffix_to_post_name_for_trashed_posts( $post_name, $post_ID );
        }

        // When trashing an existing post, change its slug to allow non-trashed posts to use it.
        if ( 'trash' === $post_status && 'trash' !== $previous_status && 'new' !== $previous_status ) {
            $post_name = wp_add_trashed_suffix_to_post_name_for_post( $post_ID );
        }

        $post_name = wp_unique_post_slug( $post_name, $post_ID, $post_status, $post_type, $post_parent );

        // Don't unslash.
        $post_mime_type = isset( $postarr['post_mime_type'] ) ? $postarr['post_mime_type'] : '';

        // Expected_slashed (everything!).
        $data = compact( 'post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_content_filtered', 'post_title', 'post_excerpt', 'post_status', 'post_type', 'comment_status', 'ping_status', 'post_password', 'post_name', 'to_ping', 'pinged', 'post_modified', 'post_modified_gmt', 'post_parent', 'menu_order', 'post_mime_type', 'guid' );

        $emoji_fields = array( 'post_title', 'post_content', 'post_excerpt' );

        foreach ( $emoji_fields as $emoji_field ) {
            if ( isset( $data[ $emoji_field ] ) ) {
                $charset = $wpdb->get_col_charset( $wpdb->posts, $emoji_field );
                if ( 'utf8' === $charset ) {
                    $data[ $emoji_field ] = wp_encode_emoji( $data[ $emoji_field ] );
                }
            }
        }

        if ( 'attachment' === $post_type ) {
            /**
             * Filters attachment post data before it is updated in or added to the database.
             *
             * @since 3.9.0
             *
             * @param array $data    An array of sanitized attachment post data.
             * @param array $postarr An array of unsanitized attachment post data.
             */
            $data = apply_filters( 'wp_insert_attachment_data', $data, $postarr );
        } else {
            /**
             * Filters slashed post data just before it is inserted into the database.
             *
             * @since 2.7.0
             *
             * @param array $data    An array of slashed post data.
             * @param array $postarr An array of sanitized, but otherwise unmodified post data.
             */
            $data = apply_filters( 'wp_insert_post_data', $data, $postarr );
        }
        $data  = wp_unslash( $data );
        $where = array( 'ID' => $post_ID );

        if ( $update ) {
            /**
             * Fires immediately before an existing post is updated in the database.
             *
             * @since 2.5.0
             *
             * @param int   $post_ID Post ID.
             * @param array $data    Array of unslashed post data.
             */
            do_action( 'pre_post_update', $post_ID, $data );
            if ( false === $wpdb->update( $wpdb->posts, $data, $where ) ) {
                if ( $wp_error ) {
                    return new WP_Error( 'db_update_error', __( 'Could not update post in the database' ), $wpdb->last_error );
                } else {
                    return 0;
                }
            }
        } else {
            // If there is a suggested ID, use it if not already present.
            if ( ! empty( $import_id ) ) {
                $import_id = (int) $import_id;
                if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE ID = %d", $import_id ) ) ) {
                    $data['ID'] = $import_id;
                }
            }
            if ( false === $wpdb->insert( $wpdb->posts, $data ) ) {
                if ( $wp_error ) {
                    return new WP_Error( 'db_insert_error', __( 'Could not insert post into the database' ), $wpdb->last_error );
                } else {
                    return 0;
                }
            }
            $post_ID = (int) $wpdb->insert_id;

            // Use the newly generated $post_ID.
            $where = array( 'ID' => $post_ID );
        }

        if ( empty( $data['post_name'] ) && ! in_array( $data['post_status'], array( 'draft', 'pending', 'auto-draft' ) ) ) {
            $data['post_name'] = wp_unique_post_slug( sanitize_title( $data['post_title'], $post_ID ), $post_ID, $data['post_status'], $post_type, $post_parent );
            $wpdb->update( $wpdb->posts, array( 'post_name' => $data['post_name'] ), $where );
            clean_post_cache( $post_ID );
        }

        if ( is_object_in_taxonomy( $post_type, 'category' ) ) {
            wp_set_post_categories( $post_ID, $post_category );
        }

        if ( isset( $postarr['tags_input'] ) && is_object_in_taxonomy( $post_type, 'post_tag' ) ) {
            wp_set_post_tags( $post_ID, $postarr['tags_input'] );
        }

        // New-style support for all custom taxonomies.
        if ( ! empty( $postarr['tax_input'] ) ) {
            foreach ( $postarr['tax_input'] as $taxonomy => $tags ) {
                $taxonomy_obj = get_taxonomy( $taxonomy );
                if ( ! $taxonomy_obj ) {
                    /* translators: %s: Taxonomy name. */
                    _doing_it_wrong( __FUNCTION__, sprintf( __( 'Invalid taxonomy: %s.' ), $taxonomy ), '4.4.0' );
                    continue;
                }

                // array = hierarchical, string = non-hierarchical.
                if ( is_array( $tags ) ) {
                    $tags = array_filter( $tags );
                }
                if ( current_user_can( $taxonomy_obj->cap->assign_terms ) ) {
                    wp_set_post_terms( $post_ID, $tags, $taxonomy );
                }
            }
        }

        if ( ! empty( $postarr['meta_input'] ) ) {
            foreach ( $postarr['meta_input'] as $field => $value ) {
                update_post_meta( $post_ID, $field, $value );
            }
        }

        $current_guid = get_post_field( 'guid', $post_ID );

        // Set GUID.
        if ( ! $update && '' == $current_guid ) {
            $wpdb->update( $wpdb->posts, array( 'guid' => get_permalink( $post_ID ) ), $where );
        }

        if ( 'attachment' === $postarr['post_type'] ) {
            if ( ! empty( $postarr['file'] ) ) {
                update_attached_file( $post_ID, $postarr['file'] );
            }

            if ( ! empty( $postarr['context'] ) ) {
                add_post_meta( $post_ID, '_wp_attachment_context', $postarr['context'], true );
            }
        }

        // Set or remove featured image.
        if ( isset( $postarr['_thumbnail_id'] ) ) {
            $thumbnail_support = current_theme_supports( 'post-thumbnails', $post_type ) && post_type_supports( $post_type, 'thumbnail' ) || 'revision' === $post_type;
            if ( ! $thumbnail_support && 'attachment' === $post_type && $post_mime_type ) {
                if ( wp_attachment_is( 'audio', $post_ID ) ) {
                    $thumbnail_support = post_type_supports( 'attachment:audio', 'thumbnail' ) || current_theme_supports( 'post-thumbnails', 'attachment:audio' );
                } elseif ( wp_attachment_is( 'video', $post_ID ) ) {
                    $thumbnail_support = post_type_supports( 'attachment:video', 'thumbnail' ) || current_theme_supports( 'post-thumbnails', 'attachment:video' );
                }
            }

            if ( $thumbnail_support ) {
                $thumbnail_id = intval( $postarr['_thumbnail_id'] );
                if ( -1 === $thumbnail_id ) {
                    delete_post_thumbnail( $post_ID );
                } else {
                    set_post_thumbnail( $post_ID, $thumbnail_id );
                }
            }
        }

        clean_post_cache( $post_ID );

        $post = static::get( $post_ID );

        if ( ! empty( $postarr['page_template'] ) ) {
            $post->page_template = $postarr['page_template'];
            $page_templates      = wp_get_theme()->get_page_templates( $post );
            if ( 'default' != $postarr['page_template'] && ! isset( $page_templates[ $postarr['page_template'] ] ) ) {
                if ( $wp_error ) {
                    return new WP_Error( 'invalid_page_template', __( 'Invalid page template.' ) );
                }
                update_post_meta( $post_ID, '_wp_page_template', 'default' );
            } else {
                update_post_meta( $post_ID, '_wp_page_template', $postarr['page_template'] );
            }
        }

        if ( 'attachment' !== $postarr['post_type'] ) {
            wp_transition_post_status( $data['post_status'], $previous_status, $post );
        } else {
            if ( $update ) {
                /**
                 * Fires once an existing attachment has been updated.
                 *
                 * @since 2.0.0
                 *
                 * @param int $post_ID Attachment ID.
                 */
                do_action( 'edit_attachment', $post_ID );
                $post_after = get_post( $post_ID );

                /**
                 * Fires once an existing attachment has been updated.
                 *
                 * @since 4.4.0
                 *
                 * @param int     $post_ID      Post ID.
                 * @param WP_Post $post_after   Post object following the update.
                 * @param WP_Post $post_before  Post object before the update.
                 */
                do_action( 'attachment_updated', $post_ID, $post_after, $post_before );
            } else {

                /**
                 * Fires once an attachment has been added.
                 *
                 * @since 2.0.0
                 *
                 * @param int $post_ID Attachment ID.
                 */
                do_action( 'add_attachment', $post_ID );
            }

            return $post_ID;
        }

        if ( $update ) {
            /**
             * Fires once an existing post has been updated.
             *
             * The dynamic portion of the hook name, `$post->post_type`, refers to
             * the post type slug.
             *
             * @since 5.1.0
             *
             * @param int     $post_ID Post ID.
             * @param WP_Post $post    Post object.
             */
            do_action( "edit_post_{$post->post_type}", $post_ID, $post );

            /**
             * Fires once an existing post has been updated.
             *
             * @since 1.2.0
             *
             * @param int     $post_ID Post ID.
             * @param WP_Post $post    Post object.
             */
            do_action( 'edit_post', $post_ID, $post );

            $post_after = static::get( $post_ID );

            /**
             * Fires once an existing post has been updated.
             *
             * @since 3.0.0
             *
             * @param int     $post_ID      Post ID.
             * @param WP_Post $post_after   Post object following the update.
             * @param WP_Post $post_before  Post object before the update.
             */
            do_action( 'post_updated', $post_ID, $post_after, $post_before );
        }

        /**
         * Fires once a post has been saved.
         *
         * The dynamic portion of the hook name, `$post->post_type`, refers to
         * the post type slug.
         *
         * @since 3.7.0
         *
         * @param int     $post_ID Post ID.
         * @param WP_Post $post    Post object.
         * @param bool    $update  Whether this is an existing post being updated or not.
         */
        do_action( "save_post_{$post->post_type}", $post_ID, $post, $update );

        /**
         * Fires once a post has been saved.
         *
         * @since 1.5.0
         *
         * @param int     $post_ID Post ID.
         * @param WP_Post $post    Post object.
         * @param bool    $update  Whether this is an existing post being updated or not.
         */
        do_action( 'save_post', $post_ID, $post, $update );

        /**
         * Fires once a post has been saved.
         *
         * @since 2.0.0
         *
         * @param int     $post_ID Post ID.
         * @param WP_Post $post    Post object.
         * @param bool    $update  Whether this is an existing post being updated or not.
         */
        do_action( 'wp_insert_post', $post_ID, $post, $update );

        return $post_ID;
    }

    /**
     * Update
     */
    public static function update(  $postarr = array(), $wp_error = false ) {
        if ( is_object( $postarr ) ) {
            // Non-escaped post was passed.
            $postarr = get_object_vars( $postarr );
            $postarr = wp_slash( $postarr );
        }

        // First, get all of the original fields.
        $post = static::get( $postarr['ID'], ARRAY_A );

        if ( is_null( $post ) ) {
            if ( $wp_error ) {
                return new WP_Error( 'invalid_post', __( 'Invalid post ID.' ) );
            }
            return 0;
        }

        // Escape data pulled from DB.
        $post = wp_slash( $post );

        // Passed post category list overwrites existing category list if not empty.
        if ( isset( $postarr['post_category'] ) && is_array( $postarr['post_category'] )
                && 0 != count( $postarr['post_category'] ) ) {
            $post_cats = $postarr['post_category'];
        } else {
            $post_cats = $post['post_category'];
        }

        // Drafts shouldn't be assigned a date unless explicitly done so by the user.
        if ( isset( $post['post_status'] ) && in_array( $post['post_status'], array( 'draft', 'pending', 'auto-draft' ) ) && empty( $postarr['edit_date'] ) &&
                ( '0000-00-00 00:00:00' == $post['post_date_gmt'] ) ) {
            $clear_date = true;
        } else {
            $clear_date = false;
        }

        // Merge old and new fields with new fields overwriting old ones.
        $postarr                  = array_merge( $post, $postarr );
        $postarr['post_category'] = $post_cats;
        if ( $clear_date ) {
            $postarr['post_date']     = current_time( 'mysql' );
            $postarr['post_date_gmt'] = '';
        }

        if ( $postarr['post_type'] == 'attachment' ) {
            return wp_insert_attachment( $postarr, false, 0, $wp_error );
        }

        return static::add( $postarr, $wp_error );
    }

    /**
     * Publish a post
     */
    public static function publish( $post ) {
        global $wpdb;

        $post = static::get( $post );
        if ( ! $post ) {
            return;
        }

        if ( 'publish' == $post->post_status ) {
            return;
        }

        $wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $post->ID ) );

        clean_post_cache( $post->ID );

        $old_status        = $post->post_status;
        $post->post_status = 'publish';
        wp_transition_post_status( 'publish', $old_status, $post );

        /** This action is documented in wp-includes/post.php */
        do_action( "edit_post_{$post->post_type}", $post->ID, $post );

        /** This action is documented in wp-includes/post.php */
        do_action( 'edit_post', $post->ID, $post );

        /** This action is documented in wp-includes/post.php */
        do_action( "save_post_{$post->post_type}", $post->ID, $post, true );

        /** This action is documented in wp-includes/post.php */
        do_action( 'save_post', $post->ID, $post, true );

        /** This action is documented in wp-includes/post.php */
        do_action( 'wp_insert_post', $post->ID, $post, true );
    }

    /**
     * Publish a future post
     */
    public static function publishFuturePost( $post_id ) {
        $post = static::get( $post_id );

        if ( empty( $post ) ) {
            return;
        }

        if ( 'future' != $post->post_status ) {
            return;
        }

        $time = strtotime( $post->post_date_gmt . ' GMT' );

        // Uh oh, someone jumped the gun!
        if ( $time > time() ) {
            wp_clear_scheduled_hook( 'publish_future_post', array( $post_id ) ); // clear anything else in the system
            wp_schedule_single_event( $time, 'publish_future_post', array( $post_id ) );
            return;
        }

        // wp_publish_post() returns no meaningful value.
        static::publish( $post_id );
    }


    /**
     * Retrieves the post type of the current post or of a given post.
     */
    public static function getPostType( $post = null ) {
        $post = get_post( $post );
        if ( $post ) {
            return $post->post_type;
        }

        return false;
    }

     /**
     * Retrieves the post type of the current post or of a given post.
     */
    public static function setPostType( $post_id = 0, $post_type = 'post' ) {
        global $wpdb;

        $post_type = sanitize_post_field( 'post_type', $post_type, $post_id, 'db' );
        $return    = $wpdb->update( $wpdb->posts, array( 'post_type' => $post_type ), array( 'ID' => $post_id ) );

        clean_post_cache( $post_id );

        return $return;
    }

    /**
     * Retrieve data from a post field based on Post ID.
     */
    public static function getField( $field, $post = null, $context = 'display' )
    {
        $post = get_post( $post );

        if ( ! $post ) {
            return '';
        }

        if ( ! isset( $post->$field ) ) {
            return '';
        }

        return sanitize_post_field( $field, $post->$field, $post->ID, $context );
    }

    /**
     * Delete a post
     */
    public static function delete( $postid = 0, $force_delete = false ) {
        global $wpdb;

        $post = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE ID = %d", $postid ) );

        if ( ! $post ) {
            return $post;
        }

        $post = get_post( $post );

        if ( ! $force_delete && ( 'post' === $post->post_type || 'page' === $post->post_type ) && 'trash' !== get_post_status( $postid ) && EMPTY_TRASH_DAYS ) {
            return wp_trash_post( $postid );
        }

        if ( 'attachment' === $post->post_type ) {
            return wp_delete_attachment( $postid, $force_delete );
        }

        /**
         * Filters whether a post deletion should take place.
         *
         * @since 4.4.0
         *
         * @param bool    $delete       Whether to go forward with deletion.
         * @param WP_Post $post         Post object.
         * @param bool    $force_delete Whether to bypass the trash.
         */
        $check = apply_filters( 'pre_delete_post', null, $post, $force_delete );
        if ( null !== $check ) {
            return $check;
        }

        /**
         * Fires before a post is deleted, at the start of wp_delete_post().
         *
         * @since 3.2.0
         *
         * @see wp_delete_post()
         *
         * @param int $postid Post ID.
         */
        do_action( 'before_delete_post', $postid );

        delete_post_meta( $postid, '_wp_trash_meta_status' );
        delete_post_meta( $postid, '_wp_trash_meta_time' );

        wp_delete_object_term_relationships( $postid, get_object_taxonomies( $post->post_type ) );

        $parent_data  = array( 'post_parent' => $post->post_parent );
        $parent_where = array( 'post_parent' => $postid );

        if ( is_post_type_hierarchical( $post->post_type ) ) {
            // Point children of this page to its parent, also clean the cache of affected children.
            $children_query = $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE post_parent = %d AND post_type = %s", $postid, $post->post_type );
            $children       = $wpdb->get_results( $children_query );
            if ( $children ) {
                $wpdb->update( $wpdb->posts, $parent_data, $parent_where + array( 'post_type' => $post->post_type ) );
            }
        }

        // Do raw query. wp_get_post_revisions() is filtered.
        $revision_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_parent = %d AND post_type = 'revision'", $postid ) );
        // Use wp_delete_post (via wp_delete_post_revision) again. Ensures any meta/misplaced data gets cleaned up.
        foreach ( $revision_ids as $revision_id ) {
            wp_delete_post_revision( $revision_id );
        }

        // Point all attachments to this post up one level.
        $wpdb->update( $wpdb->posts, $parent_data, $parent_where + array( 'post_type' => 'attachment' ) );

        wp_defer_comment_counting( true );

        $comment_ids = $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM $wpdb->comments WHERE comment_post_ID = %d", $postid ) );
        foreach ( $comment_ids as $comment_id ) {
            wp_delete_comment( $comment_id, true );
        }

        wp_defer_comment_counting( false );

        $post_meta_ids = $wpdb->get_col( $wpdb->prepare( "SELECT meta_id FROM $wpdb->postmeta WHERE post_id = %d ", $postid ) );
        foreach ( $post_meta_ids as $mid ) {
            delete_metadata_by_mid( 'post', $mid );
        }

        /**
         * Fires immediately before a post is deleted from the database.
         *
         * @since 1.2.0
         *
         * @param int $postid Post ID.
         */
        do_action( 'delete_post', $postid );
        $result = $wpdb->delete( $wpdb->posts, array( 'ID' => $postid ) );
        if ( ! $result ) {
            return false;
        }

        /**
         * Fires immediately after a post is deleted from the database.
         *
         * @since 2.2.0
         *
         * @param int $postid Post ID.
         */
        do_action( 'deleted_post', $postid );

        clean_post_cache( $post );

        if ( is_post_type_hierarchical( $post->post_type ) && $children ) {
            foreach ( $children as $child ) {
                clean_post_cache( $child );
            }
        }

        wp_clear_scheduled_hook( 'publish_future_post', array( $postid ) );

        /**
         * Fires after a post is deleted, at the conclusion of wp_delete_post().
         *
         * @since 3.2.0
         *
         * @see wp_delete_post()
         *
         * @param int $postid Post ID.
         */
        do_action( 'after_delete_post', $postid );

        return $post;
    }


    public static function trash( $post_id ) {
        if ( ! EMPTY_TRASH_DAYS ) {
            return static::delete( $post_id, true );
        }

        $post = static::get( $post_id );

        if ( ! $post ) {
            return $post;
        }

        if ( 'trash' === $post->post_status ) {
            return false;
        }

        /**
         * Filters whether a post trashing should take place.
         *
         * @since 4.9.0
         *
         * @param bool    $trash Whether to go forward with trashing.
         * @param WP_Post $post  Post object.
         */
        $check = apply_filters( 'pre_trash_post', null, $post );
        if ( null !== $check ) {
            return $check;
        }

        /**
         * Fires before a post is sent to the trash.
         *
         * @since 3.3.0
         *
         * @param int $post_id Post ID.
         */
        do_action( 'wp_trash_post', $post_id );

        add_post_meta( $post_id, '_wp_trash_meta_status', $post->post_status );
        add_post_meta( $post_id, '_wp_trash_meta_time', time() );

        $post_updated = wp_update_post(
            array(
                'ID'          => $post_id,
                'post_status' => 'trash',
            )
        );

        if ( ! $post_updated ) {
            return false;
        }

        wp_trash_post_comments( $post_id );

        /**
         * Fires after a post is sent to the trash.
         *
         * @since 2.9.0
         *
         * @param int $post_id Post ID.
         */
        do_action( 'trashed_post', $post_id );

        return $post;
    }


    /**
     * Untrash a post
     */
    public static function untrash( $post_id = 0 ) {
        $post = static::get( $post_id );

        if ( ! $post ) {
            return $post;
        }

        if ( 'trash' !== $post->post_status ) {
            return false;
        }

        /**
         * Filters whether a post untrashing should take place.
         *
         * @since 4.9.0
         *
         * @param bool    $untrash Whether to go forward with untrashing.
         * @param WP_Post $post    Post object.
         */
        $check = apply_filters( 'pre_untrash_post', null, $post );
        if ( null !== $check ) {
            return $check;
        }

        /**
         * Fires before a post is restored from the trash.
         *
         * @since 2.9.0
         *
         * @param int $post_id Post ID.
         */
        do_action( 'untrash_post', $post_id );

        $post_status = get_post_meta( $post_id, '_wp_trash_meta_status', true );

        delete_post_meta( $post_id, '_wp_trash_meta_status' );
        delete_post_meta( $post_id, '_wp_trash_meta_time' );

        $post_updated = wp_update_post(
            array(
                'ID'          => $post_id,
                'post_status' => $post_status,
            )
        );

        if ( ! $post_updated ) {
            return false;
        }

        wp_untrash_post_comments( $post_id );

        /**
         * Fires after a post is restored from the trash.
         *
         * @since 2.9.0
         *
         * @param int $post_id Post ID.
         */
        do_action( 'untrashed_post', $post_id );

        return $post;
    }

    /**
     * Count posts per post-type
     */
    public static function count( $type = 'post', $perm = '' ) {
        global $wpdb;

        if ( ! post_type_exists( $type ) ) {
            return new \stdClass;
        }

        $cache_key = _count_posts_cache_key( $type, $perm );

        $counts = wp_cache_get( $cache_key, 'counts' );
        if ( false !== $counts ) {
            /** This filter is documented in wp-includes/post.php */
            return apply_filters( 'wp_count_posts', $counts, $type, $perm );
        }

        $query = "SELECT post_status, COUNT( * ) AS num_posts FROM {$wpdb->posts} WHERE post_type = %s";
        if ( 'readable' == $perm && is_user_logged_in() ) {
            $post_type_object = get_post_type_object( $type );
            if ( ! current_user_can( $post_type_object->cap->read_private_posts ) ) {
                $query .= $wpdb->prepare(
                    " AND (post_status != 'private' OR ( post_author = %d AND post_status = 'private' ))",
                    get_current_user_id()
                );
            }
        }
        $query .= ' GROUP BY post_status';

        $results = (array) $wpdb->get_results( $wpdb->prepare( $query, $type ), ARRAY_A );
        $counts  = array_fill_keys( get_post_stati(), 0 );

        foreach ( $results as $row ) {
            $counts[ $row['post_status'] ] = $row['num_posts'];
        }

        $counts = (object) $counts;
        wp_cache_set( $cache_key, $counts, 'counts' );

        /**
         * Modify returned post counts by status for the current post type.
         *
         * @since 3.7.0
         *
         * @param object $counts An object containing the current post_type's post
         *                       counts by status.
         * @param string $type   Post type.
         * @param string $perm   The permission to determine if the posts are 'readable'
         *                       by the current user.
         */
        return apply_filters( 'wp_count_posts', $counts, $type, $perm );
    }

    /**
     * Check if this post is sticky
     */
    public static function isSticky( $post_id = 0 ) {
        $post_id = absint( $post_id );

        if ( ! $post_id ) {
            $post_id = get_the_ID();
        }

        $stickies = get_option( 'sticky_posts' );

        $is_sticky = is_array( $stickies ) && in_array( $post_id, $stickies );

        /**
         * Filters whether a post is sticky.
         *
         * @since 5.3.0
         *
         * @param bool $is_sticky Whether a post is sticky.
         * @param int  $post_id   Post ID.
         */
        return apply_filters( 'is_sticky', $is_sticky, $post_id );
    }

    /**
     * Make a post sticky
     */
    public static function makeSticky( $post_id ) {
        $stickies = get_option( 'sticky_posts' );

        if ( ! is_array( $stickies ) ) {
            $stickies = array( $post_id );
        }

        if ( ! in_array( $post_id, $stickies ) ) {
            $stickies[] = $post_id;
        }

        $updated = update_option( 'sticky_posts', $stickies );

        if ( $updated ) {
            /**
             * Fires once a post has been added to the sticky list.
             *
             * @since 4.6.0
             *
             * @param int $post_id ID of the post that was stuck.
             */
            do_action( 'post_stuck', $post_id );
        }
    }

    /**
     * Make unsticky
     */
    public static function makeUnsticky( $post_id ) {
        $stickies = get_option( 'sticky_posts' );

        if ( ! is_array( $stickies ) ) {
            return;
        }

        if ( ! in_array( $post_id, $stickies ) ) {
            return;
        }

        $offset = array_search( $post_id, $stickies );
        if ( false === $offset ) {
            return;
        }

        array_splice( $stickies, $offset, 1 );

        $updated = update_option( 'sticky_posts', $stickies );

        if ( $updated ) {
            /**
             * Fires once a post has been removed from the sticky list.
             *
             * @since 4.6.0
             *
             * @param int $post_id ID of the post that was unstuck.
             */
            do_action( 'post_unstuck', $post_id );
        }
    }


    /**
     * Reset the frontpage settings for a post
     */
    public static function resetFrontPageSettings( $post_id ) {

        $post = static::get( $post_id );
        if ( 'page' == $post->post_type ) {
            /*
            * If the page is defined in option page_on_front or post_for_posts,
            * adjust the corresponding options.
            */
            if ( get_option( 'page_on_front' ) == $post->ID ) {
                update_option( 'show_on_front', 'posts' );
                update_option( 'page_on_front', 0 );
            }
            if ( get_option( 'page_for_posts' ) == $post->ID ) {
                update_option( 'page_for_posts', 0 );
            }
        }

        static::makeUnsticky( $post->ID );
    }

    /**
     * Sanitize a post
     */
    public static function sanitize( $post, $context ) {
        if ( is_object( $post ) ) {
            // Check if post already filtered for this context.
            if ( isset( $post->filter ) && $context == $post->filter ) {
                return $post;
            }
            if ( ! isset( $post->ID ) ) {
                $post->ID = 0;
            }
            foreach ( array_keys( get_object_vars( $post ) ) as $field ) {
                $post->$field = static::sanitizeField( $field, $post->$field, $post->ID, $context );
            }
            $post->filter = $context;
        } elseif ( is_array( $post ) ) {
            // Check if post already filtered for this context.
            if ( isset( $post['filter'] ) && $context == $post['filter'] ) {
                return $post;
            }
            if ( ! isset( $post['ID'] ) ) {
                $post['ID'] = 0;
            }
            foreach ( array_keys( $post ) as $field ) {
                $post[ $field ] = static::sanitizeField( $field, $post[ $field ], $post['ID'], $context );
            }
            $post['filter'] = $context;
        }
        return $post;
    }


    /**
     * Sanitize a field
     */
    public static function sanitizeField( $field, $value, $post_id, $context = 'display' ) {

        $int_fields = array( 'ID', 'post_parent', 'menu_order' );
        if ( in_array( $field, $int_fields ) ) {
            $value = (int) $value;
        }

        // Fields which contain arrays of integers.
        $array_int_fields = array( 'ancestors' );
        if ( in_array( $field, $array_int_fields ) ) {
            $value = array_map( 'absint', $value );
            return $value;
        }

        if ( 'raw' == $context ) {
            return $value;
        }

        $prefixed = false;
        if ( false !== strpos( $field, 'post_' ) ) {
            $prefixed        = true;
            $field_no_prefix = str_replace( 'post_', '', $field );
        }

        if ( 'edit' == $context ) {
            $format_to_edit = array( 'post_content', 'post_excerpt', 'post_title', 'post_password' );

            if ( $prefixed ) {

                /**
                 * Filters the value of a specific post field to edit.
                 *
                 * The dynamic portion of the hook name, `$field`, refers to the post
                 * field name.
                 *
                 * @since 2.3.0
                 *
                 * @param mixed $value   Value of the post field.
                 * @param int   $post_id Post ID.
                 */
                $value = apply_filters( "edit_{$field}", $value, $post_id );

                /**
                 * Filters the value of a specific post field to edit.
                 *
                 * The dynamic portion of the hook name, `$field_no_prefix`, refers to
                 * the post field name.
                 *
                 * @since 2.3.0
                 *
                 * @param mixed $value   Value of the post field.
                 * @param int   $post_id Post ID.
                 */
                $value = apply_filters( "{$field_no_prefix}_edit_pre", $value, $post_id );
            } else {
                $value = apply_filters( "edit_post_{$field}", $value, $post_id );
            }

            if ( in_array( $field, $format_to_edit ) ) {
                if ( 'post_content' == $field ) {
                    $value = format_to_edit( $value, user_can_richedit() );
                } else {
                    $value = format_to_edit( $value );
                }
            } else {
                $value = esc_attr( $value );
            }
        } elseif ( 'db' == $context ) {
            if ( $prefixed ) {

                /**
                 * Filters the value of a specific post field before saving.
                 *
                 * The dynamic portion of the hook name, `$field`, refers to the post
                 * field name.
                 *
                 * @since 2.3.0
                 *
                 * @param mixed $value Value of the post field.
                 */
                $value = apply_filters( "pre_{$field}", $value );

                /**
                 * Filters the value of a specific field before saving.
                 *
                 * The dynamic portion of the hook name, `$field_no_prefix`, refers
                 * to the post field name.
                 *
                 * @since 2.3.0
                 *
                 * @param mixed $value Value of the post field.
                 */
                $value = apply_filters( "{$field_no_prefix}_save_pre", $value );
            } else {
                $value = apply_filters( "pre_post_{$field}", $value );

                /**
                 * Filters the value of a specific post field before saving.
                 *
                 * The dynamic portion of the hook name, `$field`, refers to the post
                 * field name.
                 *
                 * @since 2.3.0
                 *
                 * @param mixed $value Value of the post field.
                 */
                $value = apply_filters( "{$field}_pre", $value );
            }
        } else {

            // Use display filters by default.
            if ( $prefixed ) {

                /**
                 * Filters the value of a specific post field for display.
                 *
                 * The dynamic portion of the hook name, `$field`, refers to the post
                 * field name.
                 *
                 * @since 2.3.0
                 *
                 * @param mixed  $value   Value of the prefixed post field.
                 * @param int    $post_id Post ID.
                 * @param string $context Context for how to sanitize the field. Possible
                 *                        values include 'raw', 'edit', 'db', 'display',
                 *                        'attribute' and 'js'.
                 */
                $value = apply_filters( "{$field}", $value, $post_id, $context );
            } else {
                $value = apply_filters( "post_{$field}", $value, $post_id, $context );
            }

            if ( 'attribute' == $context ) {
                $value = esc_attr( $value );
            } elseif ( 'js' == $context ) {
                $value = esc_js( $value );
            }
        }

        return $value;
    }


    /**
     * Get extended entry info (<!--more-->).
     */
    public static function getExtended( $post ) {
        //Match the new style more links.
        if ( preg_match( '/<!--more(.*?)?-->/', $post, $matches ) ) {
            list($main, $extended) = explode( $matches[0], $post, 2 );
            $more_text             = $matches[1];
        } else {
            $main      = $post;
            $extended  = '';
            $more_text = '';
        }

        //  leading and trailing whitespace.
        $main      = preg_replace( '/^[\s]*(.*)[\s]*$/', '\\1', $main );
        $extended  = preg_replace( '/^[\s]*(.*)[\s]*$/', '\\1', $extended );
        $more_text = preg_replace( '/^[\s]*(.*)[\s]*$/', '\\1', $more_text );

        return array(
            'main'      => $main,
            'extended'  => $extended,
            'more_text' => $more_text,
        );
    }


}
