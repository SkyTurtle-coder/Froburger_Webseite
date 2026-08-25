<?php
/**
 * Server-side proxy for public event signup submissions.
 *
 * @package AVF_Events_Integration
 */

defined( 'ABSPATH' ) || exit;

class AVF_Event_Signup_Handler {

	const ACTION = 'avf_event_signup';
	const NONCE_ACTION = 'avf_event_signup_submit';
	const NOTICE_QUERY_ARG = 'avf_event_notice';
	const NOTICE_TTL = 300;

	/**
	 * Singleton instance.
	 *
	 * @var AVF_Event_Signup_Handler|null
	 */
	private static $instance = null;

	/**
	 * Returns the singleton instance.
	 *
	 * @return AVF_Event_Signup_Handler
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers POST handlers.
	 */
	private function __construct() {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Handles the public signup POST and redirects back to the detail page.
	 *
	 * @return void
	 */
	public function handle() {
		if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? (string) $_SERVER['REQUEST_METHOD'] : '' ) ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		check_admin_referer( self::NONCE_ACTION, '_avf_event_signup_nonce' );

		$slug         = isset( $_POST['event_slug'] ) ? sanitize_title( wp_unslash( $_POST['event_slug'] ) ) : '';
		$redirect_url = $this->get_redirect_url();

		if ( '' === $slug ) {
			$this->redirect_with_notice(
				$redirect_url,
				array(
					'type'    => 'error',
					'message' => __( 'Die Anmeldung konnte nicht verarbeitet werden.', 'avf-events-integration' ),
				)
			);
		}

		$client = new AVF_Events_API_Client();
		$event  = $client->get_event_detail( $slug );

		if ( is_wp_error( $event ) ) {
			$this->redirect_with_notice(
				$redirect_url,
				array(
					'type'    => 'error',
					'message' => $this->map_event_error_message( $event ),
				)
			);
		}

		$payload = $this->build_payload( $event );
		if ( is_wp_error( $payload ) ) {
			$this->redirect_with_notice(
				$redirect_url,
				array(
					'type'    => 'error',
					'message' => $payload->get_error_message(),
					'values'  => $this->extract_form_state( $event ),
				)
			);
		}

		$response = $client->submit_event_signup( $slug, $payload );

		if ( is_wp_error( $response ) ) {
			$this->redirect_with_notice(
				$redirect_url,
				array(
					'type'         => 'error',
					'message'      => $this->map_signup_error_message( $response ),
					'debug_status' => $this->format_signup_debug_status( $response ),
					'values'       => $this->extract_form_state( $event ),
				)
			);
		}

		AVF_Events_API_Client::clear_cache();

		$this->redirect_with_notice(
			$redirect_url,
			array(
				'type'    => 'success',
				'message' => __( 'Deine Anmeldung wurde erfolgreich gespeichert.', 'avf-events-integration' ),
			)
		);
	}

	/**
	 * Reads and deletes a flash notice for the current request.
	 *
	 * @return array|null
	 */
	public static function consume_notice() {
		if ( empty( $_GET[ self::NOTICE_QUERY_ARG ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flash token.
			return null;
		}

		$token = sanitize_key( wp_unslash( $_GET[ self::NOTICE_QUERY_ARG ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( '' === $token ) {
			return null;
		}

		$key    = self::notice_transient_key( $token );
		$notice = get_transient( $key );
		delete_transient( $key );

		return is_array( $notice ) ? $notice : null;
	}

	/**
	 * Returns the final redirect URL.
	 *
	 * @return string
	 */
	private function get_redirect_url() {
		$submitted_url = isset( $_POST['_avf_event_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['_avf_event_redirect'] ) ) : '';

		if ( '' !== $submitted_url ) {
			$home_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
			$url_host  = wp_parse_url( $submitted_url, PHP_URL_HOST );

			if ( $home_host && $url_host && strtolower( $home_host ) === strtolower( $url_host ) ) {
				return $submitted_url;
			}
		}

		$referer = wp_get_referer();
		if ( $referer ) {
			return $referer;
		}

		return home_url( AVF_Events_View_Helpers::get_events_page_path() );
	}

	/**
	 * Builds the signup payload expected by Django.
	 *
	 * @param array $event Normalized event detail payload.
	 * @return array|WP_Error
	 */
	private function build_payload( array $event ) {
		$website   = isset( $_POST['website'] ) ? trim( (string) wp_unslash( $_POST['website'] ) ) : '';
		$vulgo_raw = isset( $_POST['vulgo'] ) ? wp_unslash( $_POST['vulgo'] ) : '';
		$vulgo     = trim( preg_replace( '/\s+/', ' ', sanitize_text_field( $vulgo_raw ) ) );

		if ( '' !== $website ) {
			return new WP_Error( 'invalid_request', __( 'Die Anmeldung konnte nicht verarbeitet werden.', 'avf-events-integration' ) );
		}

		if ( '' === $vulgo ) {
			return new WP_Error( 'missing_vulgo', __( 'Bitte gib ein Vulgo an.', 'avf-events-integration' ) );
		}

		$attending = null;
		if ( isset( $_POST['attending'] ) ) {
			$attending = $this->parse_boolean( wp_unslash( $_POST['attending'] ) );
		}

		if ( null === $attending ) {
			return new WP_Error( 'missing_attending', __( 'Bitte gib an, ob du anwesend bist.', 'avf-events-integration' ) );
		}

		$submitted_values = array();
		if ( isset( $_POST['values'] ) && is_array( $_POST['values'] ) ) {
			$submitted_values = wp_unslash( $_POST['values'] );
		}

		$columns      = $this->index_columns( $event );
		$unknown_keys = array_diff( array_keys( $submitted_values ), array_keys( $columns ) );

		if ( ! empty( $unknown_keys ) ) {
			return new WP_Error( 'unknown_fields', __( 'Die Zusatzfelder sind ungültig.', 'avf-events-integration' ) );
		}

		$values = array();
		foreach ( $event['signup_columns'] as $column ) {
			$key       = $column['key'];
			$raw_value = isset( $submitted_values[ $key ] ) ? $submitted_values[ $key ] : null;

			switch ( $column['type'] ) {
				case 'checkbox':
					$value = (bool) $this->parse_boolean( $raw_value );
					if ( $column['required'] && ! $value ) {
						return new WP_Error( 'required_field_missing', sprintf( __( '"%s" ist ein Pflichtfeld.', 'avf-events-integration' ), $column['label'] ) );
					}
					$values[ $key ] = $value;
					break;

				case 'textarea':
					$value = trim( sanitize_textarea_field( is_scalar( $raw_value ) ? (string) $raw_value : '' ) );
					if ( $column['required'] && '' === $value ) {
						return new WP_Error( 'required_field_missing', sprintf( __( '"%s" ist ein Pflichtfeld.', 'avf-events-integration' ), $column['label'] ) );
					}
					$values[ $key ] = $value;
					break;

				case 'select':
					$value = trim( sanitize_text_field( is_scalar( $raw_value ) ? (string) $raw_value : '' ) );
					if ( $column['required'] && '' === $value ) {
						return new WP_Error( 'required_field_missing', sprintf( __( '"%s" ist ein Pflichtfeld.', 'avf-events-integration' ), $column['label'] ) );
					}
					if ( '' !== $value && ! in_array( $value, $column['options'], true ) ) {
						return new WP_Error( 'invalid_choice', sprintf( __( 'Für "%s" wurde eine ungültige Auswahl übermittelt.', 'avf-events-integration' ), $column['label'] ) );
					}
					$values[ $key ] = $value;
					break;

				default:
					$value = trim( sanitize_text_field( is_scalar( $raw_value ) ? (string) $raw_value : '' ) );
					if ( $column['required'] && '' === $value ) {
						return new WP_Error( 'required_field_missing', sprintf( __( '"%s" ist ein Pflichtfeld.', 'avf-events-integration' ), $column['label'] ) );
					}
					$values[ $key ] = $value;
					break;
			}
		}

		return array(
			'vulgo'     => $vulgo,
			'attending' => $attending,
			'website'   => '',
			'values'    => $values,
		);
	}

	/**
	 * Extracts the form state to be shown again after a redirect.
	 *
	 * @param array $event Normalized event detail payload.
	 * @return array
	 */
	private function extract_form_state( array $event ) {
		$state = array(
			'vulgo'     => isset( $_POST['vulgo'] ) ? sanitize_text_field( wp_unslash( $_POST['vulgo'] ) ) : '',
			'attending' => isset( $_POST['attending'] ) ? sanitize_text_field( wp_unslash( $_POST['attending'] ) ) : '',
			'values'    => array(),
		);

		$submitted_values = array();
		if ( isset( $_POST['values'] ) && is_array( $_POST['values'] ) ) {
			$submitted_values = wp_unslash( $_POST['values'] );
		}

		foreach ( $event['signup_columns'] as $column ) {
			$key       = $column['key'];
			$raw_value = isset( $submitted_values[ $key ] ) ? $submitted_values[ $key ] : null;

			if ( 'checkbox' === $column['type'] ) {
				$state['values'][ $key ] = (bool) $this->parse_boolean( $raw_value );
				continue;
			}

			$state['values'][ $key ] = is_scalar( $raw_value ) ? (string) $raw_value : '';
		}

		return $state;
	}

	/**
	 * Maps generic detail fetch errors to visitor-facing messages.
	 *
	 * @param WP_Error $error Error from the API client.
	 * @return string
	 */
	private function map_event_error_message( WP_Error $error ) {
		$data      = $error->get_error_data();
		$http_code = ( is_array( $data ) && isset( $data['http_code'] ) ) ? (int) $data['http_code'] : 0;

		if ( 404 === $http_code ) {
			return __( 'Dieser Anlass wurde nicht gefunden.', 'avf-events-integration' );
		}

		return __( 'Die Anlassinformationen sind momentan nicht verfügbar.', 'avf-events-integration' );
	}

	/**
	 * Maps Django signup errors to visitor-facing messages.
	 *
	 * @param WP_Error $error Error from the API client.
	 * @return string
	 */
	private function map_signup_error_message( WP_Error $error ) {
		$data     = $error->get_error_data();
		$api_code = ( is_array( $data ) && ! empty( $data['api_code'] ) ) ? (string) $data['api_code'] : '';

		switch ( $api_code ) {
			case 'event_not_found':
				return __( 'Dieser Anlass wurde nicht gefunden.', 'avf-events-integration' );
			case 'signup_disabled':
				return __( 'Für diesen Anlass sind keine Anmeldungen möglich.', 'avf-events-integration' );
			case 'signup_closed':
				return __( 'Der Anmeldeschluss ist bereits abgelaufen.', 'avf-events-integration' );
			case 'duplicate_signup':
				return __( 'Für diesen Anlass besteht bereits eine Anmeldung mit diesem Vulgo.', 'avf-events-integration' );
			case 'missing_vulgo':
				return __( 'Bitte gib ein Vulgo an.', 'avf-events-integration' );
			case 'missing_attending':
				return __( 'Bitte gib an, ob du anwesend bist.', 'avf-events-integration' );
			case 'required_field_missing':
			case 'invalid_choice':
			case 'unknown_fields':
			case 'value_too_long':
			case 'invalid_values':
				return $error->get_error_message();
			case 'rate_limited':
				return __( 'Bitte warte kurz, bevor du das Formular erneut absendest.', 'avf-events-integration' );
			case 'signup_unavailable':
				return __( 'Die Anmeldung ist momentan technisch nicht verfügbar. Bitte versuche es in wenigen Minuten erneut.', 'avf-events-integration' );
			case 'invalid_authentication':
				return __( 'Die Anmeldung konnte momentan nicht verarbeitet werden.', 'avf-events-integration' );
			default:
				return __( 'Die Anmeldung konnte momentan nicht verarbeitet werden.', 'avf-events-integration' );
		}
	}

	/**
	 * Returns a compact diagnostic signup status for administrators.
	 *
	 * @param WP_Error $error Error from the API client.
	 * @return string
	 */
	private function format_signup_debug_status( WP_Error $error ) {
		$data  = $error->get_error_data();
		$parts = array();

		if ( is_array( $data ) ) {
			if ( ! empty( $data['http_code'] ) ) {
				$parts[] = 'HTTP ' . (int) $data['http_code'];
			}

			if ( ! empty( $data['api_code'] ) ) {
				$parts[] = 'API ' . sanitize_key( $data['api_code'] );
			}

			if ( ! empty( $data['error_code'] ) ) {
				$parts[] = 'WP ' . sanitize_key( $data['error_code'] );
			}
		}

		if ( empty( $parts ) ) {
			$parts[] = 'WP ' . sanitize_key( $error->get_error_code() );
		}

		return implode( ' | ', $parts );
	}

	/**
	 * Returns a keyed column index.
	 *
	 * @param array $event Normalized event detail payload.
	 * @return array
	 */
	private function index_columns( array $event ) {
		$index = array();

		foreach ( $event['signup_columns'] as $column ) {
			$index[ $column['key'] ] = $column;
		}

		return $index;
	}

	/**
	 * Parses one boolean-like form value.
	 *
	 * @param mixed $value Raw value.
	 * @return bool|null
	 */
	private function parse_boolean( $value ) {
		if ( true === $value || false === $value ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			$value = strtolower( trim( $value ) );

			if ( in_array( $value, array( '1', 'true', 'yes', 'ja', 'on' ), true ) ) {
				return true;
			}

			if ( in_array( $value, array( '0', 'false', 'no', 'nein', 'off' ), true ) ) {
				return false;
			}
		}

		if ( is_int( $value ) ) {
			return (bool) $value;
		}

		return null;
	}

	/**
	 * Stores a flash notice and redirects.
	 *
	 * @param string $redirect_url Redirect target.
	 * @param array  $notice       Flash payload.
	 * @return void
	 */
	private function redirect_with_notice( $redirect_url, array $notice ) {
		$token = wp_generate_password( 12, false, false );

		set_transient( self::notice_transient_key( $token ), $notice, self::NOTICE_TTL );

		$redirect_url = add_query_arg( self::NOTICE_QUERY_ARG, $token, $redirect_url );

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Builds the transient key for a flash notice token.
	 *
	 * @param string $token Flash token.
	 * @return string
	 */
	private static function notice_transient_key( $token ) {
		return 'avf_event_notice_' . sanitize_key( $token );
	}
}
