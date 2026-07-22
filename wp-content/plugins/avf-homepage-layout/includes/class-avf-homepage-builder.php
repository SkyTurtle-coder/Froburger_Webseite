<?php
/**
 * Reconstructs the AV Froburger homepage Elementor structure.
 *
 * @package AVF_Homepage_Layout
 */

defined( 'ABSPATH' ) || exit;

class AVF_Homepage_Builder {

	const POST_ID = 20;

	/**
	 * Global class ID of the existing, working hero container. Stable
	 * across renames (only the human-readable label changes).
	 */
	const HERO_GLOBAL_CLASS_ID = 'g-41fbf78';

	const ID_NAMESPACE = 'avf-homepage-layout:v1:';

	/**
	 * @var int
	 */
	private $kit_id;

	public function __construct() {
		$kit_id = (int) get_option( 'elementor_active_kit' );
		$this->kit_id = $kit_id ? $kit_id : 10;
	}

	/**
	 * @return int
	 */
	public function get_post_id() {
		return self::POST_ID;
	}

	/**
	 * @return int
	 */
	public function get_kit_id() {
		return $this->kit_id;
	}

	/**
	 * Confirms the configured front page really is post 20.
	 *
	 * @return true|WP_Error
	 */
	public function verify_frontpage() {
		$front = (int) get_option( 'page_on_front' );
		if ( self::POST_ID !== $front ) {
			return new WP_Error(
				'avf_homepage_wrong_front_page',
				sprintf( 'page_on_front is %d, expected %d. Aborting.', $front, self::POST_ID )
			);
		}
		return true;
	}

	/**
	 * A deterministic 7-char hex-ish element ID derived from a stable
	 * semantic name. Same name always yields the same ID, guaranteeing
	 * idempotent rebuilds (no drifting/duplicated IDs across runs).
	 *
	 * @param string $semantic_name Stable, human-chosen name for the element.
	 * @return string
	 */
	private function stable_id( $semantic_name ) {
		return substr( md5( self::ID_NAMESPACE . $semantic_name ), 0, 7 );
	}

	/* ---------------------------------------------------------------
	 * Backup
	 * ------------------------------------------------------------- */

	/**
	 * Writes a full, timestamped, file-based backup plus registers a
	 * companion draft page copy. Never overwrites a previous backup.
	 *
	 * @return array|WP_Error Array of written file paths, or WP_Error.
	 */
	public function backup() {
		$verified = $this->verify_frontpage();
		if ( is_wp_error( $verified ) ) {
			return $verified;
		}

		$post_id = self::POST_ID;

		$elementor_data = get_post_meta( $post_id, '_elementor_data', true );
		if ( '' === $elementor_data ) {
			return new WP_Error( 'avf_homepage_no_data', 'Homepage has no _elementor_data to back up.' );
		}

		json_decode( $elementor_data, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error( 'avf_homepage_invalid_json', 'Existing _elementor_data is not valid JSON: ' . json_last_error_msg() );
		}

		$timestamp = current_time( 'Y-m-d_His' );
		$dir       = WP_CONTENT_DIR . '/uploads/avf-backups/homepage/';
		if ( ! is_dir( $dir ) ) {
			if ( ! wp_mkdir_p( $dir ) ) {
				return new WP_Error( 'avf_homepage_backup_dir_failed', 'Could not create backup directory: ' . $dir );
			}
		}

		$written = array();

		$path = $dir . 'startseite-elementor-data-before-' . $timestamp . '.json';
		if ( false === file_put_contents( $path, $elementor_data ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			return new WP_Error( 'avf_homepage_backup_failed', 'Could not write ' . $path );
		}
		$written['elementor_data'] = $path;

		$meta = array(
			'post_id'                  => $post_id,
			'_elementor_edit_mode'     => get_post_meta( $post_id, '_elementor_edit_mode', true ),
			'_elementor_template_type' => get_post_meta( $post_id, '_elementor_template_type', true ),
			'_elementor_version'       => get_post_meta( $post_id, '_elementor_version', true ),
			'_wp_page_template'        => get_post_meta( $post_id, '_wp_page_template', true ),
			'_elementor_page_settings' => get_post_meta( $post_id, '_elementor_page_settings', true ),
			'page_on_front'            => get_option( 'page_on_front' ),
			'show_on_front'            => get_option( 'show_on_front' ),
			'backup_timestamp'         => $timestamp,
		);
		$path = $dir . 'startseite-meta-before-' . $timestamp . '.json';
		file_put_contents( $path, wp_json_encode( $meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); // phpcs:ignore
		$written['meta'] = $path;

		$kit_settings = get_post_meta( $this->kit_id, '_elementor_page_settings', true );
		$custom_css   = is_array( $kit_settings ) && isset( $kit_settings['custom_css'] ) ? $kit_settings['custom_css'] : '';
		$path         = $dir . 'elementor-kit-custom-css-before-' . $timestamp . '.txt';
		file_put_contents( $path, $custom_css ); // phpcs:ignore
		$written['kit_custom_css'] = $path;

		$path = $dir . 'active-plugins-before-' . $timestamp . '.txt';
		file_put_contents( $path, implode( "\n", (array) get_option( 'active_plugins', array() ) ) . "\n" ); // phpcs:ignore
		$written['active_plugins'] = $path;

		$response = wp_remote_get( home_url( '/' ), array( 'timeout' => 15 ) );
		$dom_html = is_wp_error( $response )
			? '<!-- fetch failed: ' . esc_html( $response->get_error_message() ) . ' -->'
			: wp_remote_retrieve_body( $response );
		$path     = $dir . 'homepage-dom-before-' . $timestamp . '.html';
		file_put_contents( $path, $dom_html ); // phpcs:ignore
		$written['dom'] = $path;

		$kit_class_meta = array(
			'_elementor_global_classes_order'       => get_post_meta( $this->kit_id, '_elementor_global_classes_order', true ),
			'_elementor_global_classes_labels'      => get_post_meta( $this->kit_id, '_elementor_global_classes_labels', true ),
			'_elementor_global_classes_post_ids'    => get_post_meta( $this->kit_id, '_elementor_global_classes_post_ids', true ),
			'_elementor_global_class_usage_indexed' => get_post_meta( $this->kit_id, '_elementor_global_class_usage_indexed', true ),
		);
		$path = $dir . 'elementor-kit-global-classes-registry-before-' . $timestamp . '.json';
		file_put_contents( $path, wp_json_encode( $kit_class_meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); // phpcs:ignore
		$written['global_classes_registry'] = $path;

		$global_class_posts = get_posts( array(
			'post_type'      => 'e_global_class',
			'posts_per_page' => -1,
			'post_status'    => 'any',
		) );
		$dump = array();
		foreach ( $global_class_posts as $gcp ) {
			$dump[] = array(
				'ID'          => $gcp->ID,
				'post_title'  => $gcp->post_title,
				'post_name'   => $gcp->post_name,
				'post_status' => $gcp->post_status,
				'meta'        => array(
					'_elementor_global_class_id'   => get_post_meta( $gcp->ID, '_elementor_global_class_id', true ),
					'_elementor_global_class_data' => get_post_meta( $gcp->ID, '_elementor_global_class_data', true ),
				),
			);
		}
		$path = $dir . 'elementor-global-class-posts-before-' . $timestamp . '.json';
		file_put_contents( $path, wp_json_encode( $dump, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); // phpcs:ignore
		$written['global_class_posts'] = $path;

		// Draft page copy (idempotent per calendar minute: skip if an identical-titled draft already exists).
		$label = current_time( 'd.m.Y H:i' );
		$title = 'Startseite – Backup vor Neuaufbau – ' . $label;
		$existing = get_page_by_title( $title, OBJECT, 'page' );
		if ( $existing ) {
			$written['draft_page_id'] = $existing->ID;
		} else {
			$source = get_post( $post_id );
			$new_id = wp_insert_post( array(
				'post_title'   => $title,
				'post_content' => $source->post_content,
				'post_excerpt' => $source->post_excerpt,
				'post_status'  => 'draft',
				'post_type'    => 'page',
				'post_author'  => $source->post_author,
			), true );
			if ( ! is_wp_error( $new_id ) ) {
				foreach ( array( '_elementor_data', '_elementor_edit_mode', '_elementor_template_type', '_elementor_version', '_wp_page_template', '_elementor_page_settings' ) as $key ) {
					$value = get_post_meta( $post_id, $key, true );
					if ( '' !== $value ) {
						update_post_meta( $new_id, $key, $value );
					}
				}
				$written['draft_page_id'] = $new_id;
			}
		}

		$written['timestamp'] = $timestamp;

		return $written;
	}

	/* ---------------------------------------------------------------
	 * Analysis
	 * ------------------------------------------------------------- */

	/**
	 * Reads and JSON-decodes the current homepage Elementor data.
	 *
	 * @return array|WP_Error
	 */
	private function get_current_data() {
		$raw = get_post_meta( self::POST_ID, '_elementor_data', true );
		if ( '' === $raw ) {
			return new WP_Error( 'avf_homepage_no_data', 'No _elementor_data found on post ' . self::POST_ID );
		}
		$decoded = json_decode( $raw, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error( 'avf_homepage_invalid_json', 'Could not decode _elementor_data: ' . json_last_error_msg() );
		}
		return $decoded;
	}

	/**
	 * @return array g-id => label
	 */
	private function get_global_class_labels() {
		$labels = get_post_meta( $this->kit_id, '_elementor_global_classes_labels', true );
		return is_array( $labels ) ? $labels : array();
	}

	/**
	 * Resolves a list of local/global class IDs to their rendered
	 * class name(s), where known (global classes only; local "e-..."
	 * IDs are returned as-is since they have no separate label).
	 *
	 * @param array $ids
	 * @return array
	 */
	private function resolve_class_names( array $ids ) {
		$labels = $this->get_global_class_labels();
		$out    = array();
		foreach ( $ids as $id ) {
			$out[] = isset( $labels[ $id ] ) ? $labels[ $id ] : $id;
		}
		return $out;
	}

	/**
	 * Produces a structured, human-readable description of every
	 * top-level element and its descendants: ID, type, resolved
	 * classes, child count, whether it is empty, and a short text
	 * preview. Used by both the dry-run report and the final report.
	 *
	 * @return array|WP_Error
	 */
	public function analyze() {
		$data = $this->get_current_data();
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$out = array();
		foreach ( $data as $index => $el ) {
			$out[] = $this->describe_element( $el, 0, (string) $index );
		}
		return $out;
	}

	private function describe_element( array $el, $depth, $path ) {
		$classes = array();
		if ( isset( $el['settings']['classes']['value'] ) && is_array( $el['settings']['classes']['value'] ) ) {
			$classes = $this->resolve_class_names( $el['settings']['classes']['value'] );
		} elseif ( ! empty( $el['settings']['_css_classes'] ) ) {
			$classes = array_filter( explode( ' ', $el['settings']['_css_classes'] ) );
		}

		$text = '';
		foreach ( array( 'title', 'editor', 'shortcode', 'text' ) as $key ) {
			if ( ! empty( $el['settings'][ $key ] ) && is_string( $el['settings'][ $key ] ) ) {
				$text = wp_strip_all_tags( $el['settings'][ $key ] );
				break;
			}
		}

		$children = isset( $el['elements'] ) && is_array( $el['elements'] ) ? $el['elements'] : array();

		$node = array(
			'path'        => $path,
			'depth'       => $depth,
			'id'          => $el['id'] ?? '',
			'elType'      => $el['elType'] ?? '',
			'widgetType'  => $el['widgetType'] ?? '',
			'classes'     => $classes,
			'child_count' => count( $children ),
			'is_empty'    => empty( $children ) && 'widget' !== ( $el['elType'] ?? '' ),
			'text'        => mb_substr( $text, 0, 100 ),
			'has_avf_hero_class' => in_array( 'avf-hero', $classes, true ) || in_array( 'avf-home-hero', $classes, true ),
			'children'    => array(),
		);

		foreach ( $children as $i => $child ) {
			$node['children'][] = $this->describe_element( $child, $depth + 1, $path . '.' . $i );
		}

		return $node;
	}

	/**
	 * Locates the top-level hero element by its stable global class ID
	 * (works whether the class label currently reads "avf-hero" or
	 * already "avf-home-hero" — the ID itself never changes).
	 *
	 * @param array $data Decoded top-level elements.
	 * @return array|null
	 */
	private function find_hero_element( array $data ) {
		foreach ( $data as $el ) {
			$class_ids = $el['settings']['classes']['value'] ?? array();
			if ( in_array( self::HERO_GLOBAL_CLASS_ID, $class_ids, true ) ) {
				return $el;
			}
		}
		return null;
	}

	/* ---------------------------------------------------------------
	 * Global classes (Elementor "e_global_class" post type)
	 * ------------------------------------------------------------- */

	/**
	 * Idempotently ensures a global class with the given label exists,
	 * with the given base-desktop style props plus optional additional
	 * per-breakpoint variants. Reuses an existing class (by label) if
	 * present, updating its props; otherwise creates a new one and
	 * registers it in the Kit's class registry.
	 *
	 * @param string $label             Human-readable, unique class name (e.g. "avf-home-main").
	 * @param array  $props             Base/desktop style props, e.g. ['display' => 'flex'].
	 * @param array  $responsive_props  Optional map of breakpoint => props, e.g.
	 *                                  ['tablet' => ['margin-top' => '-44px'], 'mobile' => [...]].
	 *                                  Valid breakpoint keys in this Elementor version: mobile,
	 *                                  mobile_extra, tablet, tablet_extra, laptop, desktop, widescreen.
	 * @return string The global class ID (e.g. "g-1a2b3c4").
	 */
	private function ensure_global_class( $label, array $props = array(), array $responsive_props = array() ) {
		$labels = $this->get_global_class_labels();
		$gid    = array_search( $label, $labels, true );

		$to_style_props = function ( array $props ) {
			$style_props = array();
			foreach ( $props as $prop => $value ) {
				$style_props[ $prop ] = array(
					'$$type' => 'string',
					'value'  => (string) $value,
				);
			}
			return $style_props;
		};

		$variants = array(
			array(
				'meta'  => array(
					'breakpoint' => 'desktop',
					'state'      => null,
				),
				'props' => $to_style_props( $props ),
				'custom_css' => null,
			),
		);

		foreach ( $responsive_props as $breakpoint => $bp_props ) {
			$variants[] = array(
				'meta'  => array(
					'breakpoint' => $breakpoint,
					'state'      => null,
				),
				'props' => $to_style_props( $bp_props ),
				'custom_css' => null,
			);
		}

		$class_data = array(
			'type'     => 'class',
			'variants' => $variants,
		);

		if ( false !== $gid ) {
			// Reuse existing class post; refresh its props to the desired minimal set.
			$post_ids = get_post_meta( $this->kit_id, '_elementor_global_classes_post_ids', true );
			$post_id  = is_array( $post_ids ) && isset( $post_ids[ $gid ] ) ? (int) $post_ids[ $gid ] : 0;
			if ( $post_id && get_post( $post_id ) ) {
				update_post_meta( $post_id, '_elementor_global_class_data', $class_data );
				return $gid;
			}
			// Registered in labels but the post is missing/orphaned: fall through and recreate it below,
			// re-using the same label entry.
		}

		$gid = false !== $gid ? $gid : $this->generate_global_class_id( $label );

		$post_id = wp_insert_post( array(
			'post_type'   => 'e_global_class',
			'post_title'  => $label,
			'post_name'   => sanitize_title( $label ),
			'post_status' => 'publish',
		), true );

		if ( is_wp_error( $post_id ) ) {
			// Non-fatal: element will simply not carry this class. Logged by caller via return value check.
			return '';
		}

		update_post_meta( $post_id, '_elementor_global_class_id', $gid );
		update_post_meta( $post_id, '_elementor_global_class_data', $class_data );
		update_post_meta( $post_id, '_elementor_version', ELEMENTOR_VERSION );

		$labels[ $gid ] = $label;
		update_post_meta( $this->kit_id, '_elementor_global_classes_labels', $labels );

		$order = get_post_meta( $this->kit_id, '_elementor_global_classes_order', true );
		if ( ! is_array( $order ) || ! isset( $order['order'] ) ) {
			$order = array( 'order' => array() );
		}
		if ( ! in_array( $gid, $order['order'], true ) ) {
			$order['order'][] = $gid;
		}
		update_post_meta( $this->kit_id, '_elementor_global_classes_order', $order );

		$post_ids = get_post_meta( $this->kit_id, '_elementor_global_classes_post_ids', true );
		if ( ! is_array( $post_ids ) ) {
			$post_ids = array();
		}
		$post_ids[ $gid ] = $post_id;
		update_post_meta( $this->kit_id, '_elementor_global_classes_post_ids', $post_ids );

		// The Elementor editor reads the "_preview" (draft) registry, not just
		// the live/published one. Without this, newly created classes are
		// correctly referenced by ID on elements but invisible to the editor's
		// own class registry ("Some classes are missing" / completely
		// unstyled canvas), even though the published frontend renders fine.
		$this->sync_global_classes_registry_to_preview();

		return $gid;
	}

	/**
	 * Mirrors the live global-classes registry (order + labels) into their
	 * "_preview" counterparts, which is what the Elementor editor canvas
	 * actually reads from. Keeps both in lockstep so the editor never shows
	 * classes as "missing" for elements that reference them.
	 *
	 * @return void
	 */
	private function sync_global_classes_registry_to_preview() {
		$order = get_post_meta( $this->kit_id, '_elementor_global_classes_order', true );
		if ( is_array( $order ) ) {
			update_post_meta( $this->kit_id, '_elementor_global_classes_order_preview', $order );
		}

		$labels = get_post_meta( $this->kit_id, '_elementor_global_classes_labels', true );
		if ( is_array( $labels ) ) {
			update_post_meta( $this->kit_id, '_elementor_global_classes_labels_preview', $labels );
		}
	}

	/**
	 * Deterministic "g-xxxxxxx" ID derived from the label, guaranteed
	 * stable across runs (same idempotency guarantee as stable_id()).
	 *
	 * @param string $label
	 * @return string
	 */
	private function generate_global_class_id( $label ) {
		return 'g-' . substr( md5( self::ID_NAMESPACE . 'global-class:' . $label ), 0, 7 );
	}

	/**
	 * Renames an existing global class's label in place (ID/props
	 * untouched). Idempotent: no-op if already renamed.
	 *
	 * @param string $gid
	 * @param string $new_label
	 * @return void
	 */
	private function rename_global_class_label( $gid, $new_label ) {
		$labels = $this->get_global_class_labels();
		if ( isset( $labels[ $gid ] ) && $labels[ $gid ] === $new_label ) {
			return;
		}
		$labels[ $gid ] = $new_label;
		update_post_meta( $this->kit_id, '_elementor_global_classes_labels', $labels );
		$this->sync_global_classes_registry_to_preview();

		$post_ids = get_post_meta( $this->kit_id, '_elementor_global_classes_post_ids', true );
		$post_id  = is_array( $post_ids ) && isset( $post_ids[ $gid ] ) ? (int) $post_ids[ $gid ] : 0;
		if ( $post_id ) {
			wp_update_post( array(
				'ID'         => $post_id,
				'post_title' => $new_label,
				'post_name'  => sanitize_title( $new_label ),
			) );
		}
	}

	/**
	 * Ensures every global class needed by the new homepage structure
	 * exists, creating/updating as necessary. Returns a label => gid map.
	 *
	 * @return array
	 */
	private function ensure_all_global_classes() {
		$map = array();

		// Structurally important props are mirrored here (not just in homepage.css)
		// so that Elementor's own Style panel and editor canvas reflect the real
		// layout even before homepage.css is taken into account, per the atomic
		// global-class system confirmed to also drive the editor iframe/preview.
		// The hero/events overlap is applied as a negative margin-top on
		// avf-home-main ITSELF, not on the events-slot child. avf-home-main
		// has overflow:hidden (needed to contain the decor layer), and CSS
		// unconditionally clips any descendant that visually pokes above its
		// own parent's border box via a negative margin - regardless of
		// z-index. Putting the negative margin on main's own box sidesteps
		// that entirely, since an element is never clipped by its own
		// overflow rule.
		$map['avf-home-main']              = $this->ensure_global_class( 'avf-home-main', array(
			'display'        => 'flex',
			'flex-direction' => 'column',
			'width'          => '100%',
			'background-color' => '#F7F5EF',
			'position'        => 'relative',
			'z-index'         => '2',
			'margin-top'      => '-112px',
		), array(
			'tablet' => array( 'margin-top' => '-76px' ),
			'mobile' => array( 'margin-top' => '-40px' ),
		) );
		$map['avf-home-events-slot']       = $this->ensure_global_class( 'avf-home-events-slot', array(
			'display'         => 'flex',
			'justify-content' => 'center',
			'width'           => '100%',
		) );
		$map['avf-home-featured-slot']     = $this->ensure_global_class( 'avf-home-featured-slot', array(
			'display'         => 'flex',
			'justify-content' => 'center',
			'width'           => '100%',
		) );
		$map['avf-home-values']            = $this->ensure_global_class( 'avf-home-values', array(
			'display' => 'block',
			'width'   => '100%',
		) );
		$map['avf-home-values__inner']     = $this->ensure_global_class( 'avf-home-values__inner', array(
			'display'        => 'flex',
			'flex-direction' => 'column',
			'gap'            => '56px',
		) );
		$map['avf-home-values__heading']         = $this->ensure_global_class( 'avf-home-values__heading', array(
			'display'        => 'flex',
			'flex-direction' => 'column',
			'align-items'    => 'center',
		) );
		$map['avf-home-values__heading-main']    = $this->ensure_global_class( 'avf-home-values__heading-main', array(
			'display'        => 'flex',
			'flex-direction' => 'column',
			'align-items'    => 'center',
		) );
		$map['avf-home-values__grid']      = $this->ensure_global_class( 'avf-home-values__grid', array(
			'display'               => 'grid',
			'width'                 => '100%',
			'grid-template-columns' => 'repeat(3, minmax(0, 1fr))',
			'gap'                   => '28px',
		), array(
			'tablet' => array( 'grid-template-columns' => 'repeat(2, minmax(0, 1fr))' ),
			'mobile' => array( 'grid-template-columns' => 'minmax(0, 1fr)' ),
		) );
		$map['avf-home-value-card']        = $this->ensure_global_class( 'avf-home-value-card', array(
			'display'        => 'flex',
			'flex-direction' => 'column',
			'background-color' => '#FFFFFF',
		) );
		$map['avf-home-value-card--orange']  = $this->ensure_global_class( 'avf-home-value-card--orange', array(
			'border-top' => '3px solid #D87836',
		) );
		$map['avf-home-value-card--neutral'] = $this->ensure_global_class( 'avf-home-value-card--neutral', array(
			'border-top' => '3px solid #183F1E',
		) );
		$map['avf-home-value-card--green']   = $this->ensure_global_class( 'avf-home-value-card--green', array(
			'border-top' => '3px solid #245C2B',
		) );
		$map['avf-home-quote']             = $this->ensure_global_class( 'avf-home-quote', array(
			'display'         => 'flex',
			'align-items'     => 'center',
			'justify-content' => 'center',
			'width'           => '100%',
			'background-color' => '#0E1711',
			'min-height'      => '360px',
		) );
		$map['avf-home-quote__inner']      = $this->ensure_global_class( 'avf-home-quote__inner', array(
			'display'        => 'flex',
			'flex-direction' => 'column',
			'align-items'    => 'center',
		) );

		// Unconditional final sync: on repeat runs every class above takes the
		// "reuse existing" branch inside ensure_global_class(), which never
		// touches the order/labels registry (nothing to add). Without this,
		// the "_preview" registry the editor reads from would only ever get
		// synced on the very first run that actually creates each class.
		$this->sync_global_classes_registry_to_preview();

		return $map;
	}

	/* ---------------------------------------------------------------
	 * Element builders
	 * ------------------------------------------------------------- */

	private function classes_setting( array $ids ) {
		return array(
			'$$type' => 'classes',
			'value'  => array_values( array_filter( $ids ) ),
		);
	}

	/**
	 * Builds an e-flexbox container.
	 *
	 * @param string $semantic_id       Stable semantic name (drives the deterministic element ID).
	 * @param array  $global_class_ids  Global class IDs to attach.
	 * @param array  $children          Child element definitions.
	 * @param array  $options           Optional: 'html_id' (renders as the element's HTML id
	 *                                  attribute via _cssid) and 'title' (Elementor Navigator
	 *                                  label shown to editors, stored in editor_settings.title -
	 *                                  purely an editing aid, no effect on the frontend).
	 * @return array
	 */
	private function flexbox( $semantic_id, array $global_class_ids, array $children, array $options = array() ) {
		$settings = array(
			'classes' => $this->classes_setting( $global_class_ids ),
		);
		if ( ! empty( $options['html_id'] ) ) {
			$settings['_cssid'] = array( '$$type' => 'string', 'value' => $options['html_id'] );
		}
		$editor_settings = array();
		if ( ! empty( $options['title'] ) ) {
			$editor_settings['title'] = $options['title'];
		}
		return array(
			'id'       => $this->stable_id( $semantic_id ),
			'elType'   => 'e-flexbox',
			'settings' => $settings,
			'elements' => $children,
			'isInner'  => false,
			'styles'   => array(),
			'interactions' => array(),
			'editor_settings' => $editor_settings,
			'version'  => '0.0',
		);
	}

	private function widget_heading( $semantic_id, $title, $header_size = 'h2', $css_classes = '' ) {
		$settings = array( 'title' => $title, 'header_size' => $header_size );
		if ( $css_classes ) {
			$settings['_css_classes'] = $css_classes;
		}
		return array(
			'id'         => $this->stable_id( $semantic_id ),
			'elType'     => 'widget',
			'settings'   => $settings,
			'elements'   => array(),
			'widgetType' => 'heading',
		);
	}

	private function widget_text_editor( $semantic_id, $html, $css_classes = '' ) {
		$settings = array( 'editor' => $html );
		if ( $css_classes ) {
			$settings['_css_classes'] = $css_classes;
		}
		return array(
			'id'         => $this->stable_id( $semantic_id ),
			'elType'     => 'widget',
			'settings'   => $settings,
			'elements'   => array(),
			'widgetType' => 'text-editor',
		);
	}

	private function widget_icon( $semantic_id, $fa_icon, $css_classes = '' ) {
		$settings = array(
			'selected_icon' => array(
				'value'   => $fa_icon,
				'library' => 'fa-solid',
			),
			'size' => array( 'unit' => 'px', 'size' => 30, 'sizes' => array() ),
		);
		if ( $css_classes ) {
			$settings['_css_classes'] = $css_classes;
		}
		return array(
			'id'         => $this->stable_id( $semantic_id ),
			'elType'     => 'widget',
			'settings'   => $settings,
			'elements'   => array(),
			'widgetType' => 'icon',
		);
	}

	private function widget_button( $semantic_id, $text, $url, $css_classes = '' ) {
		$settings = array(
			'text' => $text,
			'link' => array(
				'url'                => $url,
				'is_external'        => '',
				'nofollow'           => '',
				'custom_attributes'  => '',
			),
		);
		if ( $css_classes ) {
			$settings['_css_classes'] = $css_classes;
		}
		return array(
			'id'         => $this->stable_id( $semantic_id ),
			'elType'     => 'widget',
			'settings'   => $settings,
			'elements'   => array(),
			'widgetType' => 'button',
		);
	}

	private function widget_shortcode( $semantic_id, $shortcode ) {
		return array(
			'id'         => $this->stable_id( $semantic_id ),
			'elType'     => 'widget',
			'settings'   => array( 'shortcode' => $shortcode ),
			'elements'   => array(),
			'widgetType' => 'shortcode',
		);
	}

	private function build_events_slot( array $gc ) {
		return $this->flexbox( 'events-slot', array( $gc['avf-home-events-slot'] ), array(
			$this->widget_shortcode( 'events-shortcode', '[avf_upcoming_events limit="3"]' ),
		), array( 'title' => '📅 Kommende Anlässe (Events-Slot)' ) );
	}

	private function build_featured_slot( array $gc ) {
		return $this->flexbox( 'featured-slot', array( $gc['avf-home-featured-slot'] ), array(
			$this->widget_shortcode( 'featured-shortcode', '[avf_featured_post]' ),
		), array( 'title' => '📰 Favorisierter Beitrag (Featured-Slot)' ) );
	}

	private function build_value_card( array $gc, $key, $modifier_class_key, $fa_icon, $title, $text, $link_text, $link_url, $card_number ) {
		return $this->flexbox( 'card-' . $key, array( $gc['avf-home-value-card'], $gc[ $modifier_class_key ] ), array(
			$this->widget_icon( 'card-' . $key . '-icon', $fa_icon, 'avf-home-value-card__icon' ),
			$this->widget_heading( 'card-' . $key . '-title', $title, 'h3', 'avf-home-value-card__title' ),
			$this->widget_text_editor( 'card-' . $key . '-text', '<p>' . esc_html( $text ) . '</p>', 'avf-home-value-card__text' ),
			$this->widget_button( 'card-' . $key . '-link', $link_text, $link_url, 'avf-home-value-card__link' ),
		), array( 'title' => "🃏 Karte {$card_number}: {$title}" ) );
	}

	private function build_values_section( array $gc ) {
		$heading_main = $this->flexbox( 'values-heading-main', array( $gc['avf-home-values__heading-main'] ), array(
			$this->widget_text_editor( 'values-eyebrow', '<p>AV FROBURGER</p>', 'avf-home-values__eyebrow' ),
			$this->widget_heading( 'values-title', 'Was uns verbindet', 'h2', 'avf-home-values__title' ),
		), array( 'title' => 'Eyebrow + Titel' ) );

		$heading = $this->flexbox( 'values-heading', array( $gc['avf-home-values__heading'] ), array(
			$heading_main,
			$this->widget_text_editor(
				'values-intro',
				'<p>Seit Generationen schaffen wir einen Ort für Freundschaft, persönliche Entwicklung und gelebte Verbindungskultur.</p>',
				'avf-home-values__intro'
			),
		), array( 'title' => 'Überschriftenblock' ) );

		$card1 = $this->build_value_card(
			$gc, 'orange', 'avf-home-value-card--orange', 'fas fa-book-open',
			'Geschichte & Tradition',
			'Gewachsen aus einer langen Verbindungsgeschichte und offen für die Fragen der Gegenwart.',
			'Entdecken →', '/ueber-uns/#geschichte', 1
		);
		$card2 = $this->build_value_card(
			$gc, 'neutral', 'avf-home-value-card--neutral', 'fas fa-users',
			'Freundschaft & Netzwerk',
			'Ein tragfähiges Netzwerk, das über Studienzeit, Beruf und Lebensphasen hinaus Bestand hat.',
			'Mitglieder ansehen →', '/mitglieder/', 2
		);
		$card3 = $this->build_value_card(
			$gc, 'green', 'avf-home-value-card--green', 'fas fa-shield-alt',
			'Couleur & Werte',
			'Orange, Weiss und Grün stehen für Freundschaft, Wissenschaft und Tugend – Amicitia, Scientia und Virtus.',
			'Mehr erfahren →', '/ueber-uns/#couleur', 3
		);

		$grid = $this->flexbox( 'values-grid', array( $gc['avf-home-values__grid'] ), array( $card1, $card2, $card3 ), array( 'title' => '▦ Karten-Grid (3 Spalten)' ) );

		$inner = $this->flexbox( 'values-inner', array( $gc['avf-home-values__inner'] ), array( $heading, $grid ), array( 'title' => 'Innerer Container' ) );

		return $this->flexbox( 'values-section', array( $gc['avf-home-values'] ), array( $inner ), array( 'html_id' => 'avf-values', 'title' => '🟩 Was uns verbindet (Werte-Sektion)' ) );
	}

	private function build_quote_section( array $gc ) {
		$inner = $this->flexbox( 'quote-inner', array( $gc['avf-home-quote__inner'] ), array(
			$this->widget_heading( 'quote-latin', '«Voluntate forti viam rectam!»', 'h2', 'avf-home-quote__latin' ),
			$this->widget_text_editor( 'quote-translation', '<p>Mit starkem Willen den rechten Weg.</p>', 'avf-home-quote__translation' ),
		), array( 'title' => 'Zitat + Übersetzung' ) );

		return $this->flexbox( 'quote-section', array( $gc['avf-home-quote'] ), array( $inner ), array( 'html_id' => 'avf-quote-band', 'title' => '🟢 Zitatband (Wahlspruch)' ) );
	}

	/**
	 * Builds the complete, fresh "Hauptinhalt" subtree.
	 *
	 * @param array $gc Label => global-class-ID map.
	 * @return array
	 */
	private function build_main_container( array $gc ) {
		return $this->flexbox( 'main', array( $gc['avf-home-main'] ), array(
			$this->build_events_slot( $gc ),
			$this->build_featured_slot( $gc ),
			$this->build_values_section( $gc ),
			$this->build_quote_section( $gc ),
		), array( 'title' => '⬛ Hauptinhalt (Events, Featured Post, Werte, Zitatband)' ) );
	}

	/* ---------------------------------------------------------------
	 * Plan (dry-run) / Rebuild
	 * ------------------------------------------------------------- */

	/**
	 * Produces a structured plan without changing anything.
	 *
	 * @return array|WP_Error
	 */
	public function build_plan() {
		$verified = $this->verify_frontpage();
		if ( is_wp_error( $verified ) ) {
			return $verified;
		}

		$data = $this->get_current_data();
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$hero = $this->find_hero_element( $data );

		$plan = array(
			'frontpage_ok'        => true,
			'top_level_count_now' => count( $data ),
			'hero_found'          => null !== $hero,
			'hero_id'             => $hero['id'] ?? null,
			'elements_to_remove'  => array(),
			'elements_to_keep'    => array(),
			'new_top_level_count' => 2,
			'global_classes_to_ensure' => array(
				'avf-home-main', 'avf-home-events-slot', 'avf-home-featured-slot',
				'avf-home-values', 'avf-home-values__inner', 'avf-home-values__heading',
				'avf-home-values__heading-main', 'avf-home-values__grid', 'avf-home-value-card',
				'avf-home-value-card--orange', 'avf-home-value-card--neutral', 'avf-home-value-card--green',
				'avf-home-quote', 'avf-home-quote__inner',
			),
			'global_class_rename' => array( self::HERO_GLOBAL_CLASS_ID => array( 'from' => 'avf-hero', 'to' => 'avf-home-hero' ) ),
		);

		foreach ( $data as $el ) {
			if ( $hero && $el['id'] === $hero['id'] ) {
				$plan['elements_to_keep'][] = $el['id'];
			} else {
				$plan['elements_to_remove'][] = $el['id'];
			}
		}

		return $plan;
	}

	/**
	 * Performs the full, idempotent rebuild: backup, reconstruct,
	 * validate, save via Elementor's Document API, regenerate CSS.
	 *
	 * @return array|WP_Error
	 */
	public function rebuild() {
		$verified = $this->verify_frontpage();
		if ( is_wp_error( $verified ) ) {
			return $verified;
		}

		$backup = $this->backup();
		if ( is_wp_error( $backup ) ) {
			return $backup;
		}

		$data = $this->get_current_data();
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$hero = $this->find_hero_element( $data );
		if ( null === $hero ) {
			return new WP_Error(
				'avf_homepage_hero_not_found',
				'Could not unambiguously locate the existing hero element (global class ' . self::HERO_GLOBAL_CLASS_ID . '). Aborting without changes.'
			);
		}

		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return new WP_Error( 'avf_homepage_no_elementor', 'Elementor is not loaded.' );
		}

		$this->rename_global_class_label( self::HERO_GLOBAL_CLASS_ID, 'avf-home-hero' );
		$gc = $this->ensure_all_global_classes();

		$main = $this->build_main_container( $gc );

		// Purely additive Navigator label for the untouched hero (does not
		// touch its settings/elements/content in any way).
		if ( empty( $hero['editor_settings']['title'] ) ) {
			$hero['editor_settings'] = array_merge(
				is_array( $hero['editor_settings'] ?? null ) ? $hero['editor_settings'] : array(),
				array( 'title' => '🖼️ Hero (Karussell, Wappen, Titel, Buttons)' )
			);
		}

		$new_elements = array( $hero, $main );

		// Validate: encode/decode round-trip must succeed and be lossless in structure.
		$encoded = wp_json_encode( $new_elements );
		if ( false === $encoded ) {
			return new WP_Error( 'avf_homepage_encode_failed', 'Could not JSON-encode the new element tree.' );
		}
		$roundtrip = json_decode( $encoded, true );
		if ( JSON_ERROR_NONE !== json_last_error() || count( $roundtrip ) !== count( $new_elements ) ) {
			return new WP_Error( 'avf_homepage_validate_failed', 'New element tree failed JSON validation.' );
		}

		// Ensure we have a capable current user so Document::save() is permitted
		// and does not run unfiltered_html sanitization over our content.
		$restore_user = get_current_user_id();
		$acting_user  = $this->get_acting_admin_user_id();
		if ( $acting_user ) {
			wp_set_current_user( $acting_user );
		}

		$document = \Elementor\Plugin::$instance->documents->get( self::POST_ID );
		if ( ! $document ) {
			if ( $acting_user ) {
				wp_set_current_user( $restore_user );
			}
			return new WP_Error( 'avf_homepage_no_document', 'Could not load Elementor document for post ' . self::POST_ID );
		}

		$saved = $document->save( array( 'elements' => $new_elements ) );

		if ( $acting_user ) {
			wp_set_current_user( $restore_user );
		}

		if ( ! $saved ) {
			return new WP_Error( 'avf_homepage_save_failed', 'Elementor Document::save() returned false.' );
		}

		$this->regenerate_css();

		return array(
			'backup'          => $backup,
			'hero_id'         => $hero['id'],
			'main_id'         => $main['id'],
			'global_classes'  => $gc,
			'top_level_count' => count( $new_elements ),
		);
	}

	/**
	 * Finds an administrator to act as, for capability checks during
	 * a CLI-triggered save. Never creates a user.
	 *
	 * @return int 0 if none found.
	 */
	private function get_acting_admin_user_id() {
		$admins = get_users( array(
			'role'    => 'administrator',
			'number'  => 1,
			'orderby' => 'ID',
			'order'   => 'ASC',
			'fields'  => 'ID',
		) );
		return ! empty( $admins ) ? (int) $admins[0] : 0;
	}

	/**
	 * Regenerates Elementor's generated CSS via its own supported
	 * mechanisms only (no direct edits to generated files).
	 *
	 * @return void
	 */
	public function regenerate_css() {
		if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
			$post_css = \Elementor\Core\Files\CSS\Post::create( self::POST_ID );
			$post_css->delete();
		}
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
	}

	/* ---------------------------------------------------------------
	 * Restore
	 * ------------------------------------------------------------- */

	/**
	 * Restores the homepage from a previously written backup file.
	 *
	 * @param string $backup_ref Either a bare timestamp (e.g. "2026-07-21_191838")
	 *                           or a full path/filename to a *-elementor-data-before-*.json file.
	 * @return array|WP_Error
	 */
	public function restore( $backup_ref ) {
		$verified = $this->verify_frontpage();
		if ( is_wp_error( $verified ) ) {
			return $verified;
		}

		$dir = WP_CONTENT_DIR . '/uploads/avf-backups/homepage/';

		if ( file_exists( $backup_ref ) ) {
			$file = $backup_ref;
		} elseif ( file_exists( $dir . $backup_ref ) ) {
			$file = $dir . $backup_ref;
		} else {
			$file = $dir . 'startseite-elementor-data-before-' . $backup_ref . '.json';
		}

		if ( ! file_exists( $file ) ) {
			return new WP_Error( 'avf_homepage_backup_missing', 'Backup file not found: ' . $file );
		}

		$json = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$decoded = json_decode( $json, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			return new WP_Error( 'avf_homepage_restore_invalid_json', 'Backup file is not valid JSON: ' . $file );
		}

		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return new WP_Error( 'avf_homepage_no_elementor', 'Elementor is not loaded.' );
		}

		$restore_user = get_current_user_id();
		$acting_user  = $this->get_acting_admin_user_id();
		if ( $acting_user ) {
			wp_set_current_user( $acting_user );
		}

		$document = \Elementor\Plugin::$instance->documents->get( self::POST_ID );
		$saved    = $document ? $document->save( array( 'elements' => $decoded ) ) : false;

		if ( $acting_user ) {
			wp_set_current_user( $restore_user );
		}

		if ( ! $saved ) {
			return new WP_Error( 'avf_homepage_restore_failed', 'Elementor Document::save() returned false during restore.' );
		}

		$this->regenerate_css();

		return array( 'restored_from' => $file );
	}
}
