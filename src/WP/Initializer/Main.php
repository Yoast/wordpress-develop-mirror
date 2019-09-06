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
	private $admin_route;

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
		$this->is_admin = false;
		if ( strpos( $request, '/wp-admin/' ) === 0 ) {
			$this->is_admin = true;

			preg_match( '|/(.*)\.php|U', $request, $matches );
			$this->admin_route = $matches[1];
			echo $this->admin_route;
			if ( ! array_key_exists( $this->admin_route, Routes::ROUTES ) ) {
				$this->is_admin    = false;
				$this->admin_route = '';
			}
		}
	}
}
