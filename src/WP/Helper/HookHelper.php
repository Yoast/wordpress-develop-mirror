<?php namespace WP\Helper;

/**
 * Class HookHelper
 * @package WP\Helper
 */
class HookHelper {
	public static function add_filter( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
		global $wp_filter;
		if ( ! isset( $wp_filter[ $tag ] ) ) {
			$wp_filter[ $tag ] = new \WP_Hook();
		}
		$wp_filter[ $tag ]->add_filter( $tag, $function_to_add, $priority, $accepted_args );
		return true;
	}

	public static function has_filter( $tag, $function_to_check ) {
		global $wp_filter;

		if ( ! isset( $wp_filter[ $tag ] ) ) {
			return false;
		}

		return $wp_filter[ $tag ]->has_filter( $tag, $function_to_check );
	}

	public static function apply_filters( $tag, $value ) {
		global $wp_filter, $wp_current_filter;

		$args = array();

		// Do 'all' actions first.
		if ( isset( $wp_filter['all'] ) ) {
			$wp_current_filter[] = $tag;
			$args                = func_get_args();
			self::_wp_call_all_hook( $args );
		}

		if ( ! isset( $wp_filter[ $tag ] ) ) {
			if ( isset( $wp_filter['all'] ) ) {
				array_pop( $wp_current_filter );
			}
			return $value;
		}

		if ( ! isset( $wp_filter['all'] ) ) {
			$wp_current_filter[] = $tag;
		}

		if ( empty( $args ) ) {
			$args = func_get_args();
		}

		// don't pass the tag name to WP_Hook
		array_shift( $args );

		$filtered = $wp_filter[ $tag ]->apply_filters( $value, $args );

		array_pop( $wp_current_filter );

		return $filtered;
	}

	public static function apply_filters_ref_array( $tag, $args ) {
		global $wp_filter, $wp_current_filter;

		// Do 'all' actions first
		if ( isset( $wp_filter['all'] ) ) {
			$wp_current_filter[] = $tag;
			$all_args            = func_get_args();
			self::_wp_call_all_hook( $all_args );
		}

		if ( ! isset( $wp_filter[ $tag ] ) ) {
			if ( isset( $wp_filter['all'] ) ) {
				array_pop( $wp_current_filter );
			}
			return $args[0];
		}

		if ( ! isset( $wp_filter['all'] ) ) {
			$wp_current_filter[] = $tag;
		}

		$filtered = $wp_filter[ $tag ]->apply_filters( $args[0], $args );

		array_pop( $wp_current_filter );

		return $filtered;
	}

	public static function remove_filter( $tag, $function_to_remove, $priority = 10 ) {
		global $wp_filter;

		$r = false;
		if ( isset( $wp_filter[ $tag ] ) ) {
			$r = $wp_filter[ $tag ]->remove_filter( $tag, $function_to_remove, $priority );
			if ( ! $wp_filter[ $tag ]->callbacks ) {
				unset( $wp_filter[ $tag ] );
			}
		}

		return $r;
	}

	public static function remove_all_filters( $tag, $priority = false ) {
		global $wp_filter;

		if ( isset( $wp_filter[ $tag ] ) ) {
			$wp_filter[ $tag ]->remove_all_filters( $priority );
			if ( ! $wp_filter[ $tag ]->has_filters() ) {
				unset( $wp_filter[ $tag ] );
			}
		}

		return true;
	}

	public static function current_filter() {
		global $wp_current_filter;
		return end( $wp_current_filter );
	}

	public static function current_action() {
		global $wp_current_filter;
		return end( $wp_current_filter );
	}

	public static function doing_filter( $filter = null ) {
		global $wp_current_filter;

		if ( null === $filter ) {
			return ! empty( $wp_current_filter );
		}

		return in_array( $filter, $wp_current_filter );
	}

	public static function doing_action( $action = null ) {
		return self::doing_filter( $action );
	}

	public static function add_action( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
		return self::add_filter( $tag, $function_to_add, $priority, $accepted_args );
	}

	public static function do_action( $tag, $arg = '' ) {
		global $wp_filter, $wp_actions, $wp_current_filter;

		if ( ! isset( $wp_actions[ $tag ] ) ) {
			$wp_actions[ $tag ] = 1;
		} else {
			++$wp_actions[ $tag ];
		}

		// Do 'all' actions first
		if ( isset( $wp_filter['all'] ) ) {
			$wp_current_filter[] = $tag;
			$all_args            = func_get_args();
			self::_wp_call_all_hook( $all_args );
		}

		if ( ! isset( $wp_filter[ $tag ] ) ) {
			if ( isset( $wp_filter['all'] ) ) {
				array_pop( $wp_current_filter );
			}
			return;
		}

		if ( ! isset( $wp_filter['all'] ) ) {
			$wp_current_filter[] = $tag;
		}

		$args = array();
		if ( is_array( $arg ) && 1 == count( $arg ) && isset( $arg[0] ) && is_object( $arg[0] ) ) { // array(&$this)
			$args[] =& $arg[0];
		} else {
			$args[] = $arg;
		}
		for ( $a = 2, $num = func_num_args(); $a < $num; $a++ ) {
			$args[] = func_get_arg( $a );
		}

		$wp_filter[ $tag ]->do_action( $args );

		array_pop( $wp_current_filter );
	}

	public static function did_action( $tag ) {
		global $wp_actions;

		if ( ! isset( $wp_actions[ $tag ] ) ) {
			return 0;
		}

		return $wp_actions[ $tag ];
	}

	public static function do_action_ref_array( $tag, $args ) {
		global $wp_filter, $wp_actions, $wp_current_filter;

		if ( ! isset( $wp_actions[ $tag ] ) ) {
			$wp_actions[ $tag ] = 1;
		} else {
			++$wp_actions[ $tag ];
		}

		// Do 'all' actions first
		if ( isset( $wp_filter['all'] ) ) {
			$wp_current_filter[] = $tag;
			$all_args            = func_get_args();
			self::_wp_call_all_hook( $all_args );
		}

		if ( ! isset( $wp_filter[ $tag ] ) ) {
			if ( isset( $wp_filter['all'] ) ) {
				array_pop( $wp_current_filter );
			}
			return;
		}

		if ( ! isset( $wp_filter['all'] ) ) {
			$wp_current_filter[] = $tag;
		}

		$wp_filter[ $tag ]->do_action( $args );

		array_pop( $wp_current_filter );
	}

	public static function has_action( $tag, $function_to_check = false ) {
		return self::has_filter( $tag, $function_to_check );
	}

	public static function remove_action( $tag, $function_to_remove, $priority = 10 ) {
		return self::remove_filter( $tag, $function_to_remove, $priority );
	}

	public static function remove_all_actions( $tag, $priority = false ) {
		return self::remove_all_filters( $tag, $priority );
	}

	public static function apply_filters_deprecated( $tag, $args, $version, $replacement = false, $message = null ) {
		if ( ! has_filter( $tag ) ) {
			return $args[0];
		}

		_deprecated_hook( $tag, $version, $replacement, $message );

		return apply_filters_ref_array( $tag, $args );
	}

	public static function do_action_deprecated( $tag, $args, $version, $replacement = false, $message = null ) {
		if ( ! has_action( $tag ) ) {
			return;
		}

		_deprecated_hook( $tag, $version, $replacement, $message );

		do_action_ref_array( $tag, $args );
	}

	public static function register_activation_hook( $file, $function ) {
		$file = PluginHelper::plugin_basename( $file );
		add_action( 'activate_' . $file, $function );
	}

	public static function register_deactivation_hook( $file, $function ) {
		$file = PluginHelper::plugin_basename( $file );
		add_action( 'deactivate_' . $file, $function );
	}

	public static function register_uninstall_hook( $file, $callback ) {
		if ( is_array( $callback ) && is_object( $callback[0] ) ) {
			_doing_it_wrong( __FUNCTION__, __( 'Only a static class method or function can be used in an uninstall hook.' ), '3.1.0' );
			return;
		}

		/*
		 * The option should not be autoloaded, because it is not needed in most
		 * cases. Emphasis should be put on using the 'uninstall.php' way of
		 * uninstalling the plugin.
		 */
		$uninstallable_plugins                             = (array) get_option( 'uninstall_plugins' );
		$uninstallable_plugins[ plugin_basename( $file ) ] = $callback;

		update_option( 'uninstall_plugins', $uninstallable_plugins );
	}

	public static function _wp_call_all_hook( $args ) {
		global $wp_filter;

		$wp_filter['all']->do_all_hook( $args );
	}

	public static function _wp_filter_build_unique_id( $tag, $function, $priority ) {
		global $wp_filter;
		static $filter_id_count = 0;

		if ( is_string( $function ) ) {
			return $function;
		}

		if ( is_object( $function ) ) {
			// Closures are currently implemented as objects
			$function = array( $function, '' );
		} else {
			$function = (array) $function;
		}

		if ( is_object( $function[0] ) ) {
			// Object Class Calling
			if ( function_exists( 'spl_object_hash' ) ) {
				return spl_object_hash( $function[0] ) . $function[1];
			} else {
				$obj_idx = get_class( $function[0] ) . $function[1];
				if ( ! isset( $function[0]->wp_filter_id ) ) {
					if ( false === $priority ) {
						return false;
					}
					$obj_idx                  .= isset( $wp_filter[ $tag ][ $priority ] ) ? count( (array) $wp_filter[ $tag ][ $priority ] ) : $filter_id_count;
					$function[0]->wp_filter_id = $filter_id_count;
					++$filter_id_count;
				} else {
					$obj_idx .= $function[0]->wp_filter_id;
				}

				return $obj_idx;
			}
		} elseif ( is_string( $function[0] ) ) {
			// Static Calling
			return $function[0] . '::' . $function[1];
		}
	}
}
