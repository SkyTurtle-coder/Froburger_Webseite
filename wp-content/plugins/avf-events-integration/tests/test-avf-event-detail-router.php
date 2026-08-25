<?php
/**
 * Tests for the public event detail router.
 *
 * @package AVF_Events_Integration
 */

class Test_AVF_Event_Detail_Router extends WP_UnitTestCase {

	private $original_queried_object;

	public function set_up() {
		parent::set_up();

		$this->original_queried_object = $GLOBALS['wp_query']->queried_object;
		update_option( 'avf_event_detail_page_path', '/anlassdetail/' );
	}

	public function tear_down() {
		set_query_var( AVF_Event_Detail_Router::QUERY_VAR, '' );
		$GLOBALS['wp_query']->queried_object = $this->original_queried_object;

		parent::tear_down();
	}

	public function test_public_event_detail_request_gets_zirkel_background_class() {
		$post_id = self::factory()->post->create(
			array(
				'post_name' => 'anlassdetail',
				'post_type' => 'page',
			)
		);

		set_query_var( AVF_Event_Detail_Router::QUERY_VAR, 'test-anlass' );
		$GLOBALS['wp_query']->queried_object = get_post( $post_id );

		$classes = AVF_Event_Detail_Router::instance()->add_detail_body_class( array( 'page' ) );

		$this->assertContains( 'avf-has-zirkel-bg', $classes );
	}

	public function test_normal_page_does_not_get_zirkel_background_class() {
		$post_id = self::factory()->post->create(
			array(
				'post_name' => 'normale-seite',
				'post_type' => 'page',
			)
		);

		set_query_var( AVF_Event_Detail_Router::QUERY_VAR, 'test-anlass' );
		$GLOBALS['wp_query']->queried_object = get_post( $post_id );

		$classes = AVF_Event_Detail_Router::instance()->add_detail_body_class( array( 'page' ) );

		$this->assertNotContains( 'avf-has-zirkel-bg', $classes );
	}
}
