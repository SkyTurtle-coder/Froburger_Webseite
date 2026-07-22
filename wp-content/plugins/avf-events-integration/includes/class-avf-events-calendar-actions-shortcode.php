<?php
/**
 * Registers and renders [avf_events_calendar_actions] - two links pointing
 * at Django's own calendar.ics endpoint ("Kalender abonnieren" via webcal:,
 * "ICS-Datei" via https:). WordPress never generates or parses calendar
 * data itself; both links reference the exact same Django-provided
 * resource, only the URI scheme differs (a standard convention: webcal:
 * asks the OS/calendar app to subscribe, https: prompts a one-time
 * download).
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

		return $this->render_html( $this->to_webcal( $ics_url ), $ics_url );
	}

	/**
	 * Converts an https:// (or http://) URL to its webcal:// equivalent -
	 * plain scheme substitution, not a new URL and not generated data.
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
	 * Renders the two calendar action links.
	 *
	 * @param string $webcal_url webcal:// subscribe URL.
	 * @param string $ics_url    https:// download URL.
	 * @return string
	 */
	private function render_html( $webcal_url, $ics_url ) {
		ob_start();
		?>
		<div class="avf-events-calendar-actions">
			<a class="avf-events-calendar-actions__link avf-events-calendar-actions__link--subscribe" href="<?php echo esc_url( $webcal_url ); ?>">
				<?php esc_html_e( 'Kalender abonnieren', 'avf-events-integration' ); ?>
			</a>
			<a class="avf-events-calendar-actions__link avf-events-calendar-actions__link--download" href="<?php echo esc_url( $ics_url ); ?>">
				<?php esc_html_e( 'ICS-Datei', 'avf-events-integration' ); ?>
			</a>
		</div>
		<?php
		return ob_get_clean();
	}
}
