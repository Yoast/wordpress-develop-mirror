<?php namespace WP\Helper;

/**
 * Class RoleHelper
 * @package WP\Helper
 */
class RoleHelper {
	public static function roles() {
		global $wp_roles;

		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new \WP_Roles();
		}

		return $wp_roles;
	}

	public static function getRole( $role ) {
		return self::roles()->get_role( $role );
	}

	public static function addRole( $role, $display_name, $capabilities = array() ) {
		if ( empty( $role ) ) {
			return;
		}

		return self::roles()->add_role( $role, $display_name, $capabilities );
	}

	public static function removeRole( $role ) {
		self::roles()->remove_role( $role );
	}

	public static function getSuperAdmins() {
		global $super_admins;

		if ( isset( $super_admins ) ) {
			return $super_admins;
		}

		return get_site_option( 'site_admins', array( 'admin' ) );
	}

	public static function isSuperAdmin( $user_id = false ) {
		if ( ! $user_id || $user_id == get_current_user_id() ) {
			$user = wp_get_current_user();
		} else {
			$user = get_userdata( $user_id );
		}

		if ( ! $user || ! $user->exists() ) {
			return false;
		}

		if ( is_multisite() ) {
			$super_admins = get_super_admins();
			if ( is_array( $super_admins ) && in_array( $user->user_login, $super_admins ) ) {
				return true;
			}
		} else {
			if ( $user->has_cap( 'delete_users' ) ) {
				return true;
			}
		}

		return false;
	}

	public static function grantSuperAdmin( $user_id ) {
		// If global super_admins override is defined, there is nothing to do here.
		if ( isset( $GLOBALS['super_admins'] ) || ! is_multisite() ) {
			return false;
		}

		/**
		 * Fires before the user is granted Super Admin privileges.
		 *
		 * @since 3.0.0
		 *
		 * @param int $user_id ID of the user that is about to be granted Super Admin privileges.
		 */
		do_action( 'grant_super_admin', $user_id );

		// Directly fetch site_admins instead of using get_super_admins()
		$super_admins = get_site_option( 'site_admins', array( 'admin' ) );

		$user = get_userdata( $user_id );
		if ( $user && ! in_array( $user->user_login, $super_admins ) ) {
			$super_admins[] = $user->user_login;
			update_site_option( 'site_admins', $super_admins );

			/**
			 * Fires after the user is granted Super Admin privileges.
			 *
			 * @since 3.0.0
			 *
			 * @param int $user_id ID of the user that was granted Super Admin privileges.
			 */
			do_action( 'granted_super_admin', $user_id );
			return true;
		}

		return false;
	}

	public static function revokeSuperAdmin( $user_id ) {
		// If global super_admins override is defined, there is nothing to do here.
		if ( isset( $GLOBALS['super_admins'] ) || ! is_multisite() ) {
			return false;
		}

		/**
		 * Fires before the user's Super Admin privileges are revoked.
		 *
		 * @since 3.0.0
		 *
		 * @param int $user_id ID of the user Super Admin privileges are being revoked from.
		 */
		do_action( 'revoke_super_admin', $user_id );

		// Directly fetch site_admins instead of using get_super_admins()
		$super_admins = get_site_option( 'site_admins', array( 'admin' ) );

		$user = get_userdata( $user_id );
		if ( $user && 0 !== strcasecmp( $user->user_email, get_site_option( 'admin_email' ) ) ) {
			$key = array_search( $user->user_login, $super_admins );
			if ( false !== $key ) {
				unset( $super_admins[ $key ] );
				update_site_option( 'site_admins', $super_admins );

				/**
				 * Fires after the user's Super Admin privileges are revoked.
				 *
				 * @since 3.0.0
				 *
				 * @param int $user_id ID of the user Super Admin privileges were revoked from.
				 */
				do_action( 'revoked_super_admin', $user_id );
				return true;
			}
		}

		return false;
	}
}
