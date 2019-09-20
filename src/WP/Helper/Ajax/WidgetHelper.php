<?php

namespace WP\Helper\Ajax;

class WidgetHelper {
	public static function widgetsOrder() {
		check_ajax_referer( 'save-sidebar-widgets', 'savewidgets' );

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( -1 );
		}

		unset( $_POST['savewidgets'], $_POST['action'] );

		// Save widgets order for all sidebars.
		if ( is_array( $_POST['sidebars'] ) ) {
			$sidebars = array();

			foreach ( wp_unslash( $_POST['sidebars'] ) as $key => $val ) {
				$sb = array();

				if ( ! empty( $val ) ) {
					$val = explode( ',', $val );

					foreach ( $val as $k => $v ) {
						if ( strpos( $v, 'widget-' ) === false ) {
							continue;
						}

						$sb[ $k ] = substr( $v, strpos( $v, '_' ) + 1 );
					}
				}
				$sidebars[ $key ] = $sb;
			}

			wp_set_sidebars_widgets( $sidebars );
			wp_die( 1 );
		}

		wp_die( -1 );
	}

	/**
	 * Ajax handler for saving a widget.
	 *
	 * @global array $wp_registered_widgets
	 * @global array $wp_registered_widget_controls
	 * @global array $wp_registered_widget_updates
	 */
	public static function saveWidget() {
		global $wp_registered_widgets, $wp_registered_widget_controls, $wp_registered_widget_updates;

		check_ajax_referer( 'save-sidebar-widgets', 'savewidgets' );

		if ( ! current_user_can( 'edit_theme_options' ) || ! isset( $_POST['id_base'] ) ) {
			wp_die( -1 );
		}

		unset( $_POST['savewidgets'], $_POST['action'] );

		/**
		 * Fires early when editing the widgets displayed in sidebars.
		 *
		 * @since 2.8.0
		 */
		do_action( 'load-widgets.php' ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		/**
		 * Fires early when editing the widgets displayed in sidebars.
		 *
		 * @since 2.8.0
		 */
		do_action( 'widgets.php' ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		/** This action is documented in wp-admin/widgets.php */
		do_action( 'sidebar_admin_setup' );

		$id_base      = wp_unslash( $_POST['id_base'] );
		$widget_id    = wp_unslash( $_POST['widget-id'] );
		$sidebar_id   = $_POST['sidebar'];
		$multi_number = ! empty( $_POST['multi_number'] ) ? (int) $_POST['multi_number'] : 0;
		$settings     = isset( $_POST[ 'widget-' . $id_base ] ) && is_array( $_POST[ 'widget-' . $id_base ] ) ? $_POST[ 'widget-' . $id_base ] : false;
		$error        = '<p>' . __( 'An error has occurred. Please reload the page and try again.' ) . '</p>';

		$sidebars = wp_get_sidebars_widgets();
		$sidebar  = isset( $sidebars[ $sidebar_id ] ) ? $sidebars[ $sidebar_id ] : array();

		// Delete.
		if ( isset( $_POST['delete_widget'] ) && $_POST['delete_widget'] ) {

			if ( ! isset( $wp_registered_widgets[ $widget_id ] ) ) {
				wp_die( $error );
			}

			$sidebar = array_diff( $sidebar, array( $widget_id ) );
			$_POST   = array(
				'sidebar'            => $sidebar_id,
				'widget-' . $id_base => array(),
				'the-widget-id'      => $widget_id,
				'delete_widget'      => '1',
			);

			/** This action is documented in wp-admin/widgets.php */
			do_action( 'delete_widget', $widget_id, $sidebar_id, $id_base );

		} elseif ( $settings && preg_match( '/__i__|%i%/', key( $settings ) ) ) {
			if ( ! $multi_number ) {
				wp_die( $error );
			}

			$_POST[ 'widget-' . $id_base ] = array( $multi_number => reset( $settings ) );
			$widget_id                     = $id_base . '-' . $multi_number;
			$sidebar[]                     = $widget_id;
		}
		$_POST['widget-id'] = $sidebar;

		foreach ( (array) $wp_registered_widget_updates as $name => $control ) {

			if ( $name == $id_base ) {
				if ( ! is_callable( $control['callback'] ) ) {
					continue;
				}

				ob_start();
				call_user_func_array( $control['callback'], $control['params'] );
				ob_end_clean();
				break;
			}
		}

		if ( isset( $_POST['delete_widget'] ) && $_POST['delete_widget'] ) {
			$sidebars[ $sidebar_id ] = $sidebar;
			wp_set_sidebars_widgets( $sidebars );
			echo "deleted:$widget_id";
			wp_die();
		}

		if ( ! empty( $_POST['add_new'] ) ) {
			wp_die();
		}

		$form = $wp_registered_widget_controls[ $widget_id ];
		if ( $form ) {
			call_user_func_array( $form['callback'], $form['params'] );
		}

		wp_die();
	}

	/**
	 * Ajax handler for saving a widget.
	 *
	 * @global \WP_Customize_Manager $wp_customize
	 */
	public static function updateWidget() {
		global $wp_customize;
		$wp_customize->widgets->wp_ajax_update_widget();
	}

	/**
	 * Ajax handler for removing inactive widgets.
	 */
	public static function deleteInactiveWidgets() {
		check_ajax_referer( 'remove-inactive-widgets', 'removeinactivewidgets' );

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( -1 );
		}

		unset( $_POST['removeinactivewidgets'], $_POST['action'] );
		/** This action is documented in wp-admin/includes/ajax-actions.php */
		do_action( 'load-widgets.php' ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
		/** This action is documented in wp-admin/includes/ajax-actions.php */
		do_action( 'widgets.php' ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
		/** This action is documented in wp-admin/widgets.php */
		do_action( 'sidebar_admin_setup' );

		$sidebars_widgets = wp_get_sidebars_widgets();

		foreach ( $sidebars_widgets['wp_inactive_widgets'] as $key => $widget_id ) {
			$pieces       = explode( '-', $widget_id );
			$multi_number = array_pop( $pieces );
			$id_base      = implode( '-', $pieces );
			$widget       = get_option( 'widget_' . $id_base );
			unset( $widget[ $multi_number ] );
			update_option( 'widget_' . $id_base, $widget );
			unset( $sidebars_widgets['wp_inactive_widgets'][ $key ] );
		}

		wp_set_sidebars_widgets( $sidebars_widgets );

		wp_die();
	}
}
