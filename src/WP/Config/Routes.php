<?php

namespace WP\Config;

use WP\Legacy\Action\Admin\About;

/**
 * Class Routes
 * @package WP\Config
 */
class Routes {
	const ROUTES = [
		'wp-admin/about' => About::class,
	];
}
