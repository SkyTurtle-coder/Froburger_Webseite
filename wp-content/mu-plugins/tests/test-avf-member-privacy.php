<?php
/**
 * Tests for AVF member privacy helpers.
 */

class Test_AVF_Member_Privacy extends WP_UnitTestCase {

	public function test_member_image_url_detection_matches_proxy_route() {
		$this->assertTrue( avf_is_member_image_url( home_url( '/member-media/abcdef012345abcdef012345abcdef01/' ) ) );
	}

	public function test_member_image_url_detection_matches_neutral_upload_dir() {
		$this->assertTrue( avf_is_member_image_url( home_url( '/wp-content/uploads/avf-members/member-a71c95e2.webp' ) ) );
	}

	public function test_member_image_url_detection_rejects_regular_upload() {
		$this->assertFalse( avf_is_member_image_url( home_url( '/wp-content/uploads/2026/08/hero-image.webp' ) ) );
	}

	public function test_member_media_route_pattern_is_32_hex() {
		$url = home_url( '/member-media/abcdef012345abcdef012345abcdef01/' );

		$this->assertTrue( avf_is_member_image_url( $url ) );
		$this->assertMatchesRegularExpression( '#/member-media/[a-f0-9]{32}/$#', $url );
	}

	public function test_member_robots_directives_are_added() {
		$robots = array();
		$filter = new ReflectionMethod( 'AVF_Member_Privacy', 'filter_wp_robots' );

		$this->go_to( home_url( '/mitglieder/' ) );

		$robots = $filter->invoke( null, $robots );

		$this->assertTrue( $robots['noindex'] );
		$this->assertTrue( $robots['noimageindex'] );
		$this->assertSame( 'none', $robots['max-image-preview'] );
	}
}
