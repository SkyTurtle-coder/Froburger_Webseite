<?php
/**
 * Plugin Name:       AV Froburger Featured Post
 * Description:       Zeigt den favorisierten öffentlichen WordPress-Beitrag auf der Startseite an.
 * Version:            1.0.0
 * Requires at least:  6.0
 * Requires PHP:       7.4
 * Author:             AV Froburger
 * Text Domain:        avf-featured-post
 * Domain Path:        /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'AVF_FEATURED_POST_VERSION', '1.0.0' );
define( 'AVF_FEATURED_POST_DIR', plugin_dir_path( __FILE__ ) );
define( 'AVF_FEATURED_POST_URL', plugin_dir_url( __FILE__ ) );

require_once AVF_FEATURED_POST_DIR . 'includes/class-avf-featured-post-shortcode.php';

/**
 * Bootstraps the plugin once all plugins are loaded.
 */
function avf_featured_post_init() {
	AVF_Featured_Post_Shortcode::instance();
}
add_action( 'plugins_loaded', 'avf_featured_post_init' );
