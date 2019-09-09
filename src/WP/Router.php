<?php

namespace WP;

use WP\Config\Routes;

/**
 * Class Router
 *
 * @package WP
 */
class Router {
	/**
	 * @var array 
	 */
	private $routes;

	/**
	 * Router constructor.
	 *
	 * @param array $routes An array of routes.
	 */
	public function __construct( $routes ) {
		$this->routes = $routes;
	}

	/**
	 * Route to our needed Action.
	 */
	public function route() {
		$request = filter_input( INPUT_SERVER, 'REQUEST_URI' );
		$method  = filter_input( INPUT_SERVER, 'REQUEST_METHOD' );
		$route   = ltrim( str_replace( '.php', '', parse_url( $request, PHP_URL_PATH ) ), '/' );
		if ( ! array_key_exists( $route, Routes::ROUTES[ $method ] ) ) {
			return;
		}

		$action_class = $this->routes[ $method ][ $route ];
		$action       = new $action_class;
		$action->perform();
	}

}
