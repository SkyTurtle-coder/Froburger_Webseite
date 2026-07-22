<?php
/**
 * Registers and renders the [avf_upcoming_events] shortcode.
 *
 * @package AVF_Events_Integration
 */

defined( 'ABSPATH' ) || exit;

class AVF_Upcoming_Events_Shortcode {

	const DEFAULT_LIMIT = 3;
	const MIN_LIMIT      = 1;
	const MAX_LIMIT      = 12;

	/**
	 * Singleton instance.
	 *
	 * @var AVF_Upcoming_Events_Shortcode|null
	 */
	private static $instance = null;

	/**
	 * Returns the singleton instance, creating it on first call.
	 *
	 * @return AVF_Upcoming_Events_Shortcode
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers the shortcode.
	 */
	private function __construct() {
		add_shortcode( 'avf_upcoming_events', array( $this, 'render' ) );
	}

	/**
	 * Shortcode callback for [avf_upcoming_events limit="3"].
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts  = shortcode_atts( array( 'limit' => self::DEFAULT_LIMIT ), $atts, 'avf_upcoming_events' );
		$limit = $this->sanitize_limit( $atts['limit'] );

		$client = new AVF_Events_API_Client();
		$events = $client->get_upcoming_events( $limit );

		if ( is_wp_error( $events ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				return $this->render_admin_notice();
			}

			return '';
		}

		if ( empty( $events ) ) {
			return '';
		}

		$views = array();
		foreach ( array_slice( $events, 0, $limit ) as $event ) {
			$view = $this->build_event_view( $event );
			if ( null !== $view ) {
				$views[] = $view;
			}
		}

		if ( empty( $views ) ) {
			return '';
		}

		$this->enqueue_assets();

		return $this->render_html( $views );
	}

	/**
	 * Sanitizes the requested limit, resetting invalid values to the default.
	 *
	 * @param mixed $value Raw shortcode attribute value.
	 * @return int
	 */
	private function sanitize_limit( $value ) {
		if ( ! is_numeric( $value ) ) {
			return self::DEFAULT_LIMIT;
		}

		$value = (int) $value;

		if ( $value < self::MIN_LIMIT || $value > self::MAX_LIMIT ) {
			return self::DEFAULT_LIMIT;
		}

		return $value;
	}

	/**
	 * Builds the presentation-ready view for a single normalized event.
	 *
	 * Skips the event entirely if its start date cannot be parsed.
	 *
	 * @param array $event Normalized event data.
	 * @return array|null
	 */
	private function build_event_view( array $event ) {
		try {
			$start = new DateTimeImmutable( $event['start_at'] );
		} catch ( Exception $e ) {
			$this->log( 'Unparsable start_at for event ' . $event['id'] );
			return null;
		}

		$tz        = wp_timezone();
		$timestamp = $start->getTimestamp();

		$day        = wp_date( 'j', $timestamp, $tz );
		$month_year = wp_date( 'F Y', $timestamp, $tz );
		$date_part  = wp_date( 'd.m.Y', $timestamp, $tz );
		$time_part  = wp_date( 'H:i', $timestamp, $tz );

		if ( false === $day || false === $month_year || false === $date_part || false === $time_part ) {
			$this->log( 'Failed to format date for event ' . $event['id'] );
			return null;
		}

		$meta = sprintf(
			/* translators: 1: date (d.m.Y), 2: time (H:i), 3: location name */
			__( '%1$s · %2$s Uhr · %3$s', 'avf-events-integration' ),
			$date_part,
			$time_part,
			$event['location_name']
		);

		return array(
			'id'                => $event['id'],
			'title'             => $event['title'],
			'slug'              => $event['slug'],
			'short_description' => $event['short_description'],
			'location_name'     => $event['location_name'],
			'detail_path'       => $event['detail_path'],
			'source_url'        => $event['source_url'],
			'day'               => $day,
			'month_year'        => $month_year,
			'meta'              => $meta,
			'datetime_attr'     => $event['start_at'],
		);
	}

	/**
	 * Builds the WordPress-internal detail URL for an event, filterable by developers.
	 *
	 * @param array $view Event view data (includes normalized fields).
	 * @return string
	 */
	private function build_detail_url( array $view ) {
		$page_path = get_option( 'avf_events_page_path', '/anlaesse/' );
		if ( ! is_string( $page_path ) || '' === $page_path ) {
			$page_path = '/anlaesse/';
		}

		$anchor = 'event-' . $view['slug'];
		$url    = home_url( $page_path . '#' . $anchor );

		/**
		 * Filters the generated WordPress detail URL for an upcoming event.
		 *
		 * @param string $url  Generated URL pointing to the WordPress events page anchor.
		 * @param array  $view Normalized event data used to build the URL.
		 */
		return apply_filters( 'avf_event_detail_url', $url, $view );
	}

	/**
	 * Enqueues the shortcode stylesheet, only when the shortcode actually renders.
	 *
	 * @return void
	 */
	private function enqueue_assets() {
		$css_path = AVF_EVENTS_INTEGRATION_DIR . 'assets/css/upcoming-events.css';
		$version  = AVF_EVENTS_INTEGRATION_VERSION;

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && file_exists( $css_path ) ) {
			$version = (string) filemtime( $css_path );
		}

		wp_enqueue_style(
			'avf-upcoming-events',
			AVF_EVENTS_INTEGRATION_URL . 'assets/css/upcoming-events.css',
			array(),
			$version
		);
	}

	/**
	 * Renders the discreet admin-only notice shown when data could not be loaded.
	 *
	 * @return string
	 */
	private function render_admin_notice() {
		ob_start();
		?>
		<p class="avf-events-notice">
			<?php esc_html_e( 'Die Veranstaltungsdaten konnten momentan nicht geladen werden.', 'avf-events-integration' ); ?>
		</p>
		<?php
		return ob_get_clean();
	}

	/**
	 * Renders the full events section markup.
	 *
	 * @param array $views List of event view arrays.
	 * @return string
	 */
	private function render_html( array $views ) {
		ob_start();
		?>
		<section class="avf-home-events" aria-label="<?php echo esc_attr__( 'Kommende Anlässe', 'avf-events-integration' ); ?>">
			<div class="avf-home-events__inner">
				<div class="avf-home-events__list">
					<?php foreach ( $views as $view ) : ?>
						<?php $this->render_card( $view ); ?>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}

	/**
	 * Renders a single event card. Expected to run inside an active output buffer.
	 *
	 * @param array $view Event view data.
	 * @return void
	 */
	private function render_card( array $view ) {
		$anchor = 'event-' . $view['slug'];
		$url    = $this->build_detail_url( $view );
		?>
		<article class="avf-event-card" id="<?php echo esc_attr( $anchor ); ?>">

			<time class="avf-event-card__date" datetime="<?php echo esc_attr( $view['datetime_attr'] ); ?>">
				<strong><?php echo esc_html( $view['day'] ); ?></strong>
				<span><?php echo esc_html( $view['month_year'] ); ?></span>
			</time>

			<div class="avf-event-card__content">
				<h2 class="avf-event-card__title"><?php echo esc_html( $view['title'] ); ?></h2>

				<?php if ( '' !== $view['short_description'] ) : ?>
					<p class="avf-event-card__description"><?php echo esc_html( $view['short_description'] ); ?></p>
				<?php endif; ?>

				<p class="avf-event-card__meta"><?php echo esc_html( $view['meta'] ); ?></p>
			</div>

			<a class="avf-event-card__link" href="<?php echo esc_url( $url ); ?>">
				<?php esc_html_e( 'Details', 'avf-events-integration' ); ?>
				<span aria-hidden="true">→</span>
			</a>

		</article>
		<?php
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
