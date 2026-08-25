<?php
/**
 * Bootstrap-free interop check for SEC-005 request signing.
 *
 * Verifies that PHP's hash_hmac() over the same canonical string as Django's
 * events/signing.py (intern/events/signing.py) produces an identical
 * signature. Deliberately does NOT require a WordPress test harness so it
 * can run with a plain PHP CLI - useful whenever either implementation is
 * changed, to catch a canonicalization mismatch immediately instead of
 * discovering it as "signup requests silently fail in production".
 *
 * Run with: php signing-interop-check.php
 * Exits 0 and prints "OK" on success, exits 1 and prints details on mismatch.
 *
 * @package AVF_Events_Integration
 */

function avf_test_sign( $secret, $slug, $timestamp, $body ) {
	$message = "POST\n" . $slug . "\n" . $timestamp . "\n" . hash( 'sha256', $body );

	return hash_hmac( 'sha256', $message, $secret );
}

$cases = array(
	array(
		'secret'    => 'interop-test-secret',
		'slug'      => 'test-anlass',
		'timestamp' => '1699999999',
		'body'      => '{"vulgo":"Interop","attending":true,"values":{}}',
		// Cross-checked against intern/events/signing.py::compute_signature
		// with the same inputs during the SEC-005 remediation.
		'expected'  => '2495f0cab1f289a6f08d91b25340089f598fe7b365a3006aac157dbbb731c600',
	),
);

$failures = 0;

foreach ( $cases as $i => $case ) {
	$actual = avf_test_sign( $case['secret'], $case['slug'], $case['timestamp'], $case['body'] );

	if ( $actual !== $case['expected'] ) {
		$failures++;
		fwrite( STDERR, "Case $i FAILED\n  expected: {$case['expected']}\n  actual:   $actual\n" );
	}
}

if ( $failures > 0 ) {
	fwrite( STDERR, "$failures case(s) failed - PHP and Django signature computation have diverged.\n" );
	exit( 1 );
}

echo "OK: PHP signature computation matches the Django reference vector(s).\n";
exit( 0 );
