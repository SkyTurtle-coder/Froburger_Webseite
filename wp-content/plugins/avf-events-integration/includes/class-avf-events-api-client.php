<?php
/**
 * Fetches, validates, normalizes and caches events from the Django public API.
 *
 * @package AVF_Events_Integration
 */

defined( 'ABSPATH' ) || exit;

class AVF_Events_API_Client {

	const CACHE_KEYS_OPTION = 'avf_events_cache_keys';
	const FALLBACK_MAX_AGE = DAY_IN_SECONDS;
	const V1_LIST_MAX_LIMIT = 60;
	const SIGNUP_TIMESTAMP_HEADER = 'X-AVF-Timestamp';
	const SIGNUP_SIGNATURE_HEADER = 'X-AVF-Signature';

	/**
	 * Fields required on every list item returned by Django.
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

	/**
	 * Returns up to $limit normalized upcoming events from the legacy endpoint.
	 *
	 * @param int $limit Maximum number of events to return.
	 * @return array|WP_Error
	 */
	public function get_upcoming_events( $limit ) {
		$limit        = max( 1, min( 12, (int) $limit ) );
		$cache_key    = $this->build_cache_key( 'legacy_upcoming', $limit );
		$fallback_key = $cache_key . '_fallback';

		$fresh = get_transient( $cache_key );
		if ( false !== $fresh && is_array( $fresh ) ) {
			return $fresh;
		}

		$events = $this->fetch_from_api( '/api/public/events/upcoming/', $limit );

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

	/**
	 * Returns normalized upcoming events from the v1 API.
	 *
	 * @param int $limit Maximum number of events to return.
	 * @return array|WP_Error
	 */
	public function get_upcoming_events_v1( $limit ) {
		return $this->get_v1_list( 'upcoming', $limit, true );
	}

	/**
	 * Returns normalized past events from the v1 API.
	 *
	 * @param int $limit Maximum number of events to return.
	 * @return array|WP_Error
	 */
	public function get_past_events( $limit ) {
		return $this->get_v1_list( 'past', $limit, false );
	}

	/**
	 * Returns one normalized detail payload for the given event slug.
	 *
	 * @param string $slug Event slug.
	 * @return array|WP_Error
	 */
	public function get_event_detail( $slug ) {
		$slug = sanitize_title( (string) $slug );
		if ( '' === $slug ) {
			return new WP_Error( 'avf_events_invalid_slug', 'Invalid event slug.' );
		}

		$cache_key    = $this->build_v1_cache_key( 'detail', $slug, 0 );
		$fallback_key = $cache_key . '_fallback';

		$fresh = get_transient( $cache_key );
		if ( false !== $fresh && is_array( $fresh ) ) {
			return $fresh;
		}

		$data = $this->request_json( 'GET', '/api/v1/public/events/' . rawurlencode( $slug ) . '/', 8 );

		if ( is_wp_error( $data ) ) {
			$fallback = get_transient( $fallback_key );
			if ( false !== $fallback && is_array( $fallback ) ) {
				return $fallback;
			}

			return $data;
		}

		$event = $this->normalize_event_detail( $data );
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
	 * Proxies a public signup POST to Django without automatic replay.
	 *
	 * @param string $slug    Event slug.
	 * @param array  $payload Signup payload.
	 * @return array|WP_Error
	 */
	public function submit_event_signup( $slug, array $payload ) {
		$slug = sanitize_title( (string) $slug );
		if ( '' === $slug ) {
			return new WP_Error( 'avf_events_invalid_slug', 'Invalid event slug.' );
		}

		if ( isset( $payload['values'] ) && is_array( $payload['values'] ) && empty( $payload['values'] ) ) {
			$payload['values'] = new stdClass();
		}

		$body = wp_json_encode( $payload );

		$headers = array(
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json',
		);

		$secret = $this->get_signup_secret();
		if ( '' !== $secret ) {
			// HMAC over method/slug/timestamp/body-hash, verified and replay-checked
			// on the Django side.
			$timestamp                                 = (string) time();
			$headers[ self::SIGNUP_TIMESTAMP_HEADER ]  = $timestamp;
			$headers[ self::SIGNUP_SIGNATURE_HEADER ]  = $this->sign_signup_request( $slug, $timestamp, $body, $secret );
		}

		$response = avf_internal_api_request(
			'POST',
			'/api/v1/public/events/' . rawurlencode( $slug ) . '/signup/',
			array(
				'timeout' => 8,
				'headers' => $headers,
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log(
				sprintf(
					'Signup request failed for slug "%1$s": wp_error=%2$s; message=%3$s; secret_configured=%4$s',
					$slug,
					$response->get_error_code(),
					$response->get_error_message(),
					'' !== $secret ? 'yes' : 'no'
				)
			);
			return new WP_Error(
				'avf_events_signup_request_failed',
				'The signup request failed.',
				array(
					'error_code' => $response->get_error_code(),
					'http_code'  => 0,
					'api_code'   => '',
				)
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( (string) $body, true );

		if ( $code >= 200 && $code < 300 ) {
			if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
				return new WP_Error( 'avf_events_invalid_signup_response', 'The signup API returned invalid JSON.', array( 'http_code' => $code ) );
			}

			return $data;
		}

		$message  = 'The signup API returned an unexpected response.';
		$api_code = '';

		if ( is_array( $data ) ) {
			if ( isset( $data['message'] ) && is_scalar( $data['message'] ) ) {
				$message = (string) $data['message'];
			}

			if ( isset( $data['code'] ) && is_scalar( $data['code'] ) ) {
				$api_code = (string) $data['code'];
			}
		}

		$this->log(
			sprintf(
				'Signup API rejected slug "%1$s": http=%2$d; api_code=%3$s; secret_configured=%4$s',
				$slug,
				$code,
				'' !== $api_code ? $api_code : 'none',
				'' !== $secret ? 'yes' : 'no'
			)
		);

		return new WP_Error(
			'avf_events_signup_failed',
			$message,
			array(
				'error_code' => 'avf_events_signup_failed',
				'http_code' => $code,
				'api_code'  => $api_code,
				'response'  => is_array( $data ) ? $data : null,
			)
		);
	}

	/**
	 * Returns the absolute URL to Django's calendar feed.
	 *
	 * @return string
	 */
	public function get_calendar_ics_url() {
		$configured_url = trim( (string) get_option( 'avf_calendar_public_feed_url', '' ) );
		if ( '' !== $configured_url ) {
			return esc_url_raw( $configured_url );
		}

		$url = AVF_Internal_Endpoint_Resolver::instance()->build_url( '/calendar/public/events.ics' );
		if ( ! is_wp_error( $url ) ) {
			return $url;
		}

		$url = AVF_Internal_Endpoint_Resolver::instance()->build_url( '/api/v1/public/events/calendar.ics' );

		return is_wp_error( $url ) ? '' : $url;
	}

	/**
	 * Returns the absolute URL to one single-event ICS resource.
	 *
	 * @param string $slug Event slug.
	 * @return string
	 */
	public function get_event_calendar_ics_url( $slug ) {
		$slug = sanitize_title( (string) $slug );
		if ( '' === $slug ) {
			return '';
		}

		$url = AVF_Internal_Endpoint_Resolver::instance()->build_url( '/calendar/public/events/' . rawurlencode( $slug ) . '.ics' );

		return is_wp_error( $url ) ? '' : $url;
	}

	/**
	 * Deletes all plugin-managed transients.
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
	 * Signs a signup request the same way Django verifies it (SEC-005):
	 * HMAC-SHA256 over "POST\n{slug}\n{timestamp}\n{sha256(body)}".
	 *
	 * @param string $slug      Event slug (must match the URL segment).
	 * @param string $timestamp Unix timestamp as a string.
	 * @param string $body      Exact raw request body being sent.
	 * @param string $secret    Shared secret.
	 * @return string Hex-encoded HMAC-SHA256 signature.
	 */
	private function sign_signup_request( $slug, $timestamp, $body, $secret ) {
		$body_hash = hash( 'sha256', (string) $body );
		$message   = "POST\n" . $slug . "\n" . $timestamp . "\n" . $body_hash;

		return hash_hmac( 'sha256', $message, $secret );
	}

	/**
	 * Returns the shared secret used for server-to-server signup calls.
	 *
	 * @return string
	 */
	private function get_signup_secret() {
		$secret = '';

		if ( defined( 'AVF_EVENTS_SIGNUP_SHARED_SECRET' ) && is_string( AVF_EVENTS_SIGNUP_SHARED_SECRET ) ) {
			$secret = trim( AVF_EVENTS_SIGNUP_SHARED_SECRET );
		}

		$secret = apply_filters( 'avf_events_signup_shared_secret', $secret );

		return is_string( $secret ) ? trim( $secret ) : '';
	}

	/**
	 * Returns the shared cache TTL.
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
	 * Builds a cache key namespace.
	 *
	 * @param string $resource Resource namespace.
	 * @param int    $limit    Requested limit.
	 * @return string
	 */
	private function build_cache_key( $resource, $limit ) {
		return 'avf_events_' . md5( $resource . '|' . $limit );
	}

	/**
	 * Builds a v1 cache key.
	 *
	 * @param string $resource Resource namespace.
	 * @param string $identifier Request identifier.
	 * @param int    $limit Requested limit.
	 * @return string
	 */
	private function build_v1_cache_key( $resource, $identifier, $limit ) {
		return 'avf_events_v1_' . $resource . '_' . md5( $identifier . '|' . $limit );
	}

	/**
	 * Stores a transient and tracks its key.
	 *
	 * @param string $key   Transient key.
	 * @param array  $value Value to cache.
	 * @param int    $ttl   Cache lifetime in seconds.
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
	 * Shared implementation for v1 list resources.
	 *
	 * @param string $resource              Resource name.
	 * @param int    $limit                 Requested limit.
	 * @param bool   $allow_legacy_fallback Whether 404 may fall back to legacy.
	 * @return array|WP_Error
	 */
	private function get_v1_list( $resource, $limit, $allow_legacy_fallback ) {
		$limit        = max( 1, min( self::V1_LIST_MAX_LIMIT, (int) $limit ) );
		$cache_key    = $this->build_v1_cache_key( $resource, $resource, $limit );
		$fallback_key = $cache_key . '_fallback';

		$fresh = get_transient( $cache_key );
		if ( false !== $fresh && is_array( $fresh ) ) {
			return $fresh;
		}

		$result = $this->fetch_list( '/api/v1/public/events/' . $resource . '/', $limit );

		if ( is_wp_error( $result ) ) {
			$error_data = $result->get_error_data();
			$http_code  = ( is_array( $error_data ) && isset( $error_data['http_code'] ) ) ? (int) $error_data['http_code'] : 0;

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
	 * Wraps the legacy upcoming endpoint into the v1 result shape.
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
			'total'  => count( $events ),
			'source' => 'legacy',
		);
	}

	/**
	 * Requests and parses JSON from one API path.
	 *
	 * @param string $method  HTTP method.
	 * @param string $path    Relative API path.
	 * @param int    $timeout Timeout in seconds.
	 * @return array|WP_Error
	 */
	private function request_json( $method, $path, $timeout ) {
		$response = avf_internal_api_request(
			$method,
			$path,
			array(
				'timeout' => $timeout,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log( 'Request failed: ' . $response->get_error_code() );
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
	 * Fetches events from the legacy list endpoint.
	 *
	 * @param string $path  Endpoint path.
	 * @param int    $limit Requested limit.
	 * @return array|WP_Error
	 */
	private function fetch_from_api( $path, $limit ) {
		$data = $this->request_json( 'GET', add_query_arg( array( 'limit' => $limit ), $path ), 8 );
		if ( is_wp_error( $data ) ) {
			return new WP_Error( $data->get_error_code(), $data->get_error_message(), $data->get_error_data() );
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
	 * Fetches a v1 list resource.
	 *
	 * @param string $path  Endpoint path.
	 * @param int    $limit Requested limit.
	 * @return array|WP_Error
	 */
	private function fetch_list( $path, $limit ) {
		$data = $this->request_json( 'GET', add_query_arg( array( 'limit' => $limit ), $path ), 8 );
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
	 * Normalizes one event list item.
	 *
	 * @param mixed $item Raw API item.
	 * @return array|null
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

		$resolver    = AVF_Internal_Endpoint_Resolver::instance();
		$detail_path = '';
		if ( isset( $item['detail_path'] ) && is_scalar( $item['detail_path'] ) ) {
			$detail_path = $resolver->sanitize_internal_path( (string) $item['detail_path'] );
		}

		$source_path = '';
		if ( isset( $item['source_url'] ) && is_scalar( $item['source_url'] ) ) {
			$source_path = $resolver->extract_known_internal_path( (string) $item['source_url'] );
		}

		$source_url = '';
		if ( '' !== $source_path ) {
			$source_url = $resolver->normalize_known_internal_url( $source_path );
		} elseif ( isset( $item['source_url'] ) && is_scalar( $item['source_url'] ) ) {
			$source_url = esc_url_raw( (string) $item['source_url'] );
		}

		return array(
			'id'                => (string) $item['id'],
			'title'             => (string) $item['title'],
			'slug'              => sanitize_title( (string) $item['slug'] ),
			'short_description' => (string) $item['short_description'],
			'status'            => ( isset( $item['status'] ) && is_scalar( $item['status'] ) ) ? sanitize_key( (string) $item['status'] ) : '',
			'status_label'      => ( isset( $item['status_label'] ) && is_scalar( $item['status_label'] ) ) ? sanitize_text_field( (string) $item['status_label'] ) : '',
			'start_at'          => (string) $item['start_at'],
			'end_at'            => (string) $item['end_at'],
			'timezone_name'     => (string) $item['timezone_name'],
			'location_name'     => (string) $item['location_name'],
			'detail_path'       => $detail_path,
			'source_path'       => $source_path,
			'source_url'        => $source_url,
		);
	}

	/**
	 * Normalizes the richer detail payload.
	 *
	 * @param mixed $item Raw API payload.
	 * @return array|null
	 */
	private function normalize_event_detail( $item ) {
		$event = $this->normalize_event( $item );
		if ( null === $event || ! is_array( $item ) ) {
			return null;
		}

		if ( ! isset( $item['description'], $item['date'], $item['start_time'], $item['location'], $item['signup_enabled'], $item['signup_open'], $item['signup_columns'], $item['signups'] ) ) {
			return null;
		}

		if ( ! is_array( $item['signup_columns'] ) || ! is_array( $item['signups'] ) ) {
			return null;
		}

		$signup_columns = array();
		foreach ( $item['signup_columns'] as $column ) {
			$normalized_column = $this->normalize_signup_column( $column );
			if ( null !== $normalized_column ) {
				$signup_columns[] = $normalized_column;
			}
		}

		$signups = array();
		foreach ( $item['signups'] as $signup ) {
			$normalized_signup = $this->normalize_public_signup( $signup );
			if ( null !== $normalized_signup ) {
				$signups[] = $normalized_signup;
			}
		}

		$resolver = AVF_Internal_Endpoint_Resolver::instance();
		$image_path = '';
		if ( isset( $item['image_url'] ) && is_scalar( $item['image_url'] ) ) {
			$image_path = $resolver->extract_known_internal_path( (string) $item['image_url'] );
		}

		$event['description']      = is_scalar( $item['description'] ) ? (string) $item['description'] : '';
		$event['date']             = is_scalar( $item['date'] ) ? (string) $item['date'] : '';
		$event['start_time']       = is_scalar( $item['start_time'] ) ? (string) $item['start_time'] : '';
		$event['end_time']         = ( isset( $item['end_time'] ) && is_scalar( $item['end_time'] ) ) ? (string) $item['end_time'] : '';
		$event['location']         = is_scalar( $item['location'] ) ? (string) $item['location'] : '';
		$event['image_path']       = $image_path;
		$event['image_url']        = '' !== $image_path ? $resolver->normalize_known_internal_url( $image_path ) : ( ( isset( $item['image_url'] ) && is_scalar( $item['image_url'] ) ) ? esc_url_raw( (string) $item['image_url'] ) : '' );
		$event['signup_enabled']   = (bool) $item['signup_enabled'];
		$event['signup_open']      = (bool) $item['signup_open'];
		$event['signup_deadline']  = ( isset( $item['signup_deadline'] ) && is_scalar( $item['signup_deadline'] ) ) ? (string) $item['signup_deadline'] : '';
		$event['signup_columns']   = $signup_columns;
		$event['signups']          = $signups;

		return $event;
	}

	/**
	 * Normalizes one signup column definition from Django.
	 *
	 * @param mixed $column Raw column payload.
	 * @return array|null
	 */
	private function normalize_signup_column( $column ) {
		if ( ! is_array( $column ) ) {
			return null;
		}

		$required = array( 'key', 'label', 'type', 'required', 'public', 'sort_order' );
		foreach ( $required as $field ) {
			if ( ! array_key_exists( $field, $column ) ) {
				return null;
			}
		}

		$type = is_scalar( $column['type'] ) ? (string) $column['type'] : '';
		if ( ! in_array( $type, array( 'text', 'textarea', 'select', 'checkbox' ), true ) ) {
			return null;
		}

		$options = array();
		if ( isset( $column['options'] ) && is_array( $column['options'] ) ) {
			foreach ( $column['options'] as $option ) {
				if ( is_scalar( $option ) && '' !== trim( (string) $option ) ) {
					$options[] = (string) $option;
				}
			}
		}

		return array(
			'key'         => sanitize_key( (string) $column['key'] ),
			'label'       => is_scalar( $column['label'] ) ? (string) $column['label'] : '',
			'type'        => $type,
			'required'    => (bool) $column['required'],
			'public'      => (bool) $column['public'],
			'sort_order'  => (int) $column['sort_order'],
			'options'     => $options,
			'placeholder' => ( isset( $column['placeholder'] ) && is_scalar( $column['placeholder'] ) ) ? (string) $column['placeholder'] : '',
		);
	}

	/**
	 * Normalizes one public signup row from Django.
	 *
	 * @param mixed $signup Raw signup payload.
	 * @return array|null
	 */
	private function normalize_public_signup( $signup ) {
		if ( ! is_array( $signup ) || ! isset( $signup['vulgo'], $signup['attending'], $signup['values'] ) || ! is_array( $signup['values'] ) ) {
			return null;
		}

		$values = array();
		foreach ( $signup['values'] as $key => $value ) {
			if ( ! is_string( $key ) ) {
				continue;
			}

			if ( is_bool( $value ) ) {
				$values[ sanitize_key( $key ) ] = $value;
				continue;
			}

			if ( null === $value ) {
				$values[ sanitize_key( $key ) ] = '';
				continue;
			}

			if ( is_scalar( $value ) ) {
				$values[ sanitize_key( $key ) ] = (string) $value;
			}
		}

		return array(
			'vulgo'     => is_scalar( $signup['vulgo'] ) ? (string) $signup['vulgo'] : '',
			'attending' => (bool) $signup['attending'],
			'values'    => $values,
		);
	}

	/**
	 * Logs a debug message without leaking sensitive data.
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
