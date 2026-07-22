<?php
/**
 * Plugin Name:       AV Froburger Zirkel Decor
 * Description:       Erzeugt wiederverwendbare dekorative Zirkel-Hintergründe für Elementor-Sektionen.
 * Version:            1.6.0
 * Requires at least:  6.3
 * Requires PHP:       7.4
 * Author:             AV Froburger
 * Text Domain:        avf-zirkel-decor
 * Domain Path:        /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'AVF_ZIRKEL_DECOR_VERSION', '1.6.0' );
define( 'AVF_ZIRKEL_DECOR_DIR', plugin_dir_path( __FILE__ ) );
define( 'AVF_ZIRKEL_DECOR_URL', plugin_dir_url( __FILE__ ) );

/**
 * Determines whether the decor assets should be loaded on the current request.
 *
 * Frontend only: not in wp-admin, not on the login screen, not in a REST
 * response and not in a feed.
 *
 * @return bool
 */
function avf_zirkel_decor_should_load() {
	if ( is_admin() ) {
		return false;
	}

	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}

	if ( function_exists( 'is_feed' ) && is_feed() ) {
		return false;
	}

	if ( isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'] ) {
		return false;
	}

	return true;
}

/**
 * Enqueues the decor stylesheet and script on the public frontend only.
 *
 * @return void
 */
function avf_zirkel_decor_enqueue_assets() {
	if ( ! avf_zirkel_decor_should_load() ) {
		return;
	}

	$css_path = AVF_ZIRKEL_DECOR_DIR . 'assets/css/zirkel-decor.css';
	$js_path  = AVF_ZIRKEL_DECOR_DIR . 'assets/js/zirkel-decor.js';

	// Always cache-bust on each file's own modification time rather than
	// gating on WP_DEBUG, which is off on this install and would otherwise
	// leave browsers serving a stale cached stylesheet/script after edits.
	$css_version = file_exists( $css_path ) ? (string) filemtime( $css_path ) : AVF_ZIRKEL_DECOR_VERSION;
	$js_version  = file_exists( $js_path ) ? (string) filemtime( $js_path ) : AVF_ZIRKEL_DECOR_VERSION;

	wp_enqueue_style(
		'avf-zirkel-decor',
		AVF_ZIRKEL_DECOR_URL . 'assets/css/zirkel-decor.css',
		array(),
		$css_version
	);

	wp_enqueue_script(
		'avf-zirkel-decor',
		AVF_ZIRKEL_DECOR_URL . 'assets/js/zirkel-decor.js',
		array(),
		$js_version,
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);
}
add_action( 'wp_enqueue_scripts', 'avf_zirkel_decor_enqueue_assets' );
