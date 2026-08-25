<?php
/**
 * Bootstrap-free interop check for SEC-013's content-hash change.
 *
 * Verifies that this file's copy of avf-members-page.php's canonicalization
 * logic (recursive key-sort + JSON encode + sha256) produces the exact same
 * hash as Django's accounts/public_members.py::_content_hash() for an
 * identical sample payload with the "id" field removed from both sides.
 * Deliberately does NOT require a WordPress test harness so it can run with
 * a plain PHP CLI - run alongside signing-interop-check.php whenever either
 * side's hash/payload logic changes.
 *
 * Run with: php members-hash-interop-check.php
 *
 * @package AVF_Events_Integration
 */

function avf_canonicalize_hash_payload( $value ) {
	if ( ! is_array( $value ) ) {
		return $value;
	}

	if ( array_values( $value ) === $value ) {
		$items = array();
		foreach ( $value as $item ) {
			$items[] = avf_canonicalize_hash_payload( $item );
		}
		return $items;
	}

	ksort( $value );
	$out = array();
	foreach ( $value as $key => $item ) {
		$out[ $key ] = avf_canonicalize_hash_payload( $item );
	}
	return $out;
}

// Deliberately excludes 'id' - must match accounts/public_members.py::_content_hash().
$sample_payload = array(
	'schema_version' => 3,
	'member_count'   => 1,
	'section_order'  => array( 'committee' ),
	'sections'       => array(
		'committee' => array(
			'title'   => 'Komitee',
			'count'   => 1,
			'members' => array(
				array(
					'display_name'   => 'Test Person',
					'first_name'     => 'Test',
					'last_name'      => 'Person',
					'vulgo'          => 'Testo',
					'entry_year'     => 2020,
					'entry_semester' => 'HS',
					'entry_display'  => '2020 HS',
					'academic_title' => '',
					'degree_program' => '',
					'roles'          => array( array( 'key' => 'senior', 'label' => 'Senior', 'sort_order' => 10 ) ),
					'avatar_icon'    => 'fuxmajor_m',
					'photo'          => null,
				),
			),
		),
	),
);

// Reference value cross-checked against intern/accounts/public_members.py's
// _content_hash() for this exact payload during the SEC-013 remediation.
$expected = '189f2cd8b0c99e3b74a4edbde0ec22fb0659ca075bdf4c82e6a6dd1cbc7e502f';

$canonical = avf_canonicalize_hash_payload( $sample_payload );
$raw       = json_encode( $canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
$actual    = hash( 'sha256', $raw );

if ( $actual !== $expected ) {
	fwrite( STDERR, "MISMATCH\n  expected: $expected\n  actual:   $actual\n" );
	fwrite( STDERR, "PHP and Django content-hash computation have diverged - check that both sides hash exactly the same fields (see avf-members-page.php::build_hash_payload() and accounts/public_members.py::_content_hash()).\n" );
	exit( 1 );
}

echo "OK: PHP content-hash computation matches the Django reference vector.\n";
exit( 0 );
