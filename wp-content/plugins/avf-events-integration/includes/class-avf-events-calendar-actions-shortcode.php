<?php
/**
 * Registers and renders [avf_events_calendar_actions].
 *
 * @package AVF_Events_Integration
 */

defined( 'ABSPATH' ) || exit;

class AVF_Events_Calendar_Actions_Shortcode {

	/**
	 * Singleton instance.
	 *
	 * @var AVF_Events_Calendar_Actions_Shortcode|null
	 */
	private static $instance = null;

	/**
	 * Returns the singleton instance, creating it on first call.
	 *
	 * @return AVF_Events_Calendar_Actions_Shortcode
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
		add_shortcode( 'avf_events_calendar_actions', array( $this, 'render' ) );
	}

	/**
	 * Shortcode callback for [avf_events_calendar_actions].
	 *
	 * @return string
	 */
	public function render() {
		$client  = new AVF_Events_API_Client();
		$ics_url = $client->get_calendar_ics_url();

		if ( '' === $ics_url ) {
			if ( current_user_can( 'manage_options' ) ) {
				return $this->render_admin_notice();
			}

			return '';
		}

		AVF_Events_Assets::enqueue();

		return $this->render_html( $ics_url, $this->to_webcal( $ics_url ) );
	}

	/**
	 * Converts an https:// (or http://) URL to its webcal:// equivalent.
	 *
	 * @param string $url Django-provided ICS URL.
	 * @return string
	 */
	private function to_webcal( $url ) {
		if ( 0 === strpos( $url, 'https://' ) ) {
			return 'webcal://' . substr( $url, strlen( 'https://' ) );
		}

		if ( 0 === strpos( $url, 'http://' ) ) {
			return 'webcal://' . substr( $url, strlen( 'http://' ) );
		}

		return $url;
	}

	/**
	 * Renders the discreet admin-only notice shown when no v1 API base is configured.
	 *
	 * @return string
	 */
	private function render_admin_notice() {
		ob_start();
		?>
		<p class="avf-events-notice"><?php esc_html_e( 'Kalenderaktionen benötigen eine konfigurierte v1-API-Basis (nur für Administratoren sichtbar).', 'avf-events-integration' ); ?></p>
		<?php
		return ob_get_clean();
	}

	/**
	 * Detects whether the current request comes from an Android device.
	 *
	 * Android's Google Calendar app has no in-app "subscribe to feed" flow;
	 * opening an .ics link only imports the events once. The dialog copy
	 * is adjusted accordingly so the button doesn't promise a live sync
	 * it can't deliver on this platform.
	 *
	 * @return bool
	 */
	private function is_android_user_agent() {
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		return false !== stripos( $user_agent, 'Android' );
	}

	/**
	 * Renders the public subscription action and dialog.
	 *
	 * @param string $ics_url    https:// copyable canonical URL.
	 * @param string $webcal_url webcal:// subscribe URL.
	 * @return string
	 */
	private function render_html( $ics_url, $webcal_url ) {
		$dialog_id  = wp_unique_id( 'avf-calendar-dialog-' );
		$label_id   = $dialog_id . '-label';
		$is_android = $this->is_android_user_agent();

		$subscribe_label = $is_android
			? __( 'Termine importieren', 'avf-events-integration' )
			: __( 'Abonnieren', 'avf-events-integration' );

		$dialog_intro = $is_android
			? __( 'Auf Android öffnet dieser Button den Import in Google Kalender. Die Termine werden dabei einmalig übernommen, spätere Änderungen erscheinen nicht automatisch.', 'avf-events-integration' )
			: __( 'Änderungen werden automatisch übernommen. Die Aktualisierung kann je nach Kalender-App zeitversetzt erfolgen.', 'avf-events-integration' );

		$dialog_secondary = $is_android
			? __( 'Für eine laufend aktualisierte Ansicht fügen Sie den kopierten Link am Computer in Google Kalender unter „Weitere Kalender hinzufügen → Per URL“ hinzu.', 'avf-events-integration' )
			: __( 'Bei Problemen verwenden Sie den Link unter der Schaltfläche Link kopieren.', 'avf-events-integration' );

		ob_start();
		?>
		<div class="avf-events-calendar-actions" data-avf-calendar-actions>
			<button
				type="button"
				class="avf-events-calendar-actions__link avf-events-calendar-actions__link--subscribe"
				data-avf-calendar-dialog-open
				aria-haspopup="dialog"
				aria-controls="<?php echo esc_attr( $dialog_id ); ?>"
			>
				<?php esc_html_e( 'Kalender abonnieren', 'avf-events-integration' ); ?>
			</button>

			<dialog class="avf-events-calendar-actions__dialog" id="<?php echo esc_attr( $dialog_id ); ?>" aria-labelledby="<?php echo esc_attr( $label_id ); ?>">
				<div class="avf-events-calendar-actions__dialog-inner">
					<div class="avf-events-calendar-actions__dialog-header">
						<h2 id="<?php echo esc_attr( $label_id ); ?>" class="avf-events-calendar-actions__dialog-title">
							<?php esc_html_e( 'Kalender abonnieren', 'avf-events-integration' ); ?>
						</h2>
						<button type="button" class="avf-events-calendar-actions__dialog-close" data-avf-calendar-dialog-close aria-label="<?php esc_attr_e( 'Schließen', 'avf-events-integration' ); ?>">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<p class="avf-events-calendar-actions__dialog-text">
						<?php echo esc_html( $dialog_intro ); ?>
					</p>
					<p class="avf-events-calendar-actions__dialog-text">
						<?php echo esc_html( $dialog_secondary ); ?>
					</p>
					<div class="avf-events-calendar-actions__dialog-actions">
						<button
							type="button"
							class="avf-events-calendar-actions__action avf-events-calendar-actions__action--primary"
							data-avf-calendar-subscribe
							data-subscribe-url="<?php echo esc_attr( $webcal_url ); ?>"
							data-fallback-url="<?php echo esc_attr( $ics_url ); ?>"
							data-google-url="https://calendar.google.com/calendar/u/0/r/settings/addbyurl"
						>
							<?php echo esc_html( $subscribe_label ); ?>
						</button>
						<button
							type="button"
							class="avf-events-calendar-actions__action avf-events-calendar-actions__action--secondary"
							data-avf-calendar-copy
							data-copy-value="<?php echo esc_attr( $ics_url ); ?>"
						>
							<?php esc_html_e( 'Link kopieren', 'avf-events-integration' ); ?>
						</button>
						<button
							type="button"
							class="avf-events-calendar-actions__action avf-events-calendar-actions__action--secondary"
							data-avf-calendar-open
							data-open-url="<?php echo esc_attr( $ics_url ); ?>"
						>
							<?php esc_html_e( 'ICS Download', 'avf-events-integration' ); ?>
						</button>
					</div>
				</div>
			</dialog>
		</div>
		<?php
		return ob_get_clean();
	}
}
