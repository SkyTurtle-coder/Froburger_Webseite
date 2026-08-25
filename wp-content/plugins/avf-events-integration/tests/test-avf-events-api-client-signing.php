<?php
/**
 * Tests for the signed signup request sent to Django (SEC-005).
 *
 * Note: requires a WordPress PHPUnit test harness (WP_UnitTestCase) to run,
 * matching test-avf-internal-endpoint-resolver.php in this same directory.
 * Not executable in the plain PHP CLI used elsewhere in this repo - see
 * signing-interop-check.php in this same directory for a bootstrap-free
 * cross-check against the Django implementation's signature output.
 *
 * @package AVF_Events_Integration
 */

class Test_AVF_Events_Api_Client_Signing extends WP_UnitTestCase {

	private $captured_args = null;

	public function set_up() {
		parent::set_up();

		update_option( AVF_Internal_Endpoint_Resolver::PRIMARY_OPTION, 'https://intern.avfroburger.ch' );
		AVF_Internal_Endpoint_Resolver::instance()->invalidate_health_cache();
		AVF_Events_API_Client::clear_cache();

		add_filter( 'pre_http_request', array( $this, 'capture_request' ), 10, 3 );
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'capture_request' ), 10 );
		$this->captured_args = null;
		remove_all_filters( 'avf_events_signup_shared_secret' );
		AVF_Internal_Endpoint_Resolver::instance()->invalidate_health_cache();
		AVF_Events_API_Client::clear_cache();
		parent::tear_down();
	}

	public function capture_request( $preempt, $args, $url ) {
		if ( false !== strpos( $url, '/signup/' ) ) {
			$this->captured_args = $args;

			return array(
				'response' => array( 'code' => 201 ),
				'body'     => wp_json_encode( array( 'success' => true, 'code' => 'signup_created' ) ),
			);
		}

		return $preempt;
	}

	private function set_secret( $secret ) {
		add_filter(
			'avf_events_signup_shared_secret',
			static function () use ( $secret ) {
				return $secret;
			}
		);
	}

	public function test_signup_request_includes_signature_and_timestamp_headers() {
		$this->set_secret( 'unit-test-secret' );

		$client = new AVF_Events_API_Client();
		$client->submit_event_signup( 'test-anlass', array( 'vulgo' => 'Testperson', 'attending' => true, 'values' => array() ) );

		$this->assertIsArray( $this->captured_args );
		$headers = $this->captured_args['headers'];
		$this->assertArrayHasKey( 'X-AVF-Timestamp', $headers );
		$this->assertArrayHasKey( 'X-AVF-Signature', $headers );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', $headers['X-AVF-Signature'] );

		$this->assertArrayNotHasKey( 'X-AVF-Event-Secret', $headers );
	}

	public function test_signature_matches_manual_hmac_over_method_slug_timestamp_bodyhash() {
		$this->set_secret( 'unit-test-secret' );

		$client = new AVF_Events_API_Client();
		$client->submit_event_signup( 'test-anlass', array( 'vulgo' => 'Testperson', 'attending' => true, 'values' => array() ) );

		$headers   = $this->captured_args['headers'];
		$body      = $this->captured_args['body'];
		$timestamp = $headers['X-AVF-Timestamp'];

		$expected_message   = "POST\ntest-anlass\n" . $timestamp . "\n" . hash( 'sha256', $body );
		$expected_signature = hash_hmac( 'sha256', $expected_message, 'unit-test-secret' );

		$this->assertSame( $expected_signature, $headers['X-AVF-Signature'] );
	}

	public function test_two_requests_a_moment_apart_use_different_signatures() {
		$this->set_secret( 'unit-test-secret' );
		$client = new AVF_Events_API_Client();

		$client->submit_event_signup( 'test-anlass', array( 'vulgo' => 'Erste', 'attending' => true, 'values' => array() ) );
		$first_signature = $this->captured_args['headers']['X-AVF-Signature'];

		$client->submit_event_signup( 'test-anlass', array( 'vulgo' => 'Zweite', 'attending' => true, 'values' => array() ) );
		$second_signature = $this->captured_args['headers']['X-AVF-Signature'];

		// Different payload -> different body hash -> different signature, even
		// if issued within the same second. Guards against a canonicalization
		// bug that ignores the body.
		$this->assertNotSame( $first_signature, $second_signature );
	}

	public function test_no_auth_headers_sent_when_secret_is_not_configured() {
		$this->set_secret( '' );

		$client = new AVF_Events_API_Client();
		$client->submit_event_signup( 'test-anlass', array( 'vulgo' => 'Ohne Secret', 'attending' => true, 'values' => array() ) );

		$headers = $this->captured_args['headers'];
		$this->assertArrayNotHasKey( 'X-AVF-Signature', $headers );
		$this->assertArrayNotHasKey( 'X-AVF-Event-Secret', $headers );
	}
}
