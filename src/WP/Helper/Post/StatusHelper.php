<?php

namespace WP\Helper\Post;

class StatusHelper{

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
    public static function getPostStatuses( $args = array(), $output = 'names', $operator = 'and' ) {
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

    /**
     * Transition post statusses
     */
    public static function transition( $new_status, $old_status, $post ) {
        /**
         * Fires when a post is transitioned from one status to another.
         *
         * @since 2.3.0
         *
         * @param string  $new_status New post status.
         * @param string  $old_status Old post status.
         * @param WP_Post $post       Post object.
         */
        do_action( 'transition_post_status', $new_status, $old_status, $post );

        /**
         * Fires when a post is transitioned from one status to another.
         *
         * The dynamic portions of the hook name, `$new_status` and `$old status`,
         * refer to the old and new post statuses, respectively.
         *
         * @since 2.3.0
         *
         * @param WP_Post $post Post object.
         */
        do_action( "{$old_status}_to_{$new_status}", $post );

        /**
         * Fires when a post is transitioned from one status to another.
         *
         * The dynamic portions of the hook name, `$new_status` and `$post->post_type`,
         * refer to the new post status and post type, respectively.
         *
         * Please note: When this action is hooked using a particular post status (like
         * 'publish', as `publish_{$post->post_type}`), it will fire both when a post is
         * first transitioned to that status from something else, as well as upon
         * subsequent post updates (old and new status are both the same).
         *
         * Therefore, if you are looking to only fire a callback when a post is first
         * transitioned to a status, use the {@see 'transition_post_status'} hook instead.
         *
         * @since 2.3.0
         *
         * @param int     $post_id Post ID.
         * @param WP_Post $post    Post object.
         */
        do_action( "{$new_status}_{$post->post_type}", $post->ID, $post );
    }
}