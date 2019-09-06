<?php

namespace WP\Initializer;

class AdminConstants implements InitializerInterface {

	public function initialize() {
		/**
		 * In WordPress Administration Screens
		 *
		 * @since 2.3.2
		 */
		if ( ! defined( 'WP_ADMIN' ) ) {
			define( 'WP_ADMIN', true );
		}

		if ( ! defined( 'WP_NETWORK_ADMIN' ) ) {
			define( 'WP_NETWORK_ADMIN', false );
		}

		if ( ! defined( 'WP_USER_ADMIN' ) ) {
			define( 'WP_USER_ADMIN', false );
		}

		if ( ! WP_NETWORK_ADMIN && ! WP_USER_ADMIN ) {
			define( 'WP_BLOG_ADMIN', true );
		}

		if ( isset( $_GET['import'] ) && ! defined( 'WP_LOAD_IMPORTERS' ) ) {
			define( 'WP_LOAD_IMPORTERS', true );
		}
	}
}
