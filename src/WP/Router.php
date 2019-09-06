<?php

namespace WP;

use WP\Config\Routes;

/**
 * Class Router
 *
 * @package WP
 */
class Router {
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
		preg_match( '|/(.*)\.php|U', $request, $matches );
		$route = $matches[1];
		if ( ! array_key_exists( $route, Routes::ROUTES ) ) {
			return;
		}
		$action = new $this->routes[$route];
		$action->perform();
	}

}
