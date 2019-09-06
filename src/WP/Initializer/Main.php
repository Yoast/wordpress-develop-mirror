<?php

namespace WP\Initializer;


class Main implements InitializerInterface {

	const ADMIN_ROUTES = [
		'about',
		'admin-ajax',
		'admin-functions',
		'admin-header',
		'admin-post',
		'admin',
		'async-upload',
		'comment',
		'credits',
		'customize',
		'edit-comments',
		'edit-tags',
		'edit',
		'export',
		'freedoms',
		'import',
		'index',
		'install-helper',
		'install',
		'link-add',
		'link-manager',
		'link',
		'load-scripts',
		'load-styles',
		'media-new',
		'media-upload',
		'media',
		'moderation',
		'ms-admin',
		'ms-delete-site',
		'ms-edit',
		'ms-options',
		'ms-sites',
		'ms-themes',
		'ms-upgrade-network',
		'ms-users',
		'my-sites',
		'nav-menus',
		'network',
		'options-discussion',
		'options-general',
		'options-media',
		'options-permalink',
		'options-reading',
		'options-writing',
		'options',
		'plugin-editor',
		'plugin-install',
		'plugins',
		'post-new',
		'post',
		'press-this',
		'privacy',
		'profile',
		'revision',
		'setup-config',
		'site-health-info',
		'site-health',
		'term',
		'theme-editor',
		'theme-install',
		'themes',
		'tools',
		'update-core',
		'update',
		'upgrade-functions',
		'upgrade',
		'upload',
		'user-edit',
		'user-new',
		'users',
		'widgets'
	];

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
		$this->general_initializer = new General();
		$this->admin_initializer = new Admin();
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
		$request = filter_input( INPUT_SERVER,  'REQUEST_URI' );
		$this->is_admin = false;
		if ( strpos( $request, '/wp-admin/' ) === 0 ) {
			$this->is_admin = true;

			if ( $request === '/wp-admin/' ) {
				$this->admin_route = 'index';
				return;
			}

			preg_match( '|/wp-admin/(.*)\.php|U', $request, $matches );
			$this->admin_route = $matches[1];
			if ( ! in_array( $this->admin_route, $this::ADMIN_ROUTES ) ) {
				$this->is_admin = false;
				$this->admin_route = '';
			}
		}
	}
}
