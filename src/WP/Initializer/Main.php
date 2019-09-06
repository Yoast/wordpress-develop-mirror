<?php

namespace WP\Initializer;

class Main implements InitializerInterface {

	const ADMIN_ROUTES = [

	]

	private $admin_constants_initializer;

	private $general_initializer;

	private $admin_initializer;

	public function __construct() {
		$this->$admin_constants_initializer = new AdminConstants();
		$this->$general_initializer = new General();
		$this->$admin_initializer = new Admin();
	}

	public function initialize() {
		$this->$admin_constants_initializer->initialize();
		$this->$general_initializer->initialize();
		$this->$admin_initializer->initialize();
	}
}
