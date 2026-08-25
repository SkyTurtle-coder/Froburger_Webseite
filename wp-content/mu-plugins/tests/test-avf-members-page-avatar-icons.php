<?php
/**
 * Snapshot and rendering tests for static member avatar icons.
 */

class Test_AVF_Members_Page_Avatar_Icons extends WP_UnitTestCase {

	public function test_v3_snapshot_with_valid_icon_is_accepted() {
		$payload = $this->payload( 3, 'fuxmajor_m', null );
		$normalized = $this->invoke( 'normalize_remote_payload', array( $payload ) );

		$this->assertIsArray( $normalized );
		$this->assertSame( 'fuxmajor_m', $normalized['sections']['committee']['members'][0]['avatar_icon'] );
	}

	public function test_v2_snapshot_remains_accepted_during_rollout() {
		$payload = $this->payload( 2, null, array( 'fallback' => true, 'variants' => array() ) );
		$normalized = $this->invoke( 'normalize_remote_payload', array( $payload ) );

		$this->assertIsArray( $normalized );
		$this->assertNull( $normalized['sections']['committee']['members'][0]['avatar_icon'] );
	}

	public function test_invalid_icon_key_is_neutralized_without_a_url_resolution() {
		$payload = $this->payload( 3, '../../evil.php', null );
		$normalized = $this->invoke( 'normalize_remote_payload', array( $payload ) );

		$this->assertIsArray( $normalized );
		$this->assertNull( $normalized['sections']['committee']['members'][0]['avatar_icon'] );
		$this->assertSame( '', $this->invoke( 'avatar_icon_url', array( 'https://evil.example/icon.png' ) ) );
	}

	public function test_icon_wins_over_snapshot_photo_when_rendering_card() {
		$member = $this->payload( 3, 'fux_m', array( 'fallback' => false, 'variants' => array() ) );
		$member = $member['sections']['committee']['members'][0];
		$member['photo'] = array(
			'fallback' => false,
			'variants' => array(
				'medium' => array( 'url' => 'https://evil.example/photo.webp', 'width' => 320, 'height' => 320 ),
			),
		);

		$html = $this->invoke( 'render_card', array( $member, 'committee', true, 'eager' ) );

		$this->assertStringContainsString( 'member-avatar-icons/fux_m.png', $html );
		$this->assertStringNotContainsString( 'evil.example/photo.webp', $html );
	}

	public function test_initials_render_without_icon_or_photo() {
		$member = $this->payload( 3, null, null );
		$member = $member['sections']['committee']['members'][0];

		$html = $this->invoke( 'render_card', array( $member, 'committee', true, 'eager' ) );

		$this->assertStringContainsString( 'avf-member-card__placeholder', $html );
		$this->assertStringContainsString( 'TP', $html );
	}

	private function payload( $schema_version, $avatar_icon, $photo ) {
		$member = array(
			'display_name' => 'Test Person',
			'first_name' => 'Test',
			'last_name' => 'Person',
			'vulgo' => '',
			'entry_year' => 2020,
			'entry_semester' => 'HS',
			'entry_display' => '2020 HS',
			'academic_title' => '',
			'degree_program' => '',
			'roles' => array( array( 'key' => 'senior', 'label' => 'Senior', 'sort_order' => 10 ) ),
			'photo' => $photo,
		);
		if ( 3 === $schema_version ) {
			$member['avatar_icon'] = $avatar_icon;
		}

		$payload = array(
			'schema_version' => $schema_version,
			'generated_at' => '2026-08-22T00:00:00+00:00',
			'member_count' => 1,
			'section_order' => array( 'committee', 'salon', 'stall', 'altfroburger', 'af_committee' ),
			'sections' => array(),
		);
		foreach ( $payload['section_order'] as $section_key ) {
			$payload['sections'][ $section_key ] = array(
				'title' => ucfirst( $section_key ),
				'count' => 'committee' === $section_key ? 1 : 0,
				'members' => 'committee' === $section_key ? array( $member ) : array(),
			);
		}

		$hash_payload = $this->invoke( 'build_hash_payload', array( $payload ) );
		$canonical = $this->invoke( 'canonicalize_hash_payload', array( $hash_payload ) );
		$payload['content_hash'] = hash( 'sha256', wp_json_encode( $canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
		return $payload;
	}

	private function invoke( $method, array $arguments ) {
		$reflection = new ReflectionMethod( 'AVF_Members_Page', $method );
		$reflection->setAccessible( true );
		return $reflection->invokeArgs( null, $arguments );
	}
}
