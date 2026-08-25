<?php
/**
 * Settings page for the AVF Events Integration plugin.
 *
 * @package AVF_Events_Integration
 */

defined( 'ABSPATH' ) || exit;

class AVF_Events_Settings {

	const OPTION_GROUP = 'avf_events_settings';
	const PAGE_SLUG = 'avf-events-integration';
	const CLEAR_ACTION = 'avf_events_clear_cache';
	const RECHECK_ACTION = 'avf_internal_endpoint_recheck';

	/**
	 * Singleton instance.
	 *
	 * @var AVF_Events_Settings|null
	 */
	private static $instance = null;

	/**
	 * Returns the singleton instance.
	 *
	 * @return AVF_Events_Settings
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers WordPress hooks.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_' . self::CLEAR_ACTION, array( $this, 'handle_clear_cache' ) );
		add_action( 'admin_post_' . self::RECHECK_ACTION, array( $this, 'handle_recheck_status' ) );
		add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );
	}

	/**
	 * Adds the settings page under Settings.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_options_page(
			__( 'AV Froburger Events', 'avf-events-integration' ),
			__( 'AV Froburger Events', 'avf-events-integration' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Registers plugin settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			AVF_Internal_Endpoint_Resolver::PRIMARY_OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_internal_base' ),
				'default'           => AVF_Internal_Endpoint_Resolver::default_primary_url(),
			)
		);

		register_setting(
			self::OPTION_GROUP,
			AVF_Internal_Endpoint_Resolver::FALLBACK_OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_internal_base' ),
				'default'           => AVF_Internal_Endpoint_Resolver::default_fallback_url(),
			)
		);

		register_setting(
			self::OPTION_GROUP,
			'avf_events_cache_ttl',
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( $this, 'sanitize_cache_ttl' ),
				'default'           => 300,
			)
		);

		register_setting(
			self::OPTION_GROUP,
			'avf_events_page_path',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_page_path' ),
				'default'           => '/anlaesse/',
			)
		);

		register_setting(
			self::OPTION_GROUP,
			'avf_event_detail_page_path',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_page_path' ),
				'default'           => '/anlassdetail/',
			)
		);

		register_setting(
			self::OPTION_GROUP,
			'avf_calendar_public_feed_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'default'           => '',
			)
		);

		add_settings_section(
			'avf_events_main_section',
			__( 'Interne Django-Anbindung', 'avf-events-integration' ),
			'__return_false',
			self::PAGE_SLUG
		);

		add_settings_field(
			'avf_internal_primary_url',
			__( 'Primaere Django-URL', 'avf-events-integration' ),
			array( $this, 'render_primary_url_field' ),
			self::PAGE_SLUG,
			'avf_events_main_section'
		);

		add_settings_field(
			'avf_internal_fallback_url',
			__( 'Fallback-Django-URL', 'avf-events-integration' ),
			array( $this, 'render_fallback_url_field' ),
			self::PAGE_SLUG,
			'avf_events_main_section'
		);

		add_settings_field(
			'avf_events_cache_ttl',
			__( 'Event-Cache-Dauer (Sekunden)', 'avf-events-integration' ),
			array( $this, 'render_cache_ttl_field' ),
			self::PAGE_SLUG,
			'avf_events_main_section'
		);

		add_settings_field(
			'avf_events_page_path',
			__( 'Pfad der Anlassuebersicht', 'avf-events-integration' ),
			array( $this, 'render_page_path_field' ),
			self::PAGE_SLUG,
			'avf_events_main_section'
		);

		add_settings_field(
			'avf_event_detail_page_path',
			__( 'Interne Detailseite', 'avf-events-integration' ),
			array( $this, 'render_detail_page_path_field' ),
			self::PAGE_SLUG,
			'avf_events_main_section'
		);

		add_settings_field(
			'avf_calendar_public_feed_url',
			__( 'Kanonische Kalender-Feed-URL', 'avf-events-integration' ),
			array( $this, 'render_calendar_feed_url_field' ),
			self::PAGE_SLUG,
			'avf_events_main_section'
		);
	}

	/**
	 * Sanitizes one central internal base URL.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_internal_base( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';

		if ( '' === $value ) {
			return '';
		}

		$value = esc_url_raw( $value );
		$parts = wp_parse_url( $value );
		if ( '' === $value || ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) || 'https' !== strtolower( (string) $parts['scheme'] ) ) {
			add_settings_error(
				self::OPTION_GROUP,
				'avf_internal_invalid_base',
				__( 'Die interne Basis-URL muss mit https:// beginnen und einen gueltigen Host enthalten.', 'avf-events-integration' )
			);
			return '';
		}

		return untrailingslashit( $value );
	}

	/**
	 * Sanitizes the cache TTL.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public function sanitize_cache_ttl( $value ) {
		if ( ! is_numeric( $value ) ) {
			return 300;
		}

		$value = (int) $value;

		return max( 60, min( 3600, $value ) );
	}

	/**
	 * Sanitizes a relative page path.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_page_path( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';

		if ( '' === $value ) {
			return '/anlaesse/';
		}

		$value    = wp_strip_all_tags( $value );
		$value    = str_replace( array( "\r", "\n", "\t" ), '', $value );
		$segments = array_filter( explode( '/', $value ), 'strlen' );
		$segments = array_map( 'sanitize_title', $segments );
		$segments = array_filter( $segments, 'strlen' );

		if ( empty( $segments ) ) {
			return '/anlaesse/';
		}

		return '/' . implode( '/', $segments ) . '/';
	}

	/**
	 * Renders the primary URL field.
	 *
	 * @return void
	 */
	public function render_primary_url_field() {
		$value = get_option( AVF_Internal_Endpoint_Resolver::PRIMARY_OPTION, AVF_Internal_Endpoint_Resolver::default_primary_url() );
		?>
		<input
			type="url"
			class="regular-text code"
			name="<?php echo esc_attr( AVF_Internal_Endpoint_Resolver::PRIMARY_OPTION ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			placeholder="https://intern.avfroburger.ch"
		/>
		<p class="description">
			<?php esc_html_e( 'Kanonische Basisadresse der Django-Anwendung. Health-Checks laufen ueber /healthz/.', 'avf-events-integration' ); ?>
		</p>
		<?php
	}

	/**
	 * Renders the fallback URL field.
	 *
	 * @return void
	 */
	public function render_fallback_url_field() {
		$value = get_option( AVF_Internal_Endpoint_Resolver::FALLBACK_OPTION, AVF_Internal_Endpoint_Resolver::default_fallback_url() );
		?>
		<input
			type="url"
			class="regular-text code"
			name="<?php echo esc_attr( AVF_Internal_Endpoint_Resolver::FALLBACK_OPTION ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			placeholder="https://intern-avfroburger.ch"
		/>
		<p class="description">
			<?php esc_html_e( 'Absicherung gegen Probleme eines einzelnen Hostnamens. Beide Hostnamen zeigen weiterhin auf denselben VPS.', 'avf-events-integration' ); ?>
		</p>
		<?php
	}

	/**
	 * Renders the cache TTL field.
	 *
	 * @return void
	 */
	public function render_cache_ttl_field() {
		$value = get_option( 'avf_events_cache_ttl', 300 );
		?>
		<input
			type="number"
			class="small-text"
			name="avf_events_cache_ttl"
			value="<?php echo esc_attr( $value ); ?>"
			min="60"
			max="3600"
			step="1"
		/>
		<p class="description">
			<?php esc_html_e( 'Zwischen 60 und 3600 Sekunden. Eventdaten und erfolgreiche Health-Checks werden davon getrennt gecacht.', 'avf-events-integration' ); ?>
		</p>
		<?php
	}

	/**
	 * Renders the public overview path field.
	 *
	 * @return void
	 */
	public function render_page_path_field() {
		$value = get_option( 'avf_events_page_path', '/anlaesse/' );
		?>
		<input
			type="text"
			class="regular-text code"
			name="avf_events_page_path"
			value="<?php echo esc_attr( $value ); ?>"
			placeholder="/anlaesse/"
		/>
		<p class="description">
			<?php esc_html_e( 'Sichtbarer WordPress-Pfad der Anlassuebersicht. Detailseiten werden darunter als /anlaesse/<slug>/ erzeugt.', 'avf-events-integration' ); ?>
		</p>
		<?php
	}

	/**
	 * Renders the internal detail page path field.
	 *
	 * @return void
	 */
	public function render_detail_page_path_field() {
		$value = get_option( 'avf_event_detail_page_path', '/anlassdetail/' );
		?>
		<input
			type="text"
			class="regular-text code"
			name="avf_event_detail_page_path"
			value="<?php echo esc_attr( $value ); ?>"
			placeholder="/anlassdetail/"
		/>
		<p class="description">
			<?php esc_html_e( 'Feste interne Seite mit dem Shortcode [avf_event_detail]. Die sichtbare URL bleibt trotzdem /anlaesse/<slug>/.', 'avf-events-integration' ); ?>
		</p>
		<?php
	}

	/**
	 * Renders the canonical public feed URL field.
	 *
	 * @return void
	 */
	public function render_calendar_feed_url_field() {
		$value = get_option( 'avf_calendar_public_feed_url', '' );
		?>
		<input
			type="url"
			class="regular-text code"
			name="avf_calendar_public_feed_url"
			value="<?php echo esc_attr( $value ); ?>"
			placeholder="https://intern.avfroburger.ch/calendar/public/events.ics"
		/>
		<p class="description">
			<?php esc_html_e( 'Stabile abonnierbare HTTPS-URL fuer den oeffentlichen Kalender. Wenn leer, verwendet das Plugin den Django-Endpunkt automatisch.', 'avf-events-integration' ); ?>
		</p>
		<?php
	}

	/**
	 * Renders the settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'AV Froburger Events', 'avf-events-integration' ); ?></h1>

			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Interne Endpoint-Diagnose', 'avf-events-integration' ); ?></h2>
			<?php $this->render_endpoint_diagnostics(); ?>

			<hr />

			<h2><?php esc_html_e( 'Event-Cache', 'avf-events-integration' ); ?></h2>
			<p><?php esc_html_e( 'Leert alle zwischengespeicherten Anlassdaten. Beim naechsten Aufruf werden sie erneut von Django geladen.', 'avf-events-integration' ); ?></p>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::CLEAR_ACTION ); ?>" />
				<?php wp_nonce_field( self::CLEAR_ACTION, self::CLEAR_ACTION . '_nonce' ); ?>
				<?php submit_button( __( 'Event-Cache leeren', 'avf-events-integration' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handles the cache clear action.
	 *
	 * @return void
	 */
	public function handle_clear_cache() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'avf-events-integration' ), 403 );
		}

		check_admin_referer( self::CLEAR_ACTION, self::CLEAR_ACTION . '_nonce' );

		AVF_Events_API_Client::clear_cache();

		$redirect = add_query_arg(
			array(
				'page'                => self::PAGE_SLUG,
				'avf_events_cache_ok' => '1',
			),
			admin_url( 'options-general.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Runs a fresh endpoint check for both hosts.
	 *
	 * @return void
	 */
	public function handle_recheck_status() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'avf-events-integration' ), 403 );
		}

		check_admin_referer( self::RECHECK_ACTION, self::RECHECK_ACTION . '_nonce' );

		$resolver = AVF_Internal_Endpoint_Resolver::instance();
		$resolver->invalidate_health_cache();
		$resolver->check_endpoint( $resolver->get_primary_url() );
		$resolver->check_endpoint( $resolver->get_fallback_url() );

		$redirect = add_query_arg(
			array(
				'page'                  => self::PAGE_SLUG,
				'avf_internal_check_ok' => '1',
			),
			admin_url( 'options-general.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Displays cache and endpoint notices.
	 *
	 * @return void
	 */
	public function render_admin_notices() {
		if ( ! isset( $_GET['page'] ) || self::PAGE_SLUG !== $_GET['page'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_GET['avf_events_cache_ok'] ) ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Der Event-Cache wurde geleert.', 'avf-events-integration' ); ?></p>
			</div>
			<?php
		}

		if ( isset( $_GET['avf_internal_check_ok'] ) ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Die Endpoint-Diagnose wurde aktualisiert.', 'avf-events-integration' ); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Renders the endpoint diagnostics block.
	 *
	 * @return void
	 */
	private function render_endpoint_diagnostics() {
		$resolver    = AVF_Internal_Endpoint_Resolver::instance();
		$diagnostics = $resolver->get_diagnostics();
		$derived_legacy = trailingslashit( $diagnostics['primary_url'] ) . 'api/public/events/upcoming/';
		$derived_v1     = trailingslashit( $diagnostics['primary_url'] ) . 'api/v1/public/';
		?>
		<table class="widefat striped" style="max-width: 980px">
			<tbody>
				<tr><td><strong><?php esc_html_e( 'Primary-URL', 'avf-events-integration' ); ?></strong></td><td><code><?php echo esc_html( $diagnostics['primary_url'] ); ?></code></td></tr>
				<tr><td><strong><?php esc_html_e( 'Fallback-URL', 'avf-events-integration' ); ?></strong></td><td><code><?php echo esc_html( $diagnostics['fallback_url'] ); ?></code></td></tr>
				<tr><td><strong><?php esc_html_e( 'Aktiver Endpoint', 'avf-events-integration' ); ?></strong></td><td><code><?php echo '' !== $diagnostics['active_url'] ? esc_html( $diagnostics['active_url'] ) : '&ndash;'; ?></code></td></tr>
				<tr><td><strong><?php esc_html_e( 'Abgeleiteter Legacy-Endpunkt', 'avf-events-integration' ); ?></strong></td><td><code><?php echo esc_html( $derived_legacy ); ?></code></td></tr>
				<tr><td><strong><?php esc_html_e( 'Abgeleitete v1-Basis', 'avf-events-integration' ); ?></strong></td><td><code><?php echo esc_html( $derived_v1 ); ?></code></td></tr>
				<tr><td><strong><?php esc_html_e( 'Primary-Status', 'avf-events-integration' ); ?></strong></td><td><?php echo wp_kses_post( $this->format_status_cell( $diagnostics['primary_status'] ) ); ?></td></tr>
				<tr><td><strong><?php esc_html_e( 'Fallback-Status', 'avf-events-integration' ); ?></strong></td><td><?php echo wp_kses_post( $this->format_status_cell( $diagnostics['fallback_status'] ) ); ?></td></tr>
				<tr><td><strong><?php esc_html_e( 'Stabile Weiterleitungsroute', 'avf-events-integration' ); ?></strong></td><td><code><?php echo esc_html( AVF_Internal_Portal_Router::get_portal_url( '/accounts/login/' ) ); ?></code></td></tr>
			</tbody>
		</table>
		<p class="description">
			<?php esc_html_e( 'Die Host-Fallback-Logik schuetzt gegen Ausfaelle eines einzelnen Hostnamens oder Zertifikats, nicht gegen einen Ausfall des VPS, von Nginx, Django oder MariaDB. Beim Wechsel zwischen den zwei Domains kann eine erneute Anmeldung noetig sein.', 'avf-events-integration' ); ?>
		</p>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::RECHECK_ACTION ); ?>" />
			<?php wp_nonce_field( self::RECHECK_ACTION, self::RECHECK_ACTION . '_nonce' ); ?>
			<?php submit_button( __( 'Status erneut pruefen', 'avf-events-integration' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * Formats one endpoint status row.
	 *
	 * @param array $status Stored endpoint status.
	 * @return string
	 */
	private function format_status_cell( array $status ) {
		if ( empty( $status ) ) {
			return '&ndash;';
		}

		$healthy = ! empty( $status['healthy'] );
		$parts   = array(
			$healthy ? __( 'gesund', 'avf-events-integration' ) : __( 'fehlerhaft', 'avf-events-integration' ),
		);

		if ( ! empty( $status['checked_at'] ) ) {
			$parts[] = gmdate( 'Y-m-d H:i:s', (int) $status['checked_at'] ) . ' UTC';
		}

		if ( ! empty( $status['expires_at'] ) ) {
			$parts[] = sprintf(
				/* translators: %s: UTC timestamp */
				__( 'Cache bis %s', 'avf-events-integration' ),
				gmdate( 'Y-m-d H:i:s', (int) $status['expires_at'] ) . ' UTC'
			);
		}

		if ( ! empty( $status['status'] ) ) {
			$parts[] = 'HTTP ' . (int) $status['status'];
		}

		if ( ! empty( $status['reason'] ) ) {
			$parts[] = sanitize_key( $status['reason'] );
		}

		return esc_html( implode( ' | ', $parts ) );
	}
}
