<?php
/**
 * Widget API: WP_Widget_HTML_Code class
 *
 * @package WordPress
 * @subpackage Widgets
 * @since 4.8.1
 */

/**
 * Core class used to implement a HTML Code widget.
 *
 * @since 4.8.1
 *
 * @see WP_Widget
 */
class WP_Widget_HTML_Code extends WP_Widget {

	/**
	 * Default instance.
	 *
	 * @since 4.8.1
	 * @var array
	 */
	protected $default_instance = array(
		'title' => '',
		'content' => '',
	);

	/**
	 * Sets up a new HTML Code widget instance.
	 *
	 * @since 4.8.1
	 */
	public function __construct() {
		$widget_ops = array(
			'classname' => 'widget_html_code',
			'description' => __( 'Arbitrary HTML code.' ),
			'customize_selective_refresh' => true,
		);
		$control_ops = array();
		parent::__construct( 'html_code', __( 'HTML Code' ), $widget_ops, $control_ops );
	}

	/**
	 * Outputs the content for the current HTML Code widget instance.
	 *
	 * @since 4.8.1
	 *
	 * @param array $args     Display arguments including 'before_title', 'after_title',
	 *                        'before_widget', and 'after_widget'.
	 * @param array $instance Settings for the current HTML Code widget instance.
	 */
	public function widget( $args, $instance ) {

		$instance = array_merge( $this->default_instance, $instance );

		/** This filter is documented in wp-includes/widgets/class-wp-widget-pages.php */
		$title = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );

		$content = $instance['content'];

		/**
		 * Filters the content of the HTML Code widget.
		 *
		 * @since 4.8.1
		 *
		 * @param string              $content  The widget content.
		 * @param array               $instance Array of settings for the current widget.
		 * @param WP_Widget_HTML_Code $this     Current HTML Code widget instance.
		 */
		$content = apply_filters( 'widget_html_code_content', $content, $instance, $this );

		echo $args['before_widget'];
		if ( ! empty( $title ) ) {
			echo $args['before_title'] . $title . $args['after_title'];
		}
		echo $content;
		echo $args['after_widget'];
	}

	/**
	 * Handles updating settings for the current HTML Code widget instance.
	 *
	 * @since 4.8.1
	 *
	 * @param array $new_instance New settings for this instance as input by the user via
	 *                            WP_Widget::form().
	 * @param array $old_instance Old settings for this instance.
	 * @return array Settings to save or bool false to cancel saving.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array_merge( $this->default_instance, $old_instance );
		$instance['title'] = sanitize_text_field( $new_instance['title'] );
		if ( current_user_can( 'unfiltered_html' ) ) {
			$instance['content'] = $new_instance['content'];
		} else {
			$instance['content'] = wp_kses_post( $new_instance['content'] );
		}
		return $instance;
	}

	/**
	 * Outputs the HTML Code widget settings form.
	 *
	 * @since 4.8.1
	 *
	 * @param array $instance Current instance.
	 * @returns void
	 */
	public function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, $this->default_instance );
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>"/>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'content' ); ?>"><?php _e( 'Content:' ); ?></label>
			<textarea class="widefat code" rows="16" cols="20" id="<?php echo $this->get_field_id( 'content' ); ?>" name="<?php echo $this->get_field_name( 'content' ); ?>"><?php echo esc_textarea( $instance['content'] ); ?></textarea>
		</p>

		<?php if ( ! current_user_can( 'unfiltered_html' ) ) : ?>
			<?php
			$probably_unsafe_html = array( 'script', 'iframe', 'form', 'input', 'style' );
			$allowed_html = wp_kses_allowed_html( 'post' );
			$disallowed_html = array_diff( $probably_unsafe_html, array_keys( $allowed_html ) );
			?>
			<?php if ( ! empty( $disallowed_html ) ) : ?>
				<p>
					<?php _e( 'Some HTML tags are not permitted, including:' ); ?>
					<code><?php echo join( '</code>, <code>', $disallowed_html ); ?></code>
				</p>
			<?php endif; ?>
		<?php endif; ?>
		<?php
	}
}
