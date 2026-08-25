<?php
/**
 * Plugin Name: AVF Security Headers
 * Description: Adds baseline security headers (X-Content-Type-Options, Referrer-Policy, Permissions-Policy, X-Frame-Options) to responses that WordPress core does not already cover, without duplicating headers core sends on its own (e.g. X-Frame-Options on wp-login.php / wp-admin).
 *
 * Manual smoke test (no automated test exists here, since headers can only be
 * observed on a live HTTP response):
 *
 *   curl -sI https://test.avfroburger.ch/
 *
 * Expected, among the response headers:
 *   X-Content-Type-Options: nosniff
 *   Referrer-Policy: strict-origin-when-cross-origin
 *   Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()
 *   X-Frame-Options: SAMEORIGIN
 *
 * Also check wp-login.php directly - it's on a separate WordPress bootstrap
 * that never fires `send_headers`, so this plugin ALSO hooks `login_init`
 * (and `admin_init` for wp-admin) to actually reach it:
 *
 *   curl -sI https://test.avfroburger.ch/wp-login.php
 *
 * Expected: X-Content-Type-Options/Referrer-Policy/Permissions-Policy same
 * as above, AND exactly one X-Frame-Options header (core's own
 * `send_frame_options_header()` already sends `SAMEORIGIN` there via
 * `login_init` - this plugin's dedup check must not add a second one):
 *
 *   curl -sI https://test.avfroburger.ch/wp-login.php | grep -ci x-frame-options
 *
 * That should print exactly 1, not 2.
 *
 * Explicitly out of scope here: Content-Security-Policy (handled separately
 * as a Report-Only rollout) and Strict-Transport-Security (handled at the
 * Apache/.htaccess level).
 */

defined( 'ABSPATH' ) || exit;

final class AVF_Security_Headers {

	/**
	 * Conservative Permissions-Policy allowlist. Only disables browser
	 * features this site has no legitimate use for; anything Elementor or
	 * other plugins might plausibly need (fullscreen, autoplay, etc.) is
	 * deliberately left untouched.
	 *
	 * @var string
	 */
	const PERMISSIONS_POLICY = 'camera=(), microphone=(), geolocation=(), payment=()';

	/**
	 * Boots the header hardening.
	 *
	 * @return void
	 */
	public static function init() {
		// SEC-011 fix: `send_headers` only fires from the full front-end `wp()`
		// bootstrap (see WP::send_headers() in class-wp.php) - wp-login.php
		// never calls wp() at all, and wp-admin uses its own bootstrap, so
		// neither ever reaches that hook. An earlier version of this plugin
		// only hooked `send_headers` and, as a result, never actually applied
		// these headers to wp-login.php or wp-admin despite the doc comment
		// below claiming they already had their own equivalents - core only
		// sends X-Frame-Options there (via `login_init`/`admin_init`), not
		// X-Content-Type-Options/Referrer-Policy/Permissions-Policy. Hooking
		// all three entry points, calling the same idempotent method, closes
		// that gap; the existing X-Frame-Options dedup check still prevents
		// a duplicate on top of core's own frame-options header.
		add_action( 'send_headers', array( __CLASS__, 'send_security_headers' ) );
		add_action( 'login_init', array( __CLASS__, 'send_security_headers' ) );
		add_action( 'admin_init', array( __CLASS__, 'send_security_headers' ) );
	}

	/**
	 * Sends baseline security headers on every response that reaches one of
	 * the three hooks registered in init() above (front-end, wp-login.php,
	 * wp-admin). Safe to call more than once per request (e.g. if a future
	 * WordPress version changes hook timing) - `headers_sent()` and the
	 * X-Frame-Options dedup check make repeated calls a no-op after the
	 * first successful send.
	 *
	 * @return void
	 */
	public static function send_security_headers() {
		if ( headers_sent() ) {
			return;
		}

		header( 'X-Content-Type-Options: nosniff', true );
		header( 'Referrer-Policy: strict-origin-when-cross-origin', true );
		header( 'Permissions-Policy: ' . self::PERMISSIONS_POLICY, true );

		if ( ! self::has_sent_x_frame_options_header() ) {
			header( 'X-Frame-Options: SAMEORIGIN', true );
		}
	}

	/**
	 * Returns whether an X-Frame-Options header has already been sent for
	 * this response (e.g. by WordPress core's own
	 * `send_frame_options_header()` on wp-login.php / wp-admin), so we never
	 * send a conflicting duplicate.
	 *
	 * @return bool
	 */
	private static function has_sent_x_frame_options_header() {
		if ( ! function_exists( 'headers_list' ) ) {
			return false;
		}

		foreach ( headers_list() as $sent_header ) {
			if ( 0 === stripos( $sent_header, 'X-Frame-Options:' ) ) {
				return true;
			}
		}

		return false;
	}
}

AVF_Security_Headers::init();
