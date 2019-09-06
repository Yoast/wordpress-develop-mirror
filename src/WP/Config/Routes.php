<?php

namespace WP\Config;

use WP\Legacy\Action\Admin\About;
use WP\Legacy\Action\Admin\Dashboard;
use WP\Legacy\Action\Admin\PluginInstall;
use WP\Legacy\Action\Admin\Widgets;

/**
 * Class Routes
 * @package WP\Config
 */
class Routes {
	const ROUTES = [
		'wp-admin' => Dashboard::class,
		'wp-admin/about' => About::class,
		'wp-admin/widgets' => Widgets::class,
		'wp-admin/plugin-install' => PluginInstall::class,
	];
}
