<?php
/**
 * Custom header implementation
 *
 * @link http://codex.wordpress.org/Custom_Headers
 *
 * @package WordPress
 * @subpackage Twenty_Seventeen
 * @since 1.0
 */

/**
 * Set up the WordPress core custom header feature.
 *
 * @uses twentyseventeen_header_style()
 */
function twentyseventeen_custom_header_setup() {

	/**
	 * Filter Twenty Seventeen custom-header support arguments.
	 *
	 * @since Twenty Seventeen 1.0
	 *
	 * @param array $args {
	 *     An array of custom-header support arguments.
	 *
	 *     @type string $default-image     		Default image of the header.
	 *     @type string $default_text_color     Default color of the header text.
	 *     @type int    $width                  Width in pixels of the custom header image. Default 954.
	 *     @type int    $height                 Height in pixels of the custom header image. Default 1300.
	 *     @type string $wp-head-callback       Callback function used to styles the header image and text
	 *                                          displayed on the blog.
	 *     @type string $flex-height     		Flex support for height of header.
	 * }
	 */
	add_theme_support( 'custom-header', apply_filters( 'twentyseventeen_custom_header_args', array(
		'default-image'      => get_parent_theme_file_uri( '/assets/images/header.jpg' ),
		'default-text-color' => 'ffffff',
		'width'              => 2000,
		'height'             => 1200,
		'flex-height'        => true,
		'wp-head-callback'   => 'twentyseventeen_header_style',
	) ) );

	register_default_headers( array(
		'default-image' => array(
			'url'           => '%s/assets/images/header.jpg',
			'thumbnail_url' => '%s/assets/images/header.jpg',
			'description'   => __( 'Default Header Image', 'twentyseventeen' ),
		),
	) );
}
add_action( 'after_setup_theme', 'twentyseventeen_custom_header_setup' );

if ( ! function_exists( 'twentyseventeen_header_style' ) ) :
/**
 * Styles the header image and text displayed on the blog.
 *
 * @see twentyseventeen_custom_header_setup().
 */
function twentyseventeen_header_style() {
	$header_text_color = get_header_textcolor();

	// If no custom options for text are set, let's bail.
	// get_header_textcolor() options: add_theme_support( 'custom-header' ) is default, hide text (returns 'blank') or any hex value.
	if ( get_theme_support( 'custom-header', 'default-text-color' ) === $header_text_color ) {
		return;
	}

	// If we get this far, we have custom styles. Let's do this.
	?>
	<style type="text/css">
	<?php
		// Has the text been hidden?
		if ( 'blank' === $header_text_color ) :
	?>
		.site-title,
		.site-description {
			position: absolute;
			clip: rect(1px, 1px, 1px, 1px);
		}
	<?php
		// If the user has set a custom color for the text use that.
		else :
	?>
		.site-title a,
		.twentyseventeen-front-page:not(.no-header-image) .site-title,
		.twentyseventeen-front-page:not(.no-header-image) .site-title a,
		.site-description,
		.twentyseventeen-front-page:not(.no-header-image) .site-description {
			color: #<?php echo esc_attr( $header_text_color ); ?>;
		}
	<?php endif; ?>
	</style>
	<?php
}
endif; // End of twentyseventeen_header_style.
