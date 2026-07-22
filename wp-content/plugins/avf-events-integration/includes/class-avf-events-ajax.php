<?php
/**
 * Handles the "Mehr anzeigen" AJAX request for [avf_events_upcoming] and
 * [avf_events_past]. This is the JS-driven upgrade of the same no-JS link
 * AVF_Events_List_Shortcode already renders (progressive enhancement, not a
 * second/parallel pagination mechanism) - it re-fetches the same
 * events_upcoming_v1()/get_past_events() methods and renders the new slice
 * with the exact same card renderers the initial page load used.
 *
 * @package AVF_Events_Integration
 */

defined( 'ABSPATH' ) || exit;

class AVF_Events_Ajax {

	const ACTION = 'avf_events_more';

	/**
	 * Singleton instance.
	 *
	 * @var AVF_Events_Ajax|null
	 */
	private static $instance = null;

	/**
	 * Returns the singleton instance, creating it on first call.
	 *
	 * @return AVF_Events_Ajax
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers the AJAX action for both logged-in and anonymous visitors -
	 * this is public, read-only, cached data, same visibility as the
	 * shortcodes themselves.
	 */
	private function __construct() {
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Handles the AJAX request: validates input, fetches the next batch via
	 * the same API client methods the initial render used, and returns only
	 * the newly-added cards' HTML plus updated pagination state.
	 *
	 * @return void
	 */
	public function handle() {
		check_ajax_referer( self::ACTION, 'nonce' );

		$type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
		if ( ! in_array( $type, array( 'upcoming', 'past' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Ungültiger Typ.', 'avf-events-integration' ) ) );
		}

		$shown = isset( $_POST['shown'] ) ? absint( wp_unslash( $_POST['shown'] ) ) : 0;
		$step  = isset( $_POST['step'] ) ? absint( wp_unslash( $_POST['step'] ) ) : 0;

		$shown = AVF_Events_View_Helpers::sanitize_count( $shown, 0, 1, AVF_Events_API_Client::V1_LIST_MAX_LIMIT );
		$step  = AVF_Events_View_Helpers::sanitize_count( $step, 0, 1, AVF_Events_API_Client::V1_LIST_MAX_LIMIT );

		if ( 0 === $shown || 0 === $step ) {
			wp_send_json_error( array( 'message' => __( 'Ungültige Anfrage.', 'avf-events-integration' ) ) );
		}

		$new_shown = min( AVF_Events_API_Client::V1_LIST_MAX_LIMIT, $shown + $step );

		$client = new AVF_Events_API_Client();
		$result = 'upcoming' === $type
			? $client->get_upcoming_events_v1( $new_shown )
			: $client->get_past_events( $new_shown );

		if ( is_wp_error( $result ) ) {
			// A failed refetch here is a normal, expected outcome (e.g. the
			// API is briefly unreachable) - the visitor already sees the
			// earlier batch, so this just means "no more for now", not an
			// alarming error. No technical detail is included.
			wp_send_json_error( array( 'message' => __( 'Es konnten keine weiteren Veranstaltungen geladen werden.', 'avf-events-integration' ) ) );
		}

		$events = ( isset( $result['events'] ) && is_array( $result['events'] ) ) ? $result['events'] : array();
		$total  = isset( $result['total'] ) ? $result['total'] : null;
		$views  = AVF_Events_View_Helpers::build_views( $events );

		// Only the slice beyond what the browser already rendered.
		$new_views = array_slice( $views, $shown );

		$html = '';
		foreach ( $new_views as $view ) {
			$html .= 'upcoming' === $type
				? AVF_Events_List_Shortcode::render_upcoming_card( $view )
				: AVF_Events_List_Shortcode::render_past_card( $view );
		}

		$has_more = ( null !== $total ) && ( count( $views ) < $total );

		wp_send_json_success(
			array(
				'html'     => $html,
				'shown'    => count( $views ),
				'has_more' => $has_more,
			)
		);
	}
}
