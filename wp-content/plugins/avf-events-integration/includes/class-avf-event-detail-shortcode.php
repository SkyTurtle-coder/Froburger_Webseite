<?php
/**
 * Registers and renders [avf_event_detail slug="..."] - a single event's
 * full data via the v1 detail endpoint. Optional/forward-looking: not
 * placed on the "Anlässe" page by this phase (which only needs the list and
 * calendar-action shortcodes), but available for a future dedicated event
 * page without any further backend work.
 *
 * @package AVF_Events_Integration
 */

defined( 'ABSPATH' ) || exit;

class AVF_Event_Detail_Shortcode {

	/**
	 * Singleton instance.
	 *
	 * @var AVF_Event_Detail_Shortcode|null
	 */
	private static $instance = null;

	/**
	 * Returns the singleton instance, creating it on first call.
	 *
	 * @return AVF_Event_Detail_Shortcode
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
		add_shortcode( 'avf_event_detail', array( $this, 'render' ) );
	}

	/**
	 * Shortcode callback for [avf_event_detail slug="..."].
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts = shortcode_atts( array( 'slug' => '' ), $atts, 'avf_event_detail' );
		$slug = sanitize_title( (string) $atts['slug'] );

		if ( '' === $slug ) {
			if ( current_user_can( 'manage_options' ) ) {
				return $this->render_admin_notice( __( 'Kein "slug"-Attribut angegeben.', 'avf-events-integration' ) );
			}

			return '';
		}

		$client = new AVF_Events_API_Client();
		$event  = $client->get_event_detail( $slug );

		if ( is_wp_error( $event ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				return $this->render_admin_notice( __( 'Die Veranstaltung konnte momentan nicht geladen werden.', 'avf-events-integration' ) );
			}

			return '';
		}

		$view = AVF_Events_View_Helpers::build_view( $event );
		if ( null === $view ) {
			return '';
		}

		AVF_Events_Assets::enqueue();

		return $this->render_html( $view );
	}

	/**
	 * Renders a discreet admin-only notice.
	 *
	 * @param string $message Already-translated message text.
	 * @return string
	 */
	private function render_admin_notice( $message ) {
		ob_start();
		?>
		<p class="avf-events-notice"><?php echo esc_html( $message ); ?></p>
		<?php
		return ob_get_clean();
	}

	/**
	 * Renders the full event detail markup.
	 *
	 * @param array $view Event view (from AVF_Events_View_Helpers::build_view()).
	 * @return string
	 */
	private function render_html( array $view ) {
		$client  = new AVF_Events_API_Client();
		$ics_url = $client->get_calendar_ics_url();

		ob_start();
		?>
		<article class="avf-event-detail">
			<p class="avf-event-detail__date">
				<?php
				if ( $view['is_multi_day'] && '' !== $view['end_date_part'] ) {
					printf(
						/* translators: 1: start date, 2: end date */
						esc_html__( '%1$s – %2$s', 'avf-events-integration' ),
						esc_html( $view['date_part'] ),
						esc_html( $view['end_date_part'] )
					);
				} else {
					printf(
						/* translators: 1: date, 2: time */
						esc_html__( '%1$s · %2$s Uhr', 'avf-events-integration' ),
						esc_html( $view['date_part'] ),
						esc_html( $view['time_part'] )
					);
				}
				?>
			</p>

			<h1 class="avf-event-detail__title"><?php echo esc_html( $view['title'] ); ?></h1>

			<?php if ( '' !== $view['short_description'] ) : ?>
				<p class="avf-event-detail__description"><?php echo esc_html( $view['short_description'] ); ?></p>
			<?php endif; ?>

			<p class="avf-event-detail__meta"><?php echo esc_html( $view['location_name'] ); ?></p>

			<?php if ( '' !== $ics_url ) : ?>
				<a class="avf-events-tile__ics" href="<?php echo esc_url( $ics_url ); ?>">
					<?php esc_html_e( 'Zum Kalender hinzufügen', 'avf-events-integration' ); ?>
				</a>
			<?php endif; ?>
		</article>
		<?php
		return ob_get_clean();
	}
}
