<?php
/**
 * Public event detail routing.
 *
 * Maps URLs like /veranstaltungen/<slug>/ to one fixed internal page such as
 * /anlassdetail/ and exposes the resolved slug via a query var.
 *
 * @package AVF_Events_Integration
 */

defined( 'ABSPATH' ) || exit;

class AVF_Event_Detail_Router {

	/**
	 * Query var carrying the event slug.
	 */
	const QUERY_VAR = 'avf_event_slug';

	/**
	 * Singleton instance.
	 *
	 * @var AVF_Event_Detail_Router|null
	 */
	private static $instance = null;

	/**
	 * Returns the singleton instance.
	 *
	 * @return AVF_Event_Detail_Router
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers hooks.
	 */
	private function __construct() {
		add_action( 'init', array( __CLASS__, 'register_rewrite' ) );
		add_filter( 'query_vars', array( $this, 'register_query_var' ) );
		add_filter( 'redirect_canonical', array( $this, 'preserve_detail_urls' ), 10, 2 );
		add_filter( 'get_canonical_url', array( $this, 'filter_canonical_url' ), 10, 2 );
		add_filter( 'body_class', array( $this, 'add_detail_body_class' ) );
	}

	/**
	 * Registers the rewrite rule.
	 *
	 * @return void
	 */
	public static function register_rewrite() {
		$base_path   = trim( AVF_Events_View_Helpers::get_events_page_path(), '/' );
		$detail_path = trim( AVF_Events_View_Helpers::get_detail_page_path(), '/' );

		if ( '' === $base_path || '' === $detail_path ) {
			return;
		}

		$base_regex = str_replace( '/', '\/', preg_quote( $base_path, '/' ) );

		add_rewrite_rule(
			'^' . $base_regex . '/([^/]+)/?$',
			'index.php?pagename=' . $detail_path . '&' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
	}

	/**
	 * Registers the public query var.
	 *
	 * @param array $query_vars Existing vars.
	 * @return array
	 */
	public function register_query_var( $query_vars ) {
		$query_vars[] = self::QUERY_VAR;

		return $query_vars;
	}

	/**
	 * Prevents canonical redirects from discarding the event slug.
	 *
	 * @param string|false $redirect_url Candidate redirect URL.
	 * @param string       $requested_url Original request URL.
	 * @return string|false
	 */
	public function preserve_detail_urls( $redirect_url, $requested_url ) {
		if ( get_query_var( self::QUERY_VAR ) ) {
			return false;
		}

		return $redirect_url;
	}

	/**
	 * Adds the global Zirkel background class on public event detail pages.
	 *
	 * @param array $classes Existing body classes.
	 * @return array
	 */
	public function add_detail_body_class( $classes ) {
		$slug = get_query_var( self::QUERY_VAR );

		if ( ! $slug ) {
			return $classes;
		}

		$detail_path = trim( AVF_Events_View_Helpers::get_detail_page_path(), '/' );
		$post        = get_queried_object();

		if ( ! $post instanceof WP_Post || $detail_path !== $post->post_name ) {
			return $classes;
		}

		if ( ! in_array( 'avf-has-zirkel-bg', $classes, true ) ) {
			$classes[] = 'avf-has-zirkel-bg';
		}

		return $classes;
	}

	/**
	 * Replaces the internal page canonical with the public detail URL.
	 *
	 * @param string|false $canonical_url Current canonical URL.
	 * @param WP_Post      $post Current queried post.
	 * @return string|false
	 */
	public function filter_canonical_url( $canonical_url, $post ) {
		$slug = get_query_var( self::QUERY_VAR );

		if ( ! $slug || ! $post instanceof WP_Post ) {
			return $canonical_url;
		}

		$detail_path = trim( AVF_Events_View_Helpers::get_detail_page_path(), '/' );

		if ( $detail_path !== $post->post_name ) {
			return $canonical_url;
		}

		return AVF_Events_View_Helpers::get_detail_url_from_slug( $slug );
	}
}
