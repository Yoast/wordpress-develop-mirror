<?php

namespace WP\Helper\Post;

class PostStatusHelper{

    /**
     * Retrieve the post status based on the post ID.
     */
    public static function get( $post = null ) {
        $post = get_post( $post );

        if ( ! is_object( $post ) ) {
            return false;
        }

        if ( 'attachment' == $post->post_type ) {
            if ( 'private' == $post->post_status ) {
                return 'private';
            }

            // Unattached attachments are assumed to be published.
            if ( ( 'inherit' == $post->post_status ) && ( 0 == $post->post_parent ) ) {
                return 'publish';
            }

            // Inherit status from the parent.
            if ( $post->post_parent && ( $post->ID != $post->post_parent ) ) {
                $parent_post_status = get_post_status( $post->post_parent );
                if ( 'trash' == $parent_post_status ) {
                    return get_post_meta( $post->post_parent, '_wp_trash_meta_status', true );
                } else {
                    return $parent_post_status;
                }
            }
        }

        /**
         * Filters the post status.
         *
         * @since 4.4.0
         *
         * @param string  $post_status The post status.
         * @param WP_Post $post        The post object.
         */
        return apply_filters( 'get_post_status', $post->post_status, $post );
    }

    /**
     * Retrieve a post status object by name
     */
    public static function getObject( $post_status ) {
        global $wp_post_statuses;

        if ( empty( $wp_post_statuses[ $post_status ] ) ) {
            return null;
        }

        return $wp_post_statuses[ $post_status ];
    }
    
    /**
     * Return post statuses:
     */
    public static function getPostStatuses() {
        global $wp_post_statuses;

        $field = ( 'names' == $output ) ? 'name' : false;

        return wp_filter_object_list( $wp_post_statuses, $args, $operator, $field );
    }

    /**
     * Return all post statuses
     */
    public static function getPossiblePostStatuses() {
        $status = array(
            'draft'   => __( 'Draft' ),
            'pending' => __( 'Pending Review' ),
            'private' => __( 'Private' ),
            'publish' => __( 'Published' ),
        );

        return $status;
    }

    /**
     * Retrieve all of the WordPress support page statuses
     */
    public static function getPossiblePageStatuses() {
        $status = array(
            'draft'   => __( 'Draft' ),
            'private' => __( 'Private' ),
            'publish' => __( 'Published' ),
        );

        return $status;
    }

    /**
     * Return statuses for privacy requests
     */
    public static function getPrivacyStatuses() {
        return array(
            'request-pending'   => __( 'Pending' ),      // Pending confirmation from user.
            'request-confirmed' => __( 'Confirmed' ),    // User has confirmed the action.
            'request-failed'    => __( 'Failed' ),       // User failed to confirm the action.
            'request-completed' => __( 'Completed' ),    // Admin has handled the request.
        );
    }

    /**
     * Register a post status. Do not use before init.
     */
    public static function register( $post_status, $args = array() ) {
        global $wp_post_statuses;

        if ( ! is_array( $wp_post_statuses ) ) {
            $wp_post_statuses = array();
        }

        // Args prefixed with an underscore are reserved for internal use.
        $defaults = array(
            'label'                     => false,
            'label_count'               => false,
            'exclude_from_search'       => null,
            '_builtin'                  => false,
            'public'                    => null,
            'internal'                  => null,
            'protected'                 => null,
            'private'                   => null,
            'publicly_queryable'        => null,
            'show_in_admin_status_list' => null,
            'show_in_admin_all_list'    => null,
        );
        $args     = wp_parse_args( $args, $defaults );
        $args     = (object) $args;

        $post_status = sanitize_key( $post_status );
        $args->name  = $post_status;

        // Set various defaults.
        if ( null === $args->public && null === $args->internal && null === $args->protected && null === $args->private ) {
            $args->internal = true;
        }

        if ( null === $args->public ) {
            $args->public = false;
        }

        if ( null === $args->private ) {
            $args->private = false;
        }

        if ( null === $args->protected ) {
            $args->protected = false;
        }

        if ( null === $args->internal ) {
            $args->internal = false;
        }

        if ( null === $args->publicly_queryable ) {
            $args->publicly_queryable = $args->public;
        }

        if ( null === $args->exclude_from_search ) {
            $args->exclude_from_search = $args->internal;
        }

        if ( null === $args->show_in_admin_all_list ) {
            $args->show_in_admin_all_list = ! $args->internal;
        }

        if ( null === $args->show_in_admin_status_list ) {
            $args->show_in_admin_status_list = ! $args->internal;
        }

        if ( false === $args->label ) {
            $args->label = $post_status;
        }

        if ( false === $args->label_count ) {
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralSingle,WordPress.WP.I18n.NonSingularStringLiteralPlural
            $args->label_count = _n_noop( $args->label, $args->label );
        }

        $wp_post_statuses[ $post_status ] = $args;

        return $args;
    }
}