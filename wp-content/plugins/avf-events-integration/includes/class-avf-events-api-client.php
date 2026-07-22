<?php
/**
 * Fetches, validates, normalizes and caches events from the Django public API.
 *
 * Two API generations are supported side by side:
 * - Legacy: a single full URL (`avf_events_api_endpoint`), upcoming events only.
 * - v1: a configurable base (`avf_events_api_base`), upcoming/past/detail/calendar.
 *
 * There is exactly one HTTP call site (request_json()) and exactly one cache
 * mechanism (WordPress transients via set_cached()/get_transient()) shared by
 * both generations - see the class docblock sections below for how each
 * public method composes them.
 *
 * @package AVF_Events_Integration
 */

defined( 'ABSPATH' ) || exit;

class AVF_Events_API_Client {

	/**
	 * Option name holding the list of transient keys created by this plugin,
	 * so the cache can be cleared without touching unrelated options.
	 */
	const CACHE_KEYS_OPTION = 'avf_events_cache_keys';

	/**
	 * Maximum lifetime of the fallback cache, used when the API is unreachable.
	 */
	const FALLBACK_MAX_AGE = DAY_IN_SECONDS;

	/**
	 * Upper bound for v1 list requests (upcoming/past "load more"). Higher
	 * than the legacy shortcode's MAX_LIMIT (12) since these lists are meant
	 * to grow via "Mehr anzeigen" clicks; still a hard ceiling against
	 * crafted/abusive requests.
	 */
	const V1_LIST_MAX_LIMIT = 60;

	/**
	 * Fields that must be present and non-empty on every event returned by the API.
	 * Identical for the legacy endpoint and every v1 endpoint (verified against
	 * live responses from all four v1 routes during development - see readme.txt
	 * "API-Vertrag").
	 *
	 * @var string[]
	 */
	private $required_fields = array(
		'id',
		'title',
		'slug',
		'short_description',
		'start_at',
		'end_at',
		'timezone_name',
		'location_name',
	);

	/* -----------------------------------------------------------
	 * Legacy path (unchanged behaviour/contract - used exclusively by the
	 * original [avf_upcoming_events] shortcode, which must keep working
	 * exactly as before).
	 * ----------------------------------------------------------- */

	/**
	 * Returns up to $limit normalized upcoming events, using cache where possible.
	 *
	 * @param int $limit Maximum number of events to return (already validated by caller).
	 * @return array|WP_Error Array of normalized event arrays, or WP_Error when nothing is available.
	 */
	public function get_upcoming_events( $limit ) {
		$endpoint = $this->get_legacy_endpoint();

		if ( '' === $endpoint ) {
			return new WP_Error( 'avf_events_no_endpoint', 'No API endpoint configured.' );
		}

		$limit        = max( 1, min( 12, (int) $limit ) );
		$cache_key    = $this->build_cache_key( $endpoint, $limit );
		$fallback_key = $cache_key . '_fallback';

		$fresh = get_transient( $cache_key );
		if ( false !== $fresh && is_array( $fresh ) ) {
			return $fresh;
		}

		$events = $this->fetch_from_api( $endpoint, $limit );

		if ( is_wp_error( $events ) ) {
			$fallback = get_transient( $fallback_key );
			if ( false !== $fallback && is_array( $fallback ) ) {
				return $fallback;
			}

			return $events;
		}

		$ttl = $this->get_cache_ttl();
		$this->set_cached( $cache_key, $events, $ttl );
		$this->set_cached( $fallback_key, $events, self::FALLBACK_MAX_AGE );

		return $events;
	}

	/* -----------------------------------------------------------
	 * v1 path (new). Upcoming has a controlled transition strategy: try v1
	 * first, fall back to the legacy endpoint only when v1 is not configured
	 * or responds 404 (i.e. genuinely "unsupported/not found", never on other
	 * error types - a 500, a timeout or invalid JSON from v1 must NOT trigger
	 * a silent switch to a different data source, per the project's explicit
	 * transition rules). Past/detail/calendar have no legacy equivalent, so
	 * they simply fail (leading to fallback-cache-or-empty-state) when v1
	 * isn't available.
	 * ----------------------------------------------------------- */

	/**
	 * Returns up to $limit normalized upcoming events from the v1 API, with a
	 * logged (never user-facing) fallback to the legacy endpoint if v1 is not
	 * configured or returns 404.
	 *
	 * @param int $limit Maximum number of events to return.
	 * @return array|WP_Error {events: array[], total: int|null, source: string} or WP_Error.
	 */
	public function get_upcoming_events_v1( $limit ) {
		return $this->get_v1_list( 'upcoming', $limit, true );
	}

	/**
	 * Returns up to $limit normalized past events from the v1 API. No legacy
	 * fallback exists for this resource.
	 *
	 * @param int $limit Maximum number of events to return.
	 * @return array|WP_Error {events: array[], total: int|null, source: string} or WP_Error.
	 */
	public function get_past_events( $limit ) {
		return $this->get_v1_list( 'past', $limit, false );
	}

	/**
	 * Returns a single normalized event by slug from the v1 detail endpoint.
	 *
	 * @param string $slug Event slug.
	 * @return array|WP_Error Normalized event, or WP_Error.
	 */
	public function get_event_detail( $slug ) {
		$slug = sanitize_title( (string) $slug );
		if ( '' === $slug ) {
			return new WP_Error( 'avf_events_invalid_slug', 'Invalid event slug.' );
		}

		$base = $this->get_api_base();
		if ( '' === $base ) {
			return new WP_Error( 'avf_events_no_v1_base', 'No v1 API base configured.' );
		}

		$url          = $this->build_v1_url( $base, $slug );
		$cache_key    = $this->build_v1_cache_key( 'detail', $url, 0 );
		$fallback_key = $cache_key . '_fallback';

		$fresh = get_transient( $cache_key );
		if ( false !== $fresh && is_array( $fresh ) ) {
			return $fresh;
		}

		$data = $this->request_json( $url );

		if ( is_wp_error( $data ) ) {
			$fallback = get_transient( $fallback_key );
			if ( false !== $fallback && is_array( $fallback ) ) {
				return $fallback;
			}

			return $data;
		}

		$event = $this->normalize_event( $data );
		if ( null === $event ) {
			$this->log( 'Invalid event detail response for slug: ' . $slug );
			return new WP_Error( 'avf_events_invalid_detail', 'The event detail response was invalid.' );
		}

		$ttl = $this->get_cache_ttl();
		$this->set_cached( $cache_key, $event, $ttl );
		$this->set_cached( $fallback_key, $event, self::FALLBACK_MAX_AGE );

		return $event;
	}

	/**
	 * Returns the absolute URL to Django's calendar.ics endpoint, or '' if no
	 * v1 base is configured. No HTTP request is made here - WordPress never
	 * generates or parses calendar data itself, it only ever links to this
	 * Django-provided URL (see AVF_Events_Calendar_Actions_Shortcode).
	 *
	 * @return string
	 */
	public function get_calendar_ics_url() {
		$base = $this->get_api_base();
		if ( '' === $base ) {
			return '';
		}

		return $this->build_v1_url( $base, 'calendar' );
	}

	/**
	 * Shared implementation behind get_upcoming_events_v1() and get_past_events():
	 * resolves the v1 base, applies the legacy-fallback transition rules,
	 * caches, and returns a uniform {events, total, source} shape.
	 *
	 * @param string $resource               'upcoming' or 'past'.
	 * @param int    $limit                  Requested limit.
	 * @param bool   $allow_legacy_fallback   Whether a missing/404 v1 endpoint may fall back to the legacy endpoint.
	 * @return array|WP_Error
	 */
	private function get_v1_list( $resource, $limit, $allow_legacy_fallback ) {
		$limit = max( 1, min( self::V1_LIST_MAX_LIMIT, (int) $limit ) );
		$base  = $this->get_api_base();

		if ( '' === $base ) {
			if ( $allow_legacy_fallback ) {
				$this->log( 'No v1 API base configured; falling back to legacy upcoming endpoint.' );
				return $this->as_legacy_fallback_result( $limit );
			}

			return new WP_Error( 'avf_events_no_v1_base', 'No v1 API base configured.' );
		}

		$url          = $this->build_v1_url( $base, $resource );
		$cache_key    = $this->build_v1_cache_key( $resource, $url, $limit );
		$fallback_key = $cache_key . '_fallback';

		$fresh = get_transient( $cache_key );
		if ( false !== $fresh && is_array( $fresh ) ) {
			return $fresh;
		}

		$result = $this->fetch_list( $url, $limit );

		if ( is_wp_error( $result ) ) {
			$error_data = $result->get_error_data();
			$http_code  = ( is_array( $error_data ) && isset( $error_data['http_code'] ) ) ? (int) $error_data['http_code'] : 0;

			// Only a genuine "not found/unsupported" response (404) triggers the
			// legacy fallback - never on other error types (see class docblock).
			if ( $allow_legacy_fallback && 404 === $http_code ) {
				$this->log( 'v1 "' . $resource . '" endpoint returned 404; falling back to legacy upcoming endpoint.' );
				return $this->as_legacy_fallback_result( $limit );
			}

			$fallback = get_transient( $fallback_key );
			if ( false !== $fallback && is_array( $fallback ) ) {
				return $fallback;
			}

			return $result;
		}

		$result['source'] = 'v1';

		$ttl = $this->get_cache_ttl();
		$this->set_cached( $cache_key, $result, $ttl );
		$this->set_cached( $fallback_key, $result, self::FALLBACK_MAX_AGE );

		return $result;
	}

	/**
	 * Wraps the existing, untouched legacy upcoming path (with its own
	 * cache/fallback-cache, reused as-is) into the same {events, total,
	 * source} shape the v1 list methods return, so calling code never has to
	 * care which generation actually answered.
	 *
	 * @param int $limit Requested limit.
	 * @return array|WP_Error
	 */
	private function as_legacy_fallback_result( $limit ) {
		$events = $this->get_upcoming_events( $limit );

		if ( is_wp_error( $events ) ) {
			return $events;
		}

		return array(
			'events' => $events,
			// The legacy endpoint exposes no distinct "total available" signal
			// beyond the count of returned results (see readme.txt "API-Vertrag").
			'total'  => count( $events ),
			'source' => 'legacy',
		);
	}

	/**
	 * Deletes all transients created by this plugin (both legacy and v1).
	 *
	 * @return void
	 */
	public static function clear_cache() {
		$keys = get_option( self::CACHE_KEYS_OPTION, array() );

		if ( is_array( $keys ) ) {
			foreach ( $keys as $key ) {
				delete_transient( $key );
			}
		}

		delete_option( self::CACHE_KEYS_OPTION );
	}

	/**
	 * Reads the configured legacy API endpoint (full URL).
	 *
	 * @return string
	 */
	private function get_legacy_endpoint() {
		$endpoint = get_option( 'avf_events_api_endpoint', '' );

		return is_string( $endpoint ) ? trim( $endpoint ) : '';
	}

	/**
	 * Reads the configured v1 API base URL (e.g. ".../api/v1/public/"),
	 * always returned with a trailing slash, or '' if unconfigured.
	 *
	 * @return string
	 */
	private function get_api_base() {
		$base = get_option( 'avf_events_api_base', '' );
		$base = is_string( $base ) ? trim( $base ) : '';

		if ( '' === $base ) {
			return '';
		}

		return trailingslashit( $base );
	}

	/**
	 * Builds a full v1 resource URL from the configured base.
	 *
	 * @param string $base     Trailing-slashed v1 base URL.
	 * @param string $resource 'upcoming', 'past', 'calendar', or an event slug (detail).
	 * @return string
	 */
	private function build_v1_url( $base, $resource ) {
		if ( 'upcoming' === $resource || 'past' === $resource ) {
			return $base . 'events/' . $resource . '/';
		}

		if ( 'calendar' === $resource ) {
			return $base . 'events/calendar.ics';
		}

		// Anything else is treated as an event slug for the detail endpoint.
		return $base . 'events/' . rawurlencode( $resource ) . '/';
	}

	/**
	 * Reads the configured cache TTL, clamped to the allowed range. Shared by
	 * both API generations - one cache-duration setting for the whole plugin.
	 *
	 * @return int
	 */
	private function get_cache_ttl() {
		$ttl = (int) get_option( 'avf_events_cache_ttl', 300 );

		if ( $ttl < 60 || $ttl > 3600 ) {
			$ttl = 300;
		}

		return $ttl;
	}

	/**
	 * Builds a cache key scoped to the endpoint and limit (legacy path only -
	 * unchanged so existing cached transients and behaviour are unaffected).
	 *
	 * @param string $endpoint API endpoint.
	 * @param int    $limit    Requested limit.
	 * @return string
	 */
	private function build_cache_key( $endpoint, $limit ) {
		return 'avf_events_' . md5( $endpoint . '|' . $limit );
	}

	/**
	 * Builds a cache key scoped to a v1 resource, URL and limit. Namespaced
	 * separately from the legacy cache key so the two generations can never
	 * collide even if pointed at similarly-shaped URLs.
	 *
	 * @param string $resource 'upcoming', 'past' or 'detail'.
	 * @param string $url      Full request URL.
	 * @param int    $limit    Requested limit (0 for single-item requests).
	 * @return string
	 */
	private function build_v1_cache_key( $resource, $url, $limit ) {
		return 'avf_events_v1_' . $resource . '_' . md5( $url . '|' . $limit );
	}

	/**
	 * Stores a transient and records its key so it can be cleared later.
	 *
	 * @param string $key   Transient key.
	 * @param array  $value Value to cache.
	 * @param int    $ttl   Time to live in seconds.
	 * @return void
	 */
	private function set_cached( $key, $value, $ttl ) {
		set_transient( $key, $value, $ttl );

		$keys = get_option( self::CACHE_KEYS_OPTION, array() );
		if ( ! is_array( $keys ) ) {
			$keys = array();
		}

		if ( ! in_array( $key, $keys, true ) ) {
			$keys[] = $key;
			update_option( self::CACHE_KEYS_OPTION, $keys, false );
		}
	}

	/**
	 * The one and only HTTP call site in this class: requests a URL, and
	 * validates transport, HTTP status, body and JSON shape. Returns the
	 * decoded response body (not yet interpreted as a list or single event -
	 * callers do that) or a WP_Error carrying the HTTP status code (when
	 * known) in its error data, e.g. for 404-based fallback decisions.
	 *
	 * @param string $url Full request URL.
	 * @return array|WP_Error
	 */
	private function request_json( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 8,
				'redirection' => 3,
				'headers'     => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log( 'Request failed: ' . $response->get_error_message() );
			return new WP_Error( 'avf_events_request_failed', 'The events request failed.' );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			$this->log( 'Unexpected HTTP status: ' . $code );
			return new WP_Error( 'avf_events_bad_status', 'The events API returned an unexpected status.', array( 'http_code' => $code ) );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( '' === trim( (string) $body ) ) {
			$this->log( 'Empty response body.' );
			return new WP_Error( 'avf_events_empty_body', 'The events API returned an empty response.', array( 'http_code' => $code ) );
		}

		$data = json_decode( $body, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			$this->log( 'Invalid JSON response.' );
			return new WP_Error( 'avf_events_invalid_json', 'The events API returned invalid JSON.', array( 'http_code' => $code ) );
		}

		return $data;
	}

	/**
	 * Calls the legacy endpoint and returns normalized events or a WP_Error.
	 * Used exclusively by get_upcoming_events() (unchanged contract).
	 *
	 * @param string $endpoint API endpoint.
	 * @param int    $limit    Requested limit.
	 * @return array|WP_Error
	 */
	private function fetch_from_api( $endpoint, $limit ) {
		$url = add_query_arg( array( 'limit' => $limit ), $endpoint );

		$data = $this->request_json( $url );
		if ( is_wp_error( $data ) ) {
			return new WP_Error( $data->get_error_code(), $data->get_error_message() );
		}

		if ( ! isset( $data['results'] ) || ! is_array( $data['results'] ) ) {
			$this->log( 'Missing results array in response.' );
			return new WP_Error( 'avf_events_missing_results', 'The events API response is missing results.' );
		}

		$events = array();
		foreach ( $data['results'] as $item ) {
			$normalized = $this->normalize_event( $item );
			if ( null !== $normalized ) {
				$events[] = $normalized;
			}

			if ( count( $events ) >= $limit ) {
				break;
			}
		}

		return $events;
	}

	/**
	 * Calls a v1 list endpoint (upcoming/past) and returns normalized events
	 * plus the API's reported total count.
	 *
	 * @param string $url   Full v1 list endpoint URL (without ?limit=).
	 * @param int    $limit Requested limit.
	 * @return array|WP_Error {events: array[], total: int|null} or WP_Error (http_code preserved for 404 detection).
	 */
	private function fetch_list( $url, $limit ) {
		$url = add_query_arg( array( 'limit' => $limit ), $url );

		$data = $this->request_json( $url );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		if ( ! isset( $data['results'] ) || ! is_array( $data['results'] ) ) {
			$this->log( 'Missing results array in response.' );
			return new WP_Error( 'avf_events_missing_results', 'The events API response is missing results.' );
		}

		$total = null;
		if ( isset( $data['count'] ) && is_numeric( $data['count'] ) ) {
			$total = (int) $data['count'];
		}

		$events = array();
		foreach ( $data['results'] as $item ) {
			$normalized = $this->normalize_event( $item );
			if ( null !== $normalized ) {
				$events[] = $normalized;
			}

			if ( count( $events ) >= $limit ) {
				break;
			}
		}

		return array(
			'events' => $events,
			'total'  => $total,
		);
	}

	/**
	 * Validates and normalizes a single raw event entry from the API. Shared
	 * by every code path (legacy, v1 lists, v1 detail) - the same field
	 * schema applies everywhere (verified live).
	 *
	 * @param mixed $item Raw event data.
	 * @return array|null Normalized event, or null if required fields are missing.
	 */
	private function normalize_event( $item ) {
		if ( ! is_array( $item ) ) {
			return null;
		}

		foreach ( $this->required_fields as $field ) {
			if ( ! isset( $item[ $field ] ) || ! is_scalar( $item[ $field ] ) ) {
				$this->log( 'Skipping event with missing field: ' . $field );
				return null;
			}

			if ( '' === trim( (string) $item[ $field ] ) ) {
				$this->log( 'Skipping event with empty field: ' . $field );
				return null;
			}
		}

		$detail_path = '';
		if ( isset( $item['detail_path'] ) && is_scalar( $item['detail_path'] ) ) {
			$detail_path = (string) $item['detail_path'];
		}

		$source_url = '';
		if ( isset( $item['source_url'] ) && is_scalar( $item['source_url'] ) ) {
			$source_url = (string) $item['source_url'];
		}

		return array(
			'id'                => (string) $item['id'],
			'title'             => (string) $item['title'],
			'slug'              => sanitize_title( (string) $item['slug'] ),
			'short_description' => (string) $item['short_description'],
			'start_at'          => (string) $item['start_at'],
			'end_at'            => (string) $item['end_at'],
			'timezone_name'     => (string) $item['timezone_name'],
			'location_name'     => (string) $item['location_name'],
			'detail_path'       => $detail_path,
			'source_url'        => $source_url,
		);
	}

	/**
	 * Logs a debug message without leaking sensitive data, only when WP_DEBUG is enabled.
	 *
	 * @param string $message Message to log.
	 * @return void
	 */
	private function log( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[AVF Events Integration] ' . $message );
		}
	}
}
