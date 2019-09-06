<?php

namespace WP\Helper\PostType;

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
     * Get a list of all registered post type objects.
     */
    public static function list( $args = array(), $output = 'names', $operator = 'and') {
        global $wp_post_types;

        $field = ( 'names' == $output ) ? 'name' : false;

        return wp_filter_object_list( $wp_post_types, $args, $operator, $field );
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
     * Unregister a post type
     */
    public static function unregister( $post_type ) {

        global $wp_post_types;

        if ( ! post_type_exists( $post_type ) ) {
            return new WP_Error( 'invalid_post_type', __( 'Invalid post type.' ) );
        }

        $post_type_object = get_post_type_object( $post_type );

        // Do not allow unregistering internal post types.
        if ( $post_type_object->_builtin ) {
            return new WP_Error( 'invalid_post_type', __( 'Unregistering a built-in post type is not allowed' ) );
        }

        $post_type_object->remove_supports();
        $post_type_object->remove_rewrite_rules();
        $post_type_object->unregister_meta_boxes();
        $post_type_object->remove_hooks();
        $post_type_object->unregister_taxonomies();

        unset( $wp_post_types[ $post_type ] );

        /**
         * Fires after a post type was unregistered.
         *
         * @since 4.5.0
         *
         * @param string $post_type Post type key.
         */
        do_action( 'unregistered_post_type', $post_type );

        return true;    
    }

    /**
     * Determines whether a post type is registered.
     */
    public static function exists( $post_type ) {
        return (bool) static::get( $post_type );
    }

    /**
     * Return an object with all post-types and their capabilities
     */
    public static function getCapabilities( $args ) {
        if ( ! is_array( $args->capability_type ) ) {
            $args->capability_type = array( $args->capability_type, $args->capability_type . 's' );
        }

        // Singular base for meta capabilities, plural base for primitive capabilities.
        list( $singular_base, $plural_base ) = $args->capability_type;

        $default_capabilities = array(
            // Meta capabilities
            'edit_post'          => 'edit_' . $singular_base,
            'read_post'          => 'read_' . $singular_base,
            'delete_post'        => 'delete_' . $singular_base,
            // Primitive capabilities used outside of map_meta_cap():
            'edit_posts'         => 'edit_' . $plural_base,
            'edit_others_posts'  => 'edit_others_' . $plural_base,
            'publish_posts'      => 'publish_' . $plural_base,
            'read_private_posts' => 'read_private_' . $plural_base,
        );

        // Primitive capabilities used within map_meta_cap():
        if ( $args->map_meta_cap ) {
            $default_capabilities_for_mapping = array(
                'read'                   => 'read',
                'delete_posts'           => 'delete_' . $plural_base,
                'delete_private_posts'   => 'delete_private_' . $plural_base,
                'delete_published_posts' => 'delete_published_' . $plural_base,
                'delete_others_posts'    => 'delete_others_' . $plural_base,
                'edit_private_posts'     => 'edit_private_' . $plural_base,
                'edit_published_posts'   => 'edit_published_' . $plural_base,
            );
            $default_capabilities             = array_merge( $default_capabilities, $default_capabilities_for_mapping );
        }

        $capabilities = array_merge( $default_capabilities, $args->capabilities );

        // Post creation capability simply maps to edit_posts by default:
        if ( ! isset( $capabilities['create_posts'] ) ) {
            $capabilities['create_posts'] = $capabilities['edit_posts'];
        }

        // Remember meta capabilities for future reference.
        if ( $args->map_meta_cap ) {
            _post_type_meta_capabilities( $capabilities );
        }

        return (object) $capabilities;
    }

    /**
     * Store or return a list of post type meta caps for map_meta_cap().
     */ 
    public static function getMetaCapabilities( $capabilities ) {
        global $post_type_meta_caps;

        foreach ( $capabilities as $core => $custom ) {
            if ( in_array( $core, array( 'read_post', 'delete_post', 'edit_post' ) ) ) {
                $post_type_meta_caps[ $custom ] = $core;
            }
        }
    }

    /**
     * Get all labels from a single post-type object
     */
    public static function getLabels( $post_type_object ) {
        $nohier_vs_hier_defaults              = array(
            'name'                     => array( _x( 'Posts', 'post type general name' ), _x( 'Pages', 'post type general name' ) ),
            'singular_name'            => array( _x( 'Post', 'post type singular name' ), _x( 'Page', 'post type singular name' ) ),
            'add_new'                  => array( _x( 'Add New', 'post' ), _x( 'Add New', 'page' ) ),
            'add_new_item'             => array( __( 'Add New Post' ), __( 'Add New Page' ) ),
            'edit_item'                => array( __( 'Edit Post' ), __( 'Edit Page' ) ),
            'new_item'                 => array( __( 'New Post' ), __( 'New Page' ) ),
            'view_item'                => array( __( 'View Post' ), __( 'View Page' ) ),
            'view_items'               => array( __( 'View Posts' ), __( 'View Pages' ) ),
            'search_items'             => array( __( 'Search Posts' ), __( 'Search Pages' ) ),
            'not_found'                => array( __( 'No posts found.' ), __( 'No pages found.' ) ),
            'not_found_in_trash'       => array( __( 'No posts found in Trash.' ), __( 'No pages found in Trash.' ) ),
            'parent_item_colon'        => array( null, __( 'Parent Page:' ) ),
            'all_items'                => array( __( 'All Posts' ), __( 'All Pages' ) ),
            'archives'                 => array( __( 'Post Archives' ), __( 'Page Archives' ) ),
            'attributes'               => array( __( 'Post Attributes' ), __( 'Page Attributes' ) ),
            'insert_into_item'         => array( __( 'Insert into post' ), __( 'Insert into page' ) ),
            'uploaded_to_this_item'    => array( __( 'Uploaded to this post' ), __( 'Uploaded to this page' ) ),
            'featured_image'           => array( _x( 'Featured Image', 'post' ), _x( 'Featured Image', 'page' ) ),
            'set_featured_image'       => array( _x( 'Set featured image', 'post' ), _x( 'Set featured image', 'page' ) ),
            'remove_featured_image'    => array( _x( 'Remove featured image', 'post' ), _x( 'Remove featured image', 'page' ) ),
            'use_featured_image'       => array( _x( 'Use as featured image', 'post' ), _x( 'Use as featured image', 'page' ) ),
            'filter_items_list'        => array( __( 'Filter posts list' ), __( 'Filter pages list' ) ),
            'items_list_navigation'    => array( __( 'Posts list navigation' ), __( 'Pages list navigation' ) ),
            'items_list'               => array( __( 'Posts list' ), __( 'Pages list' ) ),
            'item_published'           => array( __( 'Post published.' ), __( 'Page published.' ) ),
            'item_published_privately' => array( __( 'Post published privately.' ), __( 'Page published privately.' ) ),
            'item_reverted_to_draft'   => array( __( 'Post reverted to draft.' ), __( 'Page reverted to draft.' ) ),
            'item_scheduled'           => array( __( 'Post scheduled.' ), __( 'Page scheduled.' ) ),
            'item_updated'             => array( __( 'Post updated.' ), __( 'Page updated.' ) ),
        );
        $nohier_vs_hier_defaults['menu_name'] = $nohier_vs_hier_defaults['name'];

        $labels = _get_custom_object_labels( $post_type_object, $nohier_vs_hier_defaults );

        $post_type = $post_type_object->name;

        $default_labels = clone $labels;

        /**
         * Filters the labels of a specific post type.
         *
         * The dynamic portion of the hook name, `$post_type`, refers to
         * the post type slug.
         *
         * @since 3.5.0
         *
         * @see get_post_type_labels() for the full list of labels.
         *
         * @param object $labels Object with labels for the post type as member variables.
         */
        $labels = apply_filters( "post_type_labels_{$post_type}", $labels );

        // Ensure that the filtered labels contain all required default values.
        $labels = (object) array_merge( (array) $default_labels, (array) $labels );

        return $labels;
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
     * Add the submenus for each post-type
     */
    public static function addSubmenus() {
        foreach ( static::list( array( 'show_ui' => true ) ) as $ptype ) {
            $ptype_obj = static::get( $ptype );
            // Sub-menus only.
            if ( ! $ptype_obj->show_in_menu || $ptype_obj->show_in_menu === true ) {
                continue;
            }
            add_submenu_page( $ptype_obj->show_in_menu, $ptype_obj->labels->name, $ptype_obj->labels->all_items, $ptype_obj->cap->edit_posts, "edit.php?post_type=$ptype" );
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