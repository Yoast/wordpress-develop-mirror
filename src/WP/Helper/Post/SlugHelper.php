<?php

namespace WP\Helper\Post;

class SlugHelper{

    /**
     * Check if a post-slug is unique
     *
     * @return boolean
     */
    public static function isUnique( $slug, $post_ID, $post_status, $post_type, $post_parent ) {
        if ( in_array( $post_status, array( 'draft', 'pending', 'auto-draft' ) ) || ( 'inherit' == $post_status && 'revision' == $post_type ) || 'user_request' === $post_type ) {
            return $slug;
        }

        /**
         * Filters the post slug before it is generated to be unique.
         *
         * Returning a non-null value will short-circuit the
         * unique slug generation, returning the passed value instead.
         *
         * @since 5.1.0
         *
         * @param string $override_slug Short-circuit return value.
         * @param string $slug          The desired slug (post_name).
         * @param int    $post_ID       Post ID.
         * @param string $post_status   The post status.
         * @param string $post_type     Post type.
         * @param int    $post_parent   Post parent ID.
         */
        $override_slug = apply_filters( 'pre_wp_unique_post_slug', null, $slug, $post_ID, $post_status, $post_type, $post_parent );
        if ( null !== $override_slug ) {
            return $override_slug;
        }

        global $wpdb, $wp_rewrite;

        $original_slug = $slug;

        $feeds = $wp_rewrite->feeds;
        if ( ! is_array( $feeds ) ) {
            $feeds = array();
        }

        if ( 'attachment' == $post_type ) {
            // Attachment slugs must be unique across all types.
            $check_sql       = "SELECT post_name FROM $wpdb->posts WHERE post_name = %s AND ID != %d LIMIT 1";
            $post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $slug, $post_ID ) );

            /**
             * Filters whether the post slug would make a bad attachment slug.
             *
             * @since 3.1.0
             *
             * @param bool   $bad_slug Whether the slug would be bad as an attachment slug.
             * @param string $slug     The post slug.
             */
            if ( $post_name_check || in_array( $slug, $feeds ) || 'embed' === $slug || apply_filters( 'wp_unique_post_slug_is_bad_attachment_slug', false, $slug ) ) {
                $suffix = 2;
                do {
                    $alt_post_name   = static::truncate( $slug, 200 - ( strlen( $suffix ) + 1 ) ) . "-$suffix";
                    $post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $alt_post_name, $post_ID ) );
                    $suffix++;
                } while ( $post_name_check );
                $slug = $alt_post_name;
            }
        } elseif ( is_post_type_hierarchical( $post_type ) ) {
            if ( 'nav_menu_item' == $post_type ) {
                return $slug;
            }

            /*
            * Page slugs must be unique within their own trees. Pages are in a separate
            * namespace than posts so page slugs are allowed to overlap post slugs.
            */
            $check_sql       = "SELECT post_name FROM $wpdb->posts WHERE post_name = %s AND post_type IN ( %s, 'attachment' ) AND ID != %d AND post_parent = %d LIMIT 1";
            $post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $slug, $post_type, $post_ID, $post_parent ) );

            /**
             * Filters whether the post slug would make a bad hierarchical post slug.
             *
             * @since 3.1.0
             *
             * @param bool   $bad_slug    Whether the post slug would be bad in a hierarchical post context.
             * @param string $slug        The post slug.
             * @param string $post_type   Post type.
             * @param int    $post_parent Post parent ID.
             */
            if ( $post_name_check || in_array( $slug, $feeds ) || 'embed' === $slug || preg_match( "@^($wp_rewrite->pagination_base)?\d+$@", $slug ) || apply_filters( 'wp_unique_post_slug_is_bad_hierarchical_slug', false, $slug, $post_type, $post_parent ) ) {
                $suffix = 2;
                do {
                    $alt_post_name   = static::truncate( $slug, 200 - ( strlen( $suffix ) + 1 ) ) . "-$suffix";
                    $post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $alt_post_name, $post_type, $post_ID, $post_parent ) );
                    $suffix++;
                } while ( $post_name_check );
                $slug = $alt_post_name;
            }
        } else {
            // Post slugs must be unique across all posts.
            $check_sql       = "SELECT post_name FROM $wpdb->posts WHERE post_name = %s AND post_type = %s AND ID != %d LIMIT 1";
            $post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $slug, $post_type, $post_ID ) );

            // Prevent new post slugs that could result in URLs that conflict with date archives.
            $post                        = static::get( $post_ID );
            $conflicts_with_date_archive = false;
            if ( 'post' === $post_type && ( ! $post || $post->post_name !== $slug ) && preg_match( '/^[0-9]+$/', $slug ) ) {
                $slug_num = intval( $slug );

                if ( $slug_num ) {
                    $permastructs   = array_values( array_filter( explode( '/', get_option( 'permalink_structure' ) ) ) );
                    $postname_index = array_search( '%postname%', $permastructs );

                    /*
                    * Potential date clashes are as follows:
                    *
                    * - Any integer in the first permastruct position could be a year.
                    * - An integer between 1 and 12 that follows 'year' conflicts with 'monthnum'.
                    * - An integer between 1 and 31 that follows 'monthnum' conflicts with 'day'.
                    */
                    if ( 0 === $postname_index ||
                        ( $postname_index && '%year%' === $permastructs[ $postname_index - 1 ] && 13 > $slug_num ) ||
                        ( $postname_index && '%monthnum%' === $permastructs[ $postname_index - 1 ] && 32 > $slug_num )
                    ) {
                        $conflicts_with_date_archive = true;
                    }
                }
            }

            /**
             * Filters whether the post slug would be bad as a flat slug.
             *
             * @since 3.1.0
             *
             * @param bool   $bad_slug  Whether the post slug would be bad as a flat slug.
             * @param string $slug      The post slug.
             * @param string $post_type Post type.
             */
            if ( $post_name_check || in_array( $slug, $feeds ) || 'embed' === $slug || $conflicts_with_date_archive || apply_filters( 'wp_unique_post_slug_is_bad_flat_slug', false, $slug, $post_type ) ) {
                $suffix = 2;
                do {
                    $alt_post_name   = static::truncate( $slug, 200 - ( strlen( $suffix ) + 1 ) ) . "-$suffix";
                    $post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $alt_post_name, $post_type, $post_ID ) );
                    $suffix++;
                } while ( $post_name_check );
                $slug = $alt_post_name;
            }
        }

        /**
         * Filters the unique post slug.
         *
         * @since 3.3.0
         *
         * @param string $slug          The post slug.
         * @param int    $post_ID       Post ID.
         * @param string $post_status   The post status.
         * @param string $post_type     Post type.
         * @param int    $post_parent   Post parent ID
         * @param string $original_slug The original post slug.
         */
        return apply_filters( 'wp_unique_post_slug', $slug, $post_ID, $post_status, $post_type, $post_parent, $original_slug );
        
    }

    /**
     * Truncate a slug
     */
    public static function truncate( $slug, $length = 200 ) {
        if ( strlen( $slug ) > $length ) {
            $decoded_slug = urldecode( $slug );
            if ( $decoded_slug === $slug ) {
                $slug = substr( $slug, 0, $length );
            } else {
                $slug = utf8_uri_encode( $decoded_slug, $length );
            }
        }

        return rtrim( $slug, '-' );
    }

}