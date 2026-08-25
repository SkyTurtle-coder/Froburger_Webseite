<?php
/**
 * Plugin Name: AVF Members Page
 * Description: Elementor-friendly public members integration backed by a validated local snapshot from the Django API.
 */

defined( 'ABSPATH' ) || exit;

final class AVF_Members_Page {

	const SCHEMA_VERSION       = 3;
	const LEGACY_SCHEMA_VERSION = 2;
	const SNAPSHOT_OPTION      = 'avf_members_snapshot_v2';
	const STATUS_OPTION        = 'avf_members_snapshot_status_v2';
	const CRON_HOOK            = 'avf_members_refresh_snapshot';
	const DEFAULT_INTERVAL     = 300;
	const MIN_INTERVAL         = 300;
	const MAX_INTERVAL         = 3600;
	const REQUEST_TIMEOUT      = 8;
	const LOCK_KEY             = 'avf_members_refresh_lock_v1';
	const AVATAR_ICON_FILES    = array(
		'bursch_f'    => 'bursch_f.png',
		'bursch_m'    => 'bursch_m.png',
		'fux_f'       => 'fux_f.png',
		'fux_m'       => 'fux_m.png',
		'fuxmajor_f'  => 'fuxmajor_f.png',
		'fuxmajor_m'  => 'fuxmajor_m.png',
	);

	/**
	 * Canonical public section order.
	 *
	 * @var string[]
	 */
	private static $section_order = array(
		'committee',
		'salon',
		'stall',
		'altfroburger',
		'af_committee',
	);

	/**
	 * Ensures the unavailable state is rendered at most once per request.
	 *
	 * @var bool
	 */
	private static $rendered_unavailable = false;

	/**
	 * Boots the integration.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( 'avf_members_page', array( __CLASS__, 'render_shortcode' ) );
		add_shortcode( 'avf_members_cards', array( __CLASS__, 'render_cards_shortcode' ) );
		add_shortcode( 'avf_members_count', array( __CLASS__, 'render_count_shortcode' ) );

		add_action( 'init', array( __CLASS__, 'ensure_refresh_schedule' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'refresh_snapshot_job' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ) );
		add_action( 'admin_post_avf_members_refresh_snapshot', array( __CLASS__, 'handle_manual_refresh' ) );
	}

	/**
	 * Renders the members shortcode.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'section'       => '',
				'sections'      => '',
				'show_headings' => '1',
				'show_count'    => '1',
				'show_empty'    => '1',
				'class'         => '',
			),
			$atts,
			'avf_members_page'
		);

		$payload = self::get_snapshot_payload();
		self::enqueue_assets();

		if ( is_wp_error( $payload ) ) {
			return self::render_unavailable_state();
		}

		$sections      = self::requested_sections( $atts );
		$show_headings = self::truthy( $atts['show_headings'] );
		$show_count    = self::truthy( $atts['show_count'] );
		$show_empty    = self::truthy( $atts['show_empty'] );
		$extra_class   = sanitize_html_class( (string) $atts['class'] );

		$wrapper_classes = array( 'avf-members-sync' );
		if ( '' !== $extra_class ) {
			$wrapper_classes[] = $extra_class;
		}

		$rendered_sections = array();
		foreach ( $sections as $section_key ) {
			$section = self::get_section_payload( $payload, $section_key );
			$html    = self::render_section( $section_key, $section, $show_headings, $show_count, $show_empty );
			if ( '' !== $html ) {
				$rendered_sections[] = $html;
			}
		}

		if ( empty( $rendered_sections ) ) {
			return self::render_empty_state();
		}

		return sprintf(
			'<div class="%1$s" data-generated-at="%2$s" data-schema-version="%3$d">%4$s</div>',
			esc_attr( implode( ' ', $wrapper_classes ) ),
			esc_attr( isset( $payload['generated_at'] ) ? (string) $payload['generated_at'] : '' ),
			(int) $payload['schema_version'],
			implode( '', $rendered_sections )
		);
	}

	/**
	 * Renders only the members cards for a single section.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_cards_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'section'    => '',
				'show_role'  => '',
				'show_empty' => '1',
				'class'      => '',
			),
			$atts,
			'avf_members_cards'
		);

		$section_key = self::normalize_section_key( $atts['section'] );
		if ( '' === $section_key ) {
			return '';
		}

		$payload = self::get_snapshot_payload();
		self::enqueue_assets();

		if ( is_wp_error( $payload ) ) {
			return self::render_unavailable_state();
		}

		$section    = self::get_section_payload( $payload, $section_key );
		$members    = isset( $section['members'] ) ? $section['members'] : array();
		$show_empty = self::truthy( $atts['show_empty'] );
		$show_role  = '' === trim( (string) $atts['show_role'] )
			? self::section_shows_role( $section_key )
			: self::truthy( $atts['show_role'] );

		$classes = array(
			'avf-members-grid',
			'avf-members-grid--' . str_replace( '_', '-', $section_key ),
		);

		$extra_class = sanitize_html_class( (string) $atts['class'] );
		if ( '' !== $extra_class ) {
			$classes[] = $extra_class;
		}

		if ( empty( $members ) ) {
			if ( ! $show_empty ) {
				return '';
			}

			return sprintf(
				'<div class="%1$s"><p class="avf-members-empty">%2$s</p></div>',
				esc_attr( implode( ' ', $classes ) ),
				esc_html__( 'Aktuell sind noch keine Mitglieder fuer diesen Bereich hinterlegt.', 'default' )
			);
		}

		$cards = array();
		foreach ( array_values( $members ) as $index => $member ) {
			$cards[] = self::render_card( $member, $section_key, $show_role, 0 === $index ? 'eager' : 'lazy' );
		}

		return sprintf(
			'<div class="%1$s">%2$s</div>',
			esc_attr( implode( ' ', $classes ) ),
			implode( '', $cards )
		);
	}

	/**
	 * Renders a compact count label for one section.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_count_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'section' => '',
				'class'   => '',
			),
			$atts,
			'avf_members_count'
		);

		$section_key = self::normalize_section_key( $atts['section'] );
		if ( '' === $section_key ) {
			return '';
		}

		$payload = self::get_snapshot_payload();
		if ( is_wp_error( $payload ) ) {
			return '';
		}

		self::enqueue_assets();

		$classes = array( 'avf-members-count' );
		$extra_class = sanitize_html_class( (string) $atts['class'] );
		if ( '' !== $extra_class ) {
			$classes[] = $extra_class;
		}

		$section = self::get_section_payload( $payload, $section_key );

		return sprintf(
			'<span class="%1$s">%2$s</span>',
			esc_attr( implode( ' ', $classes ) ),
			esc_html( self::count_label( isset( $section['members'] ) ? count( $section['members'] ) : 0 ) )
		);
	}

	/**
	 * Ensures a future refresh event exists.
	 *
	 * @return void
	 */
	public static function ensure_refresh_schedule() {
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}

		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::CRON_HOOK );
	}

	/**
	 * Cron entrypoint for refreshing the local snapshot.
	 *
	 * @return void
	 */
	public static function refresh_snapshot_job() {
		self::refresh_snapshot( 'cron' );
		wp_schedule_single_event( time() + self::get_update_interval(), self::CRON_HOOK );
	}

	/**
	 * Refreshes the local snapshot from Django. Safe to call from cron, CLI or
	 * admin actions. Never used during frontend rendering.
	 *
	 * @param string $context Refresh context.
	 * @return array|WP_Error
	 */
	public static function refresh_snapshot( $context = 'manual' ) {
		if ( get_transient( self::LOCK_KEY ) ) {
			$error = new WP_Error( 'avf_members_refresh_locked', 'A members snapshot refresh is already running.' );
			self::store_refresh_status( $error, null, $context );
			return $error;
		}

		set_transient( self::LOCK_KEY, 1, MINUTE_IN_SECONDS );

		$url = self::get_api_url();
		if ( '' === $url ) {
			$error = new WP_Error( 'avf_members_no_api_base', 'No members API base configured.' );
			self::store_refresh_status( $error, null, $context );
			delete_transient( self::LOCK_KEY );
			return $error;
		}

		$data = self::request_json( $url );
		if ( is_wp_error( $data ) ) {
			self::store_refresh_status( $data, null, $context );
			delete_transient( self::LOCK_KEY );
			return $data;
		}

		$payload = self::normalize_remote_payload( $data );
		if ( is_wp_error( $payload ) ) {
			self::store_refresh_status( $payload, null, $context );
			delete_transient( self::LOCK_KEY );
			return $payload;
		}

		if ( class_exists( 'AVF_Member_Privacy' ) && method_exists( 'AVF_Member_Privacy', 'import_snapshot_payload' ) ) {
			$payload = AVF_Member_Privacy::import_snapshot_payload( $payload );
			if ( is_wp_error( $payload ) ) {
				self::store_refresh_status( $payload, null, $context );
				delete_transient( self::LOCK_KEY );
				return $payload;
			}
		}

		update_option( self::SNAPSHOT_OPTION, $payload, false );
		self::store_refresh_status( null, $payload, $context );
		delete_transient( self::LOCK_KEY );
		return $payload;
	}

	/**
	 * Registers the admin status page.
	 *
	 * @return void
	 */
	public static function register_admin_page() {
		add_management_page(
			'AVF Mitglieder',
			'AVF Mitglieder',
			'manage_options',
			'avf-members-status',
			array( __CLASS__, 'render_admin_page' )
		);
	}

	/**
	 * Handles the manual refresh action from wp-admin.
	 *
	 * @return void
	 */
	public static function handle_manual_refresh() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'default' ) );
		}

		check_admin_referer( 'avf_members_refresh_snapshot' );

		$result = self::refresh_snapshot( 'manual' );
		$status = is_wp_error( $result ) ? 'error' : 'success';

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'avf-members-status',
					'refresh' => $status,
				),
				admin_url( 'tools.php' )
			)
		);
		exit;
	}

	/**
	 * Renders the admin snapshot status page.
	 *
	 * @return void
	 */
	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$status   = self::get_status();
		$snapshot = get_option( self::SNAPSHOT_OPTION, array() );
		?>
		<div class="wrap">
			<h1>AVF Mitglieder</h1>
			<?php if ( isset( $_GET['refresh'] ) ) : ?>
				<div class="notice notice-<?php echo 'success' === sanitize_key( $_GET['refresh'] ) ? 'success' : 'error'; ?> is-dismissible">
					<p>
						<?php echo 'success' === sanitize_key( $_GET['refresh'] ) ? esc_html__( 'Mitglieder-Snapshot aktualisiert.', 'default' ) : esc_html__( 'Mitglieder-Snapshot konnte nicht aktualisiert werden. Der letzte gueltige Snapshot bleibt aktiv.', 'default' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<p>Das Frontend rendert ausschliesslich den lokal gespeicherten Snapshot. Netzwerkzugriffe auf Django finden nur bei Cron-, CLI- oder manuellen Aktualisierungen statt.</p>

			<table class="widefat striped" style="max-width: 960px">
				<tbody>
					<tr><td><strong>API-URL</strong></td><td><code><?php echo esc_html( self::get_api_url() ); ?></code></td></tr>
					<tr><td><strong>Schema-Version</strong></td><td><?php echo isset( $status['schema_version'] ) ? (int) $status['schema_version'] : 0; ?></td></tr>
					<tr><td><strong>Snapshot generiert</strong></td><td><?php echo ! empty( $status['generated_at'] ) ? esc_html( $status['generated_at'] ) : '&ndash;'; ?></td></tr>
					<tr><td><strong>Letzter Erfolg</strong></td><td><?php echo ! empty( $status['last_success_at'] ) ? esc_html( $status['last_success_at'] ) : '&ndash;'; ?></td></tr>
					<tr><td><strong>Letzter Versuch</strong></td><td><?php echo ! empty( $status['last_attempt_at'] ) ? esc_html( $status['last_attempt_at'] ) : '&ndash;'; ?></td></tr>
					<tr><td><strong>Mitgliederanzahl</strong></td><td><?php echo isset( $status['member_count'] ) ? (int) $status['member_count'] : 0; ?></td></tr>
					<tr><td><strong>Content-Hash</strong></td><td><code><?php echo ! empty( $status['content_hash'] ) ? esc_html( $status['content_hash'] ) : '&ndash;'; ?></code></td></tr>
					<tr><td><strong>Naechste Aktualisierung</strong></td><td><?php echo ! empty( $status['next_refresh_at'] ) ? esc_html( $status['next_refresh_at'] ) : '&ndash;'; ?></td></tr>
					<tr><td><strong>Letzter Fehler</strong></td><td><?php echo ! empty( $status['last_error'] ) ? esc_html( $status['last_error'] ) : '&ndash;'; ?></td></tr>
					<tr><td><strong>Snapshot vorhanden</strong></td><td><?php echo is_array( $snapshot ) && ! empty( $snapshot ) ? 'ja' : 'nein'; ?></td></tr>
				</tbody>
			</table>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 16px;">
				<?php wp_nonce_field( 'avf_members_refresh_snapshot' ); ?>
				<input type="hidden" name="action" value="avf_members_refresh_snapshot">
				<?php submit_button( 'Snapshot jetzt aktualisieren', 'primary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Returns the validated local snapshot used by the frontend.
	 *
	 * @return array|WP_Error
	 */
	private static function get_snapshot_payload() {
		$snapshot = get_option( self::SNAPSHOT_OPTION, array() );
		if ( ! is_array( $snapshot ) || empty( $snapshot ) ) {
			return new WP_Error( 'avf_members_no_snapshot', 'No members snapshot is currently available.' );
		}

		return self::normalize_stored_snapshot( $snapshot );
	}

	/**
	 * Builds the members API endpoint from WordPress settings.
	 *
	 * @return string
	 */
	private static function get_api_url() {
		if ( class_exists( 'AVF_Internal_Endpoint_Resolver' ) ) {
			$url = AVF_Internal_Endpoint_Resolver::instance()->build_url( '/api/v1/public/members/' );
			if ( ! is_wp_error( $url ) ) {
				return $url;
			}
		}

		$candidates = array();
		if ( defined( 'AVF_MEMBERS_API_URL' ) && is_string( AVF_MEMBERS_API_URL ) ) {
			$candidates[] = AVF_MEMBERS_API_URL;
		}

		$members_base = get_option( 'avf_members_api_base', '' );
		if ( is_string( $members_base ) && '' !== trim( $members_base ) ) {
			$candidates[] = trailingslashit( trim( $members_base ) ) . 'members/';
		}

		$events_base = get_option( 'avf_events_api_base', '' );
		if ( is_string( $events_base ) && '' !== trim( $events_base ) ) {
			$candidates[] = trailingslashit( trim( $events_base ) ) . 'members/';
		}

		foreach ( $candidates as $candidate ) {
			$validated = self::validate_api_url( $candidate );
			if ( '' !== $validated ) {
				return $validated;
			}
		}

		return '';
	}

	/**
	 * Requests JSON from the Django API. Only used by snapshot refresh flows.
	 *
	 * @param string $url Full endpoint URL.
	 * @return array|WP_Error
	 */
	private static function request_json( $url ) {
		if ( function_exists( 'avf_internal_api_request' ) && class_exists( 'AVF_Internal_Endpoint_Resolver' ) ) {
			$response = avf_internal_api_request(
				'GET',
				'/api/v1/public/members/',
				array(
					'timeout' => self::REQUEST_TIMEOUT,
					'headers' => array(
						'Accept' => 'application/json',
					),
				)
			);
		} else {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => self::REQUEST_TIMEOUT,
				'redirection' => 3,
				'headers'     => array(
					'Accept' => 'application/json',
				),
			)
		);
		}

		if ( is_wp_error( $response ) ) {
			self::log( 'Refresh request failed: ' . $response->get_error_message() );
			return new WP_Error( 'avf_members_request_failed', 'The members request failed.' );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			self::log( 'Refresh returned unexpected HTTP status: ' . $code );
			return new WP_Error( 'avf_members_bad_status', 'The members API returned an unexpected status.', array( 'http_code' => $code ) );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( '' === trim( (string) $body ) ) {
			self::log( 'Refresh returned an empty response body.' );
			return new WP_Error( 'avf_members_empty_body', 'The members API returned an empty response.', array( 'http_code' => $code ) );
		}

		$data = json_decode( $body, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			self::log( 'Refresh returned invalid JSON.' );
			return new WP_Error( 'avf_members_invalid_json', 'The members API returned invalid JSON.', array( 'http_code' => $code ) );
		}

		return $data;
	}

	/**
	 * Validates and normalizes the snapshot payload into a stable structure.
	 *
	 * @param array $data Raw decoded JSON.
	 * @return array|WP_Error
	 */
	private static function normalize_remote_payload( array $data ) {
		$schema_version = isset( $data['schema_version'] ) ? (int) $data['schema_version'] : 0;
		if ( ! self::is_supported_schema_version( $schema_version ) ) {
			return new WP_Error( 'avf_members_schema_mismatch', 'The members snapshot schema version is unsupported.' );
		}

		if ( empty( $data['sections'] ) || ! is_array( $data['sections'] ) ) {
			return new WP_Error( 'avf_members_missing_sections', 'The members snapshot is missing sections.' );
		}

		$expected_hash = isset( $data['content_hash'] ) && is_scalar( $data['content_hash'] )
			? (string) $data['content_hash']
			: '';
		if ( ! self::hash_matches( $data, $expected_hash ) ) {
			return new WP_Error( 'avf_members_hash_mismatch', 'The members snapshot content hash is invalid.' );
		}

		$normalized = array(
			'schema_version' => $schema_version,
			'generated_at'   => isset( $data['generated_at'] ) && is_scalar( $data['generated_at'] ) ? (string) $data['generated_at'] : '',
			'content_hash'   => $expected_hash,
			'member_count'   => isset( $data['member_count'] ) && is_numeric( $data['member_count'] ) ? (int) $data['member_count'] : 0,
			'section_order'  => self::$section_order,
			'sections'       => array(),
		);

		foreach ( self::$section_order as $section_key ) {
			$section = isset( $data['sections'][ $section_key ] ) && is_array( $data['sections'][ $section_key ] )
				? $data['sections'][ $section_key ]
				: array();

			$title = isset( $section['title'] ) && is_scalar( $section['title'] ) ? trim( (string) $section['title'] ) : self::default_section_title( $section_key );
			$raw_members = isset( $section['members'] ) && is_array( $section['members'] ) ? $section['members'] : array();
			$members = array();
			foreach ( $raw_members as $member ) {
				$normalized_member = self::normalize_member( $member );
				if ( null !== $normalized_member ) {
					$members[] = $normalized_member;
				}
			}

			$normalized['sections'][ $section_key ] = array(
				'title'   => $title,
				'count'   => isset( $section['count'] ) && is_numeric( $section['count'] ) ? (int) $section['count'] : count( $members ),
				'members' => $members,
			);
		}

		return $normalized;
	}

	/**
	 * Validates the stored local snapshot format used by the frontend after the
	 * private media import has already completed.
	 *
	 * @param array $data Stored local snapshot.
	 * @return array|WP_Error
	 */
	private static function normalize_stored_snapshot( array $data ) {
		$schema_version = isset( $data['schema_version'] ) ? (int) $data['schema_version'] : 0;
		if ( ! self::is_supported_schema_version( $schema_version ) ) {
			return new WP_Error( 'avf_members_schema_mismatch', 'The stored members snapshot schema version is unsupported.' );
		}

		if ( empty( $data['sections'] ) || ! is_array( $data['sections'] ) ) {
			return new WP_Error( 'avf_members_missing_sections', 'The stored members snapshot is missing sections.' );
		}

		$normalized = array(
			'schema_version' => $schema_version,
			'generated_at'   => isset( $data['generated_at'] ) && is_scalar( $data['generated_at'] ) ? (string) $data['generated_at'] : '',
			'content_hash'   => isset( $data['content_hash'] ) && is_scalar( $data['content_hash'] ) ? (string) $data['content_hash'] : '',
			'member_count'   => isset( $data['member_count'] ) && is_numeric( $data['member_count'] ) ? (int) $data['member_count'] : 0,
			'section_order'  => self::$section_order,
			'sections'       => array(),
		);

		foreach ( self::$section_order as $section_key ) {
			$section = isset( $data['sections'][ $section_key ] ) && is_array( $data['sections'][ $section_key ] )
				? $data['sections'][ $section_key ]
				: array();

			$title = isset( $section['title'] ) && is_scalar( $section['title'] ) ? trim( (string) $section['title'] ) : self::default_section_title( $section_key );
			$raw_members = isset( $section['members'] ) && is_array( $section['members'] ) ? $section['members'] : array();
			$members = array();
			foreach ( $raw_members as $member ) {
				$normalized_member = self::normalize_stored_member( $member );
				if ( null !== $normalized_member ) {
					$members[] = $normalized_member;
				}
			}

			$normalized['sections'][ $section_key ] = array(
				'title'   => $title,
				'count'   => isset( $section['count'] ) && is_numeric( $section['count'] ) ? (int) $section['count'] : count( $members ),
				'members' => $members,
			);
		}

		return $normalized;
	}

	/**
	 * Keeps the deployed WordPress snapshot readable while Django moves from
	 * schema V2 to V3. V2 deliberately keeps its original hash structure.
	 *
	 * @param int $schema_version Snapshot schema version.
	 * @return bool
	 */
	private static function is_supported_schema_version( $schema_version ) {
		return in_array( (int) $schema_version, array( self::LEGACY_SCHEMA_VERSION, self::SCHEMA_VERSION ), true );
	}

	/**
	 * Returns only a known static avatar key. It is never used as a path or URL.
	 *
	 * @param mixed $value Raw snapshot value.
	 * @return string|null
	 */
	private static function normalize_avatar_icon( $value ) {
		if ( ! is_string( $value ) ) {
			return null;
		}

		$value = trim( $value );
		return isset( self::AVATAR_ICON_FILES[ $value ] ) ? $value : null;
	}

	/**
	 * Resolves an icon only through the fixed server-side mapping.
	 *
	 * @param string|null $avatar_icon Validated avatar key.
	 * @return string
	 */
	private static function avatar_icon_url( $avatar_icon ) {
		if ( ! is_string( $avatar_icon ) || ! isset( self::AVATAR_ICON_FILES[ $avatar_icon ] ) ) {
			return '';
		}

		$filename = self::AVATAR_ICON_FILES[ $avatar_icon ];
		$path = plugin_dir_path( __FILE__ ) . 'assets/member-avatar-icons/' . $filename;
		if ( ! is_readable( $path ) ) {
			return '';
		}

		$url = content_url( 'mu-plugins/assets/member-avatar-icons/' . $filename );
		return add_query_arg( 'ver', (string) filemtime( $path ), $url );
	}

	/**
	 * Returns one validated section payload.
	 *
	 * @param array  $payload     Normalized payload.
	 * @param string $section_key Canonical section key.
	 * @return array
	 */
	private static function get_section_payload( array $payload, $section_key ) {
		if ( ! isset( $payload['sections'][ $section_key ] ) || ! is_array( $payload['sections'][ $section_key ] ) ) {
			return array(
				'title'   => self::default_section_title( $section_key ),
				'count'   => 0,
				'members' => array(),
			);
		}

		return $payload['sections'][ $section_key ];
	}

	/**
	 * Normalizes a single member record.
	 *
	 * @param mixed $member Raw member data.
	 * @return array|null
	 */
	private static function normalize_member( $member ) {
		if ( ! is_array( $member ) ) {
			return null;
		}

		$display_name = isset( $member['display_name'] ) && is_scalar( $member['display_name'] ) ? trim( (string) $member['display_name'] ) : '';
		if ( '' === $display_name && isset( $member['name'] ) && is_scalar( $member['name'] ) ) {
			$display_name = trim( (string) $member['name'] );
		}
		if ( '' === $display_name ) {
			return null;
		}

		$roles = array();
		if ( isset( $member['roles'] ) && is_array( $member['roles'] ) ) {
			foreach ( $member['roles'] as $role ) {
				if ( ! is_array( $role ) ) {
					continue;
				}
				$label = isset( $role['label'] ) && is_scalar( $role['label'] ) ? trim( (string) $role['label'] ) : '';
				if ( '' === $label ) {
					continue;
				}
				$roles[] = array(
					'key'        => isset( $role['key'] ) && is_scalar( $role['key'] ) ? sanitize_key( $role['key'] ) : '',
					'label'      => $label,
					'sort_order' => isset( $role['sort_order'] ) && is_numeric( $role['sort_order'] ) ? (int) $role['sort_order'] : 999,
				);
			}
		}

		usort(
			$roles,
			static function ( $left, $right ) {
				if ( $left['sort_order'] === $right['sort_order'] ) {
					return strcmp( $left['label'], $right['label'] );
				}
				return $left['sort_order'] <=> $right['sort_order'];
			}
		);

		$photo = array(
			'fallback' => true,
			'variants' => array(),
		);
		if ( isset( $member['photo'] ) && is_array( $member['photo'] ) ) {
			$photo['fallback'] = ! empty( $member['photo']['fallback'] );
			if ( isset( $member['photo']['variants'] ) && is_array( $member['photo']['variants'] ) ) {
				foreach ( array( 'small', 'medium', 'large' ) as $variant_key ) {
					$variant = isset( $member['photo']['variants'][ $variant_key ] ) && is_array( $member['photo']['variants'][ $variant_key ] )
						? $member['photo']['variants'][ $variant_key ]
						: array();
					$url = isset( $variant['url'] ) && is_scalar( $variant['url'] ) ? trim( (string) $variant['url'] ) : '';
					if ( '' === $url ) {
						continue;
					}

					$path = '';
					if ( class_exists( 'AVF_Internal_Endpoint_Resolver' ) ) {
						$path = AVF_Internal_Endpoint_Resolver::instance()->extract_known_internal_path( $url );
						if ( '' !== $path ) {
							$url = AVF_Internal_Endpoint_Resolver::instance()->normalize_known_internal_url( $path );
						}
					}

					$photo['variants'][ $variant_key ] = array(
						'url'    => esc_url_raw( $url ),
						'path'   => $path,
						'width'  => isset( $variant['width'] ) && is_numeric( $variant['width'] ) ? (int) $variant['width'] : 0,
						'height' => isset( $variant['height'] ) && is_numeric( $variant['height'] ) ? (int) $variant['height'] : 0,
					);
				}
			}
		}

		return array(
			'id'           => isset( $member['id'] ) && is_numeric( $member['id'] ) ? (int) $member['id'] : 0,
			'display_name' => $display_name,
			'first_name'   => isset( $member['first_name'] ) && is_scalar( $member['first_name'] ) ? trim( (string) $member['first_name'] ) : '',
			'last_name'    => isset( $member['last_name'] ) && is_scalar( $member['last_name'] ) ? trim( (string) $member['last_name'] ) : '',
			'vulgo'        => isset( $member['vulgo'] ) && is_scalar( $member['vulgo'] ) ? trim( (string) $member['vulgo'] ) : '',
			'entry_year'   => isset( $member['entry_year'] ) && is_numeric( $member['entry_year'] ) ? (int) $member['entry_year'] : null,
			'entry_semester' => isset( $member['entry_semester'] ) && is_scalar( $member['entry_semester'] ) ? strtoupper( trim( (string) $member['entry_semester'] ) ) : '',
			'entry_display' => isset( $member['entry_display'] ) && is_scalar( $member['entry_display'] ) ? trim( (string) $member['entry_display'] ) : '',
			'academic_title' => isset( $member['academic_title'] ) && is_scalar( $member['academic_title'] ) ? trim( (string) $member['academic_title'] ) : '',
			'degree_program' => isset( $member['degree_program'] ) && is_scalar( $member['degree_program'] ) ? trim( (string) $member['degree_program'] ) : '',
			'roles'        => $roles,
			'avatar_icon'  => self::normalize_avatar_icon( isset( $member['avatar_icon'] ) ? $member['avatar_icon'] : null ),
			'photo'        => $photo,
		);
	}

	/**
	 * Validates one stored local member record with token-based photo variants.
	 *
	 * @param mixed $member Stored member data.
	 * @return array|null
	 */
	private static function normalize_stored_member( $member ) {
		$normalized = self::normalize_member( $member );
		if ( ! is_array( $normalized ) ) {
			return null;
		}

		$stored_photo = isset( $member['photo'] ) && is_array( $member['photo'] ) ? $member['photo'] : array();
		$photo = array(
			'available' => ! empty( $stored_photo['available'] ),
			'fallback'  => ! empty( $stored_photo['fallback'] ),
			'token'     => isset( $stored_photo['token'] ) && is_scalar( $stored_photo['token'] ) ? strtolower( trim( (string) $stored_photo['token'] ) ) : '',
			'variants'  => array(),
		);

		if ( isset( $stored_photo['variants'] ) && is_array( $stored_photo['variants'] ) ) {
			foreach ( array( 'small', 'medium', 'large' ) as $variant_key ) {
				if ( empty( $stored_photo['variants'][ $variant_key ] ) || ! is_array( $stored_photo['variants'][ $variant_key ] ) ) {
					continue;
				}

				$variant = $stored_photo['variants'][ $variant_key ];
				$url = isset( $variant['url'] ) && is_scalar( $variant['url'] ) ? esc_url_raw( trim( (string) $variant['url'] ) ) : '';
				if ( '' === $url ) {
					continue;
				}

				$photo['variants'][ $variant_key ] = array(
					'token'  => isset( $variant['token'] ) && is_scalar( $variant['token'] ) ? strtolower( trim( (string) $variant['token'] ) ) : '',
					'url'    => $url,
					'width'  => isset( $variant['width'] ) && is_numeric( $variant['width'] ) ? (int) $variant['width'] : 0,
					'height' => isset( $variant['height'] ) && is_numeric( $variant['height'] ) ? (int) $variant['height'] : 0,
				);
			}
		}

		$normalized['photo'] = $photo;
		$normalized['avatar_icon'] = self::normalize_avatar_icon( isset( $member['avatar_icon'] ) ? $member['avatar_icon'] : null );
		return $normalized;
	}

	/**
	 * Renders one public members section.
	 *
	 * @param string $section_key    Canonical section key.
	 * @param array  $section        Normalized section payload.
	 * @param bool   $show_headings  Whether the section heading should render.
	 * @param bool   $show_count     Whether the member count should render.
	 * @param bool   $show_empty     Whether empty sections should render.
	 * @return string
	 */
	private static function render_section( $section_key, array $section, $show_headings, $show_count, $show_empty ) {
		$members = isset( $section['members'] ) ? $section['members'] : array();
		if ( empty( $members ) && ! $show_empty ) {
			return '';
		}

		$title         = isset( $section['title'] ) ? $section['title'] : self::default_section_title( $section_key );
		$section_class = 'avf-members-section avf-members-section--' . str_replace( '_', '-', $section_key );
		$show_role     = self::section_shows_role( $section_key );

		ob_start();
		?>
		<section class="<?php echo esc_attr( $section_class ); ?>">
			<?php if ( $show_headings ) : ?>
				<div class="avf-members-section__heading">
					<h2 class="avf-members-section__title"><?php echo esc_html( $title ); ?></h2>
					<?php if ( $show_count ) : ?>
						<p class="avf-members-section__count"><?php echo esc_html( self::count_label( count( $members ) ) ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $members ) ) : ?>
				<div class="avf-members-grid avf-members-grid--<?php echo esc_attr( str_replace( '_', '-', $section_key ) ); ?>">
					<?php foreach ( array_values( $members ) as $index => $member ) : ?>
						<?php echo self::render_card( $member, $section_key, $show_role, 0 === $index ? 'eager' : 'lazy' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="avf-members-empty"><?php esc_html_e( 'Aktuell sind noch keine Mitglieder fuer diesen Bereich hinterlegt.', 'default' ); ?></p>
			<?php endif; ?>
		</section>
		<?php
		return ob_get_clean();
	}

	/**
	 * Renders a single member card.
	 *
	 * @param array  $member         Normalized member data.
	 * @param string $section_key    Canonical section key.
	 * @param bool   $show_role      Whether role labels should render.
	 * @param string $loading_mode   'lazy' or 'eager'.
	 * @return string
	 */
	private static function render_card( array $member, $section_key, $show_role, $loading_mode ) {
		$photo              = isset( $member['photo'] ) ? $member['photo'] : array();
		$avatar_icon        = self::normalize_avatar_icon( isset( $member['avatar_icon'] ) ? $member['avatar_icon'] : null );
		$avatar_icon_url    = self::avatar_icon_url( $avatar_icon );
		$src_variant        = isset( $photo['variants']['medium'] ) ? self::resolve_photo_variant( $photo['variants']['medium'] ) : null;
		$srcset             = self::build_srcset( isset( $photo['variants'] ) ? $photo['variants'] : array() );
		$role_text          = self::role_text( $member, $section_key );
		$name_parts         = self::display_name_parts( $member['display_name'] );
		$detail_subject     = '' !== trim( (string) $member['vulgo'] ) ? trim( (string) $member['vulgo'] ) : 'Mitglied';
		$show_details_label = sprintf( 'Mehr Infos zu %s anzeigen', $detail_subject );
		$hide_details_label = sprintf( 'Mehr Infos zu %s ausblenden', $detail_subject );
		$entry_value        = self::member_detail_value( isset( $member['entry_display'] ) ? $member['entry_display'] : '' );
		$degree_value       = self::member_detail_value( isset( $member['academic_title'] ) ? $member['academic_title'] : '' );
		$program_value      = self::member_detail_value( isset( $member['degree_program'] ) ? $member['degree_program'] : '' );
		$details_panel_id   = sprintf( 'avf-member-details-%1$s-%2$s', sanitize_key( $section_key ), $member['id'] > 0 ? $member['id'] : md5( $member['display_name'] ) );

		ob_start();
		?>
		<article class="avf-member-card" data-avf-member-card="1">
			<div class="avf-member-card__surface">
				<div class="avf-member-card__media">
					<?php if ( null !== $avatar_icon && '' !== $avatar_icon_url ) : ?>
						<img
							class="avf-member-card__image"
							src="<?php echo esc_url( $avatar_icon_url ); ?>"
							width="384"
							height="384"
							loading="<?php echo esc_attr( $loading_mode ); ?>"
							decoding="async"
							alt=""
							aria-hidden="true"
						>
					<?php elseif ( null !== $avatar_icon ) : ?>
						<span class="avf-member-card__placeholder" aria-hidden="true"><?php echo esc_html( self::initials( $member['display_name'] ) ); ?></span>
					<?php elseif ( $src_variant && ! empty( $src_variant['url'] ) ) : ?>
						<img
							class="avf-member-card__image"
							src="<?php echo esc_url( $src_variant['url'] ); ?>"
							data-avf-member-photo="1"
							data-avf-member-initials="<?php echo esc_attr( self::initials( $member['display_name'] ) ); ?>"
							<?php if ( '' !== $srcset ) : ?>
								srcset="<?php echo esc_attr( $srcset ); ?>"
								sizes="(max-width: 767px) 82vw, (max-width: 1024px) 45vw, 320px"
							<?php endif; ?>
							width="<?php echo (int) $src_variant['width']; ?>"
							height="<?php echo (int) $src_variant['height']; ?>"
							loading="<?php echo esc_attr( $loading_mode ); ?>"
							decoding="async"
							alt=""
							aria-hidden="true"
						>
					<?php else : ?>
						<span class="avf-member-card__placeholder" aria-hidden="true"><?php echo esc_html( self::initials( $member['display_name'] ) ); ?></span>
					<?php endif; ?>
				</div>

				<div class="avf-member-card__body">
					<?php if ( $show_role && '' !== $role_text ) : ?>
						<span class="avf-member-card__role"><?php echo esc_html( $role_text ); ?></span>
					<?php endif; ?>

					<?php if ( '' !== $member['vulgo'] ) : ?>
						<span class="avf-member-card__name"><?php echo esc_html( $member['vulgo'] ); ?></span>
					<?php endif; ?>

					<span class="avf-member-card__vulgo" data-avf-member-display-name="1">
						<span class="avf-member-card__vulgo-part avf-member-card__vulgo-part--primary"><?php echo esc_html( $name_parts['primary'] ); ?></span>
						<?php if ( '' !== $name_parts['secondary'] ) : ?>
							<span class="avf-member-card__vulgo-part avf-member-card__vulgo-part--secondary"><?php echo esc_html( $name_parts['secondary'] ); ?></span>
						<?php endif; ?>
					</span>
				</div>

				<button
					type="button"
					class="avf-member-card__toggle"
					data-avf-member-toggle="1"
					aria-expanded="false"
					aria-controls="<?php echo esc_attr( $details_panel_id ); ?>"
					aria-label="<?php echo esc_attr( $show_details_label ); ?>"
					data-avf-label-expand="<?php echo esc_attr( $show_details_label ); ?>"
					data-avf-label-collapse="<?php echo esc_attr( $hide_details_label ); ?>"
				>
					Mehr Infos
				</button>
			</div>

			<div
				id="<?php echo esc_attr( $details_panel_id ); ?>"
				class="avf-member-card__details-panel"
				data-avf-member-panel="1"
				hidden
			>
				<div class="avf-member-card__back-inner">
					<span class="avf-member-card__details">
						<span class="avf-member-card__detail">
							<span class="avf-member-card__detail-label">Eintritt</span>
							<span class="avf-member-card__detail-value"><?php echo esc_html( $entry_value ); ?></span>
						</span>
						<span class="avf-member-card__detail">
							<span class="avf-member-card__detail-label">Abschluss</span>
							<span class="avf-member-card__detail-value"><?php echo esc_html( $degree_value ); ?></span>
						</span>
						<span class="avf-member-card__detail">
							<span class="avf-member-card__detail-label">Studiengang</span>
							<span class="avf-member-card__detail-value"><?php echo esc_html( $program_value ); ?></span>
						</span>
					</span>
				</div>
			</div>
		</article>
		<?php
		return ob_get_clean();
	}

	/**
	 * Renders the visitor/admin message when no local snapshot is available.
	 *
	 * @param WP_Error $error Snapshot error.
	 * @return string
	 */
	private static function render_unavailable_state() {
		if ( self::$rendered_unavailable ) {
			return '';
		}

		self::$rendered_unavailable = true;
		$message = '<p class="avf-members-empty">' .
			esc_html__( 'Die Mitgliederdaten sind derzeit voruebergehend nicht verfuegbar.', 'default' ) .
		'</p>';

		if ( current_user_can( 'manage_options' ) ) {
			$message .= sprintf(
				'<p class="avf-members-admin-note">%1$s</p>',
				esc_html__( 'Hinweis nur fuer Administratoren: Bitte pruefe den Snapshot-Status unter Werkzeuge -> AVF Mitglieder.', 'default' )
			);
		}

		return '<div class="avf-members-sync">' . $message . '</div>';
	}

	/**
	 * Renders the state when all requested sections are empty.
	 *
	 * @return string
	 */
	private static function render_empty_state() {
		self::enqueue_assets();
		return '<div class="avf-members-sync"><p class="avf-members-empty">' .
			esc_html__( 'Aktuell sind noch keine oeffentlichen Mitglieder hinterlegt.', 'default' ) .
		'</p></div>';
	}

	/**
	 * Registers and injects the shortcode CSS once per request.
	 *
	 * @return void
	 */
	private static function enqueue_assets() {
		static $done = false;

		if ( $done ) {
			return;
		}

		$done = true;

		wp_register_style( 'avf-members-page', false, array(), '4.3.3' );
		wp_enqueue_style( 'avf-members-page' );
		wp_add_inline_style( 'avf-members-page', self::css() );
		wp_enqueue_script(
			'avf-members-page',
			content_url( 'mu-plugins/assets/js/avf-members-page.js' ),
			array(),
			'1.3.2',
			true
		);
	}

	/**
	 * Returns the stylesheet for the public members markup.
	 *
	 * @return string
	 */
	private static function css() {
		return <<<'CSS'
.avf-members-sync {
	display: grid;
	gap: 32px;
}

.avf-members-section {
	display: grid;
	gap: 24px;
}

.avf-members-section__heading {
	display: grid;
	grid-template-columns: minmax(0, 1fr) auto;
	align-items: end;
	gap: 14px 28px;
	padding-bottom: 18px;
	border-bottom: 1px solid rgba(24, 63, 30, 0.14);
}

.avf-members-section__title,
.avf-member-card__name {
	margin: 0;
	font-family: "Cormorant Garamond", serif;
	color: #183f1e;
}

.avf-members-section__title {
	font-size: clamp(2.1rem, 4vw, 3.5rem);
	line-height: 0.98;
	font-weight: 600;
}

.avf-members-section__count,
.avf-members-count,
.avf-member-card__vulgo,
.avf-members-empty,
.avf-members-admin-note {
	margin: 0;
	color: #667166;
	font-size: 1rem;
	line-height: 1.65;
}

.avf-members-grid {
	display: flex;
	flex-wrap: wrap;
	gap: 18px;
	width: min(100%, 1200px);
	margin-inline: auto;
	justify-content: center;
	align-items: stretch;
}

.avf-members-grid--committee {
	display: flex;
	flex-wrap: wrap;
	justify-content: center;
	gap: 22px;
	width: min(100%, 1080px);
}

@media (max-width: 1024px) {
	.avf-members-grid--committee {
		max-width: none;
	}
}

.avf-member-card {
	flex: 1 1 180px;
	min-width: 0;
	max-width: 220px;
	display: grid;
	align-content: start;
	gap: 12px;
}

.avf-members-grid--committee .avf-member-card {
	flex: 0 1 calc((100% - 44px) / 3);
	min-width: 220px;
	max-width: 320px;
}

@media (max-width: 1024px) {
	.avf-members-grid--committee .avf-member-card {
		flex-basis: calc((100% - 22px) / 2);
	}
}

.avf-member-card__toggle {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 100%;
	padding: 12px 14px;
	border: 1px solid rgba(24, 63, 30, 0.18);
	background: rgba(24, 63, 30, 0.04);
	appearance: none;
	-webkit-appearance: none;
	border-radius: 0 !important;
	box-shadow: none;
	text-align: center;
	font: inherit;
	font-size: 0.92rem;
	font-weight: 700;
	line-height: 1.2;
	color: #183f1e;
	cursor: pointer;
	transition: background-color 160ms ease, border-color 160ms ease, color 160ms ease;
}

.avf-member-card__toggle:focus-visible {
	outline: 3px solid rgba(216, 120, 54, 0.55);
	outline-offset: 4px;
}

.avf-member-card__toggle:hover,
.avf-member-card__toggle[aria-expanded="true"] {
	background: rgba(24, 63, 30, 0.1);
	border-color: rgba(24, 63, 30, 0.32);
}

.avf-member-card__surface,
.avf-member-card__details-panel {
	display: flex;
	flex-direction: column;
	min-width: 0;
	width: 100%;
	background: #fff;
	border: 1px solid rgba(24, 63, 30, 0.12);
	box-shadow: 0 24px 52px rgba(14, 23, 17, 0.14);
	overflow: hidden;
}

.avf-member-card__surface {
	height: 100%;
}

.avf-members-grid[data-avf-accordion-ready="1"] .avf-member-card__details-panel {
	display: grid;
	grid-template-rows: 0fr;
	opacity: 0;
	transition: grid-template-rows 240ms ease, opacity 220ms ease;
}

.avf-members-grid[data-avf-accordion-ready="1"] .avf-member-card__details-panel[hidden] {
	display: grid !important;
}

.avf-members-grid[data-avf-accordion-ready="1"] .avf-member-card.is-expanded .avf-member-card__details-panel {
	grid-template-rows: 1fr;
	opacity: 1;
}

.avf-members-grid[data-avf-accordion-ready="1"] .avf-member-card__details-panel > .avf-member-card__back-inner {
	min-height: 0;
	overflow: hidden;
}

.avf-member-card__media {
	width: 100%;
	overflow: hidden;
	background: linear-gradient(160deg, rgba(223, 230, 220, 0.95), rgba(239, 242, 236, 0.95));
}

.avf-member-card__image,
.avf-member-card__placeholder {
	display: block;
	width: 100%;
	aspect-ratio: 1 / 1;
}

.avf-member-card__image {
	height: auto;
	object-fit: cover;
	object-position: center center;
}

.avf-member-card__placeholder {
	display: flex;
	align-items: center;
	justify-content: center;
	color: rgba(36, 92, 43, 0.55);
	font-family: "Cormorant Garamond", serif;
	font-size: clamp(3rem, 4vw, 4.5rem);
	line-height: 1;
}

.avf-member-card__body {
	display: flex;
	flex: 1 1 auto;
	flex-direction: column;
	justify-content: flex-start;
	gap: 8px;
	padding: 14px 14px 16px;
	text-align: center;
}

.avf-member-card__role {
	display: flex;
	align-items: center;
	justify-content: center;
	margin: 0;
	min-height: calc(0.82rem * 1.35 * 2);
	color: #d87836;
	font-size: 0.82rem;
	font-weight: 700;
	line-height: 1.35;
	letter-spacing: 0.04em;
	text-wrap: balance;
}

.avf-member-card__name,
.avf-member-card__back-name {
	font-size: clamp(1.25rem, 1.5vw, 1.55rem);
	line-height: 1.02;
}

.avf-member-card__vulgo {
	margin-top: auto;
	font-size: 0.9rem;
	line-height: 1.35;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	min-height: calc(0.9rem * 1.35);
}

.avf-member-card__vulgo-part {
	display: inline;
}

.avf-members-grid--stacked-names .avf-member-card__vulgo {
	display: grid;
	gap: 2px;
	white-space: normal;
	overflow: visible;
	text-overflow: clip;
	min-height: calc(0.9rem * 1.35 * 2 + 2px);
}

.avf-members-grid--stacked-names .avf-member-card__vulgo-part {
	display: block;
}

.avf-member-card__back-inner {
	display: grid;
	gap: 14px;
	padding: 18px 16px 16px;
	text-align: left;
}

.avf-member-card__back-name {
	margin: 0;
}

.avf-member-card__details {
	display: grid;
	gap: 10px;
}

.avf-member-card__detail {
	display: grid;
	gap: 3px;
	min-width: 0;
}

.avf-member-card__detail-label {
	color: #667166;
	font-size: 0.78rem;
	font-weight: 700;
	letter-spacing: 0.05em;
	text-transform: uppercase;
}

.avf-member-card__detail-value {
	color: #183f1e;
	font-size: 0.95rem;
	line-height: 1.45;
	overflow-wrap: anywhere;
	word-break: break-word;
}

.avf-members-admin-note code {
	display: inline-block;
	margin-top: 8px;
	padding: 4px 8px;
	background: rgba(24, 63, 30, 0.06);
	color: #183f1e;
}

@media (max-width: 767px) {
	.avf-members-section__heading {
		grid-template-columns: minmax(0, 1fr);
	}

	.avf-members-grid--committee {
		justify-content: center;
	}

	.avf-members-grid--committee .avf-member-card {
		flex-basis: min(100%, 360px);
		max-width: 360px;
	}

	.avf-members-grid--salon .avf-member-card,
	.avf-members-grid--stall .avf-member-card,
	.avf-members-grid--altfroburger .avf-member-card,
	.avf-members-grid--af-committee .avf-member-card {
		flex-basis: calc(50% - 9px);
		max-width: calc(50% - 9px);
	}

	.avf-member-card__back-inner {
		padding-inline: 14px;
	}
}
CSS;
	}

	/**
	 * Returns flexible section keys from shortcode attributes.
	 *
	 * @param array $atts Parsed shortcode attributes.
	 * @return string[]
	 */
	private static function requested_sections( array $atts ) {
		$raw = '';

		if ( isset( $atts['section'] ) && '' !== trim( (string) $atts['section'] ) ) {
			$raw = (string) $atts['section'];
		} elseif ( isset( $atts['sections'] ) ) {
			$raw = (string) $atts['sections'];
		}

		if ( '' === trim( $raw ) ) {
			return self::$section_order;
		}

		$items = preg_split( '/[\s,;]+/', strtolower( $raw ) );
		$sections = array();

		foreach ( $items as $item ) {
			$item = self::normalize_section_key( $item );
			if ( '' === $item ) {
				continue;
			}
			if ( ! in_array( $item, $sections, true ) ) {
				$sections[] = $item;
			}
		}

		return empty( $sections ) ? self::$section_order : $sections;
	}

	/**
	 * Normalizes one flexible section alias.
	 *
	 * @param mixed $value Raw section attribute.
	 * @return string
	 */
	private static function normalize_section_key( $value ) {
		$value = strtolower( trim( (string) $value ) );
		$aliases = array(
			'committee'    => 'committee',
			'komitee'      => 'committee',
			'salon'        => 'salon',
			'stall'        => 'stall',
			'altfroburger' => 'altfroburger',
			'af_committee' => 'af_committee',
			'af-komitee'   => 'af_committee',
			'afkomitee'    => 'af_committee',
			'af-committee' => 'af_committee',
		);

		return isset( $aliases[ $value ] ) ? $aliases[ $value ] : '';
	}

	/**
	 * Whether a section should render explicit role labels on the cards.
	 *
	 * @param string $section_key Canonical section key.
	 * @return bool
	 */
	private static function section_shows_role( $section_key ) {
		return 'committee' === $section_key || 'af_committee' === $section_key;
	}

	/**
	 * Returns the human title for a section.
	 *
	 * @param string $section_key Canonical section key.
	 * @return string
	 */
	private static function default_section_title( $section_key ) {
		$titles = array(
			'committee'    => 'Komitee',
			'salon'        => 'Der Salon',
			'stall'        => 'Der Stall',
			'altfroburger' => 'Altfroburger',
			'af_committee' => 'Das Altfroburger-Komitee',
		);

		return isset( $titles[ $section_key ] ) ? $titles[ $section_key ] : $section_key;
	}

	/**
	 * Formats member counts for headings.
	 *
	 * @param int $count Member count.
	 * @return string
	 */
	private static function count_label( $count ) {
		return 1 === (int) $count ? '1 Mitglied' : (int) $count . ' Mitglieder';
	}

	/**
	 * Splits a public display name into a primary and secondary line.
	 *
	 * @param string $display_name Full public display name.
	 * @return array{primary:string,secondary:string}
	 */
	private static function display_name_parts( $display_name ) {
		$display_name = trim( (string) $display_name );

		if ( '' === $display_name ) {
			return array(
				'primary'   => '',
				'secondary' => '',
			);
		}

		$parts = preg_split( '/\s+/', $display_name );
		if ( ! is_array( $parts ) || count( $parts ) < 2 ) {
			return array(
				'primary'   => $display_name,
				'secondary' => '',
			);
		}

		return array(
			'primary'   => array_shift( $parts ),
			'secondary' => implode( ' ', $parts ),
		);
	}

	/**
	 * Builds srcset markup from the validated photo variants.
	 *
	 * @param array $variants Photo variants.
	 * @return string
	 */
	private static function build_srcset( array $variants ) {
		$entries = array();
		foreach ( array( 'small', 'medium', 'large' ) as $variant_key ) {
			if ( empty( $variants[ $variant_key ] ) || empty( $variants[ $variant_key ]['width'] ) ) {
				continue;
			}
			$variant = self::resolve_photo_variant( $variants[ $variant_key ] );
			if ( empty( $variant['url'] ) ) {
				continue;
			}
			$entries[] = esc_url( $variant['url'] ) . ' ' . (int) $variant['width'] . 'w';
		}

		return implode( ', ', $entries );
	}

	/**
	 * Resolves a cached photo variant against the current active internal host.
	 *
	 * @param array $variant Cached photo variant.
	 * @return array
	 */
	private static function resolve_photo_variant( array $variant ) {
		$variant['url'] = isset( $variant['url'] ) && is_scalar( $variant['url'] ) ? esc_url_raw( (string) $variant['url'] ) : '';
		if ( '' === $variant['url'] ) {
			return $variant;
		}

		if ( function_exists( 'avf_member_media_url_is_ready' ) && ! avf_member_media_url_is_ready( (string) $variant['url'] ) ) {
			$variant['url'] = '';
		}

		return $variant;
	}

	/**
	 * Renders all role labels for one member as plain text.
	 *
	 * @param array  $member      Normalized member data.
	 * @param string $section_key Canonical section key.
	 * @return string
	 */
	private static function role_text( array $member, $section_key ) {
		if ( empty( $member['roles'] ) || ! is_array( $member['roles'] ) ) {
			return '';
		}

		$labels = array();
		foreach ( $member['roles'] as $role ) {
			if ( self::should_render_public_role( $role ) && ! empty( $role['label'] ) ) {
				$labels[] = $role['label'];
			}
		}

		return implode( ' · ', $labels );
	}

	/**
	 * Whether a role label should be visible on the public website.
	 *
	 * @param array $role Normalized role data.
	 * @return bool
	 */
	private static function should_render_public_role( array $role ) {
		$key   = isset( $role['key'] ) && is_scalar( $role['key'] ) ? sanitize_key( $role['key'] ) : '';
		$label = isset( $role['label'] ) && is_scalar( $role['label'] ) ? trim( (string) $role['label'] ) : '';

		if ( 'web-x' === $key ) {
			return false;
		}

		if ( 'web-x' === sanitize_title( $label ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Creates a two-letter fallback from a full name.
	 *
	 * @param string $name Full name.
	 * @return string
	 */
	private static function initials( $name ) {
		$parts    = preg_split( '/\s+/', trim( (string) $name ) );
		$initials = '';

		foreach ( $parts as $part ) {
			if ( '' === $part ) {
				continue;
			}

			$initials .= strtoupper( substr( $part, 0, 1 ) );
			if ( strlen( $initials ) >= 2 ) {
				break;
			}
		}

		return '' !== $initials ? $initials : '?';
	}

	/**
	 * Returns the public full name for the card backside heading.
	 *
	 * @param array $member Normalized member data.
	 * @return string
	 */
	private static function member_full_name( array $member ) {
		$first_name = isset( $member['first_name'] ) && is_scalar( $member['first_name'] ) ? trim( (string) $member['first_name'] ) : '';
		$last_name  = isset( $member['last_name'] ) && is_scalar( $member['last_name'] ) ? trim( (string) $member['last_name'] ) : '';
		$full_name  = trim( $first_name . ' ' . $last_name );

		return '' !== $full_name ? $full_name : trim( (string) $member['display_name'] );
	}

	/**
	 * Normalizes optional public detail values for the card backside.
	 *
	 * @param mixed $value Raw field value.
	 * @return string
	 */
	private static function member_detail_value( $value ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		return '' !== $value ? $value : 'Nicht hinterlegt';
	}

	/**
	 * Parses flexible shortcode booleans.
	 *
	 * @param mixed $value Attribute value.
	 * @return bool
	 */
	private static function truthy( $value ) {
		$value = strtolower( trim( (string) $value ) );
		return ! in_array( $value, array( '', '0', 'false', 'off', 'no' ), true );
	}

	/**
	 * Stores refresh metadata while keeping the active snapshot untouched on failure.
	 *
	 * @param WP_Error|null $error   Refresh error.
	 * @param array|null    $payload Validated snapshot payload.
	 * @param string        $context Refresh context.
	 * @return void
	 */
	private static function store_refresh_status( $error, $payload, $context ) {
		$next_timestamp = wp_next_scheduled( self::CRON_HOOK );
		$status = array(
			'context'         => (string) $context,
			'last_attempt_at' => gmdate( 'c' ),
			'last_error'      => $error instanceof WP_Error ? $error->get_error_code() . ': ' . $error->get_error_message() : '',
			'next_refresh_at' => $next_timestamp ? gmdate( 'c', (int) $next_timestamp ) : '',
		);

		$existing = get_option( self::STATUS_OPTION, array() );
		if ( is_array( $existing ) ) {
			$status = array_merge( $existing, $status );
		}

		if ( is_array( $payload ) && ! empty( $payload ) ) {
			$status['last_success_at'] = gmdate( 'c' );
			$status['generated_at']    = isset( $payload['generated_at'] ) ? (string) $payload['generated_at'] : '';
			$status['schema_version']  = isset( $payload['schema_version'] ) ? (int) $payload['schema_version'] : 0;
			$status['member_count']    = isset( $payload['member_count'] ) ? (int) $payload['member_count'] : 0;
			$status['content_hash']    = isset( $payload['content_hash'] ) ? (string) $payload['content_hash'] : '';
		}

		update_option( self::STATUS_OPTION, $status, false );
	}

	/**
	 * Returns the stored refresh metadata.
	 *
	 * @return array
	 */
	private static function get_status() {
		$status = get_option( self::STATUS_OPTION, array() );
		return is_array( $status ) ? $status : array();
	}

	/**
	 * Returns the configured refresh interval.
	 *
	 * @return int
	 */
	private static function get_update_interval() {
		$interval = get_option( 'avf_members_update_interval', '' );
		if ( '' === $interval || null === $interval ) {
			$interval = get_option( 'avf_members_cache_ttl', '' );
		}
		if ( '' === $interval || null === $interval ) {
			$interval = get_option( 'avf_events_cache_ttl', self::DEFAULT_INTERVAL );
		}

		$interval = (int) $interval;
		if ( $interval < self::MIN_INTERVAL || $interval > self::MAX_INTERVAL ) {
			$interval = self::DEFAULT_INTERVAL;
		}

		return $interval;
	}

	/**
	 * Validates a configured members API URL.
	 *
	 * @param mixed $url Candidate URL.
	 * @return string
	 */
	private static function validate_api_url( $url ) {
		if ( ! is_scalar( $url ) ) {
			return '';
		}

		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}

		$validated = esc_url_raw( $url );
		if ( '' === $validated ) {
			return '';
		}

		$parts = wp_parse_url( $validated );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		return $validated;
	}

	/**
	 * Validates that the reported content hash matches the normalized snapshot.
	 *
	 * @param array  $payload       Normalized payload.
	 * @param string $expected_hash Hash from the payload.
	 * @return bool
	 */
	private static function hash_matches( array $payload, $expected_hash ) {
		if ( '' === $expected_hash || ! preg_match( '/^[0-9a-f]{64}$/', $expected_hash ) ) {
			return false;
		}

		$hash_payload = self::build_hash_payload( $payload );
		$hash_payload = self::canonicalize_hash_payload( $hash_payload );

		$raw = wp_json_encode( $hash_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false === $raw ) {
			return false;
		}

		return hash_equals( $expected_hash, hash( 'sha256', $raw ) );
	}

	/**
	 * Builds the canonical hash payload using only the fields Django includes in
	 * its content hash.
	 *
	 * @param array $payload Normalized snapshot payload.
	 * @return array
	 */
	private static function build_hash_payload( array $payload ) {
		$sections = array();

		foreach ( $payload['section_order'] as $section_key ) {
			$section = isset( $payload['sections'][ $section_key ] ) && is_array( $payload['sections'][ $section_key ] )
				? $payload['sections'][ $section_key ]
				: array();

			$members = array();
			if ( isset( $section['members'] ) && is_array( $section['members'] ) ) {
				foreach ( $section['members'] as $member ) {
					if ( ! is_array( $member ) ) {
						continue;
					}

					$photo = null;
					$variants = array();
					if ( isset( $member['photo'] ) && is_array( $member['photo'] ) ) {
						$photo = array(
							'fallback' => ! empty( $member['photo']['fallback'] ),
							'variants' => array(),
						);
					}
					if ( is_array( $photo ) && isset( $member['photo']['variants'] ) && is_array( $member['photo']['variants'] ) && ! empty( $member['photo']['variants'] ) ) {
						foreach ( $member['photo']['variants'] as $variant_key => $variant ) {
							if ( ! is_array( $variant ) ) {
								continue;
							}

							$variants[ $variant_key ] = array(
								'url'    => isset( $variant['url'] ) ? (string) $variant['url'] : '',
								'width'  => isset( $variant['width'] ) ? (int) $variant['width'] : 0,
								'height' => isset( $variant['height'] ) ? (int) $variant['height'] : 0,
							);
						}
					}

					if ( is_array( $photo ) ) {
						$photo['variants'] = empty( $variants ) ? array() : $variants;
					}

					$member_hash = array(
						// SEC-013: 'id' deliberately excluded from the hash - Django's
						// accounts/public_members.py::_content_hash() no longer includes
						// the internal database PK in the payload or its hash. Keep this
						// in sync with that function if either side changes again.
						'display_name' => isset( $member['display_name'] ) ? (string) $member['display_name'] : '',
						'first_name'   => isset( $member['first_name'] ) ? (string) $member['first_name'] : '',
						'last_name'    => isset( $member['last_name'] ) ? (string) $member['last_name'] : '',
						'vulgo'        => isset( $member['vulgo'] ) ? (string) $member['vulgo'] : '',
						'entry_year'   => isset( $member['entry_year'] ) && is_numeric( $member['entry_year'] ) ? (int) $member['entry_year'] : null,
						'entry_semester' => isset( $member['entry_semester'] ) ? (string) $member['entry_semester'] : '',
						'entry_display' => isset( $member['entry_display'] ) ? (string) $member['entry_display'] : '',
						'academic_title' => isset( $member['academic_title'] ) ? (string) $member['academic_title'] : '',
						'degree_program' => isset( $member['degree_program'] ) ? (string) $member['degree_program'] : '',
						'roles'        => isset( $member['roles'] ) && is_array( $member['roles'] ) ? $member['roles'] : array(),
						'photo'        => $photo,
					);
					if ( self::SCHEMA_VERSION === (int) $payload['schema_version'] ) {
						$member_hash['avatar_icon'] = self::normalize_avatar_icon( isset( $member['avatar_icon'] ) ? $member['avatar_icon'] : null );
					}
					if ( self::LEGACY_SCHEMA_VERSION === (int) $payload['schema_version'] && null === $photo ) {
						$member_hash['photo'] = array( 'fallback' => true, 'variants' => array() );
					}
					$members[] = $member_hash;
				}
			}

			$sections[ $section_key ] = array(
				'title'   => isset( $section['title'] ) ? (string) $section['title'] : self::default_section_title( $section_key ),
				'count'   => isset( $section['count'] ) ? (int) $section['count'] : count( $members ),
				'members' => $members,
			);
		}

		return array(
			'schema_version' => isset( $payload['schema_version'] ) ? (int) $payload['schema_version'] : 0,
			'member_count'   => isset( $payload['member_count'] ) ? (int) $payload['member_count'] : 0,
			'section_order'  => isset( $payload['section_order'] ) && is_array( $payload['section_order'] ) ? array_values( $payload['section_order'] ) : array(),
			'sections'       => $sections,
		);
	}

	/**
	 * Recursively sorts associative arrays so Django and WordPress hash the same
	 * canonical payload regardless of insertion order.
	 *
	 * @param mixed $value Payload fragment.
	 * @return mixed
	 */
	private static function canonicalize_hash_payload( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		if ( array_values( $value ) === $value ) {
			$items = array();
			foreach ( $value as $item ) {
				$items[] = self::canonicalize_hash_payload( $item );
			}
			return $items;
		}

		$items = array();
		foreach ( $value as $key => $item ) {
			$items[ $key ] = self::canonicalize_hash_payload( $item );
		}
		ksort( $items );
		return $items;
	}

	/**
	 * Logs a debug message only when WP_DEBUG is enabled.
	 *
	 * @param string $message Log message.
	 * @return void
	 */
	private static function log( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[AVF Members Page] ' . $message );
		}
	}
}

AVF_Members_Page::init();
