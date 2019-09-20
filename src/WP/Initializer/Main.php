<?php

namespace WP\Initializer;

use WP\Router;
use WP\Config\Routes;

class Main implements InitializerInterface {

	private $admin_constants_initializer;

	private $general_initializer;

	private $admin_initializer;

	/**
	 * @var bool
	 */
	private $is_admin;

	/**
	 * @var string
	 */
	private $route;

	/**
	 * @var string[]
	 */
	private $exceptions = [
		'wp-admin/admin-post',
		'wp-admin/async-upload',
		'wp-admin/upgrade'
	];

	/**
	 * Main constructor.
	 */
	public function __construct() {
		$this->admin_constants_initializer = new AdminConstants();
		$this->general_initializer         = new General();
		$this->admin_initializer           = new Admin();
	}

	/**
	 * Initialize all of WordPress.
	 *
	 * @return void
	 */
	public function initialize() {
		$this->check_admin();

		$this->set_constants();

		if ( $this->is_admin === true ) {
			$this->admin_constants_initializer->initialize();
			$this->general_initializer->initialize();
			$this->admin_initializer->initialize();
		} else {
			$this->general_initializer->initialize();
		}

	}

	/**
	 * Check whether we're in a WP-Admin request.
	 *
	 * @return void
	 */
	protected function check_admin() {
		$request        = filter_input( INPUT_SERVER, 'REQUEST_URI' );
		$this->route    = ltrim( str_replace( '.php', '', parse_url( $request, PHP_URL_PATH ) ), '/' );
		$this->is_admin = false;
		if ( strpos( $request, '/wp-admin/' ) === 0 && ! in_array( $this->route, $this->exceptions ) ) {
			$this->is_admin = true;
		}
	}

	/**
	 * Set the required constants for these requests.
	 */
	private function set_constants() {
		switch( $this->route ) {
			case 'wp-admin/install':
			case 'wp-admin/setup-config.php':
			case 'wp-admin/upgrade.php':
				define( 'WP_INSTALLING', true );
				break;
			case 'wp-admin/network':
				break;
			case 'wp-admin/customize':
			case 'wp-admin/press-this':
			case 'wp-admin/update':
				define( 'IFRAME_REQUEST', true );
				break;
			case 'wp-admin/plugin-install.php':
				// Taken from plugin-install.php and moved here.
				if ( ! defined( 'IFRAME_REQUEST' ) && isset( $_GET['tab'] ) && ( 'plugin-information' == $_GET['tab'] ) ) {
					define( 'IFRAME_REQUEST', true );
				}
				break;
			case 'wp-admin/media-upload':
				if ( isset( $_GET['inline'] ) ) {
					define( 'IFRAME_REQUEST', true );
				}
				break;
			case 'wp-admin/profile':
			case 'wp-admin/network/profile':
			case 'wp-admin/user/profile':
				/**
				 * This is a profile page.
				 *
				 * @since 2.5.0
				 * @var bool
				 */
				define( 'IS_PROFILE_PAGE', true );
				break;
		}
	}
}
