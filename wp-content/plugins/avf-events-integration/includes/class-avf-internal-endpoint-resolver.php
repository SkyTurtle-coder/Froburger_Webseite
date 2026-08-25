<?php
/**
 * Central internal Django endpoint resolver with health checks and failover.
 *
 * @package AVF_Events_Integration
 */

defined( 'ABSPATH' ) || exit;

class AVF_Internal_Endpoint_Resolver {

	const PRIMARY_OPTION = 'avf_internal_primary_url';
	const FALLBACK_OPTION = 'avf_internal_fallback_url';
	const STATUS_OPTION = 'avf_internal_endpoint_status';
	const SUCCESS_TTL = 60;
	const PRIMARY_FAILURE_TTL = 30;
	const FALLBACK_FAILURE_TTL = 15;
	const HEALTH_PATH = '/healthz/';
	const USER_AGENT_SUFFIX = 'internal-endpoint-resolver';

	/**
	 * Singleton instance.
	 *
	 * @var AVF_Internal_Endpoint_Resolver|null
	 */
	private static $instance = null;

	/**
	 * Returns the singleton instance.
	 *
	 * @return AVF_Internal_Endpoint_Resolver
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Hidden constructor.
	 */
	private function __construct() {
	}

	/**
	 * Adds default options without overwriting existing values.
	 *
	 * @return void
	 */
	public static function ensure_default_options() {
		if ( false === get_option( self::PRIMARY_OPTION ) ) {
			add_option( self::PRIMARY_OPTION, self::default_primary_url() );
		}

		if ( false === get_option( self::FALLBACK_OPTION ) ) {
			add_option( self::FALLBACK_OPTION, self::default_fallback_url() );
		}
	}

	/**
	 * Returns the configured primary URL.
	 *
	 * @return string
	 */
	public function get_primary_url() {
		return $this->get_configured_base_url(
			'AVF_INTERNAL_PRIMARY_URL',
			self::PRIMARY_OPTION,
			self::default_primary_url()
		);
	}

	/**
	 * Returns the configured fallback URL.
	 *
	 * @return string
	 */
	public function get_fallback_url() {
		return $this->get_configured_base_url(
			'AVF_INTERNAL_FALLBACK_URL',
			self::FALLBACK_OPTION,
			self::default_fallback_url()
		);
	}

	/**
	 * Returns the currently active base URL or a controlled error.
	 *
	 * @param bool $allow_checks Whether synchronous health checks may run.
	 * @return string|WP_Error
	 */
	public function get_active_url( $allow_checks = true ) {
		$primary  = $this->get_primary_url();
		$fallback = $this->get_fallback_url();

		$primary_state = $this->get_cached_state( $primary );
		if ( $this->is_cached_healthy( $primary_state ) ) {
			return $primary;
		}

		if ( $allow_checks && ! $this->is_cached_failed( $primary_state ) ) {
			$check = $this->check_endpoint( $primary );
			if ( ! is_wp_error( $check ) ) {
				return $primary;
			}
		}

		$fallback_state = $this->get_cached_state( $fallback );
		if ( $this->is_cached_healthy( $fallback_state ) ) {
			return $fallback;
		}

		if ( $allow_checks && ! $this->is_cached_failed( $fallback_state ) ) {
			$check = $this->check_endpoint( $fallback );
			if ( ! is_wp_error( $check ) ) {
				return $fallback;
			}
		}

		return new WP_Error(
			'avf_internal_endpoint_unavailable',
			'No healthy internal endpoint is currently available.'
		);
	}

	/**
	 * Builds one absolute internal URL from a relative path.
	 *
	 * @param string      $path           Relative internal path.
	 * @param string|null $preferred_base Optional preferred base URL.
	 * @return string|WP_Error
	 */
	public function build_url( $path, $preferred_base = null ) {
		$base = null;
		if ( is_string( $preferred_base ) && '' !== trim( $preferred_base ) ) {
			$base = $this->sanitize_base_url( $preferred_base );
		}

		if ( ! $base ) {
			$base = $this->get_active_url();
			if ( is_wp_error( $base ) ) {
				return $base;
			}
		}

		$relative_path = $this->sanitize_internal_path( $path );
		if ( '' === $relative_path ) {
			$relative_path = '/';
		}

		return $base . $relative_path;
	}

	/**
	 * Performs a short health check for one base URL.
	 *
	 * @param string $base_url Internal base URL.
	 * @return array|WP_Error
	 */
	public function check_endpoint( $base_url ) {
		$base_url = $this->sanitize_base_url( $base_url );
		if ( '' === $base_url ) {
			return new WP_Error( 'avf_internal_invalid_base', 'Invalid internal base URL.' );
		}

		$response = $this->dispatch_request(
			'GET',
			$base_url . self::HEALTH_PATH,
			array(
				'timeout'     => 1.8,
				'redirection' => 0,
				'headers'     => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->mark_failure( $base_url, $response->get_error_code() );
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 === $code ) {
			$this->mark_success( $base_url );
			return array(
				'base_url' => $base_url,
				'code'     => 200,
			);
		}

		$this->mark_failure( $base_url, 'http_' . $code, $code );
		return new WP_Error(
			'avf_internal_health_bad_status',
			'Unexpected health-check status.',
			array( 'http_code' => $code )
		);
	}

	/**
	 * Deletes all health transients.
	 *
	 * @return void
	 */
	public function invalidate_health_cache() {
		foreach ( array( $this->get_primary_url(), $this->get_fallback_url() ) as $base_url ) {
			delete_transient( $this->health_cache_key( $base_url ) );
		}
	}

	/**
	 * Marks one endpoint failure.
	 *
	 * @param string $base_url Base URL.
	 * @param string $reason   Failure reason.
	 * @param int    $status   Optional HTTP status.
	 * @return void
	 */
	public function mark_failure( $base_url, $reason, $status = 0 ) {
		$state = array(
			'healthy'    => false,
			'checked_at' => time(),
			'expires_at' => time() + $this->failure_ttl_for( $base_url ),
			'reason'     => sanitize_key( (string) $reason ),
			'status'     => (int) $status,
		);

		set_transient( $this->health_cache_key( $base_url ), $state, $this->failure_ttl_for( $base_url ) );
		$this->store_status( $base_url, $state );
		$this->log_event( $base_url, 'GET', 'failure', $status, sanitize_key( (string) $reason ) );
	}

	/**
	 * Marks one endpoint success.
	 *
	 * @param string $base_url Base URL.
	 * @return void
	 */
	public function mark_success( $base_url ) {
		$state = array(
			'healthy'    => true,
			'checked_at' => time(),
			'expires_at' => time() + self::SUCCESS_TTL,
			'reason'     => 'ok',
			'status'     => 200,
		);

		set_transient( $this->health_cache_key( $base_url ), $state, self::SUCCESS_TTL );
		$this->store_status( $base_url, $state );
		$this->log_event( $base_url, 'GET', 'success', 200, 'ok' );
	}

	/**
	 * Performs one central internal API request.
	 *
	 * @param string $method HTTP method.
	 * @param string $path   Relative internal path.
	 * @param array  $args   Request arguments.
	 * @return array|WP_Error
	 */
	public function request( $method, $path, array $args = array() ) {
		$method = strtoupper( trim( (string) $method ) );
		if ( '' === $method ) {
			$method = 'GET';
		}

		$path     = $this->sanitize_internal_path( $path );
		$is_safe  = in_array( $method, array( 'GET', 'HEAD' ), true );
		$base_url = $this->get_active_url();

		if ( is_wp_error( $base_url ) ) {
			return $base_url;
		}

		$request_args = $this->prepare_request_args( $method, $args );
		$response     = $this->dispatch_request( $method, $base_url . $path, $request_args );

		if ( is_wp_error( $response ) ) {
			$this->mark_failure( $base_url, $response->get_error_code() );

			if ( $is_safe && $this->is_primary_url( $base_url ) ) {
				$fallback = $this->get_fallback_candidate();
				if ( ! is_wp_error( $fallback ) ) {
					$retry = $this->dispatch_request( $method, $fallback . $path, $request_args );
					if ( ! is_wp_error( $retry ) ) {
						$this->mark_success( $fallback );
						$retry['avf_internal_base_url'] = $fallback;
						return $retry;
					}

					$this->mark_failure( $fallback, $retry->get_error_code() );
				}
			}

			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $this->should_mark_as_failure( $code ) ) {
			$this->mark_failure( $base_url, 'http_' . $code, $code );

			if ( $is_safe && $this->is_primary_url( $base_url ) && $this->is_retryable_status( $code ) ) {
				$fallback = $this->get_fallback_candidate();
				if ( ! is_wp_error( $fallback ) ) {
					$retry = $this->dispatch_request( $method, $fallback . $path, $request_args );
					if ( ! is_wp_error( $retry ) ) {
						$retry_code = (int) wp_remote_retrieve_response_code( $retry );
						if ( ! $this->should_mark_as_failure( $retry_code ) || ! $this->is_retryable_status( $retry_code ) ) {
							$this->mark_success( $fallback );
							$retry['avf_internal_base_url'] = $fallback;
							return $retry;
						}
						$this->mark_failure( $fallback, 'http_' . $retry_code, $retry_code );
						return $retry;
					}

					$this->mark_failure( $fallback, $retry->get_error_code() );
				}
			}
		} else {
			$this->mark_success( $base_url );
		}

		$response['avf_internal_base_url'] = $base_url;

		return $response;
	}

	/**
	 * Returns diagnostic data without running network checks.
	 *
	 * @return array
	 */
	public function get_diagnostics() {
		$primary  = $this->get_primary_url();
		$fallback = $this->get_fallback_url();
		$active   = $this->get_active_url( false );

		return array(
			'primary_url'     => $primary,
			'fallback_url'    => $fallback,
			'active_url'      => is_wp_error( $active ) ? '' : $active,
			'primary_status'  => $this->get_status_for( $primary ),
			'fallback_status' => $this->get_status_for( $fallback ),
		);
	}

	/**
	 * Returns a known-internal path or an empty string.
	 *
	 * @param string $url Candidate URL.
	 * @return string
	 */
	public function extract_known_internal_path( $url ) {
		if ( ! is_string( $url ) || '' === trim( $url ) ) {
			return '';
		}

		$url = trim( $url );
		if ( 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' ) ) {
			return $this->sanitize_internal_path( $url );
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '';
		}

		$host = strtolower( (string) $parts['host'] );
		if ( ! in_array( $host, $this->allowed_internal_hosts(), true ) ) {
			return '';
		}

		$path = isset( $parts['path'] ) ? (string) $parts['path'] : '/';
		if ( isset( $parts['query'] ) && '' !== $parts['query'] ) {
			$path .= '?' . $parts['query'];
		}

		return $this->sanitize_internal_path( $path );
	}

	/**
	 * Resolves a known-internal URL to the active host.
	 *
	 * @param string      $url            Candidate URL.
	 * @param string|null $preferred_base Optional preferred base.
	 * @return string
	 */
	public function normalize_known_internal_url( $url, $preferred_base = null ) {
		$path = $this->extract_known_internal_path( $url );
		if ( '' === $path ) {
			return esc_url_raw( (string) $url );
		}

		$rebuilt = $this->build_url( $path, $preferred_base );

		return is_wp_error( $rebuilt ) ? '' : $rebuilt;
	}

	/**
	 * Sanitizes one user-provided internal path.
	 *
	 * @param string $path Candidate path.
	 * @return string
	 */
	public function sanitize_internal_path( $path ) {
		$path = is_string( $path ) ? trim( $path ) : '';
		if ( '' === $path ) {
			return '/';
		}

		$path = rawurldecode( $path );
		$path = str_replace( array( "\r", "\n", '\\' ), '', $path );
		if ( 0 === strpos( $path, 'javascript:' ) || 0 === strpos( $path, 'data:' ) ) {
			return '';
		}

		if ( false !== strpos( $path, '://' ) || 0 === strpos( $path, '//' ) ) {
			return '';
		}

		if ( '/' !== substr( $path, 0, 1 ) ) {
			$path = '/' . ltrim( $path, '/' );
		}

		return $path;
	}

	/**
	 * Returns the default primary URL.
	 *
	 * @return string
	 */
	public static function default_primary_url() {
		return 'https://intern.avfroburger.ch';
	}

	/**
	 * Returns the default fallback URL.
	 *
	 * @return string
	 */
	public static function default_fallback_url() {
		return 'https://intern-avfroburger.ch';
	}

	/**
	 * Reads one configured base URL from constant, option and fallback.
	 *
	 * @param string $constant_name Constant name.
	 * @param string $option_name   Option name.
	 * @param string $default       Default value.
	 * @return string
	 */
	private function get_configured_base_url( $constant_name, $option_name, $default ) {
		$candidate = '';

		if ( defined( $constant_name ) && is_string( constant( $constant_name ) ) ) {
			$candidate = constant( $constant_name );
		}

		if ( '' === trim( $candidate ) ) {
			$option = get_option( $option_name, $default );
			$candidate = is_string( $option ) ? $option : $default;
		}

		$sanitized = $this->sanitize_base_url( $candidate );

		return '' !== $sanitized ? $sanitized : $default;
	}

	/**
	 * Sanitizes one base URL.
	 *
	 * @param string $url Candidate URL.
	 * @return string
	 */
	private function sanitize_base_url( $url ) {
		$url = is_string( $url ) ? trim( $url ) : '';
		if ( '' === $url ) {
			return '';
		}

		$url = esc_url_raw( $url );
		if ( '' === $url ) {
			return '';
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		if ( 'https' !== strtolower( (string) $parts['scheme'] ) ) {
			return '';
		}

		return untrailingslashit( $url );
	}

	/**
	 * Returns the transient key for one base URL.
	 *
	 * @param string $base_url Base URL.
	 * @return string
	 */
	private function health_cache_key( $base_url ) {
		return 'avf_internal_health_' . md5( strtolower( (string) $base_url ) );
	}

	/**
	 * Returns one cached health state.
	 *
	 * @param string $base_url Base URL.
	 * @return array|null
	 */
	private function get_cached_state( $base_url ) {
		$state = get_transient( $this->health_cache_key( $base_url ) );

		return is_array( $state ) ? $state : null;
	}

	/**
	 * Returns true when the cached state is healthy.
	 *
	 * @param array|null $state Cached state.
	 * @return bool
	 */
	private function is_cached_healthy( $state ) {
		return is_array( $state ) && ! empty( $state['healthy'] );
	}

	/**
	 * Returns true when the cached state is unhealthy.
	 *
	 * @param array|null $state Cached state.
	 * @return bool
	 */
	private function is_cached_failed( $state ) {
		return is_array( $state ) && array_key_exists( 'healthy', $state ) && empty( $state['healthy'] );
	}

	/**
	 * Returns the failure TTL for one base URL.
	 *
	 * @param string $base_url Base URL.
	 * @return int
	 */
	private function failure_ttl_for( $base_url ) {
		return $this->is_primary_url( $base_url ) ? self::PRIMARY_FAILURE_TTL : self::FALLBACK_FAILURE_TTL;
	}

	/**
	 * Returns true when the given base is the primary URL.
	 *
	 * @param string $base_url Base URL.
	 * @return bool
	 */
	private function is_primary_url( $base_url ) {
		return strtolower( $base_url ) === strtolower( $this->get_primary_url() );
	}

	/**
	 * Returns a validated fallback base URL if available.
	 *
	 * @return string|WP_Error
	 */
	private function get_fallback_candidate() {
		$fallback = $this->get_fallback_url();
		$state    = $this->get_cached_state( $fallback );

		if ( $this->is_cached_failed( $state ) ) {
			return new WP_Error( 'avf_internal_fallback_unavailable', 'Fallback endpoint is currently unavailable.' );
		}

		if ( ! $this->is_cached_healthy( $state ) ) {
			$check = $this->check_endpoint( $fallback );
			if ( is_wp_error( $check ) ) {
				return $check;
			}
		}

		return $fallback;
	}

	/**
	 * Prepares request arguments for one central internal request.
	 *
	 * @param string $method HTTP method.
	 * @param array  $args   Raw request arguments.
	 * @return array
	 */
	private function prepare_request_args( $method, array $args ) {
		$defaults = array(
			'timeout'     => in_array( $method, array( 'GET', 'HEAD' ), true ) ? 8 : 8,
			'redirection' => 0,
			'headers'     => array(),
		);

		$args = wp_parse_args( $args, $defaults );
		if ( empty( $args['headers']['Accept'] ) ) {
			$args['headers']['Accept'] = 'application/json';
		}

		if ( empty( $args['user-agent'] ) ) {
			$args['user-agent'] = 'AVF-WordPress/' . AVF_EVENTS_INTEGRATION_VERSION . '; ' . self::USER_AGENT_SUFFIX;
		}

		return $args;
	}

	/**
	 * Dispatches one HTTP request with controlled redirect handling.
	 *
	 * @param string $method HTTP method.
	 * @param string $url    Absolute URL.
	 * @param array  $args   Request arguments.
	 * @return array|WP_Error
	 */
	private function dispatch_request( $method, $url, array $args ) {
		$response = wp_remote_request(
			$url,
			array_merge(
				$args,
				array(
					'method'     => $method,
					'sslverify'  => true,
					'redirection' => 0,
				)
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_event( $url, $method, 'transport', 0, sanitize_key( $response->get_error_code() ) );
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( in_array( $code, array( 301, 302 ), true ) ) {
			$location = wp_remote_retrieve_header( $response, 'location' );
			if ( ! is_string( $location ) || '' === trim( $location ) ) {
				$this->log_event( $url, $method, 'redirect_missing_location', $code, 'redirect_missing_location' );
				return new WP_Error( 'avf_internal_redirect_missing_location', 'Redirect response without location header.' );
			}

			$location = esc_url_raw( trim( $location ) );
			if ( ! $this->redirect_target_is_allowed( $url, $location ) ) {
				$this->log_event( $url, $method, 'redirect_disallowed', $code, 'redirect_disallowed' );
				return new WP_Error( 'avf_internal_redirect_disallowed', 'Redirect target is not allowed.' );
			}

			$response = wp_remote_request(
				$location,
				array_merge(
					$args,
					array(
						'method'      => $method,
						'sslverify'   => true,
						'redirection' => 0,
					)
				)
			);

			if ( is_wp_error( $response ) ) {
				$this->log_event( $location, $method, 'transport', 0, sanitize_key( $response->get_error_code() ) );
				return $response;
			}
		}

		return $response;
	}

	/**
	 * Returns true when a redirect target stays on an allowed internal host.
	 *
	 * @param string $source_url   Source URL.
	 * @param string $location_url Redirect target.
	 * @return bool
	 */
	private function redirect_target_is_allowed( $source_url, $location_url ) {
		$source_parts   = wp_parse_url( $source_url );
		$location_parts = wp_parse_url( $location_url );

		if ( ! is_array( $source_parts ) || ! is_array( $location_parts ) || empty( $source_parts['host'] ) || empty( $location_parts['host'] ) ) {
			return false;
		}

		$source_host   = strtolower( (string) $source_parts['host'] );
		$location_host = strtolower( (string) $location_parts['host'] );

		return $source_host === $location_host && in_array( $location_host, $this->allowed_internal_hosts(), true );
	}

	/**
	 * Returns true when the HTTP status should mark the host as failed.
	 *
	 * @param int $code HTTP status.
	 * @return bool
	 */
	private function should_mark_as_failure( $code ) {
		return $code >= 500 || in_array( $code, array( 301, 302 ), true );
	}

	/**
	 * Returns true when the HTTP status is eligible for GET/HEAD failover.
	 *
	 * @param int $code HTTP status.
	 * @return bool
	 */
	private function is_retryable_status( $code ) {
		return in_array( $code, array( 502, 503, 504 ), true );
	}

	/**
	 * Stores one status snapshot for diagnostics.
	 *
	 * @param string $base_url Base URL.
	 * @param array  $state    Cached state.
	 * @return void
	 */
	private function store_status( $base_url, array $state ) {
		$status = get_option( self::STATUS_OPTION, array() );
		if ( ! is_array( $status ) ) {
			$status = array();
		}

		$status[ strtolower( $base_url ) ] = array(
			'host'       => wp_parse_url( $base_url, PHP_URL_HOST ),
			'healthy'    => ! empty( $state['healthy'] ),
			'checked_at' => isset( $state['checked_at'] ) ? (int) $state['checked_at'] : 0,
			'expires_at' => isset( $state['expires_at'] ) ? (int) $state['expires_at'] : 0,
			'reason'     => isset( $state['reason'] ) ? (string) $state['reason'] : '',
			'status'     => isset( $state['status'] ) ? (int) $state['status'] : 0,
		);

		update_option( self::STATUS_OPTION, $status, false );
	}

	/**
	 * Returns stored status data for one base URL.
	 *
	 * @param string $base_url Base URL.
	 * @return array
	 */
	private function get_status_for( $base_url ) {
		$status = get_option( self::STATUS_OPTION, array() );
		$key    = strtolower( $base_url );

		return ( is_array( $status ) && isset( $status[ $key ] ) && is_array( $status[ $key ] ) ) ? $status[ $key ] : array();
	}

	/**
	 * Returns the allowed internal hosts.
	 *
	 * @return string[]
	 */
	private function allowed_internal_hosts() {
		$hosts = array();

		foreach ( array( $this->get_primary_url(), $this->get_fallback_url() ) as $base_url ) {
			$host = wp_parse_url( $base_url, PHP_URL_HOST );
			if ( is_string( $host ) && '' !== $host ) {
				$hosts[] = strtolower( $host );
			}
		}

		return array_values( array_unique( $hosts ) );
	}

	/**
	 * Logs one endpoint event.
	 *
	 * @param string $base_url Base URL or request URL.
	 * @param string $method   HTTP method.
	 * @param string $class    Event class.
	 * @param int    $status   HTTP status.
	 * @param string $reason   Failure or result reason.
	 * @return void
	 */
	private function log_event( $base_url, $method, $class, $status, $reason ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$host = wp_parse_url( $base_url, PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			$host = 'unknown';
		}

		error_log(
			sprintf(
				'[AVF Internal Endpoint] host=%s method=%s class=%s status=%d reason=%s time=%s',
				$host,
				strtoupper( (string) $method ),
				sanitize_key( (string) $class ),
				(int) $status,
				sanitize_key( (string) $reason ),
				gmdate( 'c' )
			)
		);
	}
}
