<?php
/**
 * Registers and renders [avf_events_upcoming] and [avf_events_past] -
 * paginated ("Mehr anzeigen") v1-powered event lists. One class handles
 * both shortcodes: they share almost all logic (fetch, cache, paginate,
 * render, empty/error states) and differ only in endpoint, defaults and
 * visual tone, per the project's "no parallel implementation" rule.
 *
 * @package AVF_Events_Integration
 */

defined( 'ABSPATH' ) || exit;

class AVF_Events_List_Shortcode {

	const TYPE_UPCOMING = 'upcoming';
	const TYPE_PAST      = 'past';

	const MIN_COUNT = 1;
	const MAX_COUNT = 60; // Matches AVF_Events_API_Client::V1_LIST_MAX_LIMIT.

	/**
	 * Singleton instance.
	 *
	 * @var AVF_Events_List_Shortcode|null
	 */
	private static $instance = null;

	/**
	 * Per-type default initial/step values.
	 *
	 * @var array
	 */
	private static $defaults = array(
		self::TYPE_UPCOMING => array(
			'initial' => 9,
			'step'    => 9,
		),
		self::TYPE_PAST      => array(
			'initial' => 6,
			'step'    => 6,
		),
	);

	/**
	 * Returns the singleton instance, creating it on first call.
	 *
	 * @return AVF_Events_List_Shortcode
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers both shortcodes.
	 */
	private function __construct() {
		add_shortcode( 'avf_events_upcoming', array( $this, 'render_upcoming' ) );
		add_shortcode( 'avf_events_past', array( $this, 'render_past' ) );
	}

	/**
	 * Shortcode callback for [avf_events_upcoming initial="9" step="9"].
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render_upcoming( $atts ) {
		return $this->render( self::TYPE_UPCOMING, $atts );
	}

	/**
	 * Shortcode callback for [avf_events_past initial="6" step="6"].
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render_past( $atts ) {
		return $this->render( self::TYPE_PAST, $atts );
	}

	/**
	 * Shared render implementation for both shortcodes.
	 *
	 * @param string       $type Either self::TYPE_UPCOMING or self::TYPE_PAST.
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	private function render( $type, $atts ) {
		$defaults = self::$defaults[ $type ];
		$atts     = shortcode_atts( $defaults, $atts, 'avf_events_' . $type );

		$initial = AVF_Events_View_Helpers::sanitize_count( $atts['initial'], $defaults['initial'], self::MIN_COUNT, self::MAX_COUNT );
		$step    = AVF_Events_View_Helpers::sanitize_count( $atts['step'], $defaults['step'], self::MIN_COUNT, self::MAX_COUNT );

		$query_var = $this->query_var( $type );
		$shown     = $initial;

		if ( isset( $_GET[ $query_var ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination state, not a state-changing action.
			$requested = AVF_Events_View_Helpers::sanitize_count( wp_unslash( $_GET[ $query_var ] ), $initial, $initial, self::MAX_COUNT ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			$shown     = max( $initial, $requested );
		}

		$client = new AVF_Events_API_Client();
		$result = self::TYPE_UPCOMING === $type
			? $client->get_upcoming_events_v1( $shown )
			: $client->get_past_events( $shown );

		if ( is_wp_error( $result ) ) {
			return $this->render_unavailable( $type );
		}

		$events = ( isset( $result['events'] ) && is_array( $result['events'] ) ) ? $result['events'] : array();
		$total  = isset( $result['total'] ) ? $result['total'] : null;
		$views  = AVF_Events_View_Helpers::build_views( $events );

		if ( empty( $views ) ) {
			return $this->render_empty( $type );
		}

		$this->enqueue_assets();

		$has_more   = ( null !== $total ) && ( count( $views ) < $total );
		$next_shown = min( self::MAX_COUNT, $shown + $step );

		return $this->render_html( $type, $views, $shown, $step, $next_shown, $has_more, $query_var );
	}

	/**
	 * The query var used for the no-JS "Mehr anzeigen" fallback, namespaced
	 * per type so both shortcodes can appear on the same page without
	 * interfering with each other's pagination state.
	 *
	 * @param string $type Event type.
	 * @return string
	 */
	private function query_var( $type ) {
		return 'avf_' . $type . '_shown';
	}

	/**
	 * Renders the friendly, visitor-facing empty state for a genuinely empty
	 * (but successfully fetched) result.
	 *
	 * @param string $type Event type.
	 * @return string
	 */
	private function render_empty( $type ) {
		$message = self::TYPE_UPCOMING === $type
			? __( 'Aktuell sind keine kommenden Veranstaltungen veröffentlicht.', 'avf-events-integration' )
			: __( 'Derzeit sind keine vergangenen Veranstaltungen verfügbar.', 'avf-events-integration' );

		$this->enqueue_assets();

		return $this->render_empty_html( $message );
	}

	/**
	 * Renders the state shown when the API could not be reached and no
	 * fallback cache exists. Visitors see the exact same calm empty state as
	 * a genuinely empty list (no URLs, no exception details, no stack
	 * traces) - administrators additionally see a discreet hint that data
	 * could not be loaded, without technical specifics.
	 *
	 * @param string $type Event type.
	 * @return string
	 */
	private function render_unavailable( $type ) {
		$message = self::TYPE_UPCOMING === $type
			? __( 'Aktuell sind keine kommenden Veranstaltungen veröffentlicht.', 'avf-events-integration' )
			: __( 'Derzeit sind keine vergangenen Veranstaltungen verfügbar.', 'avf-events-integration' );

		$this->enqueue_assets();

		$html = $this->render_empty_html( $message );

		if ( current_user_can( 'manage_options' ) ) {
			ob_start();
			?>
			<p class="avf-events-notice"><?php esc_html_e( 'Hinweis (nur für Administratoren sichtbar): Die Veranstaltungsdaten konnten momentan nicht geladen werden.', 'avf-events-integration' ); ?></p>
			<?php
			$html .= ob_get_clean();
		}

		return $html;
	}

	/**
	 * Renders the empty-state markup shared by render_empty() and
	 * render_unavailable().
	 *
	 * @param string $message Already-translated message text.
	 * @return string
	 */
	private function render_empty_html( $message ) {
		ob_start();
		?>
		<p class="avf-events-empty"><?php echo esc_html( $message ); ?></p>
		<?php
		return ob_get_clean();
	}

	/**
	 * Enqueues the shared stylesheet and script, only when a shortcode from
	 * this plugin's v1 components actually renders.
	 *
	 * @return void
	 */
	private function enqueue_assets() {
		AVF_Events_Assets::enqueue();
	}

	/**
	 * Renders the full list section: heading-free wrapper, grid/list of
	 * cards and, if there is more data than currently shown, a "Mehr
	 * anzeigen" link that works as a real link (progressive enhancement,
	 * full-page reload with more items shown) and is upgraded by JS into an
	 * AJAX-driven, no-reload button.
	 *
	 * @param string $type       Event type.
	 * @param array  $views      Event views.
	 * @param int    $shown      Number of events currently shown.
	 * @param int    $step       Step size for the next "Mehr anzeigen" click.
	 * @param int    $next_shown Shown count the next click would request.
	 * @param bool   $has_more   Whether more events exist beyond $views.
	 * @param string $query_var  No-JS fallback query var name for this instance.
	 * @return string
	 */
	private function render_html( $type, array $views, $shown, $step, $next_shown, $has_more, $query_var ) {
		$list_id       = wp_unique_id( 'avf-events-' . $type . '-list-' );
		$section_class = self::TYPE_UPCOMING === $type ? 'avf-events-upcoming' : 'avf-events-past';
		$list_class    = self::TYPE_UPCOMING === $type ? 'avf-events-upcoming__list' : 'avf-events-past__list';

		ob_start();
		?>
		<section class="<?php echo esc_attr( $section_class ); ?>" aria-label="<?php echo self::TYPE_UPCOMING === $type ? esc_attr__( 'Kommende Veranstaltungen', 'avf-events-integration' ) : esc_attr__( 'Vergangene Veranstaltungen', 'avf-events-integration' ); ?>">
			<div class="<?php echo esc_attr( $list_class ); ?>" id="<?php echo esc_attr( $list_id ); ?>">
				<?php foreach ( $views as $view ) : ?>
					<?php echo self::TYPE_UPCOMING === $type ? self::render_upcoming_card( $view ) : self::render_past_card( $view ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already-escaped markup from a trusted internal renderer. ?>
				<?php endforeach; ?>
			</div>

			<?php if ( $has_more ) : ?>
				<?php
				$next_url = add_query_arg( $query_var, $next_shown, $this->current_url() );
				?>
				<p class="avf-events-more-wrap">
					<a
						class="avf-events-more"
						href="<?php echo esc_url( $next_url ); ?>"
						data-avf-events-more="1"
						data-type="<?php echo esc_attr( $type ); ?>"
						data-shown="<?php echo esc_attr( $shown ); ?>"
						data-step="<?php echo esc_attr( $step ); ?>"
						data-list-target="<?php echo esc_attr( $list_id ); ?>"
						data-query-var="<?php echo esc_attr( $query_var ); ?>"
						data-nonce="<?php echo esc_attr( wp_create_nonce( AVF_Events_Ajax::ACTION ) ); ?>"
					>
						<?php esc_html_e( 'Mehr anzeigen', 'avf-events-integration' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</section>
		<?php
		return ob_get_clean();
	}

	/**
	 * Returns the current request URL with any existing "Mehr anzeigen"
	 * query vars for this type removed, so add_query_arg() never stacks
	 * duplicate values across repeated no-JS clicks.
	 *
	 * @return string
	 */
	private function current_url() {
		$scheme = is_ssl() ? 'https://' : 'http://';
		$host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

		return $scheme . $host . $uri;
	}

	/**
	 * Renders a single upcoming-event card: date, optional multi-day badge,
	 * title, short description, time, location, detail link and (when
	 * present) a link to Django's calendar.ics. Public + static so
	 * AVF_Events_Ajax can render additional cards for "Mehr anzeigen"
	 * without a second, parallel rendering implementation.
	 *
	 * @param array $view Event view (from AVF_Events_View_Helpers::build_view()).
	 * @return string
	 */
	public static function render_upcoming_card( array $view ) {
		$anchor     = 'event-' . $view['slug'];
		$detail_url = AVF_Events_View_Helpers::build_detail_url( $view );
		$client     = new AVF_Events_API_Client();
		$ics_url    = $client->get_event_calendar_ics_url( $view['slug'] );

		ob_start();
		?>
		<article class="avf-events-tile" id="<?php echo esc_attr( $anchor ); ?>">

			<time class="avf-events-tile__date" datetime="<?php echo esc_attr( $view['datetime_attr'] ); ?>">
				<strong><?php echo esc_html( $view['day'] ); ?></strong>
				<span class="avf-events-tile__month"><?php echo esc_html( $view['month_year'] ); ?></span>
				<?php if ( '' !== $view['status_label'] ) : ?>
					<span class="avf-events-tile__status avf-events-tile__status--<?php echo esc_attr( $view['status'] ); ?>"><?php echo esc_html( $view['status_label'] ); ?></span>
				<?php endif; ?>
			</time>

			<div class="avf-events-tile__content">
				<?php if ( $view['is_multi_day'] ) : ?>
					<span class="avf-events-tile__badge"><?php esc_html_e( 'Mehrtägig', 'avf-events-integration' ); ?></span>
				<?php endif; ?>

				<h3 class="avf-events-tile__title"><?php echo esc_html( $view['title'] ); ?></h3>

				<?php if ( '' !== $view['short_description'] ) : ?>
					<p class="avf-events-tile__description"><?php echo esc_html( $view['short_description'] ); ?></p>
				<?php endif; ?>

				<p class="avf-events-tile__meta">
					<?php
					printf(
						/* translators: 1: time (H:i), 2: location name */
						esc_html__( '%1$s Uhr · %2$s', 'avf-events-integration' ),
						esc_html( $view['time_part'] ),
						esc_html( $view['location_name'] )
					);
					?>
				</p>

				<div class="avf-events-tile__actions">
					<a class="avf-events-tile__link" href="<?php echo esc_url( $detail_url ); ?>">
						<?php esc_html_e( 'Details', 'avf-events-integration' ); ?>
						<span aria-hidden="true">→</span>
					</a>
					<?php if ( '' !== $ics_url ) : ?>
						<a class="avf-events-tile__ics" href="<?php echo esc_url( $ics_url ); ?>">
							<?php esc_html_e( 'Zum Kalender hinzufügen', 'avf-events-integration' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>

		</article>
		<?php
		return ob_get_clean();
	}

	/**
	 * Renders a compact past-event recap tile: short date + title only.
	 *
	 * @param array $view Event view (from AVF_Events_View_Helpers::build_view()).
	 * @return string
	 */
	public static function render_past_card( array $view ) {
		$anchor     = 'event-' . $view['slug'];
		$detail_url = AVF_Events_View_Helpers::build_detail_url( $view );

		ob_start();
		?>
		<article class="avf-events-past-item" id="<?php echo esc_attr( $anchor ); ?>">
			<time class="avf-events-past-item__date" datetime="<?php echo esc_attr( $view['datetime_attr'] ); ?>">
				<?php echo esc_html( $view['short_date_part'] ); ?>
			</time>
			<div class="avf-events-past-item__body">
				<h3 class="avf-events-past-item__title">
					<a class="avf-events-past-item__title-link" href="<?php echo esc_url( $detail_url ); ?>">
						<?php echo esc_html( $view['title'] ); ?>
					</a>
				</h3>
			</div>
		</article>
		<?php
		return ob_get_clean();
	}
}
