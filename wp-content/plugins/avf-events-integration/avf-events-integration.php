<?php
/**
 * Plugin Name:       AV Froburger Events Integration
 * Description:       Bindet öffentliche Anlässe aus dem Django-CMS in WordPress und Elementor ein.
 * Version:            2.1.2
 * Requires at least:  6.0
 * Requires PHP:       7.4
 * Author:             AV Froburger
 * Text Domain:        avf-events-integration
 * Domain Path:        /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'AVF_EVENTS_INTEGRATION_VERSION', '2.1.2' );
define( 'AVF_EVENTS_INTEGRATION_DIR', plugin_dir_path( __FILE__ ) );
define( 'AVF_EVENTS_INTEGRATION_URL', plugin_dir_url( __FILE__ ) );

require_once AVF_EVENTS_INTEGRATION_DIR . 'includes/class-avf-events-api-client.php';
require_once AVF_EVENTS_INTEGRATION_DIR . 'includes/class-avf-events-settings.php';
require_once AVF_EVENTS_INTEGRATION_DIR . 'includes/class-avf-upcoming-events-shortcode.php';
require_once AVF_EVENTS_INTEGRATION_DIR . 'includes/class-avf-events-view-helpers.php';
require_once AVF_EVENTS_INTEGRATION_DIR . 'includes/class-avf-events-assets.php';
require_once AVF_EVENTS_INTEGRATION_DIR . 'includes/class-avf-events-ajax.php';
require_once AVF_EVENTS_INTEGRATION_DIR . 'includes/class-avf-events-list-shortcode.php';
require_once AVF_EVENTS_INTEGRATION_DIR . 'includes/class-avf-events-calendar-actions-shortcode.php';
require_once AVF_EVENTS_INTEGRATION_DIR . 'includes/class-avf-event-detail-shortcode.php';

/**
 * Sets default plugin options on activation without overwriting existing values.
 */
function avf_events_integration_activate() {
	if ( false === get_option( 'avf_events_api_endpoint' ) ) {
		add_option( 'avf_events_api_endpoint', '' );
	}

	if ( false === get_option( 'avf_events_api_base' ) ) {
		add_option( 'avf_events_api_base', '' );
	}

	if ( false === get_option( 'avf_events_cache_ttl' ) ) {
		add_option( 'avf_events_cache_ttl', 300 );
	}

	if ( false === get_option( 'avf_events_page_path' ) ) {
		add_option( 'avf_events_page_path', '/anlaesse/' );
	}
}
register_activation_hook( __FILE__, 'avf_events_integration_activate' );

/**
 * Bootstraps the plugin components once all plugins are loaded.
 */
function avf_events_integration_init() {
	AVF_Events_Settings::instance();
	AVF_Upcoming_Events_Shortcode::instance();
	AVF_Events_Ajax::instance();
	AVF_Events_List_Shortcode::instance();
	AVF_Events_Calendar_Actions_Shortcode::instance();
	AVF_Event_Detail_Shortcode::instance();
}
add_action( 'plugins_loaded', 'avf_events_integration_init' );
