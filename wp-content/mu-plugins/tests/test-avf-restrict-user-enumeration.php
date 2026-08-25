<?php
/**
 * Tests for AVF user-enumeration restrictions.
 */

class Test_AVF_Restrict_User_Enumeration extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );
	}

	protected function tearDown(): void {
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tearDown();
	}

	public function test_anonymous_rest_users_collection_request_is_blocked() {
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	public function test_anonymous_rest_users_single_request_is_blocked() {
		wp_set_current_user( 0 );

		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/users/' . $user_id );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	public function test_authenticated_rest_users_request_still_works() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	public function test_anonymous_author_archive_query_is_flagged_for_blocking() {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( 0 );

		$this->go_to( home_url( '/?author=' . $user_id ) );

		$this->assertTrue( AVF_Restrict_User_Enumeration::should_block_author_archive_request() );
	}

	public function test_logged_in_author_archive_query_is_not_blocked() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$this->go_to( home_url( '/?author=' . $user_id ) );

		$this->assertFalse( AVF_Restrict_User_Enumeration::should_block_author_archive_request() );
	}

	public function test_non_author_request_is_not_blocked() {
		wp_set_current_user( 0 );

		$this->go_to( home_url( '/' ) );

		$this->assertFalse( AVF_Restrict_User_Enumeration::should_block_author_archive_request() );
	}

	public function test_users_sitemap_provider_is_not_registered() {
		$server = wp_sitemaps_get_server();

		$this->assertNull( $server->registry->get_provider( 'users' ) );
	}
}
