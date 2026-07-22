<?php
/**
 * Settings page for the AVF Events Integration plugin.
 *
 * @package AVF_Events_Integration
 */

defined( 'ABSPATH' ) || exit;

class AVF_Events_Settings {

	const OPTION_GROUP = 'avf_events_settings';
	const PAGE_SLUG    = 'avf-events-integration';
	const CLEAR_ACTION  = 'avf_events_clear_cache';

	/**
	 * Singleton instance.
	 *
	 * @var AVF_Events_Settings|null
	 */
	private static $instance = null;

	/**
	 * Returns the singleton instance, creating it on first call.
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
	 * Registers settings, sections and fields via the Settings API.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			'avf_events_api_endpoint',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_endpoint' ),
				'default'           => '',
			)
		);

		register_setting(
			self::OPTION_GROUP,
			'avf_events_api_base',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_api_base' ),
				'default'           => '',
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

		add_settings_section(
			'avf_events_main_section',
			__( 'API-Einstellungen', 'avf-events-integration' ),
			'__return_false',
			self::PAGE_SLUG
		);

		add_settings_field(
			'avf_events_api_endpoint',
			__( 'API-Endpunkt', 'avf-events-integration' ),
			array( $this, 'render_endpoint_field' ),
			self::PAGE_SLUG,
			'avf_events_main_section'
		);

		add_settings_field(
			'avf_events_api_base',
			__( 'API-Basis (v1)', 'avf-events-integration' ),
			array( $this, 'render_api_base_field' ),
			self::PAGE_SLUG,
			'avf_events_main_section'
		);

		add_settings_field(
			'avf_events_cache_ttl',
			__( 'Cache-Dauer (Sekunden)', 'avf-events-integration' ),
			array( $this, 'render_cache_ttl_field' ),
			self::PAGE_SLUG,
			'avf_events_main_section'
		);

		add_settings_field(
			'avf_events_page_path',
			__( 'Zielseite für Anlassdetails', 'avf-events-integration' ),
			array( $this, 'render_page_path_field' ),
			self::PAGE_SLUG,
			'avf_events_main_section'
		);
	}

	/**
	 * Sanitizes the API endpoint URL.
	 *
	 * @param mixed $value Raw submitted value.
	 * @return string
	 */
	public function sanitize_endpoint( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';

		if ( '' === $value ) {
			return '';
		}

		return esc_url_raw( $value );
	}

	/**
	 * Sanitizes the v1 API base URL, ensuring a trailing slash so URL
	 * building elsewhere never has to guess.
	 *
	 * @param mixed $value Raw submitted value.
	 * @return string
	 */
	public function sanitize_api_base( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';

		if ( '' === $value ) {
			return '';
		}

		$value = esc_url_raw( $value );

		return '' === $value ? '' : trailingslashit( $value );
	}

	/**
	 * Sanitizes and clamps the cache TTL to the allowed range.
	 *
	 * @param mixed $value Raw submitted value.
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
	 * Sanitizes the relative page path, ensuring leading and trailing slashes.
	 *
	 * @param mixed $value Raw submitted value.
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
	 * Renders the API endpoint field.
	 *
	 * @return void
	 */
	public function render_endpoint_field() {
		$value = get_option( 'avf_events_api_endpoint', '' );
		?>
		<input
			type="url"
			class="regular-text code"
			name="avf_events_api_endpoint"
			value="<?php echo esc_attr( $value ); ?>"
			placeholder="https://intern.avfroburger.ch/api/public/events/upcoming/"
		/>
		<p class="description">
			<?php esc_html_e( 'Die URL soll auf /api/public/events/upcoming/ enden.', 'avf-events-integration' ); ?>
		</p>
		<?php
	}

	/**
	 * Renders the v1 API base field.
	 *
	 * @return void
	 */
	public function render_api_base_field() {
		$value = get_option( 'avf_events_api_base', '' );
		?>
		<input
			type="url"
			class="regular-text code"
			name="avf_events_api_base"
			value="<?php echo esc_attr( $value ); ?>"
			placeholder="https://intern.avfroburger.ch/api/v1/public/"
		/>
		<p class="description">
			<?php esc_html_e( 'Basis-URL der neuen v1-API, endet auf /api/v1/public/. Wird für die neuen Shortcodes (avf_events_upcoming, avf_events_past, avf_events_calendar_actions, avf_event_detail) verwendet. Leer lassen, um ausschliesslich den Legacy-Endpunkt oben zu nutzen - [avf_upcoming_events] funktioniert davon unabhängig immer über den Legacy-Endpunkt.', 'avf-events-integration' ); ?>
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
			<?php esc_html_e( 'Zwischen 60 und 3600 Sekunden. Standard: 300.', 'avf-events-integration' ); ?>
		</p>
		<?php
	}

	/**
	 * Renders the page path field.
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
			<?php esc_html_e( 'Relativer WordPress-Pfad, z. B. /anlaesse/. Wird für interne Eventlinks verwendet.', 'avf-events-integration' ); ?>
		</p>
		<?php
	}

	/**
	 * Renders the settings page, including the cache clear form.
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

			<h2><?php esc_html_e( 'Event-Cache', 'avf-events-integration' ); ?></h2>
			<p><?php esc_html_e( 'Leert alle zwischengespeicherten Anlassdaten. Beim nächsten Aufruf werden die Daten erneut von der API geladen.', 'avf-events-integration' ); ?></p>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::CLEAR_ACTION ); ?>" />
				<?php wp_nonce_field( self::CLEAR_ACTION, self::CLEAR_ACTION . '_nonce' ); ?>
				<?php submit_button( __( 'Event-Cache leeren', 'avf-events-integration' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handles the "clear cache" form submission.
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
	 * Displays a success notice after the cache has been cleared.
	 *
	 * @return void
	 */
	public function render_admin_notices() {
		if ( ! isset( $_GET['page'], $_GET['avf_events_cache_ok'] ) || self::PAGE_SLUG !== $_GET['page'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Der Event-Cache wurde geleert.', 'avf-events-integration' ); ?></p>
		</div>
		<?php
	}
}
