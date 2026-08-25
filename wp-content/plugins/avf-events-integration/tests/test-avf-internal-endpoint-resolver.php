<?php
/**
 * Tests for the internal endpoint resolver.
 *
 * @package AVF_Events_Integration
 */

class Test_AVF_Internal_Endpoint_Resolver extends WP_UnitTestCase {

	private $http_callback = null;

	public function set_up() {
		parent::set_up();

		update_option( AVF_Internal_Endpoint_Resolver::PRIMARY_OPTION, 'https://intern.avfroburger.ch' );
		update_option( AVF_Internal_Endpoint_Resolver::FALLBACK_OPTION, 'https://intern-avfroburger.ch' );
		AVF_Internal_Endpoint_Resolver::instance()->invalidate_health_cache();
		AVF_Events_API_Client::clear_cache();

		add_filter( 'pre_http_request', array( $this, 'intercept_http_requests' ), 10, 3 );
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'intercept_http_requests' ), 10 );
		$this->http_callback = null;
		AVF_Internal_Endpoint_Resolver::instance()->invalidate_health_cache();
		AVF_Events_API_Client::clear_cache();
		parent::tear_down();
	}

	public function intercept_http_requests( $preempt, $args, $url ) {
		if ( is_callable( $this->http_callback ) ) {
			return call_user_func( $this->http_callback, $args, $url );
		}

		return $preempt;
	}

	public function test_primary_health_check_selects_primary() {
		$this->http_callback = static function ( $args, $url ) {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => '{"status":"ok"}',
				'headers'  => array(),
			);
		};

		$active = AVF_Internal_Endpoint_Resolver::instance()->get_active_url();

		$this->assertSame( 'https://intern.avfroburger.ch', $active );
	}

	public function test_primary_transport_error_selects_fallback() {
		$this->http_callback = static function ( $args, $url ) {
			if ( false !== strpos( $url, 'intern.avfroburger.ch' ) ) {
				return new WP_Error( 'http_request_failed', 'timeout' );
			}

			return array(
				'response' => array( 'code' => 200 ),
				'body'     => '{"status":"ok"}',
				'headers'  => array(),
			);
		};

		$active = AVF_Internal_Endpoint_Resolver::instance()->get_active_url();

		$this->assertSame( 'https://intern-avfroburger.ch', $active );
	}

	public function test_failed_primary_is_cached() {
		$requests = array();
		$this->http_callback = static function ( $args, $url ) use ( &$requests ) {
			$requests[] = $url;
			if ( false !== strpos( $url, 'intern.avfroburger.ch' ) ) {
				return new WP_Error( 'http_request_failed', 'timeout' );
			}

			return array(
				'response' => array( 'code' => 200 ),
				'body'     => '{"status":"ok"}',
				'headers'  => array(),
			);
		};

		AVF_Internal_Endpoint_Resolver::instance()->get_active_url();
		AVF_Internal_Endpoint_Resolver::instance()->get_active_url();

		$primary_checks = array_filter(
			$requests,
			static function ( $url ) {
				return false !== strpos( $url, 'intern.avfroburger.ch/healthz/' );
			}
		);

		$this->assertCount( 1, $primary_checks );
	}

	public function test_get_request_retries_once_on_503() {
		$seen = array();
		$this->http_callback = static function ( $args, $url ) use ( &$seen ) {
			$seen[] = $url;

			if ( false !== strpos( $url, '/healthz/' ) ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => '{"status":"ok"}',
					'headers'  => array(),
				);
			}

			if ( false !== strpos( $url, 'intern.avfroburger.ch' ) ) {
				return array(
					'response' => array( 'code' => 503 ),
					'body'     => '',
					'headers'  => array(),
				);
			}

			return array(
				'response' => array( 'code' => 200 ),
				'body'     => '{"count":0,"results":[]}',
				'headers'  => array(),
			);
		};

		$response = avf_internal_api_request( 'GET', '/api/v1/public/events/upcoming/' );

		$this->assertIsArray( $response );
		$this->assertSame( 200, wp_remote_retrieve_response_code( $response ) );
		$this->assertSame( 'https://intern-avfroburger.ch', $response['avf_internal_base_url'] );
	}

	public function test_get_request_does_not_retry_on_404() {
		$seen = array();
		$this->http_callback = static function ( $args, $url ) use ( &$seen ) {
			$seen[] = $url;

			if ( false !== strpos( $url, '/healthz/' ) ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => '{"status":"ok"}',
					'headers'  => array(),
				);
			}

			return array(
				'response' => array( 'code' => 404 ),
				'body'     => '{"code":"event_not_found"}',
				'headers'  => array(),
			);
		};

		$response = avf_internal_api_request( 'GET', '/api/v1/public/events/missing/' );

		$this->assertIsArray( $response );
		$this->assertSame( 404, wp_remote_retrieve_response_code( $response ) );
		$this->assertCount(
			1,
			array_filter(
				$seen,
				static function ( $url ) {
					return false !== strpos( $url, '/api/v1/public/events/missing/' );
				}
			)
		);
	}

	public function test_post_request_is_not_retried() {
		$seen = array();
		$this->http_callback = static function ( $args, $url ) use ( &$seen ) {
			$seen[] = $url;

			if ( false !== strpos( $url, '/healthz/' ) ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => '{"status":"ok"}',
					'headers'  => array(),
				);
			}

			return new WP_Error( 'http_request_failed', 'timeout' );
		};

		$response = avf_internal_api_request( 'POST', '/api/v1/public/events/test/signup/', array( 'body' => '{}' ) );

		$this->assertWPError( $response );
		$this->assertCount(
			1,
			array_filter(
				$seen,
				static function ( $url ) {
					return false !== strpos( $url, '/signup/' );
				}
			)
		);
	}

	public function test_known_internal_urls_are_normalized() {
		$url = AVF_Internal_Endpoint_Resolver::instance()->normalize_known_internal_url(
			'https://intern.avfroburger.ch/media/public/example.jpg'
		);

		$this->assertStringContainsString( '/media/public/example.jpg', $url );
	}

	public function test_external_urls_are_not_rewritten() {
		$url = AVF_Internal_Endpoint_Resolver::instance()->normalize_known_internal_url(
			'https://example.org/file.jpg'
		);

		$this->assertSame( 'https://example.org/file.jpg', $url );
	}

	public function test_portal_path_rejects_external_urls() {
		$this->assertSame( '', AVF_Internal_Endpoint_Resolver::instance()->sanitize_internal_path( 'https://evil.example/x' ) );
		$this->assertSame( '', AVF_Internal_Endpoint_Resolver::instance()->sanitize_internal_path( '//evil.example/x' ) );
		$this->assertSame( '', AVF_Internal_Endpoint_Resolver::instance()->sanitize_internal_path( 'javascript:alert(1)' ) );
	}

	public function test_portal_path_accepts_relative_internal_paths() {
		$this->assertSame( '/accounts/login/', AVF_Internal_Endpoint_Resolver::instance()->sanitize_internal_path( '/accounts/login/' ) );
	}

	public function test_both_hosts_unavailable_return_controlled_error() {
		$this->http_callback = static function () {
			return new WP_Error( 'http_request_failed', 'connection failed' );
		};

		$active = AVF_Internal_Endpoint_Resolver::instance()->get_active_url();

		$this->assertWPError( $active );
		$this->assertSame( 'avf_internal_endpoint_unavailable', $active->get_error_code() );
	}

	public function test_manual_cache_invalidation_clears_health_state() {
		$resolver = AVF_Internal_Endpoint_Resolver::instance();
		$resolver->mark_failure( $resolver->get_primary_url(), 'timeout' );
		$resolver->invalidate_health_cache();

		$reflection = new ReflectionClass( $resolver );
		$method = $reflection->getMethod( 'get_cached_state' );
		$method->setAccessible( true );

		$this->assertNull( $method->invoke( $resolver, $resolver->get_primary_url() ) );
	}
}
