<?php
/**
 * Plugin Name:       AV Froburger Site Styles
 * Description:       Enthält gezielte projektspezifische Layoutkorrekturen.
 * Version:            1.1.1
 * Requires at least:  6.3
 * Requires PHP:       7.4
 * Author:             AV Froburger
 * Text Domain:        avf-site-styles
 * Domain Path:        /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'AVF_SITE_STYLES_VERSION', '1.1.1' );
define( 'AVF_SITE_STYLES_DIR', plugin_dir_path( __FILE__ ) );
define( 'AVF_SITE_STYLES_URL', plugin_dir_url( __FILE__ ) );

/**
 * Enqueues the site-fixes stylesheet on the public frontend only, at a
 * late priority so it prints after Elementor's and the theme's own
 * stylesheets (allowing it to safely override them where needed).
 *
 * @return void
 */
function avf_site_styles_enqueue_assets() {
	if ( is_admin() ) {
		return;
	}

	$css_path = AVF_SITE_STYLES_DIR . 'assets/css/site-fixes.css';
	// Always cache-bust on the file's own modification time (see the same
	// fix in avf-homepage-layout) rather than gating on WP_DEBUG, which is
	// off on this install and previously left browsers serving a stale
	// cached stylesheet after edits.
	$version = file_exists( $css_path ) ? (string) filemtime( $css_path ) : AVF_SITE_STYLES_VERSION;

	wp_enqueue_style(
		'avf-site-styles',
		AVF_SITE_STYLES_URL . 'assets/css/site-fixes.css',
		array(),
		$version
	);
}
add_action( 'wp_enqueue_scripts', 'avf_site_styles_enqueue_assets', 100 );
