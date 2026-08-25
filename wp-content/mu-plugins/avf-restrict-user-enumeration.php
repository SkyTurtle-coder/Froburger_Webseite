<?php
/**
 * Plugin Name: AVF Restrict User Enumeration
 * Description: Blocks anonymous WordPress user enumeration via the REST API, ?author=N archive requests, and the core users sitemap, without disabling any of these features for authenticated users.
 */

defined( 'ABSPATH' ) || exit;

final class AVF_Restrict_User_Enumeration {

	/**
	 * REST routes (as returned by WP_REST_Request::get_route()) that expose
	 * user data and must not be reachable anonymously.
	 *
	 * @var string[]
	 */
	const BLOCKED_REST_ROUTES = array(
		'/wp/v2/users',
		'/wp/v2/users/(?P<id>[\d]+)',
	);

	/**
	 * Boots all user-enumeration protections.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'rest_endpoints', array( __CLASS__, 'restrict_users_rest_endpoints' ) );
		add_action( 'template_redirect', array( __CLASS__, 'block_anonymous_author_archive' ), 0 );
		add_filter( 'wp_sitemaps_add_provider', array( __CLASS__, 'exclude_users_sitemap_provider' ), 10, 2 );
	}

	/**
	 * Wraps the permission callback of the users REST endpoints so anonymous
	 * requests are rejected before the original callback runs. Authenticated
	 * requests fall through to the original callback unchanged, so existing
	 * capability checks (and consumers such as wp-admin or Elementor) keep
	 * working exactly as before.
	 *
	 * @param array<string,mixed> $endpoints Registered REST endpoints, keyed by route.
	 * @return array<string,mixed>
	 */
	public static function restrict_users_rest_endpoints( array $endpoints ) {
		foreach ( self::BLOCKED_REST_ROUTES as $route ) {
			if ( empty( $endpoints[ $route ] ) || ! is_array( $endpoints[ $route ] ) ) {
				continue;
			}

			foreach ( $endpoints[ $route ] as $key => $handler ) {
				if ( ! is_array( $handler ) || empty( $handler['permission_callback'] ) ) {
					continue;
				}

				$endpoints[ $route ][ $key ]['permission_callback'] = self::wrap_permission_callback( $handler['permission_callback'] );
			}
		}

		return $endpoints;
	}

	/**
	 * Returns a permission callback that blocks anonymous requests outright
	 * and otherwise defers to the original permission callback.
	 *
	 * @param callable $original_callback Original REST permission callback.
	 * @return callable
	 */
	private static function wrap_permission_callback( $original_callback ) {
		return static function ( $request ) use ( $original_callback ) {
			if ( ! is_user_logged_in() ) {
				return new WP_Error(
					'avf_rest_user_enumeration_blocked',
					__( 'Anonymous access to user data via the REST API is disabled on this site.', 'avf-restrict-user-enumeration' ),
					array( 'status' => 401 )
				);
			}

			return call_user_func( $original_callback, $request );
		};
	}

	/**
	 * Returns whether the current request is an anonymous author-archive
	 * request (e.g. the classic `?author=1` enumeration vector, or a pretty
	 * `/author/name/` archive URL).
	 *
	 * @return bool
	 */
	public static function should_block_author_archive_request() {
		if ( is_user_logged_in() ) {
			return false;
		}

		return is_author();
	}

	/**
	 * Redirects anonymous author-archive requests to the homepage so
	 * usernames cannot be enumerated via author archives.
	 *
	 * @return void
	 */
	public static function block_anonymous_author_archive() {
		if ( ! self::should_block_author_archive_request() ) {
			return;
		}

		wp_safe_redirect( home_url( '/' ), 302 );
		exit;
	}

	/**
	 * Prevents the core "users" sitemap provider from being registered, so
	 * `wp-sitemap-users-*.xml` is no longer generated or listed.
	 *
	 * @param WP_Sitemaps_Provider|false $provider Sitemap provider instance about to be added.
	 * @param string                     $name     Sitemap provider name.
	 * @return WP_Sitemaps_Provider|false
	 */
	public static function exclude_users_sitemap_provider( $provider, $name ) {
		if ( 'users' === $name ) {
			return false;
		}

		return $provider;
	}
}

AVF_Restrict_User_Enumeration::init();
