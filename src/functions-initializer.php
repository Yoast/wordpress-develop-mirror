<?php

use \WP\Helper\LabelHelper;
use \WP\Helper\Post\PostHelper;
use \WP\Helper\Post\PostStatusHelper;
use \WP\Helper\Post\AttachmentHelper;
use \WP\Helper\PostType\PostTypeHelper;
use \WP\Helper\PostType\SupportsHelper as PostTypeSupportsHelper;

/* ------------------- Post Types: --------------------------*/


/**
 * Registers a post type.
 *
 * Note: Post type registrations should not be hooked before the
 * {@see 'init'} action. Also, any taxonomy connections should be
 * registered via the `$taxonomies` argument to ensure consistency
 * when hooks such as {@see 'parse_query'} or {@see 'pre_get_posts'}
 * are used.
 *
 * Post types can support any number of built-in core features such
 * as meta boxes, custom fields, post thumbnails, post statuses,
 * comments, and more. See the `$supports` argument for a complete
 * list of supported features.
 *
 * @since 2.9.0
 * @since 3.0.0 The `show_ui` argument is now enforced on the new post screen.
 * @since 4.4.0 The `show_ui` argument is now enforced on the post type listing
 *              screen and post editing screen.
 * @since 4.6.0 Post type object returned is now an instance of `WP_Post_Type`.
 * @since 4.7.0 Introduced `show_in_rest`, `rest_base` and `rest_controller_class`
 *              arguments to register the post type in REST API.
 *
 * @global array $wp_post_types List of post types.
 *
 * @param string $post_type Post type key. Must not exceed 20 characters and may
 *                          only contain lowercase alphanumeric characters, dashes,
 *                          and underscores. See sanitize_key().
 * @param array|string $args {
 *     Array or string of arguments for registering a post type.
 *
 *     @type string      $label                 Name of the post type shown in the menu. Usually plural.
 *                                              Default is value of $labels['name'].
 *     @type array       $labels                An array of labels for this post type. If not set, post
 *                                              labels are inherited for non-hierarchical types and page
 *                                              labels for hierarchical ones. See get_post_type_labels() for a full
 *                                              list of supported labels.
 *     @type string      $description           A short descriptive summary of what the post type is.
 *                                              Default empty.
 *     @type bool        $public                Whether a post type is intended for use publicly either via
 *                                              the admin interface or by front-end users. While the default
 *                                              settings of $exclude_from_search, $publicly_queryable, $show_ui,
 *                                              and $show_in_nav_menus are inherited from public, each does not
 *                                              rely on this relationship and controls a very specific intention.
 *                                              Default false.
 *     @type bool        $hierarchical          Whether the post type is hierarchical (e.g. page). Default false.
 *     @type bool        $exclude_from_search   Whether to exclude posts with this post type from front end search
 *                                              results. Default is the opposite value of $public.
 *     @type bool        $publicly_queryable    Whether queries can be performed on the front end for the post type
 *                                              as part of parse_request(). Endpoints would include:
 *                                              * ?post_type={post_type_key}
 *                                              * ?{post_type_key}={single_post_slug}
 *                                              * ?{post_type_query_var}={single_post_slug}
 *                                              If not set, the default is inherited from $public.
 *     @type bool        $show_ui               Whether to generate and allow a UI for managing this post type in the
 *                                              admin. Default is value of $public.
 *     @type bool|string $show_in_menu          Where to show the post type in the admin menu. To work, $show_ui
 *                                              must be true. If true, the post type is shown in its own top level
 *                                              menu. If false, no menu is shown. If a string of an existing top
 *                                              level menu (eg. 'tools.php' or 'edit.php?post_type=page'), the post
 *                                              type will be placed as a sub-menu of that.
 *                                              Default is value of $show_ui.
 *     @type bool        $show_in_nav_menus     Makes this post type available for selection in navigation menus.
 *                                              Default is value $public.
 *     @type bool        $show_in_admin_bar     Makes this post type available via the admin bar. Default is value
 *                                              of $show_in_menu.
 *     @type bool        $show_in_rest          Whether to add the post type route in the REST API 'wp/v2' namespace.
 *     @type string      $rest_base             To change the base url of REST API route. Default is $post_type.
 *     @type string      $rest_controller_class REST API Controller class name. Default is 'WP_REST_Posts_Controller'.
 *     @type int         $menu_position         The position in the menu order the post type should appear. To work,
 *                                              $show_in_menu must be true. Default null (at the bottom).
 *     @type string      $menu_icon             The url to the icon to be used for this menu. Pass a base64-encoded
 *                                              SVG using a data URI, which will be colored to match the color scheme
 *                                              -- this should begin with 'data:image/svg+xml;base64,'. Pass the name
 *                                              of a Dashicons helper class to use a font icon, e.g.
 *                                              'dashicons-chart-pie'. Pass 'none' to leave div.wp-menu-image empty
 *                                              so an icon can be added via CSS. Defaults to use the posts icon.
 *     @type string      $capability_type       The string to use to build the read, edit, and delete capabilities.
 *                                              May be passed as an array to allow for alternative plurals when using
 *                                              this argument as a base to construct the capabilities, e.g.
 *                                              array('story', 'stories'). Default 'post'.
 *     @type array       $capabilities          Array of capabilities for this post type. $capability_type is used
 *                                              as a base to construct capabilities by default.
 *                                              See get_post_type_capabilities().
 *     @type bool        $map_meta_cap          Whether to use the internal default meta capability handling.
 *                                              Default false.
 *     @type array       $supports              Core feature(s) the post type supports. Serves as an alias for calling
 *                                              add_post_type_support() directly. Core features include 'title',
 *                                              'editor', 'comments', 'revisions', 'trackbacks', 'author', 'excerpt',
 *                                              'page-attributes', 'thumbnail', 'custom-fields', and 'post-formats'.
 *                                              Additionally, the 'revisions' feature dictates whether the post type
 *                                              will store revisions, and the 'comments' feature dictates whether the
 *                                              comments count will show on the edit screen. Defaults is an array
 *                                              containing 'title' and 'editor'.
 *     @type callable    $register_meta_box_cb  Provide a callback function that sets up the meta boxes for the
 *                                              edit form. Do remove_meta_box() and add_meta_box() calls in the
 *                                              callback. Default null.
 *     @type array       $taxonomies            An array of taxonomy identifiers that will be registered for the
 *                                              post type. Taxonomies can be registered later with register_taxonomy()
 *                                              or register_taxonomy_for_object_type().
 *                                              Default empty array.
 *     @type bool|string $has_archive           Whether there should be post type archives, or if a string, the
 *                                              archive slug to use. Will generate the proper rewrite rules if
 *                                              $rewrite is enabled. Default false.
 *     @type bool|array  $rewrite              {
 *         Triggers the handling of rewrites for this post type. To prevent rewrite, set to false.
 *         Defaults to true, using $post_type as slug. To specify rewrite rules, an array can be
 *         passed with any of these keys:
 *
 *         @type string $slug       Customize the permastruct slug. Defaults to $post_type key.
 *         @type bool   $with_front Whether the permastruct should be prepended with WP_Rewrite::$front.
 *                                  Default true.
 *         @type bool   $feeds      Whether the feed permastruct should be built for this post type.
 *                                  Default is value of $has_archive.
 *         @type bool   $pages      Whether the permastruct should provide for pagination. Default true.
 *         @type const  $ep_mask    Endpoint mask to assign. If not specified and permalink_epmask is set,
 *                                  inherits from $permalink_epmask. If not specified and permalink_epmask
 *                                  is not set, defaults to EP_PERMALINK.
 *     }
 *     @type string|bool $query_var             Sets the query_var key for this post type. Defaults to $post_type
 *                                              key. If false, a post type cannot be loaded at
 *                                              ?{query_var}={post_slug}. If specified as a string, the query
 *                                              ?{query_var_string}={post_slug} will be valid.
 *     @type bool        $can_export            Whether to allow this post type to be exported. Default true.
 *     @type bool        $delete_with_user      Whether to delete posts of this type when deleting a user. If true,
 *                                              posts of this type belonging to the user will be moved to trash
 *                                              when then user is deleted. If false, posts of this type belonging
 *                                              to the user will *not* be trashed or deleted. If not set (the default),
 *                                              posts are trashed if post_type_supports('author'). Otherwise posts
 *                                              are not trashed or deleted. Default null.
 *     @type bool        $_builtin              FOR INTERNAL USE ONLY! True if this post type is a native or
 *                                              "built-in" post_type. Default false.
 *     @type string      $_edit_link            FOR INTERNAL USE ONLY! URL segment to use for edit link of
 *                                              this post type. Default 'post.php?post=%d'.
 * }
 * @return WP_Post_Type|WP_Error The registered post type object, or an error object.
 */
function register_post_type( $post_type, $args = array() ) {
	return PostTypeHelper::register( $post_type, $args = array() );
}

/**
 * Unregisters a post type.
 *
 * Can not be used to unregister built-in post types.
 *
 * @since 4.5.0
 *
 * @global array $wp_post_types List of post types.
 *
 * @param string $post_type Post type to unregister.
 * @return bool|WP_Error True on success, WP_Error on failure or if the post type doesn't exist.
 */
function unregister_post_type( $post_type ) {
    return PostTypeHelper::unregister( $post_type );
}

/**
 * Build an object with all post type capabilities out of a post type object
 *
 * Post type capabilities use the 'capability_type' argument as a base, if the
 * capability is not set in the 'capabilities' argument array or if the
 * 'capabilities' argument is not supplied.
 *
 * The capability_type argument can optionally be registered as an array, with
 * the first value being singular and the second plural, e.g. array('story, 'stories')
 * Otherwise, an 's' will be added to the value for the plural form. After
 * registration, capability_type will always be a string of the singular value.
 *
 * By default, seven keys are accepted as part of the capabilities array:
 *
 * - edit_post, read_post, and delete_post are meta capabilities, which are then
 *   generally mapped to corresponding primitive capabilities depending on the
 *   context, which would be the post being edited/read/deleted and the user or
 *   role being checked. Thus these capabilities would generally not be granted
 *   directly to users or roles.
 *
 * - edit_posts - Controls whether objects of this post type can be edited.
 * - edit_others_posts - Controls whether objects of this type owned by other users
 *   can be edited. If the post type does not support an author, then this will
 *   behave like edit_posts.
 * - publish_posts - Controls publishing objects of this post type.
 * - read_private_posts - Controls whether private objects can be read.
 *
 * These four primitive capabilities are checked in core in various locations.
 * There are also seven other primitive capabilities which are not referenced
 * directly in core, except in map_meta_cap(), which takes the three aforementioned
 * meta capabilities and translates them into one or more primitive capabilities
 * that must then be checked against the user or role, depending on the context.
 *
 * - read - Controls whether objects of this post type can be read.
 * - delete_posts - Controls whether objects of this post type can be deleted.
 * - delete_private_posts - Controls whether private objects can be deleted.
 * - delete_published_posts - Controls whether published objects can be deleted.
 * - delete_others_posts - Controls whether objects owned by other users can be
 *   can be deleted. If the post type does not support an author, then this will
 *   behave like delete_posts.
 * - edit_private_posts - Controls whether private objects can be edited.
 * - edit_published_posts - Controls whether published objects can be edited.
 *
 * These additional capabilities are only used in map_meta_cap(). Thus, they are
 * only assigned by default if the post type is registered with the 'map_meta_cap'
 * argument set to true (default is false).
 *
 * @since 3.0.0
 *
 * @see register_post_type()
 * @see map_meta_cap()
 *
 * @param object $args Post type registration arguments.
 * @return object Object with all the capabilities as member variables.
 */
function get_post_type_capabilities( $args ) {
    return PostTypeHelper::getCapabilities( $args );
}

/**
 * Store or return a list of post type meta caps for map_meta_cap().
 *
 * @since 3.1.0
 * @access private
 *
 * @global array $post_type_meta_caps Used to store meta capabilities.
 *
 * @param array $capabilities Post type meta capabilities.
 */
function _post_type_meta_capabilities( $capabilities = null ) {
    return PostTypeHelper::getMetaCapabilities( $capabilities );
}


/**
 * Builds an object with all post type labels out of a post type object.
 *
 * Accepted keys of the label array in the post type object:
 *
 * - `name` - General name for the post type, usually plural. The same and overridden
 *          by `$post_type_object->label`. Default is 'Posts' / 'Pages'.
 * - `singular_name` - Name for one object of this post type. Default is 'Post' / 'Page'.
 * - `add_new` - Default is 'Add New' for both hierarchical and non-hierarchical types.
 *             When internationalizing this string, please use a {@link https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#disambiguation-by-context gettext context}
 *             matching your post type. Example: `_x( 'Add New', 'product', 'textdomain' );`.
 * - `add_new_item` - Label for adding a new singular item. Default is 'Add New Post' / 'Add New Page'.
 * - `edit_item` - Label for editing a singular item. Default is 'Edit Post' / 'Edit Page'.
 * - `new_item` - Label for the new item page title. Default is 'New Post' / 'New Page'.
 * - `view_item` - Label for viewing a singular item. Default is 'View Post' / 'View Page'.
 * - `view_items` - Label for viewing post type archives. Default is 'View Posts' / 'View Pages'.
 * - `search_items` - Label for searching plural items. Default is 'Search Posts' / 'Search Pages'.
 * - `not_found` - Label used when no items are found. Default is 'No posts found' / 'No pages found'.
 * - `not_found_in_trash` - Label used when no items are in the trash. Default is 'No posts found in Trash' /
 *                        'No pages found in Trash'.
 * - `parent_item_colon` - Label used to prefix parents of hierarchical items. Not used on non-hierarchical
 *                       post types. Default is 'Parent Page:'.
 * - `all_items` - Label to signify all items in a submenu link. Default is 'All Posts' / 'All Pages'.
 * - `archives` - Label for archives in nav menus. Default is 'Post Archives' / 'Page Archives'.
 * - `attributes` - Label for the attributes meta box. Default is 'Post Attributes' / 'Page Attributes'.
 * - `insert_into_item` - Label for the media frame button. Default is 'Insert into post' / 'Insert into page'.
 * - `uploaded_to_this_item` - Label for the media frame filter. Default is 'Uploaded to this post' /
 *                           'Uploaded to this page'.
 * - `featured_image` - Label for the Featured Image meta box title. Default is 'Featured Image'.
 * - `set_featured_image` - Label for setting the featured image. Default is 'Set featured image'.
 * - `remove_featured_image` - Label for removing the featured image. Default is 'Remove featured image'.
 * - `use_featured_image` - Label in the media frame for using a featured image. Default is 'Use as featured image'.
 * - `menu_name` - Label for the menu name. Default is the same as `name`.
 * - `filter_items_list` - Label for the table views hidden heading. Default is 'Filter posts list' /
 *                       'Filter pages list'.
 * - `items_list_navigation` - Label for the table pagination hidden heading. Default is 'Posts list navigation' /
 *                           'Pages list navigation'.
 * - `items_list` - Label for the table hidden heading. Default is 'Posts list' / 'Pages list'.
 * - `item_published` - Label used when an item is published. Default is 'Post published.' / 'Page published.'
 * - `item_published_privately` - Label used when an item is published with private visibility.
 *                              Default is 'Post published privately.' / 'Page published privately.'
 * - `item_reverted_to_draft` - Label used when an item is switched to a draft.
 *                            Default is 'Post reverted to draft.' / 'Page reverted to draft.'
 * - `item_scheduled` - Label used when an item is scheduled for publishing. Default is 'Post scheduled.' /
 *                    'Page scheduled.'
 * - `item_updated` - Label used when an item is updated. Default is 'Post updated.' / 'Page updated.'
 *
 * Above, the first default value is for non-hierarchical post types (like posts)
 * and the second one is for hierarchical post types (like pages).
 *
 * Note: To set labels used in post type admin notices, see the {@see 'post_updated_messages'} filter.
 *
 * @since 3.0.0
 * @since 4.3.0 Added the `featured_image`, `set_featured_image`, `remove_featured_image`,
 *              and `use_featured_image` labels.
 * @since 4.4.0 Added the `archives`, `insert_into_item`, `uploaded_to_this_item`, `filter_items_list`,
 *              `items_list_navigation`, and `items_list` labels.
 * @since 4.6.0 Converted the `$post_type` parameter to accept a `WP_Post_Type` object.
 * @since 4.7.0 Added the `view_items` and `attributes` labels.
 * @since 5.0.0 Added the `item_published`, `item_published_privately`, `item_reverted_to_draft`,
 *              `item_scheduled`, and `item_updated` labels.
 *
 * @access private
 *
 * @param object|WP_Post_Type $post_type_object Post type object.
 * @return object Object with all the labels as member variables.
 */
function get_post_type_labels( $post_type_object ) {
    return PostTypeHelper::getLabels( $post_type_object );
}


/**
 * Retrieves a post type object by name.
 *
 * @since 3.0.0
 * @since 4.6.0 Object returned is now an instance of `WP_Post_Type`.
 *
 * @global array $wp_post_types List of post types.
 *
 * @see register_post_type()
 *
 * @param string $post_type The name of a registered post type.
 * @return WP_Post_Type|null WP_Post_Type object if it exists, null otherwise.
 */
function get_post_type_object( $post_type ) {
    return PostTypeHelper::get( $post_type );
}


/**
 * Whether the post type is hierarchical.
 *
 * A false return value might also mean that the post type does not exist.
 *
 * @since 3.0.0
 *
 * @see get_post_type_object()
 *
 * @param string $post_type Post type name
 * @return bool Whether post type is hierarchical.
 */
function is_post_type_hierarchical( $post_type ) {
    return PostTypeHelper::isHierarchical( $post_type );
}

/**
 * Determines whether a post type is registered.
 *
 * For more information on this and similar theme functions, check out
 * the {@link https://developer.wordpress.org/themes/basics/conditional-tags/
 * Conditional Tags} article in the Theme Developer Handbook.
 *
 * @since 3.0.0
 *
 * @see get_post_type_object()
 *
 * @param string $post_type Post type name.
 * @return bool Whether post type is registered.
 */
function post_type_exists( $post_type ) {
    return PostTypeHelper::exists( $post_type );
}



/**
 * Get a list of all registered post type objects.
 *
 * @since 2.9.0
 *
 * @global array $wp_post_types List of post types.
 *
 * @see register_post_type() for accepted arguments.
 *
 * @param array|string $args     Optional. An array of key => value arguments to match against
 *                               the post type objects. Default empty array.
 * @param string       $output   Optional. The type of output to return. Accepts post type 'names'
 *                               or 'objects'. Default 'names'.
 * @param string       $operator Optional. The logical operation to perform. 'or' means only one
 *                               element from the array needs to match; 'and' means all elements
 *                               must match; 'not' means no elements may match. Default 'and'.
 * @return string[]|WP_Post_Type[] An array of post type names or objects.
 */
function get_post_types( $args = array(), $output = 'names', $operator = 'and' ) {
    return PostTypeHelper::list( $args, $output, $operator );
}


/**
 * Creates the initial post types when 'init' action is fired.
 *
 * See {@see 'init'}.
 *
 * @since 2.9.0
 */
function create_initial_post_types(){
    PostTypeHelper::createInitialPostTypes();
}


/**
 * Add submenus for post types.
 *
 * @access private
 * @since 3.1.0
 */
function _add_post_type_submenus() {
    return PostTypeHelper::addSubmenus( )
}


/**
 * Registers support of certain features for a post type.
 *
 * All core features are directly associated with a functional area of the edit
 * screen, such as the editor or a meta box. Features include: 'title', 'editor',
 * 'comments', 'revisions', 'trackbacks', 'author', 'excerpt', 'page-attributes',
 * 'thumbnail', 'custom-fields', and 'post-formats'.
 *
 * Additionally, the 'revisions' feature dictates whether the post type will
 * store revisions, and the 'comments' feature dictates whether the comments
 * count will show on the edit screen.
 *
 * Example usage:
 *
 *     add_post_type_support( 'my_post_type', 'comments' );
 *     add_post_type_support( 'my_post_type', array(
 *         'author', 'excerpt',
 *     ) );
 *     add_post_type_support( 'my_post_type', 'my_feature', array(
 *         'field' => 'value',
 *     ) );
 *
 * @since 3.0.0
 *
 * @global array $_wp_post_type_features
 *
 * @param string       $post_type The post type for which to add the feature.
 * @param string|array $feature   The feature being added, accepts an array of
 *                                feature strings or a single string.
 * @param mixed        ...$args   Optional extra arguments to pass along with certain features.
 */
function add_post_type_support( $post_type, $feature, ...$args ){
    return PostTypeSupportsHelper::add( $post_type, $feature, ...$args );
}

/**
 * Remove support for a feature from a post type.
 *
 * @since 3.0.0
 *
 * @global array $_wp_post_type_features
 *
 * @param string $post_type The post type for which to remove the feature.
 * @param string $feature   The feature being removed.
 */
function remove_post_type_support( $post_type, $feature ) {
    return PostTypeSupportsHelper::remove( $post_type, $feature );
}


/**
 * Get all the post type features
 *
 * @since 3.4.0
 *
 * @global array $_wp_post_type_features
 *
 * @param string $post_type The post type.
 * @return array Post type supports list.
 */
function get_all_post_type_supports( $post_type ) {
    return PostTypeSupportsHelper::get( $post_type );
}

/**
 * Check a post type's support for a given feature.
 *
 * @since 3.0.0
 *
 * @global array $_wp_post_type_features
 *
 * @param string $post_type The post type being checked.
 * @param string $feature   The feature being checked.
 * @return bool Whether the post type supports the given feature.
 */
function post_type_supports( $post_type, $feature ) {
    return PostTypeSupportsHelper::hasSupport( $post_type, $feature );
}

/* ------------------- Labels: --------------------------*/

/**
 * Build an object with custom-something object (post type, taxonomy) labels
 * out of a custom-something object
 *
 * @since 3.0.0
 * @access private
 *
 * @param object $object                  A custom-something object.
 * @param array  $nohier_vs_hier_defaults Hierarchical vs non-hierarchical default labels.
 * @return object Object containing labels for the given custom-something object.
 */
function _get_custom_object_labels( $object, $nohier_vs_hier_defaults ) {
    return LabelHelper::getCustomObjectLabels( $object, $nohier_vs_hier_defaults );
}



/* ------------------- Attachments: --------------------------*/

/**
 * Retrieve attached file path based on attachment ID.
 *
 * By default the path will go through the 'get_attached_file' filter, but
 * passing a true to the $unfiltered argument of get_attached_file() will
 * return the file path unfiltered.
 *
 * The function works by getting the single post meta name, named
 * '_wp_attached_file' and returning it. This is a convenience function to
 * prevent looking up the meta name and provide a mechanism for sending the
 * attached filename through a filter.
 *
 * @since 2.0.0
 *
 * @param int  $attachment_id Attachment ID.
 * @param bool $unfiltered    Optional. Whether to apply filters. Default false.
 * @return string|false The file path to where the attached file should be, false otherwise.
 */
function get_attached_file( $attachment_id, $unfiltered = false ) {
    return AttachmentHelper::getAttachedFile( $attachment_id, $unfiltered = false );
}   


/**
 * Update attachment file path based on attachment ID.
 *
 * Used to update the file path of the attachment, which uses post meta name
 * '_wp_attached_file' to store the path of the attachment.
 *
 * @since 2.1.0
 *
 * @param int    $attachment_id Attachment ID.
 * @param string $file          File path for the attachment.
 * @return bool True on success, false on failure.
 */
function update_attached_file( $attachment_id, $file ) {
    return AttachmentHelper::updateAttachedFile( $attachment_id, $file );
}


/**
 * Retrieve the mime type of an attachment based on the ID.
 *
 * This function can be used with any post type, but it makes more sense with
 * attachments.
 *
 * @since 2.0.0
 *
 * @param int|WP_Post $post Optional. Post ID or post object. Defaults to global $post.
 * @return string|false The mime type on success, false on failure.
 */
function get_post_mime_type( $post = null ) {
    return AttachmentHelper::getMimeType( $post );
}

/**
 * Return relative path to an uploaded file.
 *
 * The path is relative to the current upload dir.
 *
 * @since 2.9.0
 * @access private
 *
 * @param string $path Full path to the file.
 * @return string Relative path on success, unchanged path on failure.
 */
function _wp_relative_upload_path( $path ) {
    return AttachmentHelper::relativeUploadPath( $path );
}


/* ------------------- Posts: --------------------------*/


/**
 * Retrieves post data given a post ID or post object.
 *
 * See sanitize_post() for optional $filter values. Also, the parameter
 * `$post`, must be given as a variable, since it is passed by reference.
 *
 * @since 1.5.1
 *
 * @global WP_Post $post Global post object.
 *
 * @param int|WP_Post|null $post   Optional. Post ID or post object. Defaults to global $post.
 * @param string           $output Optional. The required return type. One of OBJECT, ARRAY_A, or ARRAY_N, which correspond to
 *                                 a WP_Post object, an associative array, or a numeric array, respectively. Default OBJECT.
 * @param string           $filter Optional. Type of filter to apply. Accepts 'raw', 'edit', 'db',
 *                                 or 'display'. Default 'raw'.
 * @return WP_Post|array|null Type corresponding to $output on success or null on failure.
 *                            When $output is OBJECT, a `WP_Post` instance is returned.
 */
function get_post( $post = null, $output = OBJECT, $filter = 'raw' ) {
    return PostHelper::getPost( $post, $output, $filter );
}


/**
 * Retrieve ancestors of a post.
 *
 * @since 2.5.0
 *
 * @param int|WP_Post $post Post ID or post object.
 * @return array Ancestor IDs or empty array if none are found.
 */
function get_post_ancestors( $post ) {
    return PostHelper::getAncestors( $post );
}

/**
 * Retrieve all children of the post parent ID.
 *
 * Normally, without any enhancements, the children would apply to pages. In the
 * context of the inner workings of WordPress, pages, posts, and attachments
 * share the same table, so therefore the functionality could apply to any one
 * of them. It is then noted that while this function does not work on posts, it
 * does not mean that it won't work on posts. It is recommended that you know
 * what context you wish to retrieve the children of.
 *
 * Attachments may also be made the child of a post, so if that is an accurate
 * statement (which needs to be verified), it would then be possible to get
 * all of the attachments for a post. Attachments have since changed since
 * version 2.5, so this is most likely inaccurate, but serves generally as an
 * example of what is possible.
 *
 * The arguments listed as defaults are for this function and also of the
 * get_posts() function. The arguments are combined with the get_children defaults
 * and are then passed to the get_posts() function, which accepts additional arguments.
 * You can replace the defaults in this function, listed below and the additional
 * arguments listed in the get_posts() function.
 *
 * The 'post_parent' is the most important argument and important attention
 * needs to be paid to the $args parameter. If you pass either an object or an
 * integer (number), then just the 'post_parent' is grabbed and everything else
 * is lost. If you don't specify any arguments, then it is assumed that you are
 * in The Loop and the post parent will be grabbed for from the current post.
 *
 * The 'post_parent' argument is the ID to get the children. The 'numberposts'
 * is the amount of posts to retrieve that has a default of '-1', which is
 * used to get all of the posts. Giving a number higher than 0 will only
 * retrieve that amount of posts.
 *
 * The 'post_type' and 'post_status' arguments can be used to choose what
 * criteria of posts to retrieve. The 'post_type' can be anything, but WordPress
 * post types are 'post', 'pages', and 'attachments'. The 'post_status'
 * argument will accept any post status within the write administration panels.
 *
 * @since 2.0.0
 *
 * @see get_posts()
 * @todo Check validity of description.
 *
 * @global WP_Post $post Global post object.
 *
 * @param mixed  $args   Optional. User defined arguments for replacing the defaults. Default empty.
 * @param string $output Optional. The required return type. One of OBJECT, ARRAY_A, or ARRAY_N, which correspond to
 *                       a WP_Post object, an associative array, or a numeric array, respectively. Default OBJECT.
 * @return array Array of children, where the type of each element is determined by $output parameter.
 *               Empty array on failure.
 */
function get_children( $args = '', $output = OBJECT ) {
    return PostHelper::getChildren( $args, $output );
}


/**
 * Retrieves the post type of the current post or of a given post.
 *
 * @since 2.1.0
 *
 * @param int|WP_Post|null $post Optional. Post ID or post object. Default is global $post.
 * @return string|false          Post type on success, false on failure.
 */
function get_post_type( $post = null ) {
    return PostHelper::getPostType( $post );
}

/**
 * Retrieve data from a post field based on Post ID.
 *
 * Examples of the post field will be, 'post_type', 'post_status', 'post_content',
 * etc and based off of the post object property or key names.
 *
 * The context values are based off of the taxonomy filter functions and
 * supported values are found within those functions.
 *
 * @since 2.3.0
 * @since 4.5.0 The `$post` parameter was made optional.
 *
 * @see sanitize_post_field()
 *
 * @param string      $field   Post field name.
 * @param int|WP_Post $post    Optional. Post ID or post object. Defaults to global $post.
 * @param string      $context Optional. How to filter the field. Accepts 'raw', 'edit', 'db',
 *                             or 'display'. Default 'display'.
 * @return string The value of the post field on success, empty string on failure.
 */
function get_post_field( $field, $post = null, $context = 'display' ) {
    return PostHelper::getField( $field, $post, $context );
}


/**
 * Get extended entry info (<!--more-->).
 *
 * There should not be any space after the second dash and before the word
 * 'more'. There can be text or space(s) after the word 'more', but won't be
 * referenced.
 *
 * The returned array has 'main', 'extended', and 'more_text' keys. Main has the text before
 * the `<!--more-->`. The 'extended' key has the content after the
 * `<!--more-->` comment. The 'more_text' key has the custom "Read More" text.
 *
 * @since 1.0.0
 *
 * @param string $post Post content.
 * @return array Post before ('main'), after ('extended'), and custom read more ('more_text').
 */
function get_extended( $post ) {
    return PostHelper::getExtended( $post );
}


/* ------------------- Post Status: --------------------------*/


/**
 * Register a post status. Do not use before init.
 *
 * A simple function for creating or modifying a post status based on the
 * parameters given. The function will accept an array (second optional
 * parameter), along with a string for the post status name.
 *
 * Arguments prefixed with an _underscore shouldn't be used by plugins and themes.
 *
 * @since 3.0.0
 * @global array $wp_post_statuses Inserts new post status object into the list
 *
 * @param string $post_status Name of the post status.
 * @param array|string $args {
 *     Optional. Array or string of post status arguments.
 *
 *     @type bool|string $label                     A descriptive name for the post status marked
 *                                                  for translation. Defaults to value of $post_status.
 *     @type bool|array  $label_count               Descriptive text to use for nooped plurals.
 *                                                  Default array of $label, twice
 *     @type bool        $exclude_from_search       Whether to exclude posts with this post status
 *                                                  from search results. Default is value of $internal.
 *     @type bool        $_builtin                  Whether the status is built-in. Core-use only.
 *                                                  Default false.
 *     @type bool        $public                    Whether posts of this status should be shown
 *                                                  in the front end of the site. Default false.
 *     @type bool        $internal                  Whether the status is for internal use only.
 *                                                  Default false.
 *     @type bool        $protected                 Whether posts with this status should be protected.
 *                                                  Default false.
 *     @type bool        $private                   Whether posts with this status should be private.
 *                                                  Default false.
 *     @type bool        $publicly_queryable        Whether posts with this status should be publicly-
 *                                                  queryable. Default is value of $public.
 *     @type bool        $show_in_admin_all_list    Whether to include posts in the edit listing for
 *                                                  their post type. Default is value of $internal.
 *     @type bool        $show_in_admin_status_list Show in the list of statuses with post counts at
 *                                                  the top of the edit listings,
 *                                                  e.g. All (12) | Published (9) | My Custom Status (2)
 *                                                  Default is value of $internal.
 * }
 * @return object
 */
function register_post_status( $post_status, $args = array() ) {
    return PostStatusHelper::register( $post_status, $args );
}

/**
 * Retrieve the post status based on the post ID.
 *
 * If the post ID is of an attachment, then the parent post status will be given
 * instead.
 *
 * @since 2.0.0
 *
 * @param int|WP_Post $post Optional. Post ID or post object. Defaults to global $post..
 * @return string|false Post status on success, false on failure.
 */
function get_post_status( $post = null ) {
    return PostStatusHelper::get( $post );
}

/**
 * Retrieve a post status object by name.
 *
 * @since 3.0.0
 *
 * @global array $wp_post_statuses List of post statuses.
 *
 * @see register_post_status()
 *
 * @param string $post_status The name of a registered post status.
 * @return object|null A post status object.
 */
function get_post_status_object( $post_status ) {
    return PostStatusHelper::getObject( $post_status );
}


/**
 * Get a list of post statuses.
 *
 * @since 3.0.0
 *
 * @global array $wp_post_statuses List of post statuses.
 *
 * @see register_post_status()
 *
 * @param array|string $args     Optional. Array or string of post status arguments to compare against
 *                               properties of the global `$wp_post_statuses objects`. Default empty array.
 * @param string       $output   Optional. The type of output to return, either 'names' or 'objects'. Default 'names'.
 * @param string       $operator Optional. The logical operation to perform. 'or' means only one element
 *                               from the array needs to match; 'and' means all elements must match.
 *                               Default 'and'.
 * @return array A list of post status names or objects.
 */
function get_post_stati( $args = array(), $output = 'names', $operator = 'and' ) {
    return PostStatusHelper::getPostStatuses( $args, $output, $operator );
}



/**
 * Retrieve all of the WordPress supported post statuses.
 *
 * Posts have a limited set of valid status values, this provides the
 * post_status values and descriptions.
 *
 * @since 2.5.0
 *
 * @return array List of post statuses.
 */
function get_post_statuses() {
    return PostStatusHelper::getPossiblePostStatuses();
}

/**
 * Retrieve all of the WordPress support page statuses.
 *
 * Pages have a limited set of valid status values, this provides the
 * post_status values and descriptions.
 *
 * @since 2.5.0
 *
 * @return array List of page statuses.
 */
function get_page_statuses() {
    return PostStatusHelper::getPossiblePageStatuses();
}


/**
 * Return statuses for privacy requests.
 *
 * @since 4.9.6
 * @access private
 *
 * @return array
 */
function _wp_privacy_statuses() {
    return PostStatusHelper::getPrivacyStatuses();
}