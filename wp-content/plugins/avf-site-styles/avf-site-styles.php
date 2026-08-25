<?php
/**
 * Plugin Name:       AV Froburger Site Styles
 * Description:       Enthält gezielte projektspezifische Layoutkorrekturen.
 * Version:            1.3.0
 * Requires at least:  6.3
 * Requires PHP:       7.4
 * Author:             AV Froburger
 * Text Domain:        avf-site-styles
 * Domain Path:        /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'AVF_SITE_STYLES_VERSION', '1.3.0' );
define( 'AVF_SITE_STYLES_DIR', plugin_dir_path( __FILE__ ) );
define( 'AVF_SITE_STYLES_URL', plugin_dir_url( __FILE__ ) );

/**
 * Returns whether the current request is a backend/editor/programmatic path
 * where this plugin must not perform any content mutations or enqueue logic.
 *
 * @return bool
 */
function avf_site_styles_is_backend_request() {
	if ( is_admin() || wp_doing_ajax() ) {
		return true;
	}

	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return true;
	}

	return false;
}

/**
 * Returns whether write/migration routines should stay inactive for the
 * current request. Elementor save/publish, wp-admin, REST and AJAX requests
 * must not trigger these background content mutations.
 *
 * @return bool
 */
function avf_site_styles_should_skip_mutations() {
	if ( avf_site_styles_is_backend_request() ) {
		return true;
	}

	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return true;
	}

	if ( avf_site_styles_is_elementor_editor_request() ) {
		return true;
	}

	return false;
}

/**
 * Detects whether the current request belongs to the Elementor editor or its
 * preview iframe. This intentionally avoids calling deeper Elementor preview
 * APIs here, because publish/save requests can hit different execution paths
 * where conservative request inspection is safer than invoking editor state.
 *
 * @return bool
 */
function avf_site_styles_is_elementor_editor_request() {
	if ( isset( $_GET['action'] ) && 'elementor' === sanitize_key( wp_unslash( $_GET['action'] ) ) ) {
		return true;
	}

	if ( isset( $_GET['elementor-preview'] ) ) {
		return true;
	}

	if (
		isset( $_GET['preview'] ) &&
		'true' === strtolower( sanitize_text_field( wp_unslash( $_GET['preview'] ) ) ) &&
		isset( $_GET['preview_id'] )
	) {
		return true;
	}

	return false;
}

/**
 * Normalizes the active Elementor kit so AVF's custom CSS reads its body and
 * display font aliases from Elementor's own global typography variables.
 *
 * The kit currently stores a second, literal font source inside its custom
 * CSS. On the frontend the body also carries the `elementor-kit-{id}` class,
 * whose generated typography rule has higher specificity than the kit's own
 * `body { font-family: ... }` block. Making the AVF aliases resolve from the
 * same Elementor variables removes that split authority at the source instead
 * of layering another override on top.
 *
 * @return void
 */
function avf_site_styles_normalize_active_kit_typography() {
	if ( avf_site_styles_should_skip_mutations() ) {
		return;
	}

	$kit_id = (int) get_option( 'elementor_active_kit' );

	if ( $kit_id <= 0 ) {
		return;
	}

	$migration_state = get_option( 'avf_site_styles_kit_typography_sync' );

	if (
		is_array( $migration_state ) &&
		isset( $migration_state['version'], $migration_state['kit_id'] ) &&
		AVF_SITE_STYLES_VERSION === $migration_state['version'] &&
		$kit_id === (int) $migration_state['kit_id']
	) {
		return;
	}

	$settings = get_post_meta( $kit_id, '_elementor_page_settings', true );

	if ( ! is_array( $settings ) || empty( $settings['custom_css'] ) || ! is_string( $settings['custom_css'] ) ) {
		update_option(
			'avf_site_styles_kit_typography_sync',
			array(
				'version' => AVF_SITE_STYLES_VERSION,
				'kit_id'  => $kit_id,
			),
			false
		);

		return;
	}

	$custom_css = $settings['custom_css'];
	$updated_css = preg_replace(
		'/--avf-font-display:\s*"Cormorant Garamond",\s*Georgia,\s*"Times New Roman",\s*serif;/',
		'--avf-font-display:
        var(--e-global-typography-primary-font-family, "Cormorant Garamond"),
        Georgia,
        "Times New Roman",
        serif;',
		$custom_css,
		1
	);

	$updated_css = preg_replace(
		'/--avf-font-body:\s*"Inter",\s*Arial,\s*Helvetica,\s*sans-serif;/',
		'--avf-font-body:
        var(--e-global-typography-text-font-family, "Inter"),
        Arial,
        Helvetica,
        sans-serif;',
		$updated_css,
		1
	);

	if ( ! is_string( $updated_css ) || $updated_css === $custom_css ) {
		update_option(
			'avf_site_styles_kit_typography_sync',
			array(
				'version' => AVF_SITE_STYLES_VERSION,
				'kit_id'  => $kit_id,
			),
			false
		);

		return;
	}

	$settings['custom_css'] = $updated_css;
	update_post_meta( $kit_id, '_elementor_page_settings', $settings );

	if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}

	update_option(
		'avf_site_styles_kit_typography_sync',
		array(
			'version' => AVF_SITE_STYLES_VERSION,
			'kit_id'  => $kit_id,
		),
		false
	);
}
// Disabled for now: this mutation routine must not interfere with
// Elementor save/publish requests.
// add_action( 'init', 'avf_site_styles_normalize_active_kit_typography', 20 );

/**
 * Removes duplicated Hero layout CSS blocks that were stored directly on
 * homepage widgets in Elementor data. Those blocks redefine the same
 * `.avf-hero-copy` class from multiple sources (image, heading, text widget),
 * which makes the frontend depend on fragile CSS generation order instead of
 * one authoritative source.
 *
 * @param mixed $elements Elementor element tree.
 * @param bool  $changed  Whether the tree was modified.
 * @return mixed
 */
function avf_site_styles_strip_duplicate_hero_copy_css( $elements, &$changed ) {
	if ( ! is_array( $elements ) ) {
		return $elements;
	}

	foreach ( $elements as &$element ) {
		if ( ! is_array( $element ) ) {
			continue;
		}

		if ( isset( $element['settings']['custom_css'] ) && is_string( $element['settings']['custom_css'] ) ) {
			$original_css = $element['settings']['custom_css'];
			$updated_css  = preg_replace(
				'/\.avf-hero-copy\s*\{(?:[^{}]+|(?R))*\}\s*/s',
				'',
				$original_css
			);

			if ( is_string( $updated_css ) && $updated_css !== $original_css ) {
				$updated_css = trim( $updated_css );

				if ( '' === $updated_css ) {
					unset( $element['settings']['custom_css'] );
				} else {
					$element['settings']['custom_css'] = $updated_css;
				}

				$changed = true;
			}
		}

		if ( ! empty( $element['elements'] ) ) {
			$element['elements'] = avf_site_styles_strip_duplicate_hero_copy_css( $element['elements'], $changed );
		}
	}
	unset( $element );

	return $elements;
}

/**
 * Normalizes the homepage Elementor data so Hero text/layout classes are not
 * defined both as global/frontend CSS and again as widget-level `custom_css`.
 *
 * @return void
 */
function avf_site_styles_normalize_homepage_hero_css() {
	if ( avf_site_styles_should_skip_mutations() ) {
		return;
	}

	$front_page_id = (int) get_option( 'page_on_front' );

	if ( $front_page_id <= 0 ) {
		return;
	}

	$migration_state = get_option( 'avf_site_styles_home_hero_css_sync' );

	if (
		is_array( $migration_state ) &&
		isset( $migration_state['version'], $migration_state['page_id'] ) &&
		AVF_SITE_STYLES_VERSION === $migration_state['version'] &&
		$front_page_id === (int) $migration_state['page_id']
	) {
		return;
	}

	$data = get_post_meta( $front_page_id, '_elementor_data', true );

	if ( empty( $data ) ) {
		update_option(
			'avf_site_styles_home_hero_css_sync',
			array(
				'version' => AVF_SITE_STYLES_VERSION,
				'page_id' => $front_page_id,
			),
			false
		);

		return;
	}

	if ( is_string( $data ) ) {
		$data = json_decode( $data, true );
	}

	if ( ! is_array( $data ) ) {
		return;
	}

	$changed      = false;
	$updated_data = avf_site_styles_strip_duplicate_hero_copy_css( $data, $changed );

	if ( ! $changed ) {
		update_option(
			'avf_site_styles_home_hero_css_sync',
			array(
				'version' => AVF_SITE_STYLES_VERSION,
				'page_id' => $front_page_id,
			),
			false
		);

		return;
	}

	update_post_meta( $front_page_id, '_elementor_data', wp_slash( wp_json_encode( $updated_data ) ) );

	if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}

	update_option(
		'avf_site_styles_home_hero_css_sync',
		array(
			'version' => AVF_SITE_STYLES_VERSION,
			'page_id' => $front_page_id,
		),
		false
	);
}
// Disabled for now: this mutation routine must not interfere with
// Elementor save/publish requests.
// add_action( 'init', 'avf_site_styles_normalize_homepage_hero_css', 25 );

/**
 * Returns the public stylesheet version for cache-busting.
 *
 * @return string
 */
function avf_site_styles_get_stylesheet_version() {
	$css_path = AVF_SITE_STYLES_DIR . 'assets/css/site-fixes.css';

	return file_exists( $css_path ) ? (string) filemtime( $css_path ) : AVF_SITE_STYLES_VERSION;
}

/**
 * Enqueues the public stylesheet if it has not been added yet.
 *
 * This helper is also called from shortcodes so the styles load inside the
 * Elementor preview iframe even though the generic frontend enqueue hook stays
 * disabled for editor requests.
 *
 * @return void
 */
function avf_site_styles_enqueue_public_stylesheet() {
	if ( wp_style_is( 'avf-site-styles', 'enqueued' ) ) {
		return;
	}

	wp_enqueue_style(
		'avf-site-styles',
		AVF_SITE_STYLES_URL . 'assets/css/site-fixes.css',
		array(),
		avf_site_styles_get_stylesheet_version()
	);
}

/**
 * Enqueues the site-fixes stylesheet on the public frontend only, at a
 * late priority so it prints after Elementor's and the theme's own
 * stylesheets (allowing it to safely override them where needed).
 *
 * @return void
 */
function avf_site_styles_enqueue_assets() {
	if ( avf_site_styles_is_backend_request() || avf_site_styles_is_elementor_editor_request() ) {
		return;
	}

	avf_site_styles_enqueue_public_stylesheet();
}
add_action( 'wp_enqueue_scripts', 'avf_site_styles_enqueue_assets', 100 );

/**
 * Returns the default milestones for the "Geschichte" timeline.
 *
 * @return array<int, array<string, string>>
 */
function avf_site_styles_get_default_history_timeline_items() {
	return array(
		array(
			'label'    => '1938',
			'datetime' => '1938',
			'text'     => 'Der Wunsch nach einer Reformverbindung auf dem Platz Basel wächst.',
		),
		array(
			'label'    => '1939',
			'datetime' => '1939-02-24',
			'text'     => 'Am 24. Februar gründen 8 Studenten die AV Froburger.',
		),
		array(
			'label'    => '1940',
			'datetime' => '1940-07-20',
			'text'     => 'Am 20. Juli wird die Verbindung offiziell anerkannt.',
		),
		array(
			'label'    => '1993',
			'datetime' => '1993',
			'text'     => 'Die AV Froburger öffnet sich für Frauen und wird zur heutigen gemischten Verbindung.',
		),
	);
}

/**
 * Parses timeline items from shortcode content.
 *
 * Expected format: one item per line, separated by pipe characters.
 * `Jahr|Text` or `Jahr|Text|YYYY-MM-DD`
 *
 * @param string $content Raw shortcode content.
 * @return array<int, array<string, string>>
 */
function avf_site_styles_parse_history_timeline_items( $content ) {
	$content = trim( (string) $content );

	if ( '' === $content ) {
		return avf_site_styles_get_default_history_timeline_items();
	}

	$items = array();
	$lines = preg_split( '/\r\n|\r|\n/', $content );

	if ( ! is_array( $lines ) ) {
		return avf_site_styles_get_default_history_timeline_items();
	}

	foreach ( $lines as $line ) {
		$line = trim( (string) $line );

		if ( '' === $line ) {
			continue;
		}

		$parts = array_map( 'trim', explode( '|', $line, 3 ) );

		if ( count( $parts ) < 2 ) {
			continue;
		}

		$label    = sanitize_text_field( $parts[0] );
		$text     = wp_kses_post( $parts[1] );
		$datetime = isset( $parts[2] ) ? sanitize_text_field( $parts[2] ) : '';

		if ( '' === $label || '' === wp_strip_all_tags( $text ) ) {
			continue;
		}

		if ( '' === $datetime && preg_match( '/^\d{4}$/', $label ) ) {
			$datetime = $label;
		}

		$items[] = array(
			'label'    => $label,
			'datetime' => $datetime,
			'text'     => $text,
		);
	}

	return ! empty( $items ) ? $items : avf_site_styles_get_default_history_timeline_items();
}

/**
 * Sanitizes an optional extra CSS class list for the timeline wrapper.
 *
 * @param string $class_names Raw class list.
 * @return string
 */
function avf_site_styles_sanitize_class_names( $class_names ) {
	$classes = preg_split( '/\s+/', trim( (string) $class_names ) );

	if ( ! is_array( $classes ) ) {
		return '';
	}

	$classes = array_filter(
		array_map( 'sanitize_html_class', $classes )
	);

	return implode( ' ', $classes );
}

/**
 * Renders the semantic history timeline shortcode.
 *
 * Usage:
 * [avf_history_timeline title="Geschichte"]
 * 1938|Der Wunsch nach einer Reformverbindung auf dem Platz Basel wächst.
 * 1939|Am 24. Februar gründen 8 Studenten die AV Froburger.|1939-02-24
 * [/avf_history_timeline]
 *
 * @param array<string, string> $atts Shortcode attributes.
 * @param string|null           $content Shortcode content.
 * @return string
 */
function avf_site_styles_render_history_timeline_shortcode( $atts, $content = null ) {
	$atts = shortcode_atts(
		array(
			'title' => '',
			'class' => '',
		),
		$atts,
		'avf_history_timeline'
	);

	avf_site_styles_enqueue_public_stylesheet();

	$items        = avf_site_styles_parse_history_timeline_items( (string) $content );
	$title        = sanitize_text_field( $atts['title'] );
	$extra_class  = avf_site_styles_sanitize_class_names( $atts['class'] );
	$wrapper_class = 'avf-history-shortcode';

	if ( '' !== $extra_class ) {
		$wrapper_class .= ' ' . $extra_class;
	}

	ob_start();
	?>
	<section class="<?php echo esc_attr( $wrapper_class ); ?>">
		<?php if ( '' !== $title ) : ?>
			<h2 class="avf-history-shortcode__title"><?php echo esc_html( $title ); ?></h2>
		<?php endif; ?>
		<ol class="avf-history-shortcode__list">
			<?php foreach ( $items as $item ) : ?>
				<li class="avf-history-shortcode__item">
					<div class="avf-history-shortcode__year">
						<time
							class="avf-history-shortcode__year-text"
							<?php if ( '' !== $item['datetime'] ) : ?>
								datetime="<?php echo esc_attr( $item['datetime'] ); ?>"
							<?php endif; ?>
						>
							<?php echo esc_html( $item['label'] ); ?>
						</time>
					</div>
					<span class="avf-history-shortcode__marker" aria-hidden="true"></span>
					<div class="avf-history-shortcode__content">
						<?php echo wpautop( $item['text'] ); ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ol>
	</section>
	<?php

	return trim( (string) ob_get_clean() );
}
add_shortcode( 'avf_history_timeline', 'avf_site_styles_render_history_timeline_shortcode' );
