<?php namespace WP\Helper\Frontend;

use WP_Error;
use WP_User;

/**
 * Class LoginHelper
 * @package WP\Helper
 */
class LoginHelper {
	public static function loginHeader( $title = 'Log In', $message = '', $wp_error = null ) {
		global $error, $interim_login, $action;

		// Don't index any of these forms
		add_action( 'login_head', 'wp_sensitive_page_meta' );
		add_action( 'login_head', 'wp_login_viewport_meta' );

		if ( ! is_wp_error( $wp_error ) ) {
			$wp_error = new WP_Error();
		}

		// Shake it!
		$shake_error_codes = array(
			'empty_password',
			'empty_email',
			'invalid_email',
			'invalidcombo',
			'empty_username',
			'invalid_username',
			'incorrect_password',
			'retrieve_password_email_failure'
		);

		/**
		 * Filters the error codes array for shaking the login form.
		 *
		 * @since 3.0.0
		 *
		 * @param array $shake_error_codes Error codes that shake the login form.
		 */
		$shake_error_codes = apply_filters( 'shake_error_codes', $shake_error_codes );

		if ( $shake_error_codes && $wp_error->has_errors() && in_array( $wp_error->get_error_code(), $shake_error_codes, true ) ) {
			add_action( 'login_head', 'wp_shake_js', 12 );
		}

		$login_title = get_bloginfo( 'name', 'display' );

		/* translators: Login screen title. 1: Login screen name, 2: Network or site name. */
		$login_title = sprintf( __( '%1$s &lsaquo; %2$s &#8212; WordPress' ), $title, $login_title );

		if ( wp_is_recovery_mode() ) {
			/* translators: %s: Login screen title. */
			$login_title = sprintf( __( 'Recovery Mode &#8212; %s' ), $login_title );
		}

		/**
		 * Filters the title tag content for login page.
		 *
		 * @since 4.9.0
		 *
		 * @param string $login_title The page title, with extra context added.
		 * @param string $title       The original page title.
		 */
		$login_title = apply_filters( 'login_title', $login_title, $title );

		?><!DOCTYPE html>
		<!--[if IE 8]>
		<html xmlns="http://www.w3.org/1999/xhtml" class="ie8" <?php language_attributes(); ?>>
		<![endif]-->
		<!--[if !(IE 8) ]><!-->
		<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
		<!--<![endif]-->
		<head>
			<meta http-equiv="Content-Type" content="<?php bloginfo( 'html_type' ); ?>; charset=<?php bloginfo( 'charset' ); ?>" />
			<title><?php echo $login_title; ?></title>
			<?php

			wp_enqueue_style( 'login' );

			/*
			 * Remove all stored post data on logging out.
			 * This could be added by add_action('login_head'...) like wp_shake_js(),
			 * but maybe better if it's not removable by plugins.
			 */
			if ( 'loggedout' === $wp_error->get_error_code() ) {
				?>
				<script>if("sessionStorage" in window){try{for(var key in sessionStorage){if(key.indexOf("wp-autosave-")!=-1){sessionStorage.removeItem(key)}}}catch(e){}};</script>
				<?php
			}

			/**
			 * Enqueue scripts and styles for the login page.
			 *
			 * @since 3.1.0
			 */
			do_action( 'login_enqueue_scripts' );

			/**
			 * Fires in the login page header after scripts are enqueued.
			 *
			 * @since 2.1.0
			 */
			do_action( 'login_head' );

			$login_header_url = __( 'https://wordpress.org/' );

			/**
			 * Filters link URL of the header logo above login form.
			 *
			 * @since 2.1.0
			 *
			 * @param string $login_header_url Login header logo URL.
			 */
			$login_header_url = apply_filters( 'login_headerurl', $login_header_url );

			$login_header_title = '';

			/**
			 * Filters the title attribute of the header logo above login form.
			 *
			 * @since 2.1.0
			 * @deprecated 5.2.0 Use login_headertext
			 *
			 * @param string $login_header_title Login header logo title attribute.
			 */
			$login_header_title = apply_filters_deprecated(
				'login_headertitle',
				array( $login_header_title ),
				'5.2.0',
				'login_headertext',
				__( 'Usage of the title attribute on the login logo is not recommended for accessibility reasons. Use the link text instead.' )
			);

			$login_header_text = empty( $login_header_title ) ? __( 'Powered by WordPress' ) : $login_header_title;

			/**
			 * Filters the link text of the header logo above the login form.
			 *
			 * @since 5.2.0
			 *
			 * @param string $login_header_text The login header logo link text.
			 */
			$login_header_text = apply_filters( 'login_headertext', $login_header_text );

			$classes = array( 'login-action-' . $action, 'wp-core-ui' );

			if ( is_rtl() ) {
				$classes[] = 'rtl';
			}

			if ( $interim_login ) {
				$classes[] = 'interim-login';

				?>
				<style type="text/css">html{background-color: transparent;}</style>
				<?php

				if ( 'success' === $interim_login ) {
					$classes[] = 'interim-login-success';
				}
			}

			$classes[] = ' locale-' . sanitize_html_class( strtolower( str_replace( '_', '-', get_locale() ) ) );

			/**
			 * Filters the login page body classes.
			 *
			 * @since 3.5.0
			 *
			 * @param array  $classes An array of body classes.
			 * @param string $action  The action that brought the visitor to the login page.
			 */
			$classes = apply_filters( 'login_body_class', $classes, $action );

			?>
		</head>
		<body class="login <?php echo esc_attr( implode( ' ', $classes ) ); ?>">
		<?php
		/**
		 * Fires in the login page header after the body tag is opened.
		 *
		 * @since 4.6.0
		 */
		do_action( 'login_header' );

		?>
		<div id="login">
			<h1><a href="<?php echo esc_url( $login_header_url ); ?>"><?php echo $login_header_text; ?></a></h1>
		<?php
		/**
		 * Filters the message to display above the login form.
		 *
		 * @since 2.1.0
		 *
		 * @param string $message Login message text.
		 */
		$message = apply_filters( 'login_message', $message );

		if ( ! empty( $message ) ) {
			echo $message . "\n";
		}

		// In case a plugin uses $error rather than the $wp_errors object.
		if ( ! empty( $error ) ) {
			$wp_error->add( 'error', $error );
			unset( $error );
		}

		if ( $wp_error->has_errors() ) {
			$errors   = '';
			$messages = '';

			foreach ( $wp_error->get_error_codes() as $code ) {
				$severity = $wp_error->get_error_data( $code );
				foreach ( $wp_error->get_error_messages( $code ) as $error_message ) {
					if ( 'message' === $severity ) {
						$messages .= '	' . $error_message . "<br />\n";
					} else {
						$errors .= '	' . $error_message . "<br />\n";
					}
				}
			}

			if ( ! empty( $errors ) ) {
				/**
				 * Filters the error messages displayed above the login form.
				 *
				 * @since 2.1.0
				 *
				 * @param string $errors Login error message.
				 */
				echo '<div id="login_error">' . apply_filters( 'login_errors', $errors ) . "</div>\n";
			}

			if ( ! empty( $messages ) ) {
				/**
				 * Filters instructional messages displayed above the login form.
				 *
				 * @since 2.5.0
				 *
				 * @param string $messages Login messages.
				 */
				echo '<p class="message">' . apply_filters( 'login_messages', $messages ) . "</p>\n";
			}
		}
	}

	public static function loginFooter( $input_id = '' ) {
		global $interim_login;

		// Don't allow interim logins to navigate away from the page.
		if ( ! $interim_login ) {
			?>
			<p id="backtoblog"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">
					<?php

					/* translators: %s: Site title. */
					printf( _x( '&larr; Back to %s', 'site' ), get_bloginfo( 'title', 'display' ) );

					?>
				</a></p>
			<?php

			the_privacy_policy_link( '<div class="privacy-policy-page-link">', '</div>' );
		}

		?>
		</div><?php // End of <div id="login"> ?>

		<?php

		if ( ! empty( $input_id ) ) {
			?>
			<script type="text/javascript">
				try{document.getElementById('<?php echo $input_id; ?>').focus();}catch(e){}
				if(typeof wpOnload=='function')wpOnload();
			</script>
			<?php
		}

		/**
		 * Fires in the login page footer.
		 *
		 * @since 3.1.0
		 */
		do_action( 'login_footer' );

		?>
		<div class="clear"></div>
		</body>
		</html>
		<?php
	}

    public static function shakeJs() {
        ?>
        <script type="text/javascript">
            addLoadEvent = function(func){if(typeof jQuery!="undefined")jQuery(document).ready(func);else if(typeof wpOnload!='function'){wpOnload=func;}else{var oldonload=wpOnload;wpOnload=function(){oldonload();func();}}};
            function s(id,pos){g(id).left=pos+'px';}
            function g(id){return document.getElementById(id).style;}
            function shake(id,a,d){c=a.shift();s(id,c);if(a.length>0){setTimeout(function(){shake(id,a,d);},d);}else{try{g(id).position='static';wp_attempt_focus();}catch(e){}}}
            addLoadEvent(function(){ var p=new Array(15,30,15,0,-15,-30,-15,0);p=p.concat(p.concat(p));var i=document.forms[0].id;g(i).position='relative';shake(i,p,20);});
        </script>
        <?php
	}

    public static function loginViewportMeta() {
        ?>
        <meta name="viewport" content="width=device-width" />
        <?php
	}

    public static function retrievePassword() {
		$errors = new WP_Error();

		if ( empty( $_POST['user_login'] ) || ! is_string( $_POST['user_login'] ) ) {
			$errors->add( 'empty_username', __( '<strong>ERROR</strong>: Enter a username or email address.' ) );
		} elseif ( strpos( $_POST['user_login'], '@' ) ) {
			$user_data = get_user_by( 'email', trim( wp_unslash( $_POST['user_login'] ) ) );
			if ( empty( $user_data ) ) {
				$errors->add( 'invalid_email', __( '<strong>ERROR</strong>: There is no account with that username or email address.' ) );
			}
		} else {
			$login     = trim( $_POST['user_login'] );
			$user_data = get_user_by( 'login', $login );
		}

		/**
		 * Fires before errors are returned from a password reset request.
		 *
		 * @since 2.1.0
		 * @since 4.4.0 Added the `$errors` parameter.
		 *
		 * @param WP_Error $errors A WP_Error object containing any errors generated
		 *                         by using invalid credentials.
		 */
		do_action( 'lostpassword_post', $errors );

		if ( $errors->has_errors() ) {
			return $errors;
		}

		if ( ! $user_data ) {
			$errors->add( 'invalidcombo', __( '<strong>ERROR</strong>: There is no account with that username or email address.' ) );
			return $errors;
		}

		// Redefining user_login ensures we return the right case in the email.
		$user_login = $user_data->user_login;
		$user_email = $user_data->user_email;
		$key        = get_password_reset_key( $user_data );

		if ( is_wp_error( $key ) ) {
			return $key;
		}

		if ( is_multisite() ) {
			$site_name = get_network()->site_name;
		} else {
			/*
			 * The blogname option is escaped with esc_html on the way into the database
			 * in sanitize_option we want to reverse this for the plain text arena of emails.
			 */
			$site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		}

		$message = __( 'Someone has requested a password reset for the following account:' ) . "\r\n\r\n";
		/* translators: %s: Site name. */
		$message .= sprintf( __( 'Site Name: %s' ), $site_name ) . "\r\n\r\n";
		/* translators: %s: User login. */
		$message .= sprintf( __( 'Username: %s' ), $user_login ) . "\r\n\r\n";
		$message .= __( 'If this was a mistake, just ignore this email and nothing will happen.' ) . "\r\n\r\n";
		$message .= __( 'To reset your password, visit the following address:' ) . "\r\n\r\n";
		$message .= '<' . network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user_login ), 'login' ) . ">\r\n";

		/* translators: Password reset notification email subject. %s: Site title. */
		$title = sprintf( __( '[%s] Password Reset' ), $site_name );

		/**
		 * Filters the subject of the password reset email.
		 *
		 * @since 2.8.0
		 * @since 4.4.0 Added the `$user_login` and `$user_data` parameters.
		 *
		 * @param string  $title      Default email title.
		 * @param string  $user_login The username for the user.
		 * @param WP_User $user_data  WP_User object.
		 */
		$title = apply_filters( 'retrieve_password_title', $title, $user_login, $user_data );

		/**
		 * Filters the message body of the password reset mail.
		 *
		 * If the filtered message is empty, the password reset email will not be sent.
		 *
		 * @since 2.8.0
		 * @since 4.1.0 Added `$user_login` and `$user_data` parameters.
		 *
		 * @param string  $message    Default mail message.
		 * @param string  $key        The activation key.
		 * @param string  $user_login The username for the user.
		 * @param WP_User $user_data  WP_User object.
		 */
		$message = apply_filters( 'retrieve_password_message', $message, $key, $user_login, $user_data );

		if ( $message && ! wp_mail( $user_email, wp_specialchars_decode( $title ), $message ) ) {
			$errors->add(
				'retrieve_password_email_failure',
				sprintf(
				/* translators: %s: Documentation URL. */
					__( '<strong>ERROR</strong>: The email could not be sent. Your site may not be correctly configured to send emails. <a href="%s">Get support for resetting your password</a>.' ),
					esc_url( __( 'https://wordpress.org/support/article/resetting-your-password/' ) )
				)
			);
			return $errors;
		}

		return true;
	}
}
