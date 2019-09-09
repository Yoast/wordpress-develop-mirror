<?php

use \WP\Helper\LabelHelper;
use WP\Helper\Frontend\LoginHelper;
use \WP\Helper\Post\PostHelper;
use \WP\Helper\Post\MetaHelper as PostMetaHelper;
use \WP\Helper\Post\StatusHelper as PostStatusHelper;
use \WP\Helper\Post\CommentHelper as PostCommentHelper;
use \WP\Helper\Post\TermHelper as PostTermHelper;
use \WP\Helper\Post\SlugHelper as PostSlugHelper;
use \WP\Helper\Post\AttachmentHelper;
use \WP\Helper\PostType\PostTypeHelper;
use \WP\Helper\PostType\SupportsHelper as PostTypeSupportsHelper;

use \WP\Helper\Taxonomy\TermHelper;

use \WP\Helper\Meta\Metadata;
use \WP\Helper\CacheHelper;

use WP\Helper\HookHelper;
use WP\Helper\PluginHelper;
use WP\Helper\CapabilitiesHelper;
use WP\Helper\RoleHelper;

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
    return PostTypeHelper::get_list( $args, $output, $operator );
}

/**
 * Determines whether a post type is considered "viewable".
 *
 * For built-in post types such as posts and pages, the 'public' value will be evaluated.
 * For all others, the 'publicly_queryable' value will be used.
 *
 * @since 4.4.0
 * @since 4.5.0 Added the ability to pass a post type name in addition to object.
 * @since 4.6.0 Converted the `$post_type` parameter to accept a `WP_Post_Type` object.
 *
 * @param string|WP_Post_Type $post_type Post type name or object.
 * @return bool Whether the post type should be considered viewable.
 */
function is_post_type_viewable( $post_type ) {
    return PostTypeHelper::isViewable( $post_type );
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
    return PostTypeHelper::addSubmenus();
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

/**
 * Retrieves a list of post type names that support a specific feature.
 *
 * @since 4.5.0
 *
 * @global array $_wp_post_type_features Post type features
 *
 * @param array|string $feature  Single feature or an array of features the post types should support.
 * @param string       $operator Optional. The logical operation to perform. 'or' means
 *                               only one element from the array needs to match; 'and'
 *                               means all elements must match; 'not' means no elements may
 *                               match. Default 'and'.
 * @return array A list of post type names.
 */
function get_post_types_by_support( $feature, $operator = 'and' ) {
    return PostTypeSupportsHelper::getPostTypesBySupport( $feature, $operator );
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
 * Count number of attachments for the mime type(s).
 *
 * If you set the optional mime_type parameter, then an array will still be
 * returned, but will only have the item you are looking for. It does not give
 * you the number of attachments that are children of a post. You can get that
 * by counting the number of children that post has.
 *
 * @since 2.5.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string|array $mime_type Optional. Array or comma-separated list of
 *                                MIME patterns. Default empty.
 * @return object An object containing the attachment counts by mime type.
 */
function wp_count_attachments( $mime_type = '' ) {
    return AttachmentHelper::count( $mime_type );
}



/**
 * Get default post mime types.
 *
 * @since 2.9.0
 * @since 5.3.0 Added the 'Documents', 'Spreadsheets', and 'Archives' mime type groups.
 *
 * @return array List of post mime types.
 */
function get_post_mime_types() {
    return AttachmentHelper::getMimeTypes();
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
 * Check a MIME-Type against a list.
 *
 * If the wildcard_mime_types parameter is a string, it must be comma separated
 * list. If the real_mime_types is a string, it is also comma separated to
 * create the list.
 *
 * @since 2.5.0
 *
 * @param string|array $wildcard_mime_types Mime types, e.g. audio/mpeg or image (same as image/*)
 *                                          or flash (same as *flash*).
 * @param string|array $real_mime_types     Real post mime type values.
 * @return array array(wildcard=>array(real types)).
 */
function wp_match_mime_types( $wildcard_mime_types, $real_mime_types ) {
    return AttachmentHelper::matchMimeTypes( $wildcard_mime_types, $real_mime_types );
}

/**
 * Convert MIME types into SQL.
 *
 * @since 2.5.0
 *
 * @param string|array $post_mime_types List of mime types or comma separated string
 *                                      of mime types.
 * @param string       $table_alias     Optional. Specify a table alias, if needed.
 *                                      Default empty.
 * @return string The SQL AND clause for mime searching.
 */
function wp_post_mime_type_where( $post_mime_types, $table_alias = '' ) {
    return AttachmentHelper::findByMimeType( $post_mime_types, $table_alias );
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
    return PostHelper::get( $post, $output, $filter );
}

/**
 * Insert or update a post.
 *
 * If the $postarr parameter has 'ID' set to a value, then post will be updated.
 *
 * You can set the post date manually, by setting the values for 'post_date'
 * and 'post_date_gmt' keys. You can close the comments or open the comments by
 * setting the value for 'comment_status' key.
 *
 * @since 1.0.0
 * @since 4.2.0 Support was added for encoding emoji in the post title, content, and excerpt.
 * @since 4.4.0 A 'meta_input' array can now be passed to `$postarr` to add post meta data.
 *
 * @see sanitize_post()
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param array $postarr {
 *     An array of elements that make up a post to update or insert.
 *
 *     @type int    $ID                    The post ID. If equal to something other than 0,
 *                                         the post with that ID will be updated. Default 0.
 *     @type int    $post_author           The ID of the user who added the post. Default is
 *                                         the current user ID.
 *     @type string $post_date             The date of the post. Default is the current time.
 *     @type string $post_date_gmt         The date of the post in the GMT timezone. Default is
 *                                         the value of `$post_date`.
 *     @type mixed  $post_content          The post content. Default empty.
 *     @type string $post_content_filtered The filtered post content. Default empty.
 *     @type string $post_title            The post title. Default empty.
 *     @type string $post_excerpt          The post excerpt. Default empty.
 *     @type string $post_status           The post status. Default 'draft'.
 *     @type string $post_type             The post type. Default 'post'.
 *     @type string $comment_status        Whether the post can accept comments. Accepts 'open' or 'closed'.
 *                                         Default is the value of 'default_comment_status' option.
 *     @type string $ping_status           Whether the post can accept pings. Accepts 'open' or 'closed'.
 *                                         Default is the value of 'default_ping_status' option.
 *     @type string $post_password         The password to access the post. Default empty.
 *     @type string $post_name             The post name. Default is the sanitized post title
 *                                         when creating a new post.
 *     @type string $to_ping               Space or carriage return-separated list of URLs to ping.
 *                                         Default empty.
 *     @type string $pinged                Space or carriage return-separated list of URLs that have
 *                                         been pinged. Default empty.
 *     @type string $post_modified         The date when the post was last modified. Default is
 *                                         the current time.
 *     @type string $post_modified_gmt     The date when the post was last modified in the GMT
 *                                         timezone. Default is the current time.
 *     @type int    $post_parent           Set this for the post it belongs to, if any. Default 0.
 *     @type int    $menu_order            The order the post should be displayed in. Default 0.
 *     @type string $post_mime_type        The mime type of the post. Default empty.
 *     @type string $guid                  Global Unique ID for referencing the post. Default empty.
 *     @type array  $post_category         Array of category IDs.
 *                                         Defaults to value of the 'default_category' option.
 *     @type array  $tags_input            Array of tag names, slugs, or IDs. Default empty.
 *     @type array  $tax_input             Array of taxonomy terms keyed by their taxonomy name. Default empty.
 *     @type array  $meta_input            Array of post meta values keyed by their post meta key. Default empty.
 * }
 * @param bool  $wp_error Optional. Whether to return a WP_Error on failure. Default false.
 * @return int|WP_Error The post ID on success. The value 0 or WP_Error on failure.
 */
function wp_insert_post( $postarr, $wp_error = false ) {
    return PostHelper::add( $postarr, $wp_error = false );
}



/**
 * Update a post with new post data.
 *
 * The date does not have to be set for drafts. You can set the date and it will
 * not be overridden.
 *
 * @since 1.0.0
 *
 * @param array|object $postarr  Optional. Post data. Arrays are expected to be escaped,
 *                               objects are not. Default array.
 * @param bool         $wp_error Optional. Allow return of WP_Error on failure. Default false.
 * @return int|WP_Error The value 0 or WP_Error on failure. The post ID on success.
 */
function wp_update_post( $postarr = array(), $wp_error = false ) {
    return PostHelper::update( $postarr, $wp_error );
}



/**
 * Publish a post by transitioning the post status.
 *
 * @since 2.1.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int|WP_Post $post Post ID or post object.
 */
function wp_publish_post( $post ) {
    return PostHelper::publish( $post );
}

/**
 * Publish future post and make sure post ID has future post status.
 *
 * Invoked by cron 'publish_future_post' event. This safeguard prevents cron
 * from publishing drafts, etc.
 *
 * @since 2.5.0
 *
 * @param int|WP_Post $post_id Post ID or post object.
 */
function check_and_publish_future_post( $post_id ) {
    return PostHelper::publishFuturePost( $post_id );
}


/**
 * Trash or delete a post or page.
 *
 * When the post and page is permanently deleted, everything that is tied to
 * it is deleted also. This includes comments, post meta fields, and terms
 * associated with the post.
 *
 * The post or page is moved to trash instead of permanently deleted unless
 * trash is disabled, item is already in the trash, or $force_delete is true.
 *
 * @since 1.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 * @see wp_delete_attachment()
 * @see wp_trash_post()
 *
 * @param int  $postid       Optional. Post ID. Default 0.
 * @param bool $force_delete Optional. Whether to bypass trash and force deletion.
 *                           Default false.
 * @return WP_Post|false|null Post data on success, false or null on failure.
 */
function wp_delete_post( $postid = 0, $force_delete = false ) {
    return PostHelper::delete( $postid, $force_delete );
}



/**
 * Retrieves an array of the latest posts, or posts matching the given criteria.
 *
 * The defaults are as follows:
 *
 * @since 1.2.0
 *
 * @see WP_Query::parse_query()
 *
 * @param array $args {
 *     Optional. Arguments to retrieve posts. See WP_Query::parse_query() for all
 *     available arguments.
 *
 *     @type int        $numberposts      Total number of posts to retrieve. Is an alias of $posts_per_page
 *                                        in WP_Query. Accepts -1 for all. Default 5.
 *     @type int|string $category         Category ID or comma-separated list of IDs (this or any children).
 *                                        Is an alias of $cat in WP_Query. Default 0.
 *     @type array      $include          An array of post IDs to retrieve, sticky posts will be included.
 *                                        Is an alias of $post__in in WP_Query. Default empty array.
 *     @type array      $exclude          An array of post IDs not to retrieve. Default empty array.
 *     @type bool       $suppress_filters Whether to suppress filters. Default true.
 * }
 * @return WP_Post[]|int[] Array of post objects or post IDs.
 */
function get_posts( $args = null ) {
    return PostHelper::getPosts( $args );
}


/**
 * Retrieve a number of recent posts.
 *
 * @since 1.0.0
 *
 * @see get_posts()
 *
 * @param array  $args   Optional. Arguments to retrieve posts. Default empty array.
 * @param string $output Optional. The required return type. One of OBJECT or ARRAY_A, which correspond to
 *                       a WP_Post object or an associative array, respectively. Default ARRAY_A.
 * @return array|false Array of recent posts, where the type of each element is determined by $output parameter.
 *                     Empty array on failure.
 */
function wp_get_recent_posts( $args = array(), $output = ARRAY_A ) {
    return PostHelper::getRecentPosts( $args, $output );
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
 * Update the post type for the post ID.
 *
 * The page or post cache will be cleaned for the post ID.
 *
 * @since 2.5.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int    $post_id   Optional. Post ID to change post type. Default 0.
 * @param string $post_type Optional. Post type. Accepts 'post' or 'page' to
 *                          name a few. Default 'post'.
 * @return int|false Amount of rows changed. Should be 1 for success and 0 for failure.
 */
function set_post_type( $post_id = 0, $post_type = 'post' ) {
    return PostHelper::setPostType( $post_id, $post_type );
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
 * Count number of posts of a post type and if user has permissions to view.
 *
 * This function provides an efficient method of finding the amount of post's
 * type a blog has. Another method is to count the amount of items in
 * get_posts(), but that method has a lot of overhead with doing so. Therefore,
 * when developing for 2.5+, use this function instead.
 *
 * The $perm parameter checks for 'readable' value and if the user can read
 * private posts, it will display that for the user that is signed in.
 *
 * @since 2.5.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string $type Optional. Post type to retrieve count. Default 'post'.
 * @param string $perm Optional. 'readable' or empty. Default empty.
 * @return object Number of posts for each status.
 */
function wp_count_posts( $type = 'post', $perm = '' ) {
    return PostHelper::count( $type, $perm );
}


/**
 * Computes a unique slug for the post, when given the desired slug and some post details.
 *
 * @since 2.8.0
 *
 * @global wpdb       $wpdb       WordPress database abstraction object.
 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
 *
 * @param string $slug        The desired slug (post_name).
 * @param int    $post_ID     Post ID.
 * @param string $post_status No uniqueness checks are made if the post is still draft or pending.
 * @param string $post_type   Post type.
 * @param int    $post_parent Post parent ID.
 * @return string Unique slug for the post, based on $post_name (with a -1, -2, etc. suffix)
 */
function wp_unique_post_slug( $slug, $post_ID, $post_status, $post_type, $post_parent ) {
    return PostSlugHelper::isUnique( $slug, $post_ID, $post_status, $post_type, $post_parent );
}

/**
 * Truncate a post slug.
 *
 * @since 3.6.0
 * @access private
 *
 * @see utf8_uri_encode()
 *
 * @param string $slug   The slug to truncate.
 * @param int    $length Optional. Max length of the slug. Default 200 (characters).
 * @return string The truncated slug.
 */
function _truncate_post_slug( $slug, $length = 200 ) {
    return PostSlugHelper::truncate( $slug, $length );
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

/**
 * Determines whether a post is sticky.
 *
 * Sticky posts should remain at the top of The Loop. If the post ID is not
 * given, then The Loop ID for the current post will be used.
 *
 * For more information on this and similar theme functions, check out
 * the {@link https://developer.wordpress.org/themes/basics/conditional-tags/
 * Conditional Tags} article in the Theme Developer Handbook.
 *
 * @since 2.7.0
 *
 * @param int $post_id Optional. Post ID. Default is ID of the global $post.
 * @return bool Whether post is sticky.
 */
function is_sticky( $post_id = 0 ) {
    return PostHelper::isSticky( $post_id );
}



/**
 * Make a post sticky.
 *
 * Sticky posts should be displayed at the top of the front page.
 *
 * @since 2.7.0
 *
 * @param int $post_id Post ID.
 */
function stick_post( $post_id ) {
    return PostHelper::makeSticky( $post_id );
}



/**
 * Un-stick a post.
 *
 * Sticky posts should be displayed at the top of the front page.
 *
 * @since 2.7.0
 *
 * @param int $post_id Post ID.
 */
function unstick_post( $post_id ) {
    return PostHelper::makeUnsticky( $post_id );
}

/**
 * Sanitize every post field.
 *
 * If the context is 'raw', then the post object or array will get minimal
 * sanitization of the integer fields.
 *
 * @since 2.3.0
 *
 * @see sanitize_post_field()
 *
 * @param object|WP_Post|array $post    The Post Object or Array
 * @param string               $context Optional. How to sanitize post fields.
 *                                      Accepts 'raw', 'edit', 'db', or 'display'.
 *                                      Default 'display'.
 * @return object|WP_Post|array The now sanitized Post Object or Array (will be the
 *                              same type as $post).
 */
function sanitize_post( $post, $context = 'display' ) {
    return PostHelper::sanitize( $post, $context );
}

/**
 * Sanitize post field based on context.
 *
 * Possible context values are:  'raw', 'edit', 'db', 'display', 'attribute' and
 * 'js'. The 'display' context is used by default. 'attribute' and 'js' contexts
 * are treated like 'display' when calling filters.
 *
 * @since 2.3.0
 * @since 4.4.0 Like `sanitize_post()`, `$context` defaults to 'display'.
 *
 * @param string $field   The Post Object field name.
 * @param mixed  $value   The Post Object value.
 * @param int    $post_id Post ID.
 * @param string $context Optional. How to sanitize post fields. Looks for 'raw', 'edit',
 *                        'db', 'display', 'attribute' and 'js'. Default 'display'.
 * @return mixed Sanitized value.
 */
function sanitize_post_field( $field, $value, $post_id, $context = 'display' ) {
    return PostHelper::sanitizeField( $field, $value, $post_id, $context );
}




/**
 * Retrieve the list of categories for a post.
 *
 * Compatibility layer for themes and plugins. Also an easy layer of abstraction
 * away from the complexity of the taxonomy layer.
 *
 * @since 2.1.0
 *
 * @see wp_get_object_terms()
 *
 * @param int   $post_id Optional. The Post ID. Does not default to the ID of the
 *                       global $post. Default 0.
 * @param array $args    Optional. Category query parameters. Default empty array.
 *                       See WP_Term_Query::__construct() for supported arguments.
 * @return array|WP_Error List of categories. If the `$fields` argument passed via `$args` is 'all' or
 *                        'all_with_object_id', an array of WP_Term objects will be returned. If `$fields`
 *                        is 'ids', an array of category ids. If `$fields` is 'names', an array of category names.
 *                        WP_Error object if 'category' taxonomy doesn't exist.
 */
function wp_get_post_categories( $post_id = 0, $args = array() ) {
    return PostTermHelper::getCategories( $post_id, $args );
}

/**
 * Retrieve the tags for a post.
 *
 * There is only one default for this function, called 'fields' and by default
 * is set to 'all'. There are other defaults that can be overridden in
 * wp_get_object_terms().
 *
 * @since 2.3.0
 *
 * @param int   $post_id Optional. The Post ID. Does not default to the ID of the
 *                       global $post. Default 0.
 * @param array $args    Optional. Tag query parameters. Default empty array.
 *                       See WP_Term_Query::__construct() for supported arguments.
 * @return array|WP_Error Array of WP_Term objects on success or empty array if no tags were found.
 *                        WP_Error object if 'post_tag' taxonomy doesn't exist.
 */
function wp_get_post_tags( $post_id = 0, $args = array() ) {
    PostTermHelper::getTags( $post_id, $args );
}

/**
 * Retrieves the terms for a post.
 *
 * @since 2.8.0
 *
 * @param int          $post_id  Optional. The Post ID. Does not default to the ID of the
 *                               global $post. Default 0.
 * @param string|array $taxonomy Optional. The taxonomy slug or array of slugs for which
 *                               to retrieve terms. Default 'post_tag'.
 * @param array        $args     {
 *     Optional. Term query parameters. See WP_Term_Query::__construct() for supported arguments.
 *
 *     @type string $fields Term fields to retrieve. Default 'all'.
 * }
 * @return array|WP_Error Array of WP_Term objects on success or empty array if no terms were found.
 *                        WP_Error object if `$taxonomy` doesn't exist.
 */
function wp_get_post_terms( $post_id = 0, $taxonomy = 'post_tag', $args = array() ) {
    PostTermHelper::getTerms( $post_id = 0, $taxonomy = 'post_tag', $args = array() );
}



/**
 * Move a post or page to the Trash
 *
 * If trash is disabled, the post or page is permanently deleted.
 *
 * @since 2.9.0
 *
 * @see wp_delete_post()
 *
 * @param int $post_id Optional. Post ID. Default is ID of the global $post
 *                     if EMPTY_TRASH_DAYS equals true.
 * @return WP_Post|false|null Post data on success, false or null on failure.
 */
function wp_trash_post( $post_id = 0 ) {
    return PostHelper::trash( $post_id );
}



/**
 * Restore a post or page from the Trash.
 *
 * @since 2.9.0
 *
 * @param int $post_id Optional. Post ID. Default is ID of the global $post.
 * @return WP_Post|false|null Post data on success, false or null on failure.
 */
function wp_untrash_post( $post_id = 0 ) {
    return PostHelper::untrash( $post_id );
}


/**
 * Moves comments for a post to the trash.
 *
 * @since 2.9.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int|WP_Post|null $post Optional. Post ID or post object. Defaults to global $post.
 * @return mixed|void False on failure.
 */
function wp_trash_post_comments( $post = null ) {
    return PostCommentHelper::trash( $post );
}



/**
 * Restore comments for a post from the trash.
 *
 * @since 2.9.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int|WP_Post|null $post Optional. Post ID or post object. Defaults to global $post.
 * @return true|void
 */
function wp_untrash_post_comments( $post = null ) {
    return PostCommentHelper::untrash( $post );
}




/**
 * Reset the page_on_front, show_on_front, and page_for_post settings when
 * a linked page is deleted or trashed.
 *
 * Also ensures the post is no longer sticky.
 *
 * @since 3.7.0
 * @access private
 *
 * @param int $post_id Post ID.
 */
function _reset_front_page_settings_for_post( $post_id ) {
    return PostHelper::resetFrontPageSettings( $post_id );
}



/* ------------------- Post Meta: --------------------------*/


/**
 * Adds a meta field to the given post.
 *
 * Post meta data is called "Custom Fields" on the Administration Screen.
 *
 * @since 1.5.0
 *
 * @param int    $post_id    Post ID.
 * @param string $meta_key   Metadata name.
 * @param mixed  $meta_value Metadata value. Must be serializable if non-scalar.
 * @param bool   $unique     Optional. Whether the same key should not be added.
 *                           Default false.
 * @return int|false Meta ID on success, false on failure.
 */
function add_post_meta( $post_id, $meta_key, $meta_value, $unique = false ) {
    return PostMetaHelper::add( $post_id, $meta_key, $meta_value, $unique );
}

/**
 * Deletes a post meta field for the given post ID.
 *
 * You can match based on the key, or key and value. Removing based on key and
 * value, will keep from removing duplicate metadata with the same key. It also
 * allows removing all metadata matching the key, if needed.
 *
 * @since 1.5.0
 *
 * @param int    $post_id    Post ID.
 * @param string $meta_key   Metadata name.
 * @param mixed  $meta_value Optional. Metadata value. Must be serializable if
 *                           non-scalar. Default empty.
 * @return bool True on success, false on failure.
 */
function delete_post_meta( $post_id, $meta_key, $meta_value = '' ) {
    return PostMetaHelper::delete( $post_id, $meta_key, $meta_value );
}

/**
 * Retrieves a post meta field for the given post ID.
 *
 * @since 1.5.0
 *
 * @param int    $post_id Post ID.
 * @param string $key     Optional. The meta key to retrieve. By default, returns
 *                        data for all keys. Default empty.
 * @param bool   $single  Optional. If true, returns only the first value for the specified meta key.
 *                        This parameter has no effect if $key is not specified. Default false.
 * @return mixed Will be an array if $single is false. Will be value of the meta
 *               field if $single is true.
 */
function get_post_meta( $post_id, $key = '', $single = false ) {
    return PostMetaHelper::get( $post_id, $key, $single );
}

/**
 * Updates a post meta field based on the given post ID.
 *
 * Use the `$prev_value` parameter to differentiate between meta fields with the
 * same key and post ID.
 *
 * If the meta field for the post does not exist, it will be added and its ID returned.
 *
 * Can be used in place of add_post_meta().
 *
 * @since 1.5.0
 *
 * @param int    $post_id    Post ID.
 * @param string $meta_key   Metadata key.
 * @param mixed  $meta_value Metadata value. Must be serializable if non-scalar.
 * @param mixed  $prev_value Optional. Previous value to check before updating.
 * @return int|bool The new meta field ID if a field with the given key didn't exist and was
 *                  therefore added, true on successful update, false on failure.
 */
function update_post_meta( $post_id, $meta_key, $meta_value, $prev_value = '' ) {
    return PostMetaHelper::update( $post_id, $meta_key, $meta_value, $prev_value );
}



/**
 * Deletes everything from post meta matching the given meta key.
 *
 * @since 2.3.0
 *
 * @param string $post_meta_key Key to search for when deleting.
 * @return bool Whether the post meta key was deleted from the database.
 */
function delete_post_meta_by_key( $post_meta_key ) {
    return PostMetaHelper::deleteByKey( $post_meta_key );
}



/**
 * Registers a meta key for posts.
 *
 * @since 4.9.8
 *
 * @param string $post_type Post type to register a meta key for. Pass an empty string
 *                          to register the meta key across all existing post types.
 * @param string $meta_key  The meta key to register.
 * @param array  $args      Data used to describe the meta key when registered. See
 *                          {@see register_meta()} for a list of supported arguments.
 * @return bool True if the meta key was successfully registered, false if not.
 */
function register_post_meta( $post_type, $meta_key, array $args ) {
    return PostMetaHelper::register( $post_type, $meta_key, $args );
}


/**
 * Unregisters a meta key for posts.
 *
 * @since 4.9.8
 *
 * @param string $post_type Post type the meta key is currently registered for. Pass
 *                          an empty string if the meta key is registered across all
 *                          existing post types.
 * @param string $meta_key  The meta key to unregister.
 * @return bool True on success, false if the meta key was not previously registered.
 */
function unregister_post_meta( $post_type, $meta_key ) {
    return PostMetaHelper::unregister( $post_type, $meta_key );
}


/**
 * Retrieve post meta fields, based on post ID.
 *
 * The post meta fields are retrieved from the cache where possible,
 * so the function is optimized to be called more than once.
 *
 * @since 1.2.0
 *
 * @param int $post_id Optional. Post ID. Default is ID of the global $post.
 * @return array Post meta for the given post.
 */
function get_post_custom( $post_id = 0 ) {
    return PostMetaHelper::getCustom( $post_id );
}


/**
 * Retrieve meta field names for a post.
 *
 * If there are no meta fields, then nothing (null) will be returned.
 *
 * @since 1.2.0
 *
 * @param int $post_id Optional. Post ID. Default is ID of the global $post.
 * @return array|void Array of the keys, if retrieved.
 */
function get_post_custom_keys( $post_id = 0 ) {
    return PostMetaHelper::getCustomKeys( $post_id );
}

/**
 * Retrieve values for a custom post field.
 *
 * The parameters must not be considered optional. All of the post meta fields
 * will be retrieved and only the meta field key values returned.
 *
 * @since 1.2.0
 *
 * @param string $key     Optional. Meta field key. Default empty.
 * @param int    $post_id Optional. Post ID. Default is ID of the global $post.
 * @return array|null Meta field values.
 */
function get_post_custom_values( $key = '', $post_id = 0 ) {
    return PostMetaHelper::getCustomValues( $key, $post_id );
}

/* ------------------- Taxonomies: -----------------------------*/

/**
 * Retrieves the terms associated with the given object(s), in the supplied taxonomies.
 *
 * @since 2.3.0
 * @since 4.2.0 Added support for 'taxonomy', 'parent', and 'term_taxonomy_id' values of `$orderby`.
 *              Introduced `$parent` argument.
 * @since 4.4.0 Introduced `$meta_query` and `$update_term_meta_cache` arguments. When `$fields` is 'all' or
 *              'all_with_object_id', an array of `WP_Term` objects will be returned.
 * @since 4.7.0 Refactored to use WP_Term_Query, and to support any WP_Term_Query arguments.
 *
 * @param int|int[]       $object_ids The ID(s) of the object(s) to retrieve.
 * @param string|string[] $taxonomies The taxonomy names to retrieve terms from.
 * @param array|string    $args       See WP_Term_Query::__construct() for supported arguments.
 * @return array|WP_Error The requested term data or empty array if no terms found.
 *                        WP_Error if any of the taxonomies don't exist.
 */
function wp_get_object_terms( $object_ids, $taxonomies, $args = array() ) {
    return TermHelper::getForObject( $object_ids, $taxonomies, $args );
}



/**
 * Create Term and Taxonomy Relationships.
 *
 * Relates an object (post, link etc) to a term and taxonomy type. Creates the
 * term and taxonomy relationship if it doesn't already exist. Creates a term if
 * it doesn't exist (using the slug).
 *
 * A relationship means that the term is grouped in or belongs to the taxonomy.
 * A term has no meaning until it is given context by defining which taxonomy it
 * exists under.
 *
 * @since 2.3.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int              $object_id The object to relate to.
 * @param string|int|array $terms     A single term slug, single term id, or array of either term slugs or ids.
 *                                    Will replace all existing related terms in this taxonomy. Passing an
 *                                    empty value will remove all related terms.
 * @param string           $taxonomy  The context in which to relate the term to the object.
 * @param bool             $append    Optional. If false will delete difference of terms. Default false.
 * @return array|WP_Error Term taxonomy IDs of the affected terms or WP_Error on failure.
 */
function wp_set_object_terms( $object_id, $terms, $taxonomy, $append = false ) {
    return TermHelper::setForObject( $object_id, $terms, $taxonomy, $append );
}


/**
 * Add a new term to the database.
 *
 * A non-existent term is inserted in the following sequence:
 * 1. The term is added to the term table, then related to the taxonomy.
 * 2. If everything is correct, several actions are fired.
 * 3. The 'term_id_filter' is evaluated.
 * 4. The term cache is cleaned.
 * 5. Several more actions are fired.
 * 6. An array is returned containing the term_id and term_taxonomy_id.
 *
 * If the 'slug' argument is not empty, then it is checked to see if the term
 * is invalid. If it is not a valid, existing term, it is added and the term_id
 * is given.
 *
 * If the taxonomy is hierarchical, and the 'parent' argument is not empty,
 * the term is inserted and the term_id will be given.
 *
 * Error handling:
 * If $taxonomy does not exist or $term is empty,
 * a WP_Error object will be returned.
 *
 * If the term already exists on the same hierarchical level,
 * or the term slug and name are not unique, a WP_Error object will be returned.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @since 2.3.0
 *
 * @param string       $term     The term to add or update.
 * @param string       $taxonomy The taxonomy to which to add the term.
 * @param array|string $args {
 *     Optional. Array or string of arguments for inserting a term.
 *
 *     @type string $alias_of    Slug of the term to make this term an alias of.
 *                               Default empty string. Accepts a term slug.
 *     @type string $description The term description. Default empty string.
 *     @type int    $parent      The id of the parent term. Default 0.
 *     @type string $slug        The term slug to use. Default empty string.
 * }
 * @return array|WP_Error An array containing the `term_id` and `term_taxonomy_id`,
 *                        WP_Error otherwise.
 */
function wp_insert_term( $term, $taxonomy, $args = array() ) {
    return TermHelper::add( $term, $taxonomy, $args );
}

/* ------------------- Metadata: -----------------------------*/

/**
 * Add metadata for the specified object.
 *
 * @since 2.9.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string $meta_type  Type of object metadata is for (e.g., comment, post, term, or user).
 * @param int    $object_id  ID of the object metadata is for
 * @param string $meta_key   Metadata key
 * @param mixed  $meta_value Metadata value. Must be serializable if non-scalar.
 * @param bool   $unique     Optional, default is false.
 *                           Whether the specified metadata key should be unique for the object.
 *                           If true, and the object already has a value for the specified metadata key,
 *                           no change will be made.
 * @return int|false The meta ID on success, false on failure.
 */
function add_metadata( $meta_type, $object_id, $meta_key, $meta_value, $unique = false ) {
    return Metadata::add( $meta_type, $object_id, $meta_key, $meta_value, $unique );
}

/**
 * Update metadata for the specified object. If no value already exists for the specified object
 * ID and metadata key, the metadata will be added.
 *
 * @since 2.9.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string $meta_type  Type of object metadata is for (e.g., comment, post, term, or user).
 * @param int    $object_id  ID of the object metadata is for
 * @param string $meta_key   Metadata key
 * @param mixed  $meta_value Metadata value. Must be serializable if non-scalar.
 * @param mixed  $prev_value Optional. If specified, only update existing metadata entries with
 *                           the specified value. Otherwise, update all entries.
 * @return int|bool The new meta field ID if a field with the given key didn't exist and was
 *                  therefore added, true on successful update, false on failure.
 */
function update_metadata( $meta_type, $object_id, $meta_key, $meta_value, $prev_value = '' ) {
    return Metadata::update( $meta_type, $object_id, $meta_key, $meta_value, $prev_value );
}



/**
 * Delete metadata for the specified object.
 *
 * @since 2.9.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string $meta_type  Type of object metadata is for (e.g., comment, post, term, or user).
 * @param int    $object_id  ID of the object metadata is for
 * @param string $meta_key   Metadata key
 * @param mixed  $meta_value Optional. Metadata value. Must be serializable if non-scalar. If specified, only delete
 *                           metadata entries with this value. Otherwise, delete all entries with the specified meta_key.
 *                           Pass `null`, `false`, or an empty string to skip this check. (For backward compatibility,
 *                           it is not possible to pass an empty string to delete those entries with an empty string
 *                           for a value.)
 * @param bool   $delete_all Optional, default is false. If true, delete matching metadata entries for all objects,
 *                           ignoring the specified object_id. Otherwise, only delete matching metadata entries for
 *                           the specified object_id.
 * @return bool True on successful delete, false on failure.
 */
function delete_metadata( $meta_type, $object_id, $meta_key, $meta_value = '', $delete_all = false ) {
    return Metadata::delete( $meta_type, $object_id, $meta_key, $meta_value, $delete_all );
}

/**
 * Retrieve metadata for the specified object.
 *
 * @since 2.9.0
 *
 * @param string $meta_type Type of object metadata is for (e.g., comment, post, term, or user).
 * @param int    $object_id ID of the object metadata is for
 * @param string $meta_key  Optional. Metadata key. If not specified, retrieve all metadata for
 *                          the specified object.
 * @param bool   $single    Optional, default is false.
 *                          If true, return only the first value of the specified meta_key.
 *                          This parameter has no effect if meta_key is not specified.
 * @return mixed Single metadata value, or array of values
 */
function get_metadata( $meta_type, $object_id, $meta_key = '', $single = false ) {
    return Metadata::get( $meta_type, $object_id, $meta_key, $single );
}


/**
 * Registers a meta key.
 *
 * It is recommended to register meta keys for a specific combination of object type and object subtype. If passing
 * an object subtype is omitted, the meta key will be registered for the entire object type, however it can be partly
 * overridden in case a more specific meta key of the same name exists for the same object type and a subtype.
 *
 * If an object type does not support any subtypes, such as users or comments, you should commonly call this function
 * without passing a subtype.
 *
 * @since 3.3.0
 * @since 4.6.0 {@link https://core.trac.wordpress.org/ticket/35658 Modified
 *              to support an array of data to attach to registered meta keys}. Previous arguments for
 *              `$sanitize_callback` and `$auth_callback` have been folded into this array.
 * @since 4.9.8 The `$object_subtype` argument was added to the arguments array.
 *
 * @param string $object_type    Type of object this meta is registered to.
 * @param string $meta_key       Meta key to register.
 * @param array  $args {
 *     Data used to describe the meta key when registered.
 *
 *     @type string $object_subtype    A subtype; e.g. if the object type is "post", the post type. If left empty,
 *                                     the meta key will be registered on the entire object type. Default empty.
 *     @type string $type              The type of data associated with this meta key.
 *                                     Valid values are 'string', 'boolean', 'integer', and 'number'.
 *     @type string $description       A description of the data attached to this meta key.
 *     @type bool   $single            Whether the meta key has one value per object, or an array of values per object.
 *     @type string $sanitize_callback A function or method to call when sanitizing `$meta_key` data.
 *     @type string $auth_callback     Optional. A function or method to call when performing edit_post_meta, add_post_meta, and delete_post_meta capability checks.
 *     @type bool   $show_in_rest      Whether data associated with this meta key can be considered public and
 *                                     should be accessible via the REST API. A custom post type must also declare
 *                                     support for custom fields for registered meta to be accessible via REST.
 * }
 * @param string|array $deprecated Deprecated. Use `$args` instead.
 *
 * @return bool True if the meta key was successfully registered in the global array, false if not.
 *                       Registering a meta key with distinct sanitize and auth callbacks will fire those
 *                       callbacks, but will not add to the global registry.
 */
function register_meta( $object_type, $meta_key, $args, $deprecated = null ) {
    return Metadata::register( $object_type, $meta_key, $args, $deprecated );
}

/**
 * Unregisters a meta key from the list of registered keys.
 *
 * @since 4.6.0
 * @since 4.9.8 The `$object_subtype` parameter was added.
 *
 * @param string $object_type    The type of object.
 * @param string $meta_key       The meta key.
 * @param string $object_subtype Optional. The subtype of the object type.
 * @return bool True if successful. False if the meta key was not registered.
 */
function unregister_meta_key( $object_type, $meta_key, $object_subtype = '' ) {
    return Metadata::unregisterKey( $object_type, $meta_key, $object_subtype = '' );
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

/* ------------------- Hooks: --------------------------*/


/**
 * Hook a function or method to a specific filter action.
 *
 * WordPress offers filter hooks to allow plugins to modify
 * various types of internal data at runtime.
 *
 * A plugin can modify data by binding a callback to a filter hook. When the filter
 * is later applied, each bound callback is run in order of priority, and given
 * the opportunity to modify a value by returning a new value.
 *
 * The following example shows how a callback function is bound to a filter hook.
 *
 * Note that `$example` is passed to the callback, (maybe) modified, then returned:
 *
 *     function example_callback( $example ) {
 *         // Maybe modify $example in some way.
 *         return $example;
 *     }
 *     add_filter( 'example_filter', 'example_callback' );
 *
 * Bound callbacks can accept from none to the total number of arguments passed as parameters
 * in the corresponding apply_filters() call.
 *
 * In other words, if an apply_filters() call passes four total arguments, callbacks bound to
 * it can accept none (the same as 1) of the arguments or up to four. The important part is that
 * the `$accepted_args` value must reflect the number of arguments the bound callback *actually*
 * opted to accept. If no arguments were accepted by the callback that is considered to be the
 * same as accepting 1 argument. For example:
 *
 *     // Filter call.
 *     $value = apply_filters( 'hook', $value, $arg2, $arg3 );
 *
 *     // Accepting zero/one arguments.
 *     function example_callback() {
 *         ...
 *         return 'some value';
 *     }
 *     add_filter( 'hook', 'example_callback' ); // Where $priority is default 10, $accepted_args is default 1.
 *
 *     // Accepting two arguments (three possible).
 *     function example_callback( $value, $arg2 ) {
 *         ...
 *         return $maybe_modified_value;
 *     }
 *     add_filter( 'hook', 'example_callback', 10, 2 ); // Where $priority is 10, $accepted_args is 2.
 *
 * *Note:* The function will return true whether or not the callback is valid.
 * It is up to you to take care. This is done for optimization purposes, so
 * everything is as quick as possible.
 *
 * @since 0.71
 *
 * @global array $wp_filter      A multidimensional array of all hooks and the callbacks hooked to them.
 *
 * @param string   $tag             The name of the filter to hook the $function_to_add callback to.
 * @param callable $function_to_add The callback to be run when the filter is applied.
 * @param int      $priority        Optional. Used to specify the order in which the functions
 *                                  associated with a particular action are executed. Default 10.
 *                                  Lower numbers correspond with earlier execution,
 *                                  and functions with the same priority are executed
 *                                  in the order in which they were added to the action.
 * @param int      $accepted_args   Optional. The number of arguments the function accepts. Default 1.
 * @return true
 */
function add_filter( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
	return HookHelper::addFilter( $tag, $function_to_add, $priority, $accepted_args );
}

/**
 * Check if any filter has been registered for a hook.
 *
 * @since 2.5.0
 *
 * @global array $wp_filter Stores all of the filters.
 *
 * @param string        $tag               The name of the filter hook.
 * @param callable|bool $function_to_check Optional. The callback to check for. Default false.
 * @return false|int If $function_to_check is omitted, returns boolean for whether the hook has
 *                   anything registered. When checking a specific function, the priority of that
 *                   hook is returned, or false if the function is not attached. When using the
 *                   $function_to_check argument, this function may return a non-boolean value
 *                   that evaluates to false (e.g.) 0, so use the === operator for testing the
 *                   return value.
 */
function has_filter( $tag, $function_to_check = false ) {
	return HookHelper::hasFilter( $tag, $function_to_check );
}

/**
 * Calls the callback functions that have been added to a filter hook.
 *
 * The callback functions attached to the filter hook are invoked by calling
 * this function. This function can be used to create a new filter hook by
 * simply calling this function with the name of the new hook specified using
 * the `$tag` parameter.
 *
 * The function also allows for multiple additional arguments to be passed to hooks.
 *
 * Example usage:
 *
 *     // The filter callback function
 *     function example_callback( $string, $arg1, $arg2 ) {
 *         // (maybe) modify $string
 *         return $string;
 *     }
 *     add_filter( 'example_filter', 'example_callback', 10, 3 );
 *
 *     /*
 *      * Apply the filters by calling the 'example_callback()' function that's
 *      * hooked onto `example_filter` above.
 *      *
 *      * - 'example_filter' is the filter hook
 *      * - 'filter me' is the value being filtered
 *      * - $arg1 and $arg2 are the additional arguments passed to the callback.
 *     $value = apply_filters( 'example_filter', 'filter me', $arg1, $arg2 );
 *
 * @since 0.71
 *
 * @global array $wp_filter         Stores all of the filters.
 * @global array $wp_current_filter Stores the list of current filters with the current one last.
 *
 * @param string $tag     The name of the filter hook.
 * @param mixed  $value   The value to filter.
 * @param mixed  ...$args Additional parameters to pass to the callback functions.
 * @return mixed The filtered value after all hooked functions are applied to it.
 */
function apply_filters( $tag, $value, ...$args ) {
	return HookHelper::applyFilters( $tag, $value, ...$args );
}

/**
 * Calls the callback functions that have been added to a filter hook, specifying arguments in an array.
 *
 * @since 3.0.0
 *
 * @see apply_filters() This function is identical, but the arguments passed to the
 * functions hooked to `$tag` are supplied using an array.
 *
 * @global array $wp_filter         Stores all of the filters
 * @global array $wp_current_filter Stores the list of current filters with the current one last
 *
 * @param string $tag  The name of the filter hook.
 * @param array  $args The arguments supplied to the functions hooked to $tag.
 * @return mixed The filtered value after all hooked functions are applied to it.
 */
function apply_filters_ref_array( $tag, $args ) {
	return HookHelper::applyFiltersRefArray( $tag, $args );
}

/**
 * Removes a function from a specified filter hook.
 *
 * This function removes a function attached to a specified filter hook. This
 * method can be used to remove default functions attached to a specific filter
 * hook and possibly replace them with a substitute.
 *
 * To remove a hook, the $function_to_remove and $priority arguments must match
 * when the hook was added. This goes for both filters and actions. No warning
 * will be given on removal failure.
 *
 * @since 1.2.0
 *
 * @global array $wp_filter         Stores all of the filters
 *
 * @param string   $tag                The filter hook to which the function to be removed is hooked.
 * @param callable $function_to_remove The name of the function which should be removed.
 * @param int      $priority           Optional. The priority of the function. Default 10.
 * @return bool    Whether the function existed before it was removed.
 */
function remove_filter( $tag, $function_to_remove, $priority = 10 ) {
	return HookHelper::removeFilter( $tag, $function_to_remove, $priority );
}

/**
 * Remove all of the hooks from a filter.
 *
 * @since 2.7.0
 *
 * @global array $wp_filter  Stores all of the filters
 *
 * @param string   $tag      The filter to remove hooks from.
 * @param int|bool $priority Optional. The priority number to remove. Default false.
 * @return true True when finished.
 */
function remove_all_filters( $tag, $priority = false ) {
	return HookHelper::removeAllFilters( $tag, $priority );
}

/**
 * Retrieve the name of the current filter or action.
 *
 * @since 2.5.0
 *
 * @global array $wp_current_filter Stores the list of current filters with the current one last
 *
 * @return string Hook name of the current filter or action.
 */
function current_filter() {
	return HookHelper::currentFilter();
}

/**
 * Retrieve the name of the current action.
 *
 * @since 3.9.0
 *
 * @return string Hook name of the current action.
 */
function current_action() {
	return HookHelper::currentAction();
}

/**
 * Retrieve the name of a filter currently being processed.
 *
 * The function current_filter() only returns the most recent filter or action
 * being executed. did_action() returns true once the action is initially
 * processed.
 *
 * This function allows detection for any filter currently being
 * executed (despite not being the most recent filter to fire, in the case of
 * hooks called from hook callbacks) to be verified.
 *
 * @since 3.9.0
 *
 * @see current_filter()
 * @see did_action()
 * @global array $wp_current_filter Current filter.
 *
 * @param null|string $filter Optional. Filter to check. Defaults to null, which
 *                            checks if any filter is currently being run.
 * @return bool Whether the filter is currently in the stack.
 */
function doing_filter( $filter = null ) {
	return HookHelper::doingFilter( $filter );
}

/**
 * Retrieve the name of an action currently being processed.
 *
 * @since 3.9.0
 *
 * @param string|null $action Optional. Action to check. Defaults to null, which checks
 *                            if any action is currently being run.
 * @return bool Whether the action is currently in the stack.
 */
function doing_action( $action = null ) {
	return HookHelper::doingAction( $action );
}

/**
 * Hooks a function on to a specific action.
 *
 * Actions are the hooks that the WordPress core launches at specific points
 * during execution, or when specific events occur. Plugins can specify that
 * one or more of its PHP functions are executed at these points, using the
 * Action API.
 *
 * @since 1.2.0
 *
 * @param string   $tag             The name of the action to which the $function_to_add is hooked.
 * @param callable $function_to_add The name of the function you wish to be called.
 * @param int      $priority        Optional. Used to specify the order in which the functions
 *                                  associated with a particular action are executed. Default 10.
 *                                  Lower numbers correspond with earlier execution,
 *                                  and functions with the same priority are executed
 *                                  in the order in which they were added to the action.
 * @param int      $accepted_args   Optional. The number of arguments the function accepts. Default 1.
 * @return true Will always return true.
 */
function add_action( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
	return HookHelper::addAction( $tag, $function_to_add, $priority, $accepted_args );
}

/**
 * Execute functions hooked on a specific action hook.
 *
 * This function invokes all functions attached to action hook `$tag`. It is
 * possible to create new action hooks by simply calling this function,
 * specifying the name of the new hook using the `$tag` parameter.
 *
 * You can pass extra arguments to the hooks, much like you can with `apply_filters()`.
 *
 * Example usage:
 *
 *     // The action callback function
 *     function example_callback( $arg1, $arg2 ) {
 *         // (maybe) do something with the args
 *     }
 *     add_action( 'example_action', 'example_callback', 10, 2 );
 *
 *     /*
 *      * Trigger the actions by calling the 'example_callback()' function that's
 *      * hooked onto `example_action` above.
 *      *
 *      * - 'example_action' is the action hook
 *      * - $arg1 and $arg2 are the additional arguments passed to the callback.
 *     $value = do_action( 'example_action', $arg1, $arg2 );
 *
 * @since 1.2.0
 *
 * @global array $wp_filter         Stores all of the filters
 * @global array $wp_actions        Increments the amount of times action was triggered.
 * @global array $wp_current_filter Stores the list of current filters with the current one last
 *
 * @param string $tag    The name of the action to be executed.
 * @param mixed  ...$arg Optional. Additional arguments which are passed on to the
 *                       functions hooked to the action. Default empty.
 */
function do_action( $tag, $arg = '' ) {
	HookHelper::doAction( $tag, $arg );
}

/**
 * Retrieve the number of times an action is fired.
 *
 * @since 2.1.0
 *
 * @global array $wp_actions Increments the amount of times action was triggered.
 *
 * @param string $tag The name of the action hook.
 * @return int The number of times action hook $tag is fired.
 */
function did_action( $tag ) {
	return HookHelper::didAction( $tag );
}

/**
 * Calls the callback functions that have been added to an action hook, specifying arguments in an array.
 *
 * @since 2.1.0
 *
 * @see do_action() This function is identical, but the arguments passed to the
 *                  functions hooked to `$tag` are supplied using an array.
 * @global array $wp_filter         Stores all of the filters
 * @global array $wp_actions        Increments the amount of times action was triggered.
 * @global array $wp_current_filter Stores the list of current filters with the current one last
 *
 * @param string $tag  The name of the action to be executed.
 * @param array  $args The arguments supplied to the functions hooked to `$tag`.
 */
function do_action_ref_array( $tag, $args ) {
	HookHelper::doActionRefArray( $tag, $args );
}

/**
 * Check if any action has been registered for a hook.
 *
 * @since 2.5.0
 *
 * @see has_filter() has_action() is an alias of has_filter().
 *
 * @param string        $tag               The name of the action hook.
 * @param callable|bool $function_to_check Optional. The callback to check for. Default false.
 * @return bool|int If $function_to_check is omitted, returns boolean for whether the hook has
 *                  anything registered. When checking a specific function, the priority of that
 *                  hook is returned, or false if the function is not attached. When using the
 *                  $function_to_check argument, this function may return a non-boolean value
 *                  that evaluates to false (e.g.) 0, so use the === operator for testing the
 *                  return value.
 */
function has_action( $tag, $function_to_check = false ) {
	return HookHelper::hasAction( $tag, $function_to_check );
}

/**
 * Removes a function from a specified action hook.
 *
 * This function removes a function attached to a specified action hook. This
 * method can be used to remove default functions attached to a specific filter
 * hook and possibly replace them with a substitute.
 *
 * @since 1.2.0
 *
 * @param string   $tag                The action hook to which the function to be removed is hooked.
 * @param callable $function_to_remove The name of the function which should be removed.
 * @param int      $priority           Optional. The priority of the function. Default 10.
 * @return bool Whether the function is removed.
 */
function remove_action( $tag, $function_to_remove, $priority = 10 ) {
	return HookHelper::removeAction( $tag, $function_to_remove, $priority = 10 );
}

/**
 * Remove all of the hooks from an action.
 *
 * @since 2.7.0
 *
 * @param string   $tag      The action to remove hooks from.
 * @param int|bool $priority The priority number to remove them from. Default false.
 * @return true True when finished.
 */
function remove_all_actions( $tag, $priority = false ) {
	return HookHelper::removeAllActions( $tag, $priority );
}

/**
 * Fires functions attached to a deprecated filter hook.
 *
 * When a filter hook is deprecated, the apply_filters() call is replaced with
 * apply_filters_deprecated(), which triggers a deprecation notice and then fires
 * the original filter hook.
 *
 * Note: the value and extra arguments passed to the original apply_filters() call
 * must be passed here to `$args` as an array. For example:
 *
 *     // Old filter.
 *     return apply_filters( 'wpdocs_filter', $value, $extra_arg );
 *
 *     // Deprecated.
 *     return apply_filters_deprecated( 'wpdocs_filter', array( $value, $extra_arg ), '4.9', 'wpdocs_new_filter' );
 *
 * @param string 		$tag         The name of the filter hook.
 * @param array  		$args        Array of additional function arguments to be passed to apply_filters().
 * @param string 		$version     The version of WordPress that deprecated the hook.
 * @param bool|string 	$replacement Optional. The hook that should have been used. Default false.
 * @param string 		$message     Optional. A message regarding the change. Default null.
 *
 * @return mixed
 * @since 4.6.0
 *
 * @see   _deprecated_hook()
 *
 */
function apply_filters_deprecated( $tag, $args, $version, $replacement = false, $message = null ) {
	return HookHelper::applyFiltersDeprecated( $tag, $args, $version, $replacement, $message );
}

/**
 * Fires functions attached to a deprecated action hook.
 *
 * When an action hook is deprecated, the do_action() call is replaced with
 * do_action_deprecated(), which triggers a deprecation notice and then fires
 * the original hook.
 *
 * @since 4.6.0
 *
 * @see _deprecated_hook()
 *
 * @param string $tag         The name of the action hook.
 * @param array  $args        Array of additional function arguments to be passed to do_action().
 * @param string $version     The version of WordPress that deprecated the hook.
 * @param bool|string $replacement Optional. The hook that should have been used.
 * @param string $message     Optional. A message regarding the change.
 */
function do_action_deprecated( $tag, $args, $version, $replacement = false, $message = null ) {
	HookHelper::doActionDeprecated( $tag, $args, $version, $replacement, $message );
}

/**
 * Set the activation hook for a plugin.
 *
 * When a plugin is activated, the action 'activate_PLUGINNAME' hook is
 * called. In the name of this hook, PLUGINNAME is replaced with the name
 * of the plugin, including the optional subdirectory. For example, when the
 * plugin is located in wp-content/plugins/sampleplugin/sample.php, then
 * the name of this hook will become 'activate_sampleplugin/sample.php'.
 *
 * When the plugin consists of only one file and is (as by default) located at
 * wp-content/plugins/sample.php the name of this hook will be
 * 'activate_sample.php'.
 *
 * @since 2.0.0
 *
 * @param string   $file     The filename of the plugin including the path.
 * @param callable $function The function hooked to the 'activate_PLUGIN' action.
 */
function register_activation_hook( $file, $function ) {
	HookHelper::registerActivationHook( $file, $function );
}

/**
 * Set the deactivation hook for a plugin.
 *
 * When a plugin is deactivated, the action 'deactivate_PLUGINNAME' hook is
 * called. In the name of this hook, PLUGINNAME is replaced with the name
 * of the plugin, including the optional subdirectory. For example, when the
 * plugin is located in wp-content/plugins/sampleplugin/sample.php, then
 * the name of this hook will become 'deactivate_sampleplugin/sample.php'.
 *
 * When the plugin consists of only one file and is (as by default) located at
 * wp-content/plugins/sample.php the name of this hook will be
 * 'deactivate_sample.php'.
 *
 * @since 2.0.0
 *
 * @param string   $file     The filename of the plugin including the path.
 * @param callable $function The function hooked to the 'deactivate_PLUGIN' action.
 */
function register_deactivation_hook( $file, $function ) {
	HookHelper::registerDeactivationHook( $file, $function );
}

/**
 * Set the uninstallation hook for a plugin.
 *
 * Registers the uninstall hook that will be called when the user clicks on the
 * uninstall link that calls for the plugin to uninstall itself. The link won't
 * be active unless the plugin hooks into the action.
 *
 * The plugin should not run arbitrary code outside of functions, when
 * registering the uninstall hook. In order to run using the hook, the plugin
 * will have to be included, which means that any code laying outside of a
 * function will be run during the uninstallation process. The plugin should not
 * hinder the uninstallation process.
 *
 * If the plugin can not be written without running code within the plugin, then
 * the plugin should create a file named 'uninstall.php' in the base plugin
 * folder. This file will be called, if it exists, during the uninstallation process
 * bypassing the uninstall hook. The plugin, when using the 'uninstall.php'
 * should always check for the 'WP_UNINSTALL_PLUGIN' constant, before
 * executing.
 *
 * @since 2.7.0
 *
 * @param string   $file     Plugin file.
 * @param callable $callback The callback to run when the hook is called. Must be
 *                           a static method or function.
 */
function register_uninstall_hook( $file, $callback ) {
	HookHelper::registerUninstallHook( $file, $callback );
}

/**
 * Call the 'all' hook, which will process the functions hooked into it.
 *
 * The 'all' hook passes all of the arguments or parameters that were used for
 * the hook, which this function was called for.
 *
 * This function is used internally for apply_filters(), do_action(), and
 * do_action_ref_array() and is not meant to be used from outside those
 * functions. This function does not check for the existence of the all hook, so
 * it will fail unless the all hook exists prior to this function call.
 *
 * @since 2.5.0
 * @access private
 *
 * @global array $wp_filter  Stores all of the filters
 *
 * @param array $args The collected parameters from the hook that was called.
 */
function _wp_call_all_hook( $args ) {
	HookHelper::callAllHook( $args );
}

/**
 * Build Unique ID for storage and retrieval.
 *
 * The old way to serialize the callback caused issues and this function is the
 * solution. It works by checking for objects and creating a new property in
 * the class to keep track of the object and new objects of the same class that
 * need to be added.
 *
 * It also allows for the removal of actions and filters for objects after they
 * change class properties. It is possible to include the property $wp_filter_id
 * in your class and set it to "null" or a number to bypass the workaround.
 * However this will prevent you from adding new classes and any new classes
 * will overwrite the previous hook by the same class.
 *
 * Functions and static method callbacks are just returned as strings and
 * shouldn't have any speed penalty.
 *
 * @link https://core.trac.wordpress.org/ticket/3875
 *
 * @since 2.2.3
 * @access private
 *
 * @global array $wp_filter Storage for all of the filters and actions.
 * @staticvar int $filter_id_count
 *
 * @param string   $tag      Used in counting how many hooks were applied
 * @param callable $function Used for creating unique id
 * @param int|bool $priority Used in counting how many hooks were applied. If === false
 *                           and $function is an object reference, we return the unique
 *                           id only if it already has one, false otherwise.
 * @return string|false Unique ID for usage as array key or false if $priority === false
 *                      and $function is an object reference, and it does not already have
 *                      a unique id.
 */
function _wp_filter_build_unique_id( $tag, $function, $priority ) {
	return HookHelper::buildUniqueId( $tag, $function, $priority );
}

/* ------------------- Plugins: --------------------------*/

/**
 * Gets the basename of a plugin.
 *
 * This method extracts the name of a plugin from its filename.
 *
 * @since 1.5.0
 *
 * @global array $wp_plugin_paths
 *
 * @param string $file The filename of plugin.
 * @return string The name of a plugin.
 */
function plugin_basename( $file ) {
	return PluginHelper::basename( $file );
}

/**
 * Register a plugin's real path.
 *
 * This is used in plugin_basename() to resolve symlinked paths.
 *
 * @since 3.9.0
 *
 * @see wp_normalize_path()
 *
 * @global array $wp_plugin_paths
 *
 * @staticvar string $wp_plugin_path
 * @staticvar string $wpmu_plugin_path
 *
 * @param string $file Known path to the file.
 * @return bool Whether the path was able to be registered.
 */
function wp_register_plugin_realpath( $file ) {
	return PluginHelper::registerRealpath( $file );
}

/**
 * Get the filesystem directory path (with trailing slash) for the plugin __FILE__ passed in.
 *
 * @since 2.8.0
 *
 * @param string $file The filename of the plugin (__FILE__).
 * @return string the filesystem path of the directory that contains the plugin.
 */
function plugin_dir_path( $file ) {
	return PluginHelper::dirPath( $file );
}

/**
 * Get the URL directory path (with trailing slash) for the plugin __FILE__ passed in.
 *
 * @since 2.8.0
 *
 * @param string $file The filename of the plugin (__FILE__).
 * @return string the URL path of the directory that contains the plugin.
 */
function plugin_dir_url( $file ) {
	return PluginHelper::dirUrl( $file );
}

/* ------------------- Login: --------------------------*/

/**
 * Output the login page header.
 *
 * @since 2.1.0
 *
 * @param string   $title    Optional. WordPress login Page title to display in the `<title>` element.
 *                           Default 'Log In'.
 * @param string   $message  Optional. Message to display in header. Default empty.
 * @param WP_Error $wp_error Optional. The error to pass. Default is a WP_Error instance.
 */
function login_header( $title = 'Log In', $message = '', $wp_error = null ) {
	LoginHelper::loginHeader( $title, $message, $wp_error );
}

/**
 * Outputs the footer for the login page.
 *
 * @since 3.1.0
 *
 * @param string $input_id Which input to auto-focus.
 */
function login_footer( $input_id = '' ) {
	LoginHelper::loginFooter( $input_id );
}

/**
 * Outputs the Javascript to handle the form shaking.
 *
 * @since 3.0.0
 */
function wp_shake_js() {
	LoginHelper::shakeJs();
}

/**
 * Outputs the viewport meta tag.
 *
 * @since 3.7.0
 */
function wp_login_viewport_meta() {
	LoginHelper::loginViewportMeta();
}

/**
 * Handles sending password retrieval email to user.
 *
 * @since 2.5.0
 *
 * @return bool|WP_Error True: when finish. WP_Error on error
 */
function retrieve_password() {
	return LoginHelper::retrievePassword();
}


/* ------------------- Caching: --------------------------*/

/**
 * Return the cache key for wp_count_posts() based on the passed arguments.
 *
 * @since 3.9.0
 * @access private
 *
 * @param string $type Optional. Post type to retrieve count Default 'post'.
 * @param string $perm Optional. 'readable' or empty. Default empty.
 * @return string The cache key.
 */
function _count_posts_cache_key( $type = 'post', $perm = '' ) {
    return CacheHelper::countPostCacheKey( $type, $perm );
}

/* ------------------- Capabilities: --------------------------*/

/**
 * Maps meta capabilities to primitive capabilities.
 *
 * This function also accepts an ID of an object to map against if the capability is a meta capability. Meta
 * capabilities such as `edit_post` and `edit_user` are capabilities used by this function to map to primitive
 * capabilities that a user or role has, such as `edit_posts` and `edit_others_posts`.
 *
 * Example usage:
 *
 *     map_meta_cap( 'edit_posts', $user->ID );
 *     map_meta_cap( 'edit_post', $user->ID, $post->ID );
 *     map_meta_cap( 'edit_post_meta', $user->ID, $post->ID, $meta_key );
 *
 * This does not actually compare whether the user ID has the actual capability,
 * just what the capability or capabilities are. Meta capability list value can
 * be 'delete_user', 'edit_user', 'remove_user', 'promote_user', 'delete_post',
 * 'delete_page', 'edit_post', 'edit_page', 'read_post', or 'read_page'.
 *
 * @since 2.0.0
 *
 * @global array $post_type_meta_caps Used to get post type meta capabilities.
 *
 * @param string $cap     Capability name.
 * @param int    $user_id User ID.
 * @param mixed  ...$args Optional further parameters, typically starting with an object ID.
 * @return array Actual capabilities for meta capability.
 */
function map_meta_cap( $cap, $user_id, ...$args ) {
	return CapabilitiesHelper::mapMetaCap( $cap, $user_id, ...$args );
}

/**
 * Returns whether the current user has the specified capability.
 *
 * This function also accepts an ID of an object to check against if the capability is a meta capability. Meta
 * capabilities such as `edit_post` and `edit_user` are capabilities used by the `map_meta_cap()` function to
 * map to primitive capabilities that a user or role has, such as `edit_posts` and `edit_others_posts`.
 *
 * Example usage:
 *
 *     current_user_can( 'edit_posts' );
 *     current_user_can( 'edit_post', $post->ID );
 *     current_user_can( 'edit_post_meta', $post->ID, $meta_key );
 *
 * While checking against particular roles in place of a capability is supported
 * in part, this practice is discouraged as it may produce unreliable results.
 *
 * Note: Will always return true if the current user is a super admin, unless specifically denied.
 *
 * @since 2.0.0
 *
 * @see WP_User::has_cap()
 * @see map_meta_cap()
 *
 * @param string $capability Capability name.
 * @param mixed  ...$args    Optional further parameters, typically starting with an object ID.
 * @return bool Whether the current user has the given capability. If `$capability` is a meta cap and `$object_id` is
 *              passed, whether the current user has the given meta capability for the given object.
 */
function current_user_can( $capability, ...$args ) {
	return CapabilitiesHelper::currentUserCan( $capability, ...$args );
}

/**
 * Returns whether the current user has the specified capability for a given site.
 *
 * This function also accepts an ID of an object to check against if the capability is a meta capability. Meta
 * capabilities such as `edit_post` and `edit_user` are capabilities used by the `map_meta_cap()` function to
 * map to primitive capabilities that a user or role has, such as `edit_posts` and `edit_others_posts`.
 *
 * Example usage:
 *
 *     current_user_can_for_blog( $blog_id, 'edit_posts' );
 *     current_user_can_for_blog( $blog_id, 'edit_post', $post->ID );
 *     current_user_can_for_blog( $blog_id, 'edit_post_meta', $post->ID, $meta_key );
 *
 * @since 3.0.0
 *
 * @param int    $blog_id    Site ID.
 * @param string $capability Capability name.
 * @param mixed  ...$args    Optional further parameters, typically starting with an object ID.
 * @return bool Whether the user has the given capability.
 */
function current_user_can_for_blog( $blog_id, $capability, ...$args ) {
	return CapabilitiesHelper::currentUserCanForBlog( $blog_id, $capability, ...$args );
}

/**
 * Returns whether the author of the supplied post has the specified capability.
 *
 * This function also accepts an ID of an object to check against if the capability is a meta capability. Meta
 * capabilities such as `edit_post` and `edit_user` are capabilities used by the `map_meta_cap()` function to
 * map to primitive capabilities that a user or role has, such as `edit_posts` and `edit_others_posts`.
 *
 * Example usage:
 *
 *     author_can( $post, 'edit_posts' );
 *     author_can( $post, 'edit_post', $post->ID );
 *     author_can( $post, 'edit_post_meta', $post->ID, $meta_key );
 *
 * @since 2.9.0
 *
 * @param int|WP_Post $post       Post ID or post object.
 * @param string      $capability Capability name.
 * @param mixed       ...$args    Optional further parameters, typically starting with an object ID.
 * @return bool Whether the post author has the given capability.
 */
function author_can( $post, $capability, ...$args ) {
	return CapabilitiesHelper::authorCan( $post, $capability, ...$args );
}

/**
 * Returns whether a particular user has the specified capability.
 *
 * This function also accepts an ID of an object to check against if the capability is a meta capability. Meta
 * capabilities such as `edit_post` and `edit_user` are capabilities used by the `map_meta_cap()` function to
 * map to primitive capabilities that a user or role has, such as `edit_posts` and `edit_others_posts`.
 *
 * Example usage:
 *
 *     user_can( $user->ID, 'edit_posts' );
 *     user_can( $user->ID, 'edit_post', $post->ID );
 *     user_can( $user->ID, 'edit_post_meta', $post->ID, $meta_key );
 *
 * @since 3.1.0
 *
 * @param int|WP_User $user       User ID or object.
 * @param string      $capability Capability name.
 * @param mixed       ...$args    Optional further parameters, typically starting with an object ID.
 * @return bool Whether the user has the given capability.
 */
function user_can( $user, $capability, ...$args ) {
	return CapabilitiesHelper::userCan( $user, $capability, ...$args );
}

/**
 * Filters the user capabilities to grant the 'install_languages' capability as necessary.
 *
 * A user must have at least one out of the 'update_core', 'install_plugins', and
 * 'install_themes' capabilities to qualify for 'install_languages'.
 *
 * @since 4.9.0
 *
 * @param bool[] $allcaps An array of all the user's capabilities.
 * @return bool[] Filtered array of the user's capabilities.
 */
function wp_maybe_grant_install_languages_cap( $allcaps ) {
	return CapabilitiesHelper::maybeGrantInstallLanguages( $allcaps );
}

/**
 * Filters the user capabilities to grant the 'resume_plugins' and 'resume_themes' capabilities as necessary.
 *
 * @since 5.2.0
 *
 * @param bool[] $allcaps An array of all the user's capabilities.
 * @return bool[] Filtered array of the user's capabilities.
 */
function wp_maybe_grant_resume_extensions_caps( $allcaps ) {
	return CapabilitiesHelper::maybeGrantResumeExtensions( $allcaps );
}

/**
 * Filters the user capabilities to grant the 'view_site_health_checks' capabilities as necessary.
 *
 * @since 5.2.2
 *
 * @param bool[]   $allcaps An array of all the user's capabilities.
 * @param string[] $caps    Required primitive capabilities for the requested capability.
 * @param array    $args {
 *     Arguments that accompany the requested capability check.
 *
 *     @type string    $0 Requested capability.
 *     @type int       $1 Concerned user ID.
 *     @type mixed  ...$2 Optional second and further parameters, typically object ID.
 * }
 * @param \WP_User  $user    The user object.
 * @return bool[] Filtered array of the user's capabilities.
 */
function wp_maybe_grant_site_health_caps( $allcaps, $caps, $args, $user ) {
	return CapabilitiesHelper::maybeGrantSiteHealth( $allcaps, $caps, $args, $user );
}

/* ------------------- Roles: --------------------------*/

/**
 * Retrieves the global WP_Roles instance and instantiates it if necessary.
 *
 * @since 4.3.0
 *
 * @global WP_Roles $wp_roles WordPress role management object.
 *
 * @return WP_Roles WP_Roles global instance if not already instantiated.
 */
function wp_roles() {
	return RoleHelper::roles();
}

/**
 * Retrieve role object.
 *
 * @since 2.0.0
 *
 * @param string $role Role name.
 * @return WP_Role|null WP_Role object if found, null if the role does not exist.
 */
function get_role( $role ) {
	return RoleHelper::getRole( $role );
}

/**
 * Add role, if it does not exist.
 *
 * @since 2.0.0
 *
 * @param string $role Role name.
 * @param string $display_name Display name for role.
 * @param array $capabilities List of capabilities, e.g. array( 'edit_posts' => true, 'delete_posts' => false );
 * @return WP_Role|null WP_Role object if role is added, null if already exists.
 */
function add_role( $role, $display_name, $capabilities = array() ) {
	return RoleHelper::addRole( $role, $display_name, $capabilities );
}

/**
 * Remove role, if it exists.
 *
 * @since 2.0.0
 *
 * @param string $role Role name.
 */
function remove_role( $role ) {
	RoleHelper::removeRole( $role );
}

/**
 * Retrieve a list of super admins.
 *
 * @since 3.0.0
 *
 * @global array $super_admins
 * @return array List of super admin logins
 */
function get_super_admins() {
	return RoleHelper::getSuperAdmins();
}

/**
 * Determine if user is a site admin.
 *
 * @since 3.0.0
 *
 * @param bool|int $user_id (Optional) The ID of a user. Defaults to the current user.
 * @return bool True if the user is a site admin.
 */
function is_super_admin( $user_id = false ) {
	return RoleHelper::isSuperAdmin( $user_id );
}

/**
 * Grants Super Admin privileges.
 *
 * @since 3.0.0
 *
 * @global array $super_admins
 *
 * @param int $user_id ID of the user to be granted Super Admin privileges.
 * @return bool True on success, false on failure. This can fail when the user is
 *              already a super admin or when the `$super_admins` global is defined.
 */
function grant_super_admin( $user_id ) {
	return RoleHelper::grantSuperAdmin( $user_id );
}

/**
 * Revokes Super Admin privileges.
 *
 * @since 3.0.0
 *
 * @global array $super_admins
 *
 * @param int $user_id ID of the user Super Admin privileges to be revoked from.
 * @return bool True on success, false on failure. This can fail when the user's email
 *              is the network admin email or when the `$super_admins` global is defined.
 */
function revoke_super_admin( $user_id ) {
	return RoleHelper::revokeSuperAdmin( $user_id );
}
