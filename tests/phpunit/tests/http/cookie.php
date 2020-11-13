<?php

/**
 * Tests the WP_Http_Cookie class.
 *
 * @group http
 */
class Tests_HTTP_Cookie extends WP_UnitTestCase {

	/**
	 * Tests the constructor.
	 *
	 * @dataProvider constructorValues
	 * @covers       WP_Http_Cookie::__construct
	 *
	 * @param array|string $data                Raw cookie data.
	 * @param string       $requested_url       The requested url.
	 * @param array        $expected_attributes Expected attribute values.
	 */
	public function test_contruct( $data, $requested_url, $expected_attributes ) {
		$instance = new WP_Http_Cookie( $data, $requested_url );

		foreach ( $expected_attributes as $attribute => $expected ) {
			$this->assertSame( $expected, $instance->$attribute );
		}
	}

	/**
	 * Provides data for the constructor test.
	 *
	 * @return array[] Test data.
	 */
	public function constructorValues() {
		return array(
			'with_request_url_containing_trailing_slash'       => array(
				'data'                => array(
					'name'  => 'Cookiename',
					'value' => 'Cookievalue',
				),
				'requested_url'       => 'https://example.org/path/',
				'expected_attributes' => array(
					'name'   => 'Cookiename',
					'value'  => 'Cookievalue',
					'domain' => 'example.org',
					'path'   => '/path/',
				),
			),
			'with_requested_url_not_containing_trailing_slash' => array(
				'data'                => array(
					'name'  => 'Cookiename',
					'value' => 'Cookievalue',
				),
				'requested_url'       => 'https://example.org/path/route',
				'expected_attributes' => array(
					'name'   => 'Cookiename',
					'value'  => 'Cookievalue',
					'domain' => 'example.org',
					'path'   => '/path/',
				),
			),
			'with_data_array_given'                            => array(
				'data'                => array(
					'name'      => 'Cookiename',
					'value'     => 'Cookievalue',
					'domain'    => 'example.org',
					'path'      => '/path/',
					'port'      => 80,
					'host_only' => true,
				),
				'requested_url'       => '',
				'expected_attributes' => array(
					'name'      => 'Cookiename',
					'value'     => 'Cookievalue',
					'domain'    => 'example.org',
					'path'      => '/path/',
					'port'      => 80,
					'host_only' => true,
				),
			),
			'with_data_array_and_requested_url_given'          => array(
				'data'                => array(
					'name'      => 'Cookiename',
					'value'     => 'Cookievalue',
					'domain'    => 'example.org',
					'path'      => '/path/',
					'port'      => 80,
					'host_only' => true,
				),
				'requested_url'       => 'https://another_example.org/other_path/',
				'expected_attributes' => array(
					'name'      => 'Cookiename',
					'value'     => 'Cookievalue',
					'domain'    => 'example.org',
					'path'      => '/path/',
					'port'      => 80,
					'host_only' => true,
					'expires'   => null,
				),
			),
			'with_data_array_containing_no_name'               => array(
				'data'                => array(
					'value' => 'Cookievalue',
				),
				'requested_url'       => '',
				'expected_attributes' => array(
					'name'  => null,
					'value' => null,
				),
			),
			'with_int_expires_value_given'                     => array(
				'data'                => array(
					'name'    => 'Cookiename',
					'value'   => 'Cookievalue',
					'expires' => 123456789,
				),
				'requested_url'       => '',
				'expected_attributes' => array(
					'name'    => 'Cookiename',
					'value'   => 'Cookievalue',
					'expires' => 123456789,
				),
			),
			'with_string_expires_value_given'                  => array(
				'data'                => array(
					'name'    => 'Cookiename',
					'value'   => 'Cookievalue',
					'expires' => '2020-11-13 14:15:00',
				),
				'requested_url'       => '',
				'expected_attributes' => array(
					'name'    => 'Cookiename',
					'value'   => 'Cookievalue',
					'expires' => strtotime( '2020-11-13 14:15:00' ),
				),
			),
			'with_data_string_given'                           => array(
				'data'                => 'Cookiename=Cookievalue;domain=example.org;path=/path/;port=80;host_only=true',
				'requested_url'       => '',
				'expected_attributes' => array(
					'name'      => 'Cookiename',
					'value'     => 'Cookievalue',
					'domain'    => 'example.org',
					'path'      => '/path/',
					'port'      => '80',
					'host_only' => 'true',
				),
			),
			'with_data_string_contain_empty_pair_given'        => array(
				'data'                => 'Cookiename=Cookievalue;',
				'requested_url'       => '',
				'expected_attributes' => array(
					'name'  => 'Cookiename',
					'value' => 'Cookievalue',
				),
			),
			'with_data_string_containing_expires_value'        => array(
				'data'                => 'Cookiename=Cookievalue;expires=2020-11-13 14:15:00',
				'requested_url'       => '',
				'expected_attributes' => array(
					'name'    => 'Cookiename',
					'value'   => 'Cookievalue',
					'expires' => strtotime( '2020-11-13 14:15:00' ),
				),
			),
			'with_data_string_containing_url_encoded_value'    => array(
				'data'                => 'Cookiename=Cookie%20value',
				'requested_url'       => '',
				'expected_attributes' => array(
					'name'  => 'Cookiename',
					'value' => 'Cookie value',
				),
			),
			'with_data_string_containing_spaces_after'         => array(
				'data'                => 'Cookiename =Cookievalue',
				'requested_url'       => '',
				'expected_attributes' => array(
					'name'  => 'Cookiename',
					'value' => 'Cookievalue',
				),
			),
			'with_data_string_containing_only_a_key'           => array(
				'data'                => 'Cookiename=Cookievalue;domain',
				'requested_url'       => '',
				'expected_attributes' => array(
					'name'   => 'Cookiename',
					'value'  => 'Cookievalue',
					'domain' => ''
				),
			),
		);
	}

	/**
	 * Tests the test method.
	 *
	 * @dataProvider validateCookieProvider
	 *
	 * @param array  $data     Raw cookie data to use.
	 * @param string $url      URL to test.
	 * @param bool   $expected Expected value.
	 *
	 * @covers WP_Http_Cookie::test
	 */
	public function test_the_test_method( $data, $url, $expected ) {
		$instance = new WP_Http_Cookie( $data );

		$this->assertSame( $expected, $instance->test( $url ) );
	}

	/**
	 * Provides data for the test method.
	 *
	 * @return array[] Test data.
	 */
	public function validateCookieProvider() {
		return array(
			'with_no_name_present'                                => array(
				'data'     => array(
					'value' => 'Cookievalue',
				),
				'url'      => 'https://example.org',
				'expected' => false,
			),
			'with_expired_cookie'                                 => array(
				'data'     => array(
					'name'    => 'Cookiename',
					'value'   => 'Cookievalue',
					'expires' => strtotime( "-1week" ),
				),
				'url'      => 'https://example.org',
				'expected' => false,
			),
			'happy_path'                                          => array(
				'data'     => array(
					'name'   => 'Cookiename',
					'value'  => 'Cookievalue',
					'domain' => 'example.org',
					'path'   => '/path/',
					'port'   => 80,
				),
				'url'      => 'https://example.org:80/path/',
				'expected' => true,
			),
			'url_not_containing_the_port_on_https'                => array(
				'data'     => array(
					'name'   => 'Cookiename',
					'value'  => 'Cookievalue',
					'domain' => 'example.org',
					'path'   => '/path/',
					'port'   => 443,
				),
				'url'      => 'https://example.org/path/',
				'expected' => true,
			),
			'url_not_containing_the_port_on_http'                 => array(
				'data'     => array(
					'name'   => 'Cookiename',
					'value'  => 'Cookievalue',
					'domain' => 'example.org',
					'path'   => '/path/',
					'port'   => 80,
				),
				'url'      => 'http://example.org/path/',
				'expected' => true,
			),
			'url_not_containing_the_path'                         => array(
				'data'     => array(
					'name'   => 'Cookiename',
					'value'  => 'Cookievalue',
					'domain' => 'example.org',
					'path'   => '/',
					'port'   => 80,
				),
				'url'      => 'http://example.org',
				'expected' => true,
			),
			'data_not_containing_the_path'                        => array(
				'data'     => array(
					'name'   => 'Cookiename',
					'value'  => 'Cookievalue',
					'domain' => 'example.org',
					'port'   => 80,
				),
				'url'      => 'http://example.org',
				'expected' => true,
			),
			'data_not_containing_the_port'                        => array(
				'data'     => array(
					'name'   => 'Cookiename',
					'value'  => 'Cookievalue',
					'domain' => 'example.org',
				),
				'url'      => 'http://example.org',
				'expected' => true,
			),
			'data_not_containing_the_domain'                      => array(
				'data'     => array(
					'name'  => 'Cookiename',
					'value' => 'Cookievalue',
				),
				'url'      => 'http://example.org',
				'expected' => true,
			),
			'data_not_containing_the_domain_and_url_is_localhost' => array(
				'data'     => array(
					'name'   => 'Cookiename',
					'value'  => 'Cookievalue',
					'domain' => 'localhost'
				),
				'url'      => 'http://localhost.local',
				'expected' => true,
			),
			'data_containing_all_domains_and_url_is_subdomain'    => array(
				'data'     => array(
					'name'   => 'Cookiename',
					'value'  => 'Cookievalue',
					'domain' => '.example.org',
				),
				'url'      => 'http://sub.example.org',
				'expected' => true,
			),
			'data_containing_all_domains_and_url_is_main_domain' => array(
				'data'     => array(
					'name'   => 'Cookiename',
					'value'  => 'Cookievalue',
					'domain' => '.example.org',
				),
				'url'      => 'http://example.org',
				'expected' => true,
			),

			'data_containing_subdomain_and_url_is_main_domain'  => array(
				'data'     => array(
					'name'   => 'Cookiename',
					'value'  => 'Cookievalue',
					'domain' => 'sub.example.org'
				),
				'url'      => 'http://example.org',
				'expected' => false,
			),
			'data_containing_port_list'                        => array(
				'data'     => array(
					'name'   => 'Cookiename',
					'value'  => 'Cookievalue',
					'domain' => 'example.org',
					'path'   => '/path/',
					'port'   => "80,8080",
				),
				'url'      => 'https://example.org:8081/path/',
				'expected' => false,
			),
			'data_containing_path_url_contains_other_path'     => array(
				'data'     => array(
					'name'   => 'Cookiename',
					'value'  => 'Cookievalue',
					'domain' => 'example.org',
					'path'   => '/path/',
				),
				'url'      => 'https://example.org/other_path',
				'expected' => false,
			),
		);
	}

	/**
	 * Tests the getHeaderValue method.
	 *
	 * @dataProvider getHeaderValues
	 *
	 * @covers       WP_Http_Cookie::getHeaderValue
	 *
	 * @param array  $data     Raw cookie data.
	 * @param string $expected Expected value.
	 */
	public function test_getHeaderValue( $data, $expected ) {
		$instance = new WP_Http_Cookie( $data );

		$this->assertSame( $expected, $instance->getHeaderValue() );
	}

	/**
	 * Provides data for the getHeaderValueTest
	 *
	 * @return array[]
	 */
	public function getHeaderValues() {
		return array(
			'happy_path'        => array(
				'data'     => array(
					'name'  => 'Cookiename',
					'value' => 'Cookievalue'
				),
				'expected' => 'Cookiename=Cookievalue',
			),
			'with_no_name_set'  => array(
				'data'     => array(
					'value' => 'Cookievalue'
				),
				'expected' => '',
			),
			'with_no_value_set' => array(
				'data'     => array(
					'name' => 'Cookiename'
				),
				'expected' => '',
			),
		);
	}

	/**
	 * Tests the getHeaderValue method with having the filter applied.
	 *
	 * @covers WP_Http_Cookie::getHeaderValue
	 */
	public function test_getHeaderValue_with_the_filter_applied() {
		$instance = new WP_Http_Cookie( array(
			'name'  => 'Cookiename',
			'value' => 'Cookievalue'
		) );

		add_filter( 'wp_http_cookie_value', [ $this, '_filter_cookie_value' ], 10, 2 );

		$this->assertSame( 'Cookiename=FilteredCookievalue', $instance->getHeaderValue() );

		remove_filter( 'wp_http_cookie_value', [ $this, '_filter_cookie_value' ], 10 );
	}

	/**
	 * Filters the cookie value.
	 *
	 * @param string $value The cookie value.
	 * @param string $name  The cookie name.
	 *
	 * @return string The filtered value.
	 */
	public function _filter_cookie_value( $value, $name ) {
		$this->assertSame( 'Cookievalue', $value );
		$this->assertSame( 'Cookiename', $name );

		return 'FilteredCookievalue';
	}

	/**
	 * Tests the getFullHeader method.
	 *
	 * @covers WP_Http_Cookie::getFullHeader
	 */
	public function test_getFullHeader() {
		$instance = new WP_Http_Cookie( array(
			'name'  => 'Cookiename',
			'value' => 'Cookievalue'
		) );

		$this->assertSame( 'Cookie: Cookiename=Cookievalue', $instance->getFullHeader() );
	}

	/**
	 * Tests the get_attributes method.
	 *
	 * @covers WP_Http_Cookie::get_attributes()
	 */
	public function test_get_attributes() {
		$expires = time();

		$instance = new WP_Http_Cookie( array(
			'name'    => 'Cookiename',
			'value'   => 'Cookievalue',
			'expires' => $expires,
			'path'    => '/path',
			'domain'  => 'example.org',
		) );

		$this->assertSame(
			array(
				'expires' => $expires,
				'path'    => '/path',
				'domain'  => 'example.org',
			),
			$instance->get_attributes()
		);
	}
}
