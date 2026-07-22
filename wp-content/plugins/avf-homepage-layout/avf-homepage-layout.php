<?php
/**
 * Plugin Name:       AV Froburger Homepage Layout
 * Description:       Reproduzierbarer, versionierter Neuaufbau der Startseiten-Elementor-Struktur inklusive Backup/Restore.
 * Version:            1.0.0
 * Requires at least:  6.3
 * Requires PHP:       7.4
 * Author:             AV Froburger
 * Text Domain:        avf-homepage-layout
 * Domain Path:        /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'AVF_HOMEPAGE_LAYOUT_VERSION', '1.0.0' );
define( 'AVF_HOMEPAGE_LAYOUT_DIR', plugin_dir_path( __FILE__ ) );
define( 'AVF_HOMEPAGE_LAYOUT_URL', plugin_dir_url( __FILE__ ) );

require_once AVF_HOMEPAGE_LAYOUT_DIR . 'includes/class-avf-homepage-builder.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once AVF_HOMEPAGE_LAYOUT_DIR . 'includes/class-avf-homepage-cli-command.php';
	WP_CLI::add_command( 'avf homepage', 'AVF_Homepage_CLI_Command' );
}

/**
 * Enqueues the homepage stylesheet, only on the front page, after
 * Elementor's and the theme's own stylesheets.
 *
 * @return void
 */
function avf_homepage_layout_enqueue_assets() {
	if ( is_admin() || ! is_front_page() ) {
		return;
	}

	$css_path = AVF_HOMEPAGE_LAYOUT_DIR . 'assets/css/homepage.css';

	// Always cache-bust on the file's own modification time rather than the
	// static plugin version. This homepage layout is edited far more often
	// than a typical plugin release; relying on WP_DEBUG (off by default on
	// this install) previously caused browsers to keep serving a stale
	// cached stylesheet after edits, with no visible error anywhere.
	$version = file_exists( $css_path ) ? (string) filemtime( $css_path ) : AVF_HOMEPAGE_LAYOUT_VERSION;

	wp_enqueue_style(
		'avf-homepage-layout',
		AVF_HOMEPAGE_LAYOUT_URL . 'assets/css/homepage.css',
		array(),
		$version
	);
}
// Priority 100: enqueued after Elementor/theme (default priority 10) and after avf-site-styles (priority 100 too,
// but registered later in the request since this plugin loads after avf-site-styles alphabetically/by activation
// order is not guaranteed — explicit higher priority avoids relying on that).
add_action( 'wp_enqueue_scripts', 'avf_homepage_layout_enqueue_assets', 200 );

/**
 * No automatic rebuild ever runs on activation. The homepage is only
 * ever modified by explicitly running `wp avf homepage rebuild`.
 *
 * @return void
 */
function avf_homepage_layout_activate() {
	// Intentionally does nothing beyond making the plugin available.
}
register_activation_hook( __FILE__, 'avf_homepage_layout_activate' );
