<?php

namespace WP\Config;

use WP\Legacy\Action\Admin\About;
use WP\Legacy\Action\Admin\AdminAjax;
use WP\Legacy\Action\Admin\AdminPost;
use WP\Legacy\Action\Admin\AsyncUpload;
use WP\Legacy\Action\Admin\Comment;
use WP\Legacy\Action\Admin\Credits;
use WP\Legacy\Action\Admin\Customize;
use WP\Legacy\Action\Admin\Dashboard;
use WP\Legacy\Action\Admin\MediaLibrary;
use WP\Legacy\Action\Admin\PluginInstall;
use WP\Legacy\Action\Admin\Plugins;
use WP\Legacy\Action\Admin\Upgrade;
use WP\Legacy\Action\Admin\UserEdit;
use WP\Legacy\Action\Admin\UserNew;
use WP\Legacy\Action\Admin\Users;
use WP\Legacy\Action\Admin\Widgets;

/**
 * Class Routes
 * @package WP\Config
 */
class Routes {
	const ROUTES = [
		'wp-admin/'               => Dashboard::class,
		'wp-admin/index'          => Dashboard::class,
		'wp-admin/about'          => About::class,
		'wp-admin/admin-ajax'     => AdminAjax::class,
		'wp-admin/admin-post'     => AdminPost::class,
		'wp-admin/async-upload'   => AsyncUpload::class,
		'wp-admin/comments'       => Comment::class,
		'wp-admin/credits'		  => Credits::class,
		'wp-admin/customize'	  => Customize::class,
		'wp-admin/plugin-install' => PluginInstall::class,
		'wp-admin/plugins'        => Plugins::class,
		'wp-admin/upload'         => MediaLibrary::class,
		'wp-admin/upgrade'        => Upgrade::class,
		'wp-admin/user-edit'      => UserEdit::class,
		'wp-admin/user-new'       => UserNew::class,
		'wp-admin/users'          => Users::class,
		'wp-admin/widgets'        => Widgets::class,
	];
}
