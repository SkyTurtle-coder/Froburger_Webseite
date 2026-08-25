<?php
/**
 * Plugin Name: AVF Member Privacy
 * Description: Central privacy and deindexing controls for public member pages and member media.
 */

defined( 'ABSPATH' ) || exit;

final class AVF_Member_Privacy {

	const VERSION                    = '1.0.0';
	const MEMBER_IMAGE_META_KEY      = '_avf_member_image';
	const MIGRATION_NOTICE_OPTION    = 'avf_member_privacy_migration_notice';
	const MIGRATION_PLAN_OPTION      = 'avf_member_privacy_migration_plan_v1';
	const MEDIA_REGISTRY_OPTION      = 'avf_member_media_registry_v1';
	const TOMBSTONE_OPTION           = 'avf_member_media_tombstones_v1';
	const REWRITE_VERSION_OPTION     = 'avf_member_privacy_rewrite_version';
	const REWRITE_VERSION            = 2;
	const MEDIA_QUERY_VAR            = 'avf_member_media';
	const DEFAULT_MEDIA_BASE         = 'member-media';
	const DEFAULT_UPLOAD_SUBDIR      = 'avf-members';
	const SNAPSHOT_OPTION            = 'avf_members_snapshot_v2';
	const MIGRATION_PLAN_SCHEMA      = 1;
	const PRIVATE_DIR_CONST          = 'AVF_MEMBER_MEDIA_PRIVATE_DIR';
	const MAX_IMPORT_BYTES           = 8388608;
	const TOKEN_BYTES                = 16;
	const MEMBER_MEDIA_FILENAME      = 'member-image';
	const DEFAULT_MEMBER_PAGE_PATHS  = array(
		'/mitglieder/',
		'/mitglieder',
	);
	const DEFAULT_MEMBER_SHORTCODES  = array(
		'avf_members_page',
		'avf_members_cards',
		'avf_members_count',
	);

	/**
	 * In-request cache for computed member page IDs.
	 *
	 * @var int[]|null
	 */
	private static $member_page_ids = null;

	/**
	 * In-request cache for computed member media map.
	 *
	 * @var array<string,array<string,mixed>>|null
	 */
	private static $media_map = null;

	/**
	 * Boots all member privacy protections.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_rewrite' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrite_rules' ), 20 );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'intercept_member_media_request' ), 0 );
		add_action( 'template_redirect', array( __CLASS__, 'intercept_legacy_member_upload_request' ), 0 );
		add_action( 'template_redirect', array( __CLASS__, 'block_member_attachment_page' ), 1 );
		add_action( 'send_headers', array( __CLASS__, 'send_member_page_headers' ) );
		add_filter( 'wp_robots', array( __CLASS__, 'filter_wp_robots' ) );
		add_filter( 'wp_get_attachment_url', array( __CLASS__, 'filter_attachment_url' ), 10, 2 );
		add_filter( 'wp_get_attachment_image_attributes', array( __CLASS__, 'filter_attachment_image_attributes' ), 10, 3 );
		add_filter( 'get_image_tag', array( __CLASS__, 'filter_legacy_image_tag' ), 10, 6 );
		add_filter( 'rest_prepare_attachment', array( __CLASS__, 'filter_rest_prepare_attachment' ), 10, 3 );
		add_filter( 'wp_prepare_attachment_for_js', array( __CLASS__, 'filter_attachment_js_payload' ), 10, 3 );
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'filter_sitemap_query_args' ), 10, 2 );
		add_filter( 'wpseo_sitemap_exclude_post_type', array( __CLASS__, 'filter_wpseo_exclude_post_type' ), 10, 2 );
		add_filter( 'robots_txt', array( __CLASS__, 'filter_robots_txt' ), 10, 2 );
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ) );
		add_action( 'admin_post_avf_member_privacy_migrate', array( __CLASS__, 'handle_migration_request' ) );
		add_action( 'add_attachment', array( __CLASS__, 'mark_member_attachment_after_upload' ) );
		add_filter( 'wp_handle_upload_prefilter', array( __CLASS__, 'filter_member_upload_filename' ) );
		add_filter( 'sanitize_file_name', array( __CLASS__, 'filter_member_sanitize_filename' ), 10, 2 );
		add_action( 'update_option_' . self::SNAPSHOT_OPTION, array( __CLASS__, 'invalidate_media_map' ), 10, 0 );
		self::ensure_member_upload_htaccess();
	}

	/**
	 * Returns the central configuration.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_config() {
		$config = array(
			'member_page_paths' => self::DEFAULT_MEMBER_PAGE_PATHS,
			'member_shortcodes' => self::DEFAULT_MEMBER_SHORTCODES,
			'member_media_base' => self::DEFAULT_MEDIA_BASE,
			'upload_subdir'     => self::DEFAULT_UPLOAD_SUBDIR,
			'google_extended'   => false,
		);

		return apply_filters( 'avf_member_privacy_config', $config );
	}

	/**
	 * Registers the pretty member media route.
	 *
	 * @return void
	 */
	public static function register_rewrite() {
		$base = trim( (string) self::get_config()['member_media_base'], '/' );
		if ( '' === $base ) {
			return;
		}

		add_rewrite_rule(
			'^' . preg_quote( $base, '/' ) . '/([a-f0-9]{32})/?$',
			'index.php?' . self::MEDIA_QUERY_VAR . '=$matches[1]',
			'top'
		);
	}

	/**
	 * Flushes rewrite rules once when the rewrite version changes.
	 *
	 * @return void
	 */
	public static function maybe_flush_rewrite_rules() {
		global $wp_rewrite;

		if ( ! ( $wp_rewrite instanceof WP_Rewrite ) ) {
			return;
		}

		if ( (int) get_option( self::REWRITE_VERSION_OPTION, 0 ) === self::REWRITE_VERSION ) {
			return;
		}

		self::register_rewrite();
		flush_rewrite_rules( false );
		update_option( self::REWRITE_VERSION_OPTION, self::REWRITE_VERSION, false );
	}

	/**
	 * Ensures the neutral uploads folder always sends anti-indexing headers.
	 *
	 * @return void
	 */
	public static function ensure_member_upload_htaccess() {
		$upload = wp_get_upload_dir();
		if ( empty( $upload['basedir'] ) ) {
			return;
		}

		$directory = trailingslashit( $upload['basedir'] ) . self::get_config()['upload_subdir'];
		if ( ! is_dir( $directory ) ) {
			return;
		}

		$htaccess_path = trailingslashit( $directory ) . '.htaccess';
		$contents      = <<<HTACCESS
<IfModule mod_headers.c>
    <FilesMatch "\.(jpe?g|png|gif|webp|avif)$">
        Header always set X-Robots-Tag "noindex"
        Header always set X-Content-Type-Options "nosniff"
        Header always set Referrer-Policy "same-origin"
    </FilesMatch>
</IfModule>
Options -Indexes
HTACCESS;

		if ( file_exists( $htaccess_path ) ) {
			$existing = file_get_contents( $htaccess_path );
			if ( is_string( $existing ) && trim( $existing ) === trim( $contents ) ) {
				return;
			}
		}

		wp_mkdir_p( $directory );
		file_put_contents( $htaccess_path, $contents );
	}

	/**
	 * Registers the public query var used by the media proxy.
	 *
	 * @param string[] $vars Existing query vars.
	 * @return string[]
	 */
	public static function register_query_vars( array $vars ) {
		$vars[] = self::MEDIA_QUERY_VAR;
		return $vars;
	}

	/**
	 * Returns whether the current request is a public member page.
	 *
	 * @return bool
	 */
	public static function is_member_page() {
		if ( self::should_bypass_member_page_detection() ) {
			return false;
		}

		if ( self::is_member_media_request() ) {
			return false;
		}

		if ( is_attachment() && self::is_member_attachment() ) {
			return true;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$request_uri = (string) wp_parse_url( $request_uri, PHP_URL_PATH );

		foreach ( self::get_config()['member_page_paths'] as $path ) {
			$normalized = untrailingslashit( (string) $path );
			if ( '' !== $normalized && $normalized === untrailingslashit( $request_uri ) ) {
				return true;
			}
		}

		$page_ids = self::get_member_page_ids();
		if ( ! empty( $page_ids ) && is_page( $page_ids ) ) {
			return true;
		}

		$post = get_queried_object();
		if ( $post instanceof WP_Post && 'page' === $post->post_type ) {
			foreach ( self::get_config()['member_shortcodes'] as $shortcode ) {
				if ( has_shortcode( (string) $post->post_content, (string) $shortcode ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Skips member-page detection in non-frontend or fragile runtime contexts.
	 *
	 * The public page protections only matter on normal frontend page requests.
	 * Running expensive page discovery during admin, AJAX, REST, WP-CLI or
	 * recovery/fatal-error rendering adds avoidable load and can turn unrelated
	 * failures into secondary fatals.
	 *
	 * @return bool
	 */
	private static function should_bypass_member_page_detection() {
		if ( is_admin() ) {
			return true;
		}

		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return true;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}

		if ( function_exists( 'wp_installing' ) && wp_installing() ) {
			return true;
		}

		if ( function_exists( 'wp_is_recovery_mode' ) && wp_is_recovery_mode() ) {
			return true;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$request_uri = strtolower( (string) wp_parse_url( $request_uri, PHP_URL_PATH ) );
		if ( '' !== $request_uri ) {
			if ( 0 === strpos( $request_uri, '/wp-admin/' ) || '/wp-login.php' === $request_uri ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns all public member page IDs discoverable from current content.
	 *
	 * @return int[]
	 */
	private static function get_member_page_ids() {
		if ( null !== self::$member_page_ids ) {
			return self::$member_page_ids;
		}

		$ids = array();
		$query = new WP_Query(
			array(
				'post_type'              => 'page',
				'post_status'            => array( 'publish', 'private', 'draft' ),
				'posts_per_page'         => 50,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		if ( ! empty( $query->posts ) ) {
			foreach ( $query->posts as $post_id ) {
				$post = get_post( $post_id );
				if ( ! $post instanceof WP_Post ) {
					continue;
				}

				$matches_shortcode = false;
				foreach ( self::get_config()['member_shortcodes'] as $shortcode ) {
					if ( has_shortcode( (string) $post->post_content, (string) $shortcode ) ) {
						$matches_shortcode = true;
						break;
					}
				}

				$path = wp_parse_url( get_permalink( $post ), PHP_URL_PATH );
				$matches_path = false;
				if ( is_string( $path ) ) {
					foreach ( self::get_config()['member_page_paths'] as $configured_path ) {
						if ( untrailingslashit( $configured_path ) === untrailingslashit( $path ) ) {
							$matches_path = true;
							break;
						}
					}
				}

				if ( $matches_shortcode || $matches_path ) {
					$ids[] = (int) $post->ID;
				}
			}
		}

		self::$member_page_ids = array_values( array_unique( array_filter( $ids ) ) );
		return self::$member_page_ids;
	}

	/**
	 * Returns whether the current queried attachment or the given attachment ID
	 * belongs to the protected member media set.
	 *
	 * @param int|null $attachment_id Optional attachment ID.
	 * @return bool
	 */
	public static function is_member_attachment( $attachment_id = null ) {
		if ( null === $attachment_id ) {
			$post = get_queried_object();
			if ( ! ( $post instanceof WP_Post ) ) {
				return false;
			}
			$attachment_id = (int) $post->ID;
		}

		$attachment_id = (int) $attachment_id;
		if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) ) {
			return false;
		}

		if ( get_post_meta( $attachment_id, self::MEMBER_IMAGE_META_KEY, true ) ) {
			return true;
		}

		$relative_file = get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( is_string( $relative_file ) && self::is_member_upload_relative_path( $relative_file ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Returns whether a relative uploads path points to protected member media.
	 *
	 * @param string $relative_path Relative uploads path.
	 * @return bool
	 */
	private static function is_member_upload_relative_path( $relative_path ) {
		$relative_path = ltrim( (string) $relative_path, '/\\' );
		$relative_path = str_replace( '\\', '/', $relative_path );

		return 0 === strpos( $relative_path, trailingslashit( self::get_config()['upload_subdir'] ) );
	}

	/**
	 * Returns whether a public URL points to protected member media.
	 *
	 * @param string $url Public URL.
	 * @return bool
	 */
	public static function is_member_image_url( $url ) {
		$url  = trim( (string) $url );
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		if ( '' === $path ) {
			return false;
		}

		$member_upload_fragment = '/wp-content/uploads/' . trim( self::get_config()['upload_subdir'], '/' ) . '/';
		$member_media_fragment  = '/' . trim( self::get_config()['member_media_base'], '/' ) . '/';

		if ( false !== strpos( $path, $member_upload_fragment ) ) {
			return true;
		}

		if ( false !== strpos( $path, $member_media_fragment ) ) {
			return true;
		}

		return false !== strpos( $path, '/media/public/members/' );
	}

	/**
	 * Returns whether the current REST request is a public member-media request.
	 *
	 * @return bool
	 */
	public static function is_public_member_api_request() {
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
			return false;
		}

		if ( is_user_logged_in() ) {
			return false;
		}

		$route = '';
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$route = (string) wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
		}

		return false !== strpos( $route, '/wp-json/wp/v2/media' ) || false !== strpos( $route, '/wp-json/wp/v2/search' );
	}

	/**
	 * Applies noindex/noimageindex directives on member pages.
	 *
	 * @param array<string,mixed> $robots Existing robots directives.
	 * @return array<string,mixed>
	 */
	public static function filter_wp_robots( array $robots ) {
		if ( ! self::is_member_page() ) {
			return $robots;
		}

		$robots['noindex']           = true;
		$robots['nofollow']          = true;
		$robots['noarchive']         = true;
		$robots['nosnippet']         = true;
		$robots['noimageindex']      = true;
		$robots['max-image-preview'] = 'none';

		return $robots;
	}

	/**
	 * Sends the fallback headers for page and media requests routed through PHP.
	 *
	 * @return void
	 */
	public static function send_member_page_headers() {
		if ( self::is_member_page() ) {
			header( 'X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex, max-image-preview:none', true );
		}
	}

	/**
	 * Forces protected member attachment pages to return 410.
	 *
	 * @return void
	 */
	public static function block_member_attachment_page() {
		if ( ! is_attachment() || ! self::is_member_attachment() ) {
			return;
		}

		status_header( 410 );
		nocache_headers();
		header( 'X-Robots-Tag: noindex, noimageindex', true );
		exit;
	}

	/**
	 * Rewrites member attachment URLs to the opaque media route on the public
	 * front end.
	 *
	 * @param string $url           Attachment URL.
	 * @param int    $attachment_id Attachment ID.
	 * @return string
	 */
	public static function filter_attachment_url( $url, $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		if ( is_admin() || is_user_logged_in() || ! self::is_member_attachment( $attachment_id ) ) {
			return $url;
		}

		return self::member_media_url_for_attachment( $attachment_id );
	}

	/**
	 * Ensures public member images are decorative and never expose title-like
	 * attributes.
	 *
	 * @param array        $attr       Image attributes.
	 * @param WP_Post      $attachment Attachment object.
	 * @param string|array $size       Requested size.
	 * @return array
	 */
	public static function filter_attachment_image_attributes( array $attr, $attachment, $size ) {
		if ( ! ( $attachment instanceof WP_Post ) || ! self::is_member_attachment( (int) $attachment->ID ) ) {
			return $attr;
		}

		$attr['alt']         = '';
		$attr['aria-hidden'] = 'true';
		unset( $attr['title'], $attr['data-caption'], $attr['data-description'], $attr['data-elementor-lightbox-title'], $attr['data-elementor-lightbox-description'] );

		return $attr;
	}

	/**
	 * Rewrites legacy image tags when WordPress generates HTML from an attachment.
	 *
	 * @param string       $html    Image tag HTML.
	 * @param int          $id      Attachment ID.
	 * @param string       $alt     Alt text.
	 * @param string       $title   Title attribute.
	 * @param string       $align   Alignment.
	 * @param string|array $size    Requested size.
	 * @return string
	 */
	public static function filter_legacy_image_tag( $html, $id, $alt, $title, $align, $size ) {
		if ( ! self::is_member_attachment( (int) $id ) ) {
			return $html;
		}

		$html = preg_replace( '/\salt="[^"]*"/i', ' alt=""', $html );
		$html = preg_replace( '/\stitle="[^"]*"/i', '', $html );
		if ( false === strpos( $html, 'aria-hidden=' ) ) {
			$html = preg_replace( '/<img\b/i', '<img aria-hidden="true"', $html, 1 );
		}

		return (string) $html;
	}

	/**
	 * Removes identifying attachment data from unauthenticated REST responses.
	 *
	 * @param WP_REST_Response $response  REST response.
	 * @param WP_Post          $post      Attachment post.
	 * @param WP_REST_Request  $request   REST request.
	 * @return WP_REST_Response
	 */
	public static function filter_rest_prepare_attachment( $response, $post, $request ) {
		if ( is_user_logged_in() || ! ( $post instanceof WP_Post ) || ! self::is_member_attachment( (int) $post->ID ) ) {
			return $response;
		}

		$data = $response->get_data();
		if ( ! is_array( $data ) ) {
			return $response;
		}

		$data['title']['rendered']       = '';
		$data['caption']['rendered']     = '';
		$data['description']['rendered'] = '';
		$data['alt_text']                = '';
		$data['slug']                    = 'member-media';
		$data['guid']['rendered']        = '';
		$data['link']                    = '';
		$data['source_url']              = self::member_media_url_for_attachment( (int) $post->ID );
		$data['filename']                = '';
		if ( isset( $data['media_details']['image_meta'] ) ) {
			$data['media_details']['image_meta'] = array();
		}
		if ( isset( $data['media_details']['file'] ) ) {
			$data['media_details']['file'] = '';
		}
		if ( isset( $data['media_details']['sizes'] ) && is_array( $data['media_details']['sizes'] ) ) {
			foreach ( $data['media_details']['sizes'] as $size_key => $size_data ) {
				if ( ! is_array( $size_data ) ) {
					continue;
				}

				$data['media_details']['sizes'][ $size_key ]['file']       = '';
				$data['media_details']['sizes'][ $size_key ]['source_url'] = self::member_media_url_for_attachment_size( (int) $post->ID, (string) $size_key );
			}
		}
		unset( $data['permalink_template'], $data['generated_slug'], $data['missing_image_sizes'] );

		$response->set_data( $data );
		return $response;
	}

	/**
	 * Removes identifying attachment metadata from public media-library payloads.
	 *
	 * @param array|null $response Prepared JS response.
	 * @param WP_Post    $attachment Attachment post.
	 * @param array      $meta Attachment metadata.
	 * @return array|null
	 */
	public static function filter_attachment_js_payload( $response, $attachment, $meta ) {
		if ( is_user_logged_in() || ! is_array( $response ) || ! ( $attachment instanceof WP_Post ) || ! self::is_member_attachment( (int) $attachment->ID ) ) {
			return $response;
		}

		$response['title']       = '';
		$response['caption']     = '';
		$response['description'] = '';
		$response['alt']         = '';
		$response['filename']    = '';
		$response['url']         = self::member_media_url_for_attachment( (int) $attachment->ID );

		return $response;
	}

	/**
	 * Excludes member pages and member attachments from public sitemaps.
	 *
	 * @param array  $args         Sitemap query args.
	 * @param string $post_type    Post type currently being rendered.
	 * @return array
	 */
	public static function filter_sitemap_query_args( array $args, $post_type ) {
		if ( 'page' === $post_type ) {
			$args['post__not_in'] = array_values(
				array_unique(
					array_merge(
						isset( $args['post__not_in'] ) && is_array( $args['post__not_in'] ) ? $args['post__not_in'] : array(),
						self::get_member_page_ids()
					)
				)
			);
		}

		if ( 'attachment' === $post_type ) {
			$args['meta_query'] = array(
				'relation' => 'AND',
				array(
					'relation' => 'OR',
					array(
						'key'     => self::MEMBER_IMAGE_META_KEY,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => self::MEMBER_IMAGE_META_KEY,
						'value'   => '1',
						'compare' => '!=',
					),
				),
				array(
					'key'     => '_wp_attached_file',
					'value'   => trailingslashit( self::get_config()['upload_subdir'] ),
					'compare' => 'NOT LIKE',
				),
			);
		}

		return $args;
	}

	/**
	 * Excludes attachment sitemaps for Yoast when it is present.
	 *
	 * @param bool   $excluded  Existing exclusion decision.
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public static function filter_wpseo_exclude_post_type( $excluded, $post_type ) {
		if ( 'attachment' === $post_type ) {
			return true;
		}

		return $excluded;
	}

	/**
	 * Optionally appends a Google-Extended rule without affecting normal search.
	 *
	 * @param string $output Existing robots text.
	 * @param bool   $public Blog-public flag.
	 * @return string
	 */
	public static function filter_robots_txt( $output, $public ) {
		if ( empty( self::get_config()['google_extended'] ) ) {
			return $output;
		}

		return trim( $output ) . "\n\nUser-agent: Google-Extended\nDisallow: /\n";
	}

	/**
	 * Registers a small admin tool for dry-run and live migration.
	 *
	 * @return void
	 */
	public static function register_admin_page() {
		add_management_page(
			'AVF Member Privacy',
			'AVF Member Privacy',
			'manage_options',
			'avf-member-privacy',
			array( __CLASS__, 'render_admin_page' )
		);
	}

	/**
	 * Renders the migration admin page.
	 *
	 * @return void
	 */
	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'default' ) );
		}

		$notice = get_option( self::MIGRATION_NOTICE_OPTION, array() );
		if ( ! empty( $notice ) ) {
			delete_option( self::MIGRATION_NOTICE_OPTION );
		}

		$plan = self::get_saved_migration_plan();
		$summary = self::plan_summary( $plan );
		?>
		<div class="wrap">
			<h1>AVF Member Privacy</h1>
			<p>Dieses Werkzeug erstellt einen gespeicherten, deterministischen Migrationsplan fuer bestehende Mitgliederbilder im oeffentlichen Upload-Ordner und fuehrt ihn bei Bedarf kontrolliert aus. Vor jeder Ausfuehrung zuerst Backup von Datenbank und Uploads erstellen.</p>
			<?php if ( ! empty( $notice['message'] ) ) : ?>
				<div class="notice notice-<?php echo ! empty( $notice['success'] ) ? 'success' : 'warning'; ?>">
					<p><?php echo esc_html( $notice['message'] ); ?></p>
				</div>
			<?php endif; ?>

			<h2>Gespeicherter Plan</h2>
			<?php if ( empty( $plan ) ) : ?>
				<p>Es ist noch kein Migrationsplan gespeichert.</p>
			<?php else : ?>
				<table class="widefat striped" style="max-width:960px">
					<tbody>
						<tr><td><strong>Plan-ID</strong></td><td><code><?php echo esc_html( (string) $plan['plan_id'] ); ?></code></td></tr>
						<tr><td><strong>Status</strong></td><td><?php echo esc_html( (string) $plan['status'] ); ?></td></tr>
						<tr><td><strong>Erstellt</strong></td><td><?php echo esc_html( (string) $plan['created_at'] ); ?></td></tr>
						<tr><td><strong>Aktualisiert</strong></td><td><?php echo esc_html( (string) $plan['updated_at'] ); ?></td></tr>
						<tr><td><strong>Quellzustands-Hash</strong></td><td><code><?php echo esc_html( (string) $plan['source_state_hash'] ); ?></code></td></tr>
						<tr><td><strong>Bilder</strong></td><td><?php echo (int) $summary['images']; ?></td></tr>
						<tr><td><strong>Varianten</strong></td><td><?php echo (int) $summary['variants']; ?></td></tr>
						<tr><td><strong>Dateikopien</strong></td><td><?php echo (int) $summary['copies']; ?></td></tr>
						<tr><td><strong>Re-Encodierungen</strong></td><td><?php echo (int) $summary['reencodes']; ?></td></tr>
						<tr><td><strong>Loeschungen</strong></td><td><?php echo (int) $summary['deletes']; ?></td></tr>
						<tr><td><strong>Datenbankaenderungen</strong></td><td><?php echo (int) $summary['db_changes']; ?></td></tr>
						<tr><td><strong>Warnungen</strong></td><td><?php echo (int) count( isset( $plan['warnings'] ) && is_array( $plan['warnings'] ) ? $plan['warnings'] : array() ); ?></td></tr>
						<tr><td><strong>Fehler</strong></td><td><?php echo (int) count( isset( $plan['errors'] ) && is_array( $plan['errors'] ) ? $plan['errors'] : array() ); ?></td></tr>
					</tbody>
				</table>

				<?php if ( ! empty( $plan['warnings'] ) ) : ?>
					<h3>Warnungen</h3>
					<ul><?php foreach ( $plan['warnings'] as $warning ) : ?><li><?php echo esc_html( (string) $warning ); ?></li><?php endforeach; ?></ul>
				<?php endif; ?>

				<?php if ( ! empty( $plan['errors'] ) ) : ?>
					<h3>Blockierende Fehler</h3>
					<ul><?php foreach ( $plan['errors'] as $error ) : ?><li><?php echo esc_html( (string) $error ); ?></li><?php endforeach; ?></ul>
				<?php endif; ?>

				<h3>Plan-Elemente</h3>
				<?php foreach ( (array) $plan['items'] as $item ) : ?>
					<details style="margin:0 0 12px 0;padding:8px 12px;border:1px solid #dcdcde;background:#fff;">
						<summary>
							<strong><?php echo esc_html( (string) $item['member_reference'] ); ?></strong>
							<?php if ( ! empty( $item['attachment_id'] ) ) : ?>
								<span> | Attachment <?php echo (int) $item['attachment_id']; ?></span>
							<?php endif; ?>
							<span> | Token <code><?php echo esc_html( (string) $item['planned_token'] ); ?></code></span>
						</summary>
						<p><strong>Vorher:</strong> <code><?php echo esc_html( (string) $item['old_local_file'] ); ?></code></p>
						<p><strong>Nachher:</strong> <code><?php echo esc_html( (string) $item['planned_storage_name'] ); ?></code> -> <code><?php echo esc_html( (string) $item['planned_public_url'] ); ?></code></p>
						<p><strong>Alt-URL Status:</strong> <?php echo esc_html( (string) $item['expected_old_url_status'] ); ?></p>
						<p><strong>Alt-Text:</strong> <?php echo esc_html( (string) $item['existing_alt_text'] ); ?></p>
						<p><strong>Titel:</strong> <?php echo esc_html( (string) $item['existing_title'] ); ?></p>
						<h4>Dateivarianten</h4>
						<ul>
							<?php foreach ( (array) $item['variants'] as $variant ) : ?>
								<li>
									<code><?php echo esc_html( (string) $variant['relative_path'] ); ?></code>
									| <?php echo esc_html( (string) $variant['kind'] ); ?>
									| <?php echo esc_html( (string) $variant['mime_type'] ); ?>
									| <?php echo (int) $variant['size_bytes']; ?> B
									| <code><?php echo esc_html( (string) $variant['content_hash'] ); ?></code>
								</li>
							<?php endforeach; ?>
						</ul>
					</details>
				<?php endforeach; ?>
			<?php endif; ?>

			<h2>Aktionen</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:12px;">
				<?php wp_nonce_field( 'avf_member_privacy_migrate' ); ?>
				<input type="hidden" name="action" value="avf_member_privacy_migrate">
				<input type="hidden" name="dry_run" value="1">
				<?php submit_button( 'Dry Run erstellen oder wiederverwenden', 'secondary', 'submit', false ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
				<?php wp_nonce_field( 'avf_member_privacy_migrate' ); ?>
				<input type="hidden" name="action" value="avf_member_privacy_migrate">
				<input type="hidden" name="dry_run" value="0">
				<?php submit_button( 'Gespeicherten Plan ausfuehren', 'primary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handles the dry-run or live migration request.
	 *
	 * @return void
	 */
	public static function handle_migration_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'default' ) );
		}

		check_admin_referer( 'avf_member_privacy_migrate' );
		$dry_run = ! empty( $_POST['dry_run'] );
		$plan    = self::create_or_reuse_migration_plan();

		if ( ! $dry_run && ! is_wp_error( $plan ) ) {
			$plan = self::execute_migration_plan( $plan );
		}

		update_option(
			self::MIGRATION_NOTICE_OPTION,
			array(
				'success' => ! is_wp_error( $plan ),
				'message' => is_wp_error( $plan )
					? $plan->get_error_message()
					: ( $dry_run
						? sprintf( 'Dry Run gespeichert. Plan-ID %s mit %d Bild(er)n.', (string) $plan['plan_id'], count( (array) $plan['items'] ) )
						: sprintf( 'Migration abgeschlossen. Plan-ID %s.', (string) $plan['plan_id'] ) ),
			),
			false
		);

		wp_safe_redirect( admin_url( 'tools.php?page=avf-member-privacy' ) );
		exit;
	}

	/**
	 * Scans all attachments in the neutral members directory.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function scan_member_attachment_migration_plan() {
		$inventory = self::collect_migration_inventory();
		$state_hash = self::build_source_state_hash( $inventory );
		$existing = self::get_saved_migration_plan();
		if ( ! empty( $existing ) && isset( $existing['source_state_hash'] ) && hash_equals( (string) $existing['source_state_hash'], $state_hash ) ) {
			return $existing;
		}

		$warnings = array();
		$errors   = array();
		$items    = array();

		foreach ( $inventory['items'] as $record ) {
			// SEC-012 fix: this used to be hash('sha256', $state_hash . '|' .
			// $member_reference . '|' . $old_local_file) - fully derivable
			// from inputs that were themselves public before migration.
			// Cryptographically random instead now. This doesn't break
			// dry-run/real-run consistency: the caller
			// (create_or_reuse_migration_plan -> execute_migration_plan)
			// already gets that from the $existing-plan cache check above
			// (keyed on source_state_hash, persisted via
			// update_option(MIGRATION_PLAN_OPTION, ...)) - the token itself
			// was never what made re-running this idempotent.
			$seed = bin2hex( random_bytes( 28 ) );
			$token = substr( $seed, 0, 32 );
			$extension = self::preferred_migration_extension( $record );
			$storage_name = 'migrated-' . substr( $seed, 32, 24 ) . $extension;
			$planned_url = self::member_media_route_url( $token );

			$copies = array();
			$deletes = array();
			foreach ( $record['variants'] as $variant ) {
				$copies[] = array(
					'from' => $variant['absolute_path'],
					'to'   => trailingslashit( self::get_private_media_dir() ) . $storage_name,
				);
				$deletes[] = $variant['absolute_path'];
			}

			$items[] = array(
				'member_reference'        => $record['member_reference'],
				'attachment_id'           => $record['attachment_id'],
				'old_local_file'          => $record['old_local_file'],
				'old_public_url'          => $record['old_public_url'],
				'variants'                => $record['variants'],
				'existing_title'          => $record['existing_title'],
				'existing_alt_text'       => $record['existing_alt_text'],
				'attachment_metadata'     => $record['attachment_metadata'],
				'planned_token'           => $token,
				'planned_storage_name'    => $storage_name,
				'planned_public_url'      => $planned_url,
				'planned_registry_change' => array(
					'token'        => $token,
					'storage_name' => $storage_name,
					'source_key'   => $record['attachment_id'] > 0 ? 'attachment:' . $record['attachment_id'] . ':original' : '',
				),
				'planned_snapshot_change'   => 'none',
				'planned_attachment_change' => $record['attachment_id'] > 0 ? 'sanitize-public-fields' : 'none',
				'planned_file_copies'       => $copies,
				'planned_reencode'          => true,
				'planned_deletions'         => $deletes,
				'expected_old_url_status'   => 410,
			);
		}

		$plan = array(
			'plan_id'            => 'avfmp-' . substr( $state_hash, 0, 16 ),
			'created_at'         => gmdate( 'c' ),
			'updated_at'         => gmdate( 'c' ),
			'status'             => 'planned',
			'source_state_hash'  => $state_hash,
			'schema_version'     => self::MIGRATION_PLAN_SCHEMA,
			'items'              => $items,
			'warnings'           => $warnings,
			'errors'             => $errors,
		);

		update_option( self::MIGRATION_PLAN_OPTION, $plan, false );
		return $plan;
	}

	/**
	 * Migrates one attachment to a pseudonymous base filename.
	 *
	 * @param array<string,mixed> $item Planned attachment migration item.
	 * @return bool
	 */
	private static function migrate_attachment_file( array $item ) {
		$attachment_id = isset( $item['attachment_id'] ) ? (int) $item['attachment_id'] : 0;
		$token         = isset( $item['planned_token'] ) ? strtolower( (string) $item['planned_token'] ) : '';
		$storage_name  = isset( $item['planned_storage_name'] ) ? wp_basename( (string) $item['planned_storage_name'] ) : '';
		if ( '' === $token || '' === $storage_name || empty( $item['variants'] ) || ! is_array( $item['variants'] ) ) {
			return false;
		}

		$private_dir = self::ensure_private_media_dir();
		if ( is_wp_error( $private_dir ) ) {
			return false;
		}

		$stage_dir = trailingslashit( $private_dir ) . '.migration-' . substr( $token, 0, 12 );
		wp_mkdir_p( $stage_dir );
		$stage_file = trailingslashit( $stage_dir ) . $storage_name;

		$source_variant = self::preferred_variant_for_migration( $item['variants'] );
		if ( empty( $source_variant['absolute_path'] ) || ! is_readable( $source_variant['absolute_path'] ) ) {
			self::cleanup_stage_directory( $stage_dir );
			return false;
		}

		$editor = wp_get_image_editor( $source_variant['absolute_path'] );
		if ( is_wp_error( $editor ) ) {
			self::cleanup_stage_directory( $stage_dir );
			return false;
		}

		$mime_type = self::preferred_variant_mime_type( $item['variants'] );
		$saved = $editor->save( $stage_file, $mime_type );
		if ( is_wp_error( $saved ) || ! file_exists( $stage_file ) ) {
			self::cleanup_stage_directory( $stage_dir );
			return false;
		}

		$final_path = trailingslashit( $private_dir ) . $storage_name;
		@unlink( $final_path );
		if ( ! @rename( $stage_file, $final_path ) ) {
			self::cleanup_stage_directory( $stage_dir );
			return false;
		}

		$registry = self::load_media_registry();
		$content_hash = hash_file( 'sha256', $final_path );
		$mime_type = wp_get_image_mime( $final_path );
		$registry['items'][ $token ] = array(
			'token'        => $token,
			'source_key'   => $attachment_id > 0 ? 'attachment:' . $attachment_id . ':original' : '',
			'variant_key'  => 'original',
			'storage_name' => $storage_name,
			'mime_type'    => $mime_type,
			'content_hash' => $content_hash,
			'width'        => isset( $saved['width'] ) ? (int) $saved['width'] : 0,
			'height'       => isset( $saved['height'] ) ? (int) $saved['height'] : 0,
			'updated_at'   => gmdate( 'c' ),
		);
		if ( $attachment_id > 0 ) {
			$registry['by_source'][ 'attachment:' . $attachment_id . ':original' ] = $token;
		}

		foreach ( (array) $item['variants'] as $variant ) {
			$legacy_key = self::legacy_registry_key( isset( $variant['public_url'] ) ? (string) $variant['public_url'] : '', '' );
			if ( '' !== $legacy_key ) {
				$registry['by_legacy'][ $legacy_key ] = $token;
			}
		}

		update_option( self::MEDIA_REGISTRY_OPTION, $registry, false );
		self::invalidate_media_map();
		self::register_tombstones_for_item( $item );

		foreach ( (array) $item['variants'] as $variant ) {
			if ( ! empty( $variant['absolute_path'] ) && is_file( $variant['absolute_path'] ) ) {
				@unlink( $variant['absolute_path'] );
			}
		}

		if ( $attachment_id > 0 ) {
			self::sanitize_attachment_record( $attachment_id, $storage_name );
		}

		self::cleanup_stage_directory( $stage_dir );
		return true;
	}

	/**
	 * Marks the attachment and clears public-facing attachment metadata.
	 *
	 * @param int         $attachment_id Attachment ID.
	 * @param string|null $title_hint Optional pseudonymous title.
	 * @return void
	 */
	private static function sanitize_attachment_record( $attachment_id, $title_hint = null ) {
		$attachment_id = (int) $attachment_id;
		self::mark_attachment_as_member_image( $attachment_id );

		$postarr = array(
			'ID'           => $attachment_id,
			'post_title'   => null !== $title_hint ? (string) pathinfo( $title_hint, PATHINFO_FILENAME ) : 'Member media',
			'post_excerpt' => '',
			'post_content' => '',
			'post_name'    => null !== $title_hint ? sanitize_title( pathinfo( $title_hint, PATHINFO_FILENAME ) ) : 'member-media',
		);
		wp_update_post( wp_slash( $postarr ) );
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', '' );
	}

	/**
	 * Strips identifying metadata from a marked member image by re-encoding it.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	private static function strip_member_image_metadata( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		$file          = get_attached_file( $attachment_id );
		if ( ! is_string( $file ) || '' === $file || ! file_exists( $file ) ) {
			return;
		}

		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) {
			return;
		}

		$saved = $editor->save( $file );
		if ( is_wp_error( $saved ) ) {
			return;
		}
	}

	/**
	 * Loads the saved migration plan if present.
	 *
	 * @return array<string,mixed>
	 */
	private static function get_saved_migration_plan() {
		$plan = get_option( self::MIGRATION_PLAN_OPTION, array() );
		return is_array( $plan ) ? $plan : array();
	}

	/**
	 * Returns plan counters for the admin page.
	 *
	 * @param array<string,mixed> $plan Saved plan.
	 * @return array<string,int>
	 */
	private static function plan_summary( array $plan ) {
		$summary = array(
			'images'     => 0,
			'variants'   => 0,
			'copies'     => 0,
			'reencodes'  => 0,
			'deletes'    => 0,
			'db_changes' => 0,
		);

		foreach ( (array) ( isset( $plan['items'] ) ? $plan['items'] : array() ) as $item ) {
			++$summary['images'];
			$summary['variants'] += count( isset( $item['variants'] ) && is_array( $item['variants'] ) ? $item['variants'] : array() );
			$summary['copies'] += count( isset( $item['planned_file_copies'] ) && is_array( $item['planned_file_copies'] ) ? $item['planned_file_copies'] : array() );
			$summary['deletes'] += count( isset( $item['planned_deletions'] ) && is_array( $item['planned_deletions'] ) ? $item['planned_deletions'] : array() );
			$summary['reencodes'] += ! empty( $item['planned_reencode'] ) ? 1 : 0;
			$summary['db_changes'] += ! empty( $item['planned_registry_change'] ) ? 1 : 0;
			$summary['db_changes'] += ! empty( $item['planned_attachment_change'] ) && 'none' !== $item['planned_attachment_change'] ? 1 : 0;
		}

		return $summary;
	}

	/**
	 * Creates or reuses a deterministic migration plan for the current source state.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	private static function create_or_reuse_migration_plan() {
		$plan = self::scan_member_attachment_migration_plan();
		if ( empty( $plan['items'] ) ) {
			return new WP_Error( 'avf_member_migration_no_items', 'Es wurden keine migrierbaren Mitgliederbilder im oeffentlichen Upload-Ordner gefunden.' );
		}

		if ( ! empty( $plan['errors'] ) ) {
			return new WP_Error( 'avf_member_migration_plan_errors', implode( '; ', (array) $plan['errors'] ) );
		}

		return $plan;
	}

	/**
	 * Executes one previously saved migration plan.
	 *
	 * @param array<string,mixed> $plan Saved plan.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function execute_migration_plan( array $plan ) {
		if ( empty( $plan['plan_id'] ) || empty( $plan['source_state_hash'] ) ) {
			return new WP_Error( 'avf_member_migration_missing_plan', 'Es ist kein gueltiger gespeicherter Migrationsplan vorhanden.' );
		}

		$current_hash = self::build_source_state_hash( self::collect_migration_inventory() );
		if ( ! hash_equals( (string) $plan['source_state_hash'], $current_hash ) ) {
			$plan['status']     = 'failed';
			$plan['updated_at'] = gmdate( 'c' );
			$plan['errors'][]   = 'Der gespeicherte Quellzustand stimmt nicht mehr mit dem aktuellen Bestand ueberein.';
			update_option( self::MIGRATION_PLAN_OPTION, $plan, false );
			return new WP_Error( 'avf_member_migration_hash_changed', 'Der gespeicherte Dry-Run ist veraltet und darf nicht ausgefuehrt werden.' );
		}

		if ( 'completed' === (string) $plan['status'] ) {
			return $plan;
		}

		$plan['status']     = 'running';
		$plan['updated_at'] = gmdate( 'c' );
		update_option( self::MIGRATION_PLAN_OPTION, $plan, false );

		foreach ( (array) $plan['items'] as $item ) {
			if ( ! self::migrate_attachment_file( $item ) ) {
				$plan['status']     = 'failed';
				$plan['updated_at'] = gmdate( 'c' );
				$plan['errors'][]   = 'Mindestens ein Migrationsobjekt konnte nicht in den privaten Speicher uebernommen werden.';
				update_option( self::MIGRATION_PLAN_OPTION, $plan, false );
				return new WP_Error( 'avf_member_migration_failed', 'Die Migration wurde abgebrochen, ohne den alten Bestand vollstaendig zu ersetzen.' );
			}
		}

		$plan['status']     = 'completed';
		$plan['updated_at'] = gmdate( 'c' );
		update_option( self::MIGRATION_PLAN_OPTION, $plan, false );
		return $plan;
	}

	/**
	 * Collects a deterministic inventory of the public member upload directory.
	 *
	 * @return array<string,mixed>
	 */
	private static function collect_migration_inventory() {
		$upload = wp_get_upload_dir();
		$base_dir = isset( $upload['basedir'] ) ? wp_normalize_path( (string) $upload['basedir'] ) : '';
		$base_url = isset( $upload['baseurl'] ) ? untrailingslashit( (string) $upload['baseurl'] ) : '';
		$member_dir = trailingslashit( $base_dir ) . self::get_config()['upload_subdir'];
		$attachments = get_posts(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'     => '_wp_attached_file',
						'value'   => trailingslashit( self::get_config()['upload_subdir'] ),
						'compare' => 'LIKE',
					),
				),
			)
		);

		$items = array();
		foreach ( $attachments as $attachment_id ) {
			$relative_path = (string) get_post_meta( (int) $attachment_id, '_wp_attached_file', true );
			if ( '' === $relative_path ) {
				continue;
			}

			$absolute = trailingslashit( $base_dir ) . ltrim( $relative_path, '/\\' );
			$stem = pathinfo( wp_basename( $relative_path ), PATHINFO_FILENAME );
			$variants = array();

			foreach ( (array) glob( trailingslashit( dirname( $absolute ) ) . $stem . '*' ) as $path ) {
				if ( ! is_file( $path ) ) {
					continue;
				}

				$relative_variant = ltrim( str_replace( wp_normalize_path( $base_dir ), '', wp_normalize_path( $path ) ), '/\\' );
				$mime_type = wp_get_image_mime( $path );
				$variants[] = array(
					'kind'         => self::variant_kind( wp_basename( $path ) ),
					'absolute_path'=> wp_normalize_path( $path ),
					'relative_path'=> $relative_variant,
					'public_url'   => $base_url . '/' . str_replace( '\\', '/', $relative_variant ),
					'size_bytes'   => (int) filesize( $path ),
					'mime_type'    => is_string( $mime_type ) ? $mime_type : '',
					'content_hash' => hash_file( 'sha256', $path ),
				);
			}

			usort(
				$variants,
				static function ( $left, $right ) {
					return strcmp( (string) $left['relative_path'], (string) $right['relative_path'] );
				}
			);

			$items[] = array(
				'member_reference'    => 'attachment:' . (int) $attachment_id,
				'attachment_id'       => (int) $attachment_id,
				'old_local_file'      => $relative_path,
				'old_public_url'      => $base_url . '/' . str_replace( '\\', '/', ltrim( $relative_path, '/\\' ) ),
				'existing_title'      => (string) get_the_title( (int) $attachment_id ),
				'existing_alt_text'   => (string) get_post_meta( (int) $attachment_id, '_wp_attachment_image_alt', true ),
				'attachment_metadata' => wp_get_attachment_metadata( (int) $attachment_id ),
				'variants'            => $variants,
			);
		}

		return array(
			'member_dir' => $member_dir,
			'items'      => $items,
		);
	}

	/**
	 * Builds a stable source-state hash for the migration plan.
	 *
	 * @param array<string,mixed> $inventory Inventory payload.
	 * @return string
	 */
	private static function build_source_state_hash( array $inventory ) {
		$payload = array();
		foreach ( (array) $inventory['items'] as $item ) {
			$variant_payload = array();
			foreach ( (array) $item['variants'] as $variant ) {
				$variant_payload[] = array(
					'relative_path' => (string) $variant['relative_path'],
					'size_bytes'    => (int) $variant['size_bytes'],
					'mime_type'     => (string) $variant['mime_type'],
					'content_hash'  => (string) $variant['content_hash'],
				);
			}

			$payload[] = array(
				'attachment_id' => (int) $item['attachment_id'],
				'old_local_file'=> (string) $item['old_local_file'],
				'title'         => (string) $item['existing_title'],
				'alt'           => (string) $item['existing_alt_text'],
				'metadata'      => (array) $item['attachment_metadata'],
				'variants'      => $variant_payload,
			);
		}

		return hash( 'sha256', wp_json_encode( $payload ) );
	}

	/**
	 * Returns the preferred output extension for one migration item.
	 *
	 * @param array<string,mixed> $record Inventory record.
	 * @return string
	 */
	private static function preferred_migration_extension( array $record ) {
		foreach ( (array) $record['variants'] as $variant ) {
			if ( 'image/webp' === (string) $variant['mime_type'] ) {
				return '.webp';
			}
		}

		$mime_type = self::preferred_variant_mime_type( isset( $record['variants'] ) && is_array( $record['variants'] ) ? $record['variants'] : array() );
		return self::mime_to_extension( $mime_type );
	}

	/**
	 * Returns a stable human label for one discovered variant.
	 *
	 * @param string $filename Variant filename.
	 * @return string
	 */
	private static function variant_kind( $filename ) {
		$filename = strtolower( (string) $filename );
		if ( false !== strpos( $filename, '-scaled.' ) ) {
			return 'scaled';
		}
		if ( preg_match( '/-\d+x\d+\./', $filename ) ) {
			return 'thumbnail';
		}
		return 'original';
	}

	/**
	 * Returns the preferred source variant for migration.
	 *
	 * @param array<int,array<string,mixed>> $variants Variant list.
	 * @return array<string,mixed>
	 */
	private static function preferred_variant_for_migration( array $variants ) {
		foreach ( $variants as $variant ) {
			if ( 'image/webp' === (string) $variant['mime_type'] && 'original' === (string) $variant['kind'] ) {
				return $variant;
			}
		}
		foreach ( $variants as $variant ) {
			if ( 'original' === (string) $variant['kind'] ) {
				return $variant;
			}
		}
		return ! empty( $variants ) ? reset( $variants ) : array();
	}

	/**
	 * Returns the preferred output MIME type for migrated variants.
	 *
	 * @param array<int,array<string,mixed>> $variants Variant list.
	 * @return string
	 */
	private static function preferred_variant_mime_type( array $variants ) {
		foreach ( $variants as $variant ) {
			if ( 'image/webp' === (string) $variant['mime_type'] ) {
				return 'image/webp';
			}
		}
		foreach ( $variants as $variant ) {
			if ( in_array( (string) $variant['mime_type'], array( 'image/jpeg', 'image/png' ), true ) ) {
				return (string) $variant['mime_type'];
			}
		}
		return 'image/webp';
	}

	/**
	 * Registers 410 tombstones for known legacy member image URLs.
	 *
	 * @param array<string,mixed> $item Plan item.
	 * @return void
	 */
	private static function register_tombstones_for_item( array $item ) {
		$tombstones = get_option( self::TOMBSTONE_OPTION, array() );
		if ( ! is_array( $tombstones ) ) {
			$tombstones = array();
		}

		foreach ( (array) $item['variants'] as $variant ) {
			$path = (string) wp_parse_url( (string) $variant['public_url'], PHP_URL_PATH );
			if ( '' === $path ) {
				continue;
			}

			$tombstones[ self::legacy_path_hash( $path ) ] = array(
				'status'     => 410,
				'removed_at' => gmdate( 'c' ),
			);
		}

		update_option( self::TOMBSTONE_OPTION, $tombstones, false );
	}

	/**
	 * Marks attachments in the neutral members directory as member images.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public static function mark_member_attachment_after_upload( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		$relative_file = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( self::is_member_upload_relative_path( $relative_file ) || ! empty( $_REQUEST['avf_member_image'] ) ) {
			self::mark_attachment_as_member_image( $attachment_id );
			self::sanitize_attachment_record( $attachment_id );
		}
	}

	/**
	 * Renames new member-upload filenames to opaque random tokens.
	 *
	 * @param array<string,mixed> $file Incoming upload payload.
	 * @return array<string,mixed>
	 */
	public static function filter_member_upload_filename( array $file ) {
		if ( empty( $_REQUEST['avf_member_image'] ) || empty( $file['name'] ) || ! is_string( $file['name'] ) ) {
			return $file;
		}

		$extension   = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		$file['name'] = 'member-' . wp_generate_password( 8, false, false ) . '.' . $extension;

		return $file;
	}

	/**
	 * Replaces descriptive filenames for explicit member uploads.
	 *
	 * @param string $filename Raw filename.
	 * @param string $raw_name Raw upload name.
	 * @return string
	 */
	public static function filter_member_sanitize_filename( $filename, $raw_name ) {
		if ( empty( $_REQUEST['avf_member_image'] ) ) {
			return $filename;
		}

		if ( self::is_pseudonymous_filename( (string) $filename ) ) {
			return $filename;
		}

		$extension = strtolower( pathinfo( (string) $filename, PATHINFO_EXTENSION ) );
		return 'member-' . wp_generate_password( 8, false, false ) . '.' . $extension;
	}

	/**
	 * Marks an attachment as protected member media.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	private static function mark_attachment_as_member_image( $attachment_id ) {
		update_post_meta( (int) $attachment_id, self::MEMBER_IMAGE_META_KEY, '1' );
	}

	/**
	 * Returns whether a filename is already pseudonymous.
	 *
	 * @param string $filename Filename only.
	 * @return bool
	 */
	private static function is_pseudonymous_filename( $filename ) {
		return 1 === preg_match( '/^(member|portrait|media)-[a-z0-9]{8,}\.[a-z0-9]+$/i', (string) $filename );
	}

	/**
	 * Rebuilds the in-request map when the snapshot changes.
	 *
	 * @return void
	 */
	public static function invalidate_media_map() {
		self::$media_map = null;
	}

	/**
	 * Resolves the opaque member media URL for a known registry entry.
	 *
	 * @param string $source_url Legacy source URL.
	 * @param string $path       Legacy internal path.
	 * @return string
	 */
	public static function member_media_url( $source_url, $path = '' ) {
		$registry = self::load_media_registry();
		$legacy_key = self::legacy_registry_key( $source_url, $path );
		if ( '' === $legacy_key || empty( $registry['by_legacy'][ $legacy_key ] ) ) {
			return '';
		}

		$token = (string) $registry['by_legacy'][ $legacy_key ];
		if ( ! self::member_media_token_is_ready( $token ) ) {
			return '';
		}

		return self::member_media_route_url( $token );
	}

	/**
	 * Returns the opaque member media URL for a migrated member attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	public static function member_media_url_for_attachment( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id <= 0 ) {
			return '';
		}

		$registry = self::load_media_registry();
		$source_key = 'attachment:' . $attachment_id . ':original';
		if ( empty( $registry['by_source'][ $source_key ] ) ) {
			return '';
		}

		$token = (string) $registry['by_source'][ $source_key ];
		return self::member_media_token_is_ready( $token ) ? self::member_media_route_url( $token ) : '';
	}

	/**
	 * Returns the opaque member media URL for one migrated attachment size.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $size_key      Attachment size key.
	 * @return string
	 */
	public static function member_media_url_for_attachment_size( $attachment_id, $size_key ) {
		$attachment_id = (int) $attachment_id;
		$size_key      = sanitize_key( (string) $size_key );
		if ( $attachment_id <= 0 || '' === $size_key ) {
			return '';
		}

		$registry   = self::load_media_registry();
		$source_key = 'attachment:' . $attachment_id . ':size:' . $size_key;
		if ( empty( $registry['by_source'][ $source_key ] ) ) {
			return '';
		}

		$token = (string) $registry['by_source'][ $source_key ];
		return self::member_media_token_is_ready( $token ) ? self::member_media_route_url( $token ) : '';
	}

	/**
	 * Returns the current token map backed by the stored registry.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function get_media_map() {
		if ( null !== self::$media_map ) {
			return self::$media_map;
		}

		$registry = self::load_media_registry();
		self::$media_map = isset( $registry['items'] ) && is_array( $registry['items'] ) ? $registry['items'] : array();
		return self::$media_map;
	}

	/**
	 * Imports all member-photo variants from a validated Django snapshot into the
	 * private local store and atomically activates the new registry and snapshot
	 * representation only after all files are ready.
	 *
	 * @param array $payload Validated remote snapshot payload.
	 * @return array|WP_Error
	 */
	public static function import_snapshot_payload( array $payload ) {
		$private_dir = self::ensure_private_media_dir();
		if ( is_wp_error( $private_dir ) ) {
			return $private_dir;
		}

		$registry     = self::load_media_registry();
		$new_registry = self::clone_media_registry( $registry );
		$active_keys  = array();
		$stage_dir    = trailingslashit( $private_dir ) . '.tmp-' . wp_generate_password( 12, false, false );
		$staged_files = array();
		$public       = $payload;

		if ( ! wp_mkdir_p( $stage_dir ) ) {
			return new WP_Error( 'avf_member_media_stage_dir_failed', 'The member media staging directory could not be created.' );
		}

		try {
			if ( empty( $payload['sections'] ) || ! is_array( $payload['sections'] ) ) {
				throw new Exception( 'Snapshot sections missing during media import.' );
			}

			foreach ( $payload['sections'] as $section_key => $section ) {
				if ( empty( $section['members'] ) || ! is_array( $section['members'] ) ) {
					continue;
				}

				foreach ( $section['members'] as $member_index => $member ) {
					$public_member = is_array( $member ) ? $member : array();
					$public_member['photo'] = array(
						'available' => false,
						'fallback'  => true,
						'token'     => '',
						'variants'  => array(),
					);

					$source_variants = isset( $member['photo'] ) && is_array( $member['photo'] ) && isset( $member['photo']['variants'] ) && is_array( $member['photo']['variants'] )
						? $member['photo']['variants']
						: array();

					foreach ( array( 'small', 'medium', 'large' ) as $variant_key ) {
						if ( empty( $source_variants[ $variant_key ] ) || ! is_array( $source_variants[ $variant_key ] ) ) {
							continue;
						}

						$variant = $source_variants[ $variant_key ];
						$source_url = isset( $variant['url'] ) && is_scalar( $variant['url'] ) ? trim( (string) $variant['url'] ) : '';
						if ( '' === $source_url ) {
							continue;
						}

						$source_key = self::snapshot_source_key( $member, (string) $section_key, (int) $member_index, $variant_key );
						$imported = self::stage_remote_member_media( $source_key, $source_url, $variant_key, $variant, $new_registry, $stage_dir );
						if ( is_wp_error( $imported ) ) {
							throw new Exception( $imported->get_error_message() );
						}

						if ( ! empty( $imported['tmp_path'] ) ) {
							$staged_files[] = (string) $imported['tmp_path'];
							unset( $imported['tmp_path'] );
						}

						$new_registry['items'][ $imported['token'] ] = $imported;
						$new_registry['by_source'][ $source_key ]    = $imported['token'];
						if ( ! empty( $imported['legacy_key'] ) ) {
							$new_registry['by_legacy'][ $imported['legacy_key'] ] = $imported['token'];
						}

						$active_keys[ $source_key ] = true;
						$public_member['photo']['available'] = true;
						$public_member['photo']['fallback']  = false;
						if ( '' === $public_member['photo']['token'] && 'medium' === $variant_key ) {
							$public_member['photo']['token'] = $imported['token'];
						}

						$public_member['photo']['variants'][ $variant_key ] = array(
							'token'  => $imported['token'],
							'url'    => self::member_media_route_url( $imported['token'] ),
							'width'  => isset( $imported['width'] ) ? (int) $imported['width'] : 0,
							'height' => isset( $imported['height'] ) ? (int) $imported['height'] : 0,
						);
					}

					if ( empty( $public_member['photo']['token'] ) && ! empty( $public_member['photo']['variants'] ) ) {
						$first_variant = reset( $public_member['photo']['variants'] );
						$public_member['photo']['token'] = isset( $first_variant['token'] ) ? (string) $first_variant['token'] : '';
					}

					$public['sections'][ $section_key ]['members'][ $member_index ] = $public_member;
				}
			}

			self::commit_staged_member_media( $new_registry, $active_keys, $stage_dir );
		} catch ( Exception $exception ) {
			self::cleanup_stage_directory( $stage_dir );
			return new WP_Error( 'avf_member_media_import_failed', $exception->getMessage() );
		}

		return $public;
	}

	/**
	 * Returns whether the token is backed by a readable local file.
	 *
	 * @param string $token Public opaque token.
	 * @return bool
	 */
	public static function member_media_token_is_ready( $token ) {
		$token = strtolower( trim( (string) $token ) );
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $token ) ) {
			return false;
		}

		$map = self::get_media_map();
		if ( empty( $map[ $token ] ) || ! is_array( $map[ $token ] ) ) {
			return false;
		}

		$file_path = self::member_media_file_path( $map[ $token ] );
		return is_string( $file_path ) && '' !== $file_path && is_readable( $file_path );
	}

	/**
	 * Returns whether a member-media route URL currently resolves to a readable
	 * local file in the registry.
	 *
	 * @param string $url Public token route URL.
	 * @return bool
	 */
	public static function member_media_url_is_ready( $url ) {
		$token = self::token_from_member_media_url( $url );
		return '' !== $token && self::member_media_token_is_ready( $token );
	}

	/**
	 * Returns whether the current request targets the media proxy route.
	 *
	 * @return bool
	 */
	private static function is_member_media_request() {
		global $wp_query;

		if ( ! ( $wp_query instanceof WP_Query ) ) {
			return false;
		}

		return '' !== (string) get_query_var( self::MEDIA_QUERY_VAR, '' );
	}

	/**
	 * Intercepts and serves the proxy member-media route.
	 *
	 * @return void
	 */
	public static function intercept_member_media_request() {
		$token = (string) get_query_var( self::MEDIA_QUERY_VAR, '' );
		if ( '' === $token ) {
			return;
		}

		$token = strtolower( $token );
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $token ) ) {
			status_header( 404 );
			exit;
		}

		$map = self::get_media_map();
		if ( empty( $map[ $token ] ) || ! is_array( $map[ $token ] ) ) {
			status_header( 404 );
			exit;
		}

		$item = $map[ $token ];
		header( 'X-Robots-Tag: noindex, noimageindex', true );
		header( 'X-Content-Type-Options: nosniff', true );
		header( 'Cache-Control: public, max-age=86400', true );
		self::serve_local_member_media( $item );
	}

	/**
	 * Intercepts known legacy member-upload URLs after the files have been
	 * migrated away from the public uploads directory.
	 *
	 * @return void
	 */
	public static function intercept_legacy_member_upload_request() {
		$request_path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
		$fragment = '/wp-content/uploads/' . trim( self::get_config()['upload_subdir'], '/' ) . '/';
		if ( '' === $request_path || false === strpos( $request_path, $fragment ) ) {
			return;
		}

		$hash = self::legacy_path_hash( $request_path );
		$tombstones = get_option( self::TOMBSTONE_OPTION, array() );
		if ( is_array( $tombstones ) && ! empty( $tombstones[ $hash ] ) ) {
			status_header( 410 );
			header( 'X-Robots-Tag: noindex, noimageindex', true );
			exit;
		}

		status_header( 404 );
		header( 'X-Robots-Tag: noindex, noimageindex', true );
		exit;
	}

	/**
	 * Serves a local member-media file from the private media directory.
	 *
	 * @param array<string,mixed> $item Local media map item.
	 * @return void
	 */
	private static function serve_local_member_media( array $item ) {
		$real_file = self::member_media_file_path( $item );
		if ( ! is_string( $real_file ) || '' === $real_file || ! is_readable( $real_file ) ) {
			status_header( 404 );
			exit;
		}

		$mime = isset( $item['mime_type'] ) && is_string( $item['mime_type'] ) ? $item['mime_type'] : '';
		$actual_mime = wp_get_image_mime( $real_file );
		if ( ! is_string( $actual_mime ) || '' === $actual_mime ) {
			status_header( 404 );
			exit;
		}

		if ( '' === $mime ) {
			$mime = $actual_mime;
		}

		if ( $mime !== $actual_mime ) {
			$mime = 'application/octet-stream';
		}

		$last_modified = filemtime( $real_file );
		$etag = ! empty( $item['content_hash'] ) ? '"' . (string) $item['content_hash'] . '"' : '';
		if ( '' !== $etag ) {
			header( 'ETag: ' . $etag, true );
		}
		if ( false !== $last_modified ) {
			header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', (int) $last_modified ) . ' GMT', true );
		}

		$if_none_match = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? trim( (string) wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) : '';
		$if_modified_since = isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ? trim( (string) wp_unslash( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) : '';
		$normalized_etag = trim( str_replace( 'W/', '', $if_none_match ), '"' );
		$current_etag = trim( $etag, '"' );
		if ( '' !== $current_etag && '' !== $normalized_etag && hash_equals( $current_etag, $normalized_etag ) ) {
			status_header( 304 );
			exit;
		}
		if ( false !== $last_modified && '' !== $if_modified_since && strtotime( $if_modified_since ) >= (int) $last_modified ) {
			status_header( 304 );
			exit;
		}

		header( 'Content-Type: ' . $mime, true );
		header( 'Content-Disposition: inline; filename="' . self::MEMBER_MEDIA_FILENAME . self::mime_to_extension( $mime ) . '"', true );
		header( 'Content-Length: ' . (string) filesize( $real_file ), true );
		if ( 'HEAD' === strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET' ) ) {
			exit;
		}

		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		readfile( $real_file );
		exit;
	}

	/**
	 * Builds a relative member-media path for one attachment size variant.
	 *
	 * @param int                  $attachment_id Attachment ID.
	 * @param string|int           $size_key      Metadata size key.
	 * @param array<string,mixed>  $size_data     Metadata size payload.
	 * @return string
	 */
	private static function relative_member_size_file( $attachment_id, $size_key, array $size_data ) {
		$base_relative = (string) get_post_meta( (int) $attachment_id, '_wp_attached_file', true );
		if ( '' === $base_relative || empty( $size_data['file'] ) || ! is_string( $size_data['file'] ) ) {
			return '';
		}

		$directory = wp_normalize_path( dirname( $base_relative ) );
		if ( '.' === $directory ) {
			return $size_data['file'];
		}

		return trailingslashit( $directory ) . wp_basename( $size_data['file'] );
	}

	/**
	 * Resolves the final local file path for one registry item.
	 *
	 * @param array<string,mixed> $item Media registry item.
	 * @return string
	 */
	private static function member_media_file_path( array $item ) {
		$storage_name = isset( $item['storage_name'] ) && is_string( $item['storage_name'] ) ? $item['storage_name'] : '';
		if ( '' === $storage_name ) {
			return '';
		}

		$base_dir = self::get_private_media_dir();
		$absolute = trailingslashit( $base_dir ) . wp_basename( $storage_name );
		$real_base = realpath( $base_dir );
		$real_file = realpath( $absolute );

		if ( false === $real_base || false === $real_file || 0 !== strpos( wp_normalize_path( $real_file ), wp_normalize_path( $real_base ) ) ) {
			return '';
		}

		return $real_file;
	}

	/**
	 * Returns the configured absolute private media directory.
	 *
	 * @return string
	 */
	private static function get_private_media_dir() {
		if ( defined( self::PRIVATE_DIR_CONST ) && is_string( constant( self::PRIVATE_DIR_CONST ) ) && '' !== trim( constant( self::PRIVATE_DIR_CONST ) ) ) {
			return wp_normalize_path( constant( self::PRIVATE_DIR_CONST ) );
		}

		return wp_normalize_path( dirname( ABSPATH ) . '/member-media-private' );
	}

	/**
	 * Ensures the private media directory exists.
	 *
	 * @return string|WP_Error
	 */
	private static function ensure_private_media_dir() {
		$directory = self::get_private_media_dir();
		if ( '' === $directory ) {
			return new WP_Error( 'avf_member_media_no_private_dir', 'No private member media directory is configured.' );
		}

		if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
			return new WP_Error( 'avf_member_media_private_dir_failed', 'The private member media directory could not be created.' );
		}

		$index_file = trailingslashit( $directory ) . 'index.php';
		if ( ! file_exists( $index_file ) ) {
			file_put_contents( $index_file, "<?php\nhttp_response_code(404);\nexit;\n" );
		}

		return $directory;
	}

	/**
	 * Loads the persisted media registry.
	 *
	 * @return array<string,mixed>
	 */
	private static function load_media_registry() {
		$registry = get_option( self::MEDIA_REGISTRY_OPTION, array() );
		if ( ! is_array( $registry ) ) {
			$registry = array();
		}

		return array(
			'version'   => 1,
			'items'     => isset( $registry['items'] ) && is_array( $registry['items'] ) ? $registry['items'] : array(),
			'by_source' => isset( $registry['by_source'] ) && is_array( $registry['by_source'] ) ? $registry['by_source'] : array(),
			'by_legacy' => isset( $registry['by_legacy'] ) && is_array( $registry['by_legacy'] ) ? $registry['by_legacy'] : array(),
		);
	}

	/**
	 * Creates a mutable clone of an existing registry payload.
	 *
	 * @param array<string,mixed> $registry Current registry.
	 * @return array<string,mixed>
	 */
	private static function clone_media_registry( array $registry ) {
		return array(
			'version'   => 1,
			'items'     => isset( $registry['items'] ) && is_array( $registry['items'] ) ? $registry['items'] : array(),
			'by_source' => isset( $registry['by_source'] ) && is_array( $registry['by_source'] ) ? $registry['by_source'] : array(),
			'by_legacy' => isset( $registry['by_legacy'] ) && is_array( $registry['by_legacy'] ) ? $registry['by_legacy'] : array(),
		);
	}

	/**
	 * Builds one stable internal source key for a snapshot member variant.
	 *
	 * @param array  $member       Normalized member payload.
	 * @param string $section_key  Section key.
	 * @param int    $member_index Member index in the section.
	 * @param string $variant_key  Variant key.
	 * @return string
	 */
	private static function snapshot_source_key( array $member, $section_key, $member_index, $variant_key ) {
		$member_id = isset( $member['id'] ) ? (int) $member['id'] : 0;
		if ( $member_id > 0 ) {
			return 'snapshot:' . $member_id . ':' . $variant_key;
		}

		$fingerprint = hash( 'sha256', wp_json_encode( array(
			'section'      => (string) $section_key,
			'display_name' => isset( $member['display_name'] ) ? (string) $member['display_name'] : '',
			'vulgo'        => isset( $member['vulgo'] ) ? (string) $member['vulgo'] : '',
			'index'        => (int) $member_index,
		) ) );

		return 'snapshot:' . $fingerprint . ':' . $variant_key;
	}

	/**
	 * Downloads, validates, re-encodes and stages one remote snapshot image.
	 *
	 * @param string               $source_key  Stable internal source key.
	 * @param string               $source_url  Remote snapshot image URL.
	 * @param string               $variant_key Variant label.
	 * @param array<string,mixed>  $variant     Source variant payload.
	 * @param array<string,mixed>  $registry    Mutable target registry.
	 * @param string               $stage_dir   Temporary private staging directory.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function stage_remote_member_media( $source_key, $source_url, $variant_key, array $variant, array $registry, $stage_dir ) {
		$validated_url = self::validate_internal_member_image_url( $source_url );
		if ( '' === $validated_url ) {
			return new WP_Error( 'avf_member_media_invalid_source', 'A member image URL did not pass internal-source validation.' );
		}

		$tmp_file = tempnam( sys_get_temp_dir(), 'avf-member-media-' );
		if ( ! is_string( $tmp_file ) || '' === $tmp_file ) {
			return new WP_Error( 'avf_member_media_tmp_failed', 'A temporary file for member media could not be created.' );
		}

		$response = wp_remote_get(
			$validated_url,
			array(
				'timeout'             => 10,
				'redirection'         => 1,
				'stream'              => true,
				'filename'            => $tmp_file,
				'limit_response_size' => self::MAX_IMPORT_BYTES,
				'headers'             => array(
					'Accept' => 'image/*',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			@unlink( $tmp_file );
			return new WP_Error( 'avf_member_media_request_failed', 'A member image could not be downloaded.' );
		}

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			@unlink( $tmp_file );
			return new WP_Error( 'avf_member_media_bad_status', 'A member image returned a non-200 response during import.' );
		}

		if ( ! file_exists( $tmp_file ) || filesize( $tmp_file ) <= 0 || filesize( $tmp_file ) > self::MAX_IMPORT_BYTES ) {
			@unlink( $tmp_file );
			return new WP_Error( 'avf_member_media_bad_size', 'A member image exceeded the maximum allowed size.' );
		}

		$mime_type = wp_get_image_mime( $tmp_file );
		if ( ! in_array( $mime_type, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
			@unlink( $tmp_file );
			return new WP_Error( 'avf_member_media_bad_mime', 'A member image used an unsupported MIME type.' );
		}

		$content_hash = hash_file( 'sha256', $tmp_file );
		$existing_token = isset( $registry['by_source'][ $source_key ] ) ? (string) $registry['by_source'][ $source_key ] : '';
		$existing_item  = ( '' !== $existing_token && ! empty( $registry['items'][ $existing_token ] ) && is_array( $registry['items'][ $existing_token ] ) )
			? $registry['items'][ $existing_token ]
			: array();

		if ( ! empty( $existing_item['content_hash'] ) && hash_equals( (string) $existing_item['content_hash'], $content_hash ) ) {
			$existing_file = self::member_media_file_path( $existing_item );
			if ( is_string( $existing_file ) && '' !== $existing_file && is_readable( $existing_file ) ) {
				@unlink( $tmp_file );
				return $existing_item;
			}
		}

		$editor = wp_get_image_editor( $tmp_file );
		if ( is_wp_error( $editor ) ) {
			@unlink( $tmp_file );
			return new WP_Error( 'avf_member_media_editor_failed', 'The imported member image could not be opened for re-encoding.' );
		}

		$extension    = self::mime_to_extension( $mime_type );
		$storage_name = ! empty( $existing_item['storage_name'] ) && is_string( $existing_item['storage_name'] )
			? wp_basename( (string) $existing_item['storage_name'] )
			: 'media-' . bin2hex( random_bytes( self::TOKEN_BYTES ) ) . $extension;
		$stage_path   = trailingslashit( $stage_dir ) . $storage_name;
		$saved        = $editor->save( $stage_path, $mime_type );
		@unlink( $tmp_file );
		if ( is_wp_error( $saved ) || ! file_exists( $stage_path ) ) {
			return new WP_Error( 'avf_member_media_save_failed', 'The imported member image could not be written to the private staging area.' );
		}

		$final_hash = hash_file( 'sha256', $stage_path );
		$final_mime = wp_get_image_mime( $stage_path );
		if ( $mime_type !== $final_mime || '' === $final_hash ) {
			@unlink( $stage_path );
			return new WP_Error( 'avf_member_media_verify_failed', 'The imported member image failed post-save verification.' );
		}

		$token = '' !== $existing_token ? $existing_token : bin2hex( random_bytes( self::TOKEN_BYTES ) );
		$legacy_key = self::legacy_registry_key( $source_url, isset( $variant['path'] ) ? (string) $variant['path'] : '' );

		return array(
			'token'        => $token,
			'source_key'   => $source_key,
			'variant_key'  => $variant_key,
			'storage_name' => $storage_name,
			'mime_type'    => $mime_type,
			'content_hash' => $final_hash,
			'width'        => isset( $variant['width'] ) ? (int) $variant['width'] : 0,
			'height'       => isset( $variant['height'] ) ? (int) $variant['height'] : 0,
			'legacy_key'   => $legacy_key,
			'updated_at'   => gmdate( 'c' ),
			'tmp_path'     => $stage_path,
		);
	}

	/**
	 * Atomically publishes all staged snapshot media files and prunes unreferenced
	 * snapshot files from the previous registry.
	 *
	 * @param array<string,mixed>    $registry    Target registry.
	 * @param array<string,bool>     $active_keys Active snapshot source keys.
	 * @param string                 $stage_dir   Private staging directory.
	 * @return void
	 */
	private static function commit_staged_member_media( array $registry, array $active_keys, $stage_dir ) {
		$private_dir = self::get_private_media_dir();
		$current     = self::load_media_registry();
		$staged      = isset( $registry['items'] ) && is_array( $registry['items'] ) ? $registry['items'] : array();

		foreach ( $staged as $item ) {
			if ( empty( $item['storage_name'] ) || ! is_string( $item['storage_name'] ) ) {
				continue;
			}

			$stage_path = trailingslashit( $stage_dir ) . wp_basename( $item['storage_name'] );
			if ( ! file_exists( $stage_path ) ) {
				continue;
			}

			$final_path = trailingslashit( $private_dir ) . wp_basename( $item['storage_name'] );
			@unlink( $final_path );
			if ( ! @rename( $stage_path, $final_path ) ) {
				throw new Exception( 'A staged member image could not be activated.' );
			}
		}

		update_option( self::MEDIA_REGISTRY_OPTION, $registry, false );
		self::invalidate_media_map();
		self::cleanup_stage_directory( $stage_dir );

		$current_items = isset( $current['items'] ) && is_array( $current['items'] ) ? $current['items'] : array();
		foreach ( $current_items as $token => $item ) {
			$source_key = isset( $item['source_key'] ) ? (string) $item['source_key'] : '';
			if ( 0 !== strpos( $source_key, 'snapshot:' ) || isset( $active_keys[ $source_key ] ) ) {
				continue;
			}

			$file = self::member_media_file_path( $item );
			if ( is_string( $file ) && '' !== $file && file_exists( $file ) ) {
				@unlink( $file );
			}
		}
	}

	/**
	 * Removes the staging directory and any remaining files below it.
	 *
	 * @param string $stage_dir Temporary directory.
	 * @return void
	 */
	private static function cleanup_stage_directory( $stage_dir ) {
		$stage_dir = wp_normalize_path( (string) $stage_dir );
		if ( '' === $stage_dir || ! is_dir( $stage_dir ) ) {
			return;
		}

		foreach ( glob( trailingslashit( $stage_dir ) . '*' ) as $file ) {
			if ( is_file( $file ) ) {
				@unlink( $file );
			}
		}

		@rmdir( $stage_dir );
	}

	/**
	 * Returns the public token route URL for one token.
	 *
	 * @param string $token Opaque token.
	 * @return string
	 */
	private static function member_media_route_url( $token ) {
		return home_url( '/' . trim( self::get_config()['member_media_base'], '/' ) . '/' . strtolower( (string) $token ) . '/' );
	}

	/**
	 * Validates an internal member image URL against known internal hosts.
	 *
	 * @param string $url Candidate source URL.
	 * @return string
	 */
	private static function validate_internal_member_image_url( $url ) {
		$url = esc_url_raw( trim( (string) $url ) );
		if ( '' === $url ) {
			return '';
		}

		if ( class_exists( 'AVF_Internal_Endpoint_Resolver' ) ) {
			$path = AVF_Internal_Endpoint_Resolver::instance()->extract_known_internal_path( $url );
			if ( '' !== $path ) {
				$normalized = AVF_Internal_Endpoint_Resolver::instance()->build_url( $path );
				return is_wp_error( $normalized ) ? '' : (string) $normalized;
			}
		}

		$parts = wp_parse_url( $url );
		$api_parts = wp_parse_url( home_url() );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || ! is_array( $api_parts ) || empty( $api_parts['host'] ) ) {
			return '';
		}

		if ( strcasecmp( (string) $parts['host'], (string) $api_parts['host'] ) === 0 ) {
			return $url;
		}

		$members_api = get_option( 'avf_members_api_base', '' );
		if ( is_string( $members_api ) && '' !== trim( $members_api ) ) {
			$base_parts = wp_parse_url( $members_api );
			if ( is_array( $base_parts ) && ! empty( $base_parts['host'] ) && strcasecmp( (string) $parts['host'], (string) $base_parts['host'] ) === 0 ) {
				return $url;
			}
		}

		return '';
	}

	/**
	 * Returns the canonical registry key for one legacy source URL/path pair.
	 *
	 * @param string $source_url Source URL.
	 * @param string $path       Source path.
	 * @return string
	 */
	private static function legacy_registry_key( $source_url, $path ) {
		$source_url = trim( (string) $source_url );
		$path = trim( (string) $path );
		if ( '' === $source_url && '' === $path ) {
			return '';
		}

		return hash( 'sha256', $path . '|' . $source_url );
	}

	/**
	 * Returns a stable hash for one old public member-upload path.
	 *
	 * @param string $path Public request path.
	 * @return string
	 */
	private static function legacy_path_hash( $path ) {
		$path = strtolower( trim( (string) $path ) );
		$path = preg_replace( '#/+#', '/', $path );
		return hash( 'sha256', (string) $path );
	}

	/**
	 * Extracts a token from a member-media route URL.
	 *
	 * @param string $url Route URL.
	 * @return string
	 */
	private static function token_from_member_media_url( $url ) {
		$path = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
		$base = trim( (string) self::get_config()['member_media_base'], '/' );
		if ( '' === $base ) {
			return '';
		}

		if ( 1 !== preg_match( '#/' . preg_quote( $base, '#' ) . '/([a-f0-9]{32})/?$#i', $path, $matches ) ) {
			return '';
		}

		return strtolower( (string) $matches[1] );
	}

	/**
	 * Converts an image MIME type to a file extension.
	 *
	 * @param string $mime_type Image MIME type.
	 * @return string
	 */
	private static function mime_to_extension( $mime_type ) {
		$map = array(
			'image/jpeg' => '.jpg',
			'image/png'  => '.png',
			'image/webp' => '.webp',
		);

		return isset( $map[ $mime_type ] ) ? $map[ $mime_type ] : '.bin';
	}
}

AVF_Member_Privacy::init();

/**
 * Whether the current request renders a protected public member page.
 *
 * @return bool
 */
function avf_is_member_page() {
	return AVF_Member_Privacy::is_member_page();
}

/**
 * Whether the current or given attachment belongs to protected member media.
 *
 * @param int|null $attachment_id Optional attachment ID.
 * @return bool
 */
function avf_is_member_attachment( $attachment_id = null ) {
	return AVF_Member_Privacy::is_member_attachment( $attachment_id );
}

/**
 * Whether a URL points to protected member media.
 *
 * @param string $url Public URL.
 * @return bool
 */
function avf_is_member_image_url( $url ) {
	return AVF_Member_Privacy::is_member_image_url( $url );
}

/**
 * Whether the current REST request is a public member-related API request.
 *
 * @return bool
 */
function avf_is_public_member_api_request() {
	return AVF_Member_Privacy::is_public_member_api_request();
}

/**
 * Converts a public member image source into its opaque local route.
 *
 * @param string $source_url Original image URL.
 * @param string $path Optional sanitized internal path.
 * @return string
 */
function avf_member_media_url( $source_url, $path = '' ) {
	return AVF_Member_Privacy::member_media_url( $source_url, $path );
}

/**
 * Returns whether a public member-media route currently resolves to a readable
 * local file.
 *
 * @param string $url Public token route URL.
 * @return bool
 */
function avf_member_media_url_is_ready( $url ) {
	return AVF_Member_Privacy::member_media_url_is_ready( $url );
}
