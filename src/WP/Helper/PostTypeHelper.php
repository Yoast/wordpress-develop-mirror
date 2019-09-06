<?php

namespace WP\Helper;

use WP_Error;
use WP_Post_Type;
use WP\Helper\Post\PostStatusHelper;

class PostTypeHelper{


    /**
     * Return a post-type as object
     */
    public static function get( $post_type ) {
        global $wp_post_types;

        if ( ! is_scalar( $post_type ) || empty( $wp_post_types[ $post_type ] ) ) {
            return null;
        }

        return $wp_post_types[ $post_type ];
    }

    /**
     * Register a post type
     *
     * @return WP_Post_Type|WP_Error The registered post type object, or an error object.
     */
    public static function register( $post_type, $args )
    {
        global $wp_post_types;

        if ( ! is_array( $wp_post_types ) ) {
            $wp_post_types = array();
        }

        // Sanitize post type name
        $post_type = sanitize_key( $post_type );

        if ( empty( $post_type ) || strlen( $post_type ) > 20 ) {
            _doing_it_wrong( __FUNCTION__, __( 'Post type names must be between 1 and 20 characters in length.' ), '4.2.0' );
            return new WP_Error( 'post_type_length_invalid', __( 'Post type names must be between 1 and 20 characters in length.' ) );
        }

        $post_type_object = new WP_Post_Type( $post_type, $args );
        $post_type_object->add_supports();
        $post_type_object->add_rewrite_rules();
        $post_type_object->register_meta_boxes();

        $wp_post_types[ $post_type ] = $post_type_object;

        $post_type_object->add_hooks();
        $post_type_object->register_taxonomies();

        /**
         * Fires after a post type is registered.
         *
         * @since 3.3.0
         * @since 4.6.0 Converted the `$post_type` parameter to accept a `WP_Post_Type` object.
         *
         * @param string       $post_type        Post type.
         * @param WP_Post_Type $post_type_object Arguments used to register the post type.
         */
        do_action( 'registered_post_type', $post_type, $post_type_object );

        return $post_type_object;
    }

    /**
     * Determines whether a post type is registered.
     */
    public static function exists( $post_type ) {
        return (bool) static::get( $post_type );
    }


    

    /**
     * Whether the post type is hierarchical.
     */
    public static function isHierarchical( $post_type ) {
        if ( ! post_type_exists( $post_type ) ) {
            return false;
        }

        $post_type = get_post_type_object( $post_type );
        return $post_type->hierarchical;
    }

    /**
     * Registers support of certain features for a post type.
     */
    public static function addSupport( $post_type, $feature, ...$args  )
    {
        global $_wp_post_type_features;
        $features = (array) $feature;
        foreach ( $features as $feature ) {
            if ( $args ) {
                $_wp_post_type_features[ $post_type ][ $feature ] = $args;
            } else {
                $_wp_post_type_features[ $post_type ][ $feature ] = true;
            }
        }
    }


    /**
     * Create initial post-types
     *
     * @return void
     */
    public static function createInitialPostTypes()
    {
        static::register(
            'post',
            array(
                'labels'                => array(
                    'name_admin_bar' => _x( 'Post', 'add new from admin bar' ),
                ),
                'public'                => true,
                '_builtin'              => true, /* internal use only. don't use this when registering your own post type. */
                '_edit_link'            => 'post.php?post=%d', /* internal use only. don't use this when registering your own post type. */
                'capability_type'       => 'post',
                'map_meta_cap'          => true,
                'menu_position'         => 5,
                'hierarchical'          => false,
                'rewrite'               => false,
                'query_var'             => false,
                'delete_with_user'      => true,
                'supports'              => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'trackbacks', 'custom-fields', 'comments', 'revisions', 'post-formats' ),
                'show_in_rest'          => true,
                'rest_base'             => 'posts',
                'rest_controller_class' => 'WP_REST_Posts_Controller',
            )
        );

        static::register(
            'page',
            array(
                'labels'                => array(
                    'name_admin_bar' => _x( 'Page', 'add new from admin bar' ),
                ),
                'public'                => true,
                'publicly_queryable'    => false,
                '_builtin'              => true, /* internal use only. don't use this when registering your own post type. */
                '_edit_link'            => 'post.php?post=%d', /* internal use only. don't use this when registering your own post type. */
                'capability_type'       => 'page',
                'map_meta_cap'          => true,
                'menu_position'         => 20,
                'hierarchical'          => true,
                'rewrite'               => false,
                'query_var'             => false,
                'delete_with_user'      => true,
                'supports'              => array( 'title', 'editor', 'author', 'thumbnail', 'page-attributes', 'custom-fields', 'comments', 'revisions' ),
                'show_in_rest'          => true,
                'rest_base'             => 'pages',
                'rest_controller_class' => 'WP_REST_Posts_Controller',
            )
        );

        static::register(
            'attachment',
            array(
                'labels'                => array(
                    'name'           => _x( 'Media', 'post type general name' ),
                    'name_admin_bar' => _x( 'Media', 'add new from admin bar' ),
                    'add_new'        => _x( 'Add New', 'add new media' ),
                    'edit_item'      => __( 'Edit Media' ),
                    'view_item'      => __( 'View Attachment Page' ),
                    'attributes'     => __( 'Attachment Attributes' ),
                ),
                'public'                => true,
                'show_ui'               => true,
                '_builtin'              => true, /* internal use only. don't use this when registering your own post type. */
                '_edit_link'            => 'post.php?post=%d', /* internal use only. don't use this when registering your own post type. */
                'capability_type'       => 'post',
                'capabilities'          => array(
                    'create_posts' => 'upload_files',
                ),
                'map_meta_cap'          => true,
                'hierarchical'          => false,
                'rewrite'               => false,
                'query_var'             => false,
                'show_in_nav_menus'     => false,
                'delete_with_user'      => true,
                'supports'              => array( 'title', 'author', 'comments' ),
                'show_in_rest'          => true,
                'rest_base'             => 'media',
                'rest_controller_class' => 'WP_REST_Attachments_Controller',
            )
        );

        static::register(
            'revision',
            array(
                'labels'           => array(
                    'name'          => __( 'Revisions' ),
                    'singular_name' => __( 'Revision' ),
                ),
                'public'           => false,
                '_builtin'         => true, /* internal use only. don't use this when registering your own post type. */
                '_edit_link'       => 'revision.php?revision=%d', /* internal use only. don't use this when registering your own post type. */
                'capability_type'  => 'post',
                'map_meta_cap'     => true,
                'hierarchical'     => false,
                'rewrite'          => false,
                'query_var'        => false,
                'can_export'       => false,
                'delete_with_user' => true,
                'supports'         => array( 'author' ),
            )
        );

        static::register(
            'nav_menu_item',
            array(
                'labels'           => array(
                    'name'          => __( 'Navigation Menu Items' ),
                    'singular_name' => __( 'Navigation Menu Item' ),
                ),
                'public'           => false,
                '_builtin'         => true, /* internal use only. don't use this when registering your own post type. */
                'hierarchical'     => false,
                'rewrite'          => false,
                'delete_with_user' => false,
                'query_var'        => false,
            )
        );

        static::register(
            'custom_css',
            array(
                'labels'           => array(
                    'name'          => __( 'Custom CSS' ),
                    'singular_name' => __( 'Custom CSS' ),
                ),
                'public'           => false,
                'hierarchical'     => false,
                'rewrite'          => false,
                'query_var'        => false,
                'delete_with_user' => false,
                'can_export'       => true,
                '_builtin'         => true, /* internal use only. don't use this when registering your own post type. */
                'supports'         => array( 'title', 'revisions' ),
                'capabilities'     => array(
                    'delete_posts'           => 'edit_theme_options',
                    'delete_post'            => 'edit_theme_options',
                    'delete_published_posts' => 'edit_theme_options',
                    'delete_private_posts'   => 'edit_theme_options',
                    'delete_others_posts'    => 'edit_theme_options',
                    'edit_post'              => 'edit_css',
                    'edit_posts'             => 'edit_css',
                    'edit_others_posts'      => 'edit_css',
                    'edit_published_posts'   => 'edit_css',
                    'read_post'              => 'read',
                    'read_private_posts'     => 'read',
                    'publish_posts'          => 'edit_theme_options',
                ),
            )
        );

        static::register(
            'customize_changeset',
            array(
                'labels'           => array(
                    'name'               => _x( 'Changesets', 'post type general name' ),
                    'singular_name'      => _x( 'Changeset', 'post type singular name' ),
                    'menu_name'          => _x( 'Changesets', 'admin menu' ),
                    'name_admin_bar'     => _x( 'Changeset', 'add new on admin bar' ),
                    'add_new'            => _x( 'Add New', 'Customize Changeset' ),
                    'add_new_item'       => __( 'Add New Changeset' ),
                    'new_item'           => __( 'New Changeset' ),
                    'edit_item'          => __( 'Edit Changeset' ),
                    'view_item'          => __( 'View Changeset' ),
                    'all_items'          => __( 'All Changesets' ),
                    'search_items'       => __( 'Search Changesets' ),
                    'not_found'          => __( 'No changesets found.' ),
                    'not_found_in_trash' => __( 'No changesets found in Trash.' ),
                ),
                'public'           => false,
                '_builtin'         => true, /* internal use only. don't use this when registering your own post type. */
                'map_meta_cap'     => true,
                'hierarchical'     => false,
                'rewrite'          => false,
                'query_var'        => false,
                'can_export'       => false,
                'delete_with_user' => false,
                'supports'         => array( 'title', 'author' ),
                'capability_type'  => 'customize_changeset',
                'capabilities'     => array(
                    'create_posts'           => 'customize',
                    'delete_others_posts'    => 'customize',
                    'delete_post'            => 'customize',
                    'delete_posts'           => 'customize',
                    'delete_private_posts'   => 'customize',
                    'delete_published_posts' => 'customize',
                    'edit_others_posts'      => 'customize',
                    'edit_post'              => 'customize',
                    'edit_posts'             => 'customize',
                    'edit_private_posts'     => 'customize',
                    'edit_published_posts'   => 'do_not_allow',
                    'publish_posts'          => 'customize',
                    'read'                   => 'read',
                    'read_post'              => 'customize',
                    'read_private_posts'     => 'customize',
                ),
            )
        );

        static::register(
            'oembed_cache',
            array(
                'labels'           => array(
                    'name'          => __( 'oEmbed Responses' ),
                    'singular_name' => __( 'oEmbed Response' ),
                ),
                'public'           => false,
                'hierarchical'     => false,
                'rewrite'          => false,
                'query_var'        => false,
                'delete_with_user' => false,
                'can_export'       => false,
                '_builtin'         => true, /* internal use only. don't use this when registering your own post type. */
                'supports'         => array(),
            )
        );

        static::register(
            'user_request',
            array(
                'labels'           => array(
                    'name'          => __( 'User Requests' ),
                    'singular_name' => __( 'User Request' ),
                ),
                'public'           => false,
                '_builtin'         => true, /* internal use only. don't use this when registering your own post type. */
                'hierarchical'     => false,
                'rewrite'          => false,
                'query_var'        => false,
                'can_export'       => false,
                'delete_with_user' => false,
                'supports'         => array(),
            )
        );

        static::register(
            'wp_block',
            array(
                'labels'                => array(
                    'name'                     => _x( 'Blocks', 'post type general name' ),
                    'singular_name'            => _x( 'Block', 'post type singular name' ),
                    'menu_name'                => _x( 'Blocks', 'admin menu' ),
                    'name_admin_bar'           => _x( 'Block', 'add new on admin bar' ),
                    'add_new'                  => _x( 'Add New', 'Block' ),
                    'add_new_item'             => __( 'Add New Block' ),
                    'new_item'                 => __( 'New Block' ),
                    'edit_item'                => __( 'Edit Block' ),
                    'view_item'                => __( 'View Block' ),
                    'all_items'                => __( 'All Blocks' ),
                    'search_items'             => __( 'Search Blocks' ),
                    'not_found'                => __( 'No blocks found.' ),
                    'not_found_in_trash'       => __( 'No blocks found in Trash.' ),
                    'filter_items_list'        => __( 'Filter blocks list' ),
                    'items_list_navigation'    => __( 'Blocks list navigation' ),
                    'items_list'               => __( 'Blocks list' ),
                    'item_published'           => __( 'Block published.' ),
                    'item_published_privately' => __( 'Block published privately.' ),
                    'item_reverted_to_draft'   => __( 'Block reverted to draft.' ),
                    'item_scheduled'           => __( 'Block scheduled.' ),
                    'item_updated'             => __( 'Block updated.' ),
                ),
                'public'                => false,
                '_builtin'              => true, /* internal use only. don't use this when registering your own post type. */
                'show_ui'               => true,
                'show_in_menu'          => false,
                'rewrite'               => false,
                'show_in_rest'          => true,
                'rest_base'             => 'blocks',
                'rest_controller_class' => 'WP_REST_Blocks_Controller',
                'capability_type'       => 'block',
                'capabilities'          => array(
                    // You need to be able to edit posts, in order to read blocks in their raw form.
                    'read'                   => 'edit_posts',
                    // You need to be able to publish posts, in order to create blocks.
                    'create_posts'           => 'publish_posts',
                    'edit_posts'             => 'edit_posts',
                    'edit_published_posts'   => 'edit_published_posts',
                    'delete_published_posts' => 'delete_published_posts',
                    'edit_others_posts'      => 'edit_others_posts',
                    'delete_others_posts'    => 'delete_others_posts',
                ),
                'map_meta_cap'          => true,
                'supports'              => array(
                    'title',
                    'editor',
                ),
            )
        );

        //add support:
        static::addSupport( 'attachment:audio', 'thumbnail' );
        static::addSupport( 'attachment:video', 'thumbnail' );


        PostStatusHelper::register(
            'publish',
            array(
                'label'       => _x( 'Published', 'post status' ),
                'public'      => true,
                '_builtin'    => true, /* internal use only. */
                /* translators: %s: Number of published posts. */
                'label_count' => _n_noop(
                    'Published <span class="count">(%s)</span>',
                    'Published <span class="count">(%s)</span>'
                ),
            )
        );

        PostStatusHelper::register(
            'future',
            array(
                'label'       => _x( 'Scheduled', 'post status' ),
                'protected'   => true,
                '_builtin'    => true, /* internal use only. */
                /* translators: %s: Number of scheduled posts. */
                'label_count' => _n_noop(
                    'Scheduled <span class="count">(%s)</span>',
                    'Scheduled <span class="count">(%s)</span>'
                ),
            )
        );

        PostStatusHelper::register(
            'draft',
            array(
                'label'       => _x( 'Draft', 'post status' ),
                'protected'   => true,
                '_builtin'    => true, /* internal use only. */
                /* translators: %s: Number of draft posts. */
                'label_count' => _n_noop(
                    'Draft <span class="count">(%s)</span>',
                    'Drafts <span class="count">(%s)</span>'
                ),
            )
        );

        PostStatusHelper::register(
            'pending',
            array(
                'label'       => _x( 'Pending', 'post status' ),
                'protected'   => true,
                '_builtin'    => true, /* internal use only. */
                /* translators: %s: Number of pending posts. */
                'label_count' => _n_noop(
                    'Pending <span class="count">(%s)</span>',
                    'Pending <span class="count">(%s)</span>'
                ),
            )
        );

        PostStatusHelper::register(
            'private',
            array(
                'label'       => _x( 'Private', 'post status' ),
                'private'     => true,
                '_builtin'    => true, /* internal use only. */
                /* translators: %s: Number of private posts. */
                'label_count' => _n_noop(
                    'Private <span class="count">(%s)</span>',
                    'Private <span class="count">(%s)</span>'
                ),
            )
        );

        PostStatusHelper::register(
            'trash',
            array(
                'label'                     => _x( 'Trash', 'post status' ),
                'internal'                  => true,
                '_builtin'                  => true, /* internal use only. */
                /* translators: %s: Number of trashed posts. */
                'label_count'               => _n_noop(
                    'Trash <span class="count">(%s)</span>',
                    'Trash <span class="count">(%s)</span>'
                ),
                'show_in_admin_status_list' => true,
            )
        );

        PostStatusHelper::register(
            'auto-draft',
            array(
                'label'    => 'auto-draft',
                'internal' => true,
                '_builtin' => true, /* internal use only. */
            )
        );

        PostStatusHelper::register(
            'inherit',
            array(
                'label'               => 'inherit',
                'internal'            => true,
                '_builtin'            => true, /* internal use only. */
                'exclude_from_search' => false,
            )
        );

        PostStatusHelper::register(
            'request-pending',
            array(
                'label'               => _x( 'Pending', 'request status' ),
                'internal'            => true,
                '_builtin'            => true, /* internal use only. */
                /* translators: %s: Number of pending requests. */
                'label_count'         => _n_noop(
                    'Pending <span class="count">(%s)</span>',
                    'Pending <span class="count">(%s)</span>'
                ),
                'exclude_from_search' => false,
            )
        );

        PostStatusHelper::register(
            'request-confirmed',
            array(
                'label'               => _x( 'Confirmed', 'request status' ),
                'internal'            => true,
                '_builtin'            => true, /* internal use only. */
                /* translators: %s: Number of confirmed requests. */
                'label_count'         => _n_noop(
                    'Confirmed <span class="count">(%s)</span>',
                    'Confirmed <span class="count">(%s)</span>'
                ),
                'exclude_from_search' => false,
            )
        );

        PostStatusHelper::register(
            'request-failed',
            array(
                'label'               => _x( 'Failed', 'request status' ),
                'internal'            => true,
                '_builtin'            => true, /* internal use only. */
                /* translators: %s: Number of failed requests. */
                'label_count'         => _n_noop(
                    'Failed <span class="count">(%s)</span>',
                    'Failed <span class="count">(%s)</span>'
                ),
                'exclude_from_search' => false,
            )
        );

        PostStatusHelper::register(
            'request-completed',
            array(
                'label'               => _x( 'Completed', 'request status' ),
                'internal'            => true,
                '_builtin'            => true, /* internal use only. */
                /* translators: %s: Number of completed requests. */
                'label_count'         => _n_noop(
                    'Completed <span class="count">(%s)</span>',
                    'Completed <span class="count">(%s)</span>'
                ),
                'exclude_from_search' => false,
            )
        );

    }
}