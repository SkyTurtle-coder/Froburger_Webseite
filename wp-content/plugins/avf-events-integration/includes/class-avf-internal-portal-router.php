<?php
/**
 * Stable WordPress portal route for the internal Django area.
 *
 * @package AVF_Events_Integration
 */

defined( 'ABSPATH' ) || exit;

class AVF_Internal_Portal_Router {

	const QUERY_VAR = 'avf_internal_portal';
	const ROUTE_PATH = '/intern/';
	const ALT_ROUTE_PATH = '/intern-portal/';
	const STATUS_ACTION = 'avf_internal_endpoint_check';

	/**
	 * Singleton instance.
	 *
	 * @var AVF_Internal_Portal_Router|null
	 */
	private static $instance = null;

	/**
	 * Returns the singleton instance.
	 *
	 * @return AVF_Internal_Portal_Router
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
		add_action( 'init', array( __CLASS__, 'register_public_query_var' ) );
		add_action( 'parse_request', array( $this, 'maybe_handle_portal_request' ), 0 );
		add_filter( 'query_vars', array( $this, 'register_query_var' ) );
		add_filter( 'allowed_redirect_hosts', array( $this, 'allow_internal_hosts' ) );
		add_action( 'template_redirect', array( $this, 'maybe_handle_portal_route' ) );
	}

	/**
	 * Registers the stable /intern/ route.
	 *
	 * @return void
	 */
	public static function register_rewrite() {
		add_rewrite_rule( '^intern/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
		add_rewrite_rule( '^intern-portal/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	/**
	 * Registers the query var directly on the global WP object.
	 *
	 * Some environments ignore the custom query var during parse_request()
	 * unless it is added explicitly before the main request is resolved.
	 *
	 * @return void
	 */
	public static function register_public_query_var() {
		global $wp;

		if ( $wp instanceof WP ) {
			$wp->add_query_var( self::QUERY_VAR );
		}
	}

	/**
	 * Returns the local portal URL.
	 *
	 * @param string $path Optional internal Django path.
	 * @return string
	 */
	public static function get_portal_url( $path = '/' ) {
		$url = home_url( self::ROUTE_PATH );
		$path = AVF_Internal_Endpoint_Resolver::instance()->sanitize_internal_path( $path );

		if ( '' !== $path && '/' !== $path ) {
			$url = add_query_arg( 'path', $path, $url );
		}

		return $url;
	}

	/**
	 * Adds the route query var.
	 *
	 * @param array $query_vars Existing vars.
	 * @return array
	 */
	public function register_query_var( $query_vars ) {
		$query_vars[] = self::QUERY_VAR;

		return $query_vars;
	}

	/**
	 * Whitelists the internal hosts for safe redirects.
	 *
	 * @param string[] $hosts Allowed hosts.
	 * @return string[]
	 */
	public function allow_internal_hosts( $hosts ) {
		$resolver = AVF_Internal_Endpoint_Resolver::instance();
		$candidates = array(
			wp_parse_url( $resolver->get_primary_url(), PHP_URL_HOST ),
			wp_parse_url( $resolver->get_fallback_url(), PHP_URL_HOST ),
		);

		foreach ( $candidates as $host ) {
			if ( is_string( $host ) && '' !== $host && ! in_array( $host, $hosts, true ) ) {
				$hosts[] = $host;
			}
		}

		return $hosts;
	}

	/**
	 * Redirects /intern/ to the healthy internal host or renders a 503 page.
	 *
	 * @return void
	 */
	public function maybe_handle_portal_route() {
		if ( ! $this->is_portal_request() ) {
			return;
		}

		$this->handle_portal_request();
	}

	/**
	 * Handles the portal route during request parsing as a fallback for hosts
	 * that do not reliably carry the rewrite result into template_redirect.
	 *
	 * @return void
	 */
	public function maybe_handle_portal_request() {
		if ( ! $this->is_portal_request() ) {
			return;
		}

		$this->handle_portal_request();
	}

	/**
	 * Performs the actual redirect or renders the branded 503 page.
	 *
	 * @return void
	 */
	private function handle_portal_request() {
		$resolver = AVF_Internal_Endpoint_Resolver::instance();
		$target_path = '/';
		if ( isset( $_GET['path'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$target_path = $resolver->sanitize_internal_path( wp_unslash( $_GET['path'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( '' === $target_path ) {
				$target_path = '/';
			}
		}

		$target = $resolver->build_url( $target_path );
		if ( is_wp_error( $target ) ) {
			$this->render_unavailable_page();
		}

		wp_safe_redirect( $target, 302, 'AVF Internal Portal' );
		exit;
	}

	/**
	 * Returns true when the current request targets the local portal route.
	 *
	 * @return bool
	 */
	private function is_portal_request() {
		if ( get_query_var( self::QUERY_VAR ) ) {
			return true;
		}

		if ( isset( $_GET[ self::QUERY_VAR ] ) && '1' === (string) wp_unslash( $_GET[ self::QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		if ( '' === $request_uri ) {
			return false;
		}

		$request_path = wp_parse_url( $request_uri, PHP_URL_PATH );
		if ( ! is_string( $request_path ) ) {
			return false;
		}

		$portal_paths = array(
			wp_parse_url( self::ROUTE_PATH, PHP_URL_PATH ),
			wp_parse_url( self::ALT_ROUTE_PATH, PHP_URL_PATH ),
		);

		foreach ( $portal_paths as $portal_path ) {
			if ( is_string( $portal_path ) && untrailingslashit( $request_path ) === untrailingslashit( $portal_path ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Renders a branded 503 page when both hosts are unavailable.
	 *
	 * @return void
	 */
	private function render_unavailable_page() {
		status_header( 503 );
		header( 'Retry-After: 60' );
		nocache_headers();

		$title = __( 'Interner Bereich vorübergehend nicht erreichbar', 'avf-events-integration' );
		$retry = esc_url( home_url( self::ROUTE_PATH ) );

		echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . esc_html( $title ) . '</title>';
		echo '<style>body{margin:0;font-family:Georgia,serif;background:#f5f0e7;color:#1f2f22}.wrap{max-width:720px;margin:12vh auto;padding:32px}.card{background:#fff;border:1px solid rgba(31,47,34,.12);box-shadow:0 18px 36px rgba(15,23,17,.08);padding:32px}.eyebrow{margin:0 0 12px;color:#b46124;font-size:.82rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase}h1{margin:0 0 16px;font-size:clamp(2rem,5vw,3.5rem);line-height:.98}.copy{margin:0 0 24px;font-size:1rem;line-height:1.7;color:#5d695f}.button{display:inline-block;padding:12px 18px;background:#183f1e;color:#fff;text-decoration:none}</style>';
		echo '</head><body><main class="wrap"><section class="card"><p class="eyebrow">AV Froburger</p><h1>' . esc_html( $title ) . '</h1><p class="copy">' . esc_html__( 'Der interne Bereich ist momentan nicht erreichbar. Bitte versuche es in kurzer Zeit erneut.', 'avf-events-integration' ) . '</p><p><a class="button" href="' . $retry . '">' . esc_html__( 'Erneut versuchen', 'avf-events-integration' ) . '</a></p></section></main></body></html>';
		exit;
	}
}
