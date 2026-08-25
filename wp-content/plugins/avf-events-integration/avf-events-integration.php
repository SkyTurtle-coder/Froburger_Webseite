<?php
/**
 * Plugin Name:       AV Froburger Events Integration
 * Description:       Bindet öffentliche Anlässe aus dem Django-CMS in WordPress und Elementor ein.
 * Version:            2.3.0
 * Requires at least:  6.0
 * Requires PHP:       7.4
 * Author:             AV Froburger
 * Text Domain:        avf-events-integration
 * Domain Path:        /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'AVF_EVENTS_INTEGRATION_VERSION', '2.3.0' );
define( 'AVF_EVENTS_INTEGRATION_DIR', plugin_dir_path( __FILE__ ) );
define( 'AVF_EVENTS_INTEGRATION_URL', plugin_dir_url( __FILE__ ) );
define( 'AVF_EVENTS_INTEGRATION_DB_VERSION_OPTION', 'avf_events_integration_version' );

require_once AVF_EVENTS_INTEGRATION_DIR . 'includes/class-avf-internal-endpoint-resolver.php';
require_once AVF_EVENTS_INTEGRATION_DIR . 'includes/class-avf-internal-portal-router.php';
require_once AVF_EVENTS_INTEGRATION_DIR . 'includes/class-avf-events-api-client.php';
require_once AVF_EVENTS_INTEGRATION_DIR . 'includes/class-avf-events-settings.php';
require_once AVF_EVENTS_INTEGRATION_DIR . 'includes/class-avf-upcoming-events-shortcode.php';
require_once AVF_EVENTS_INTEGRATION_DIR . 'includes/class-avf-events-view-helpers.php';
require_once AVF_EVENTS_INTEGRATION_DIR . 'includes/class-avf-events-assets.php';
require_once AVF_EVENTS_INTEGRATION_DIR . 'includes/class-avf-events-ajax.php';
require_once AVF_EVENTS_INTEGRATION_DIR . 'includes/class-avf-events-list-shortcode.php';
require_once AVF_EVENTS_INTEGRATION_DIR . 'includes/class-avf-events-calendar-actions-shortcode.php';
require_once AVF_EVENTS_INTEGRATION_DIR . 'includes/class-avf-event-detail-router.php';
require_once AVF_EVENTS_INTEGRATION_DIR . 'includes/class-avf-event-detail-shortcode.php';
require_once AVF_EVENTS_INTEGRATION_DIR . 'includes/class-avf-event-signup-handler.php';

/**
 * Sets default plugin options on activation without overwriting existing values.
 */
function avf_events_integration_activate() {
	if ( false === get_option( 'avf_events_cache_ttl' ) ) {
		add_option( 'avf_events_cache_ttl', 300 );
	}

	if ( false === get_option( 'avf_events_page_path' ) ) {
		add_option( 'avf_events_page_path', '/anlaesse/' );
	}

	if ( false === get_option( 'avf_event_detail_page_path' ) ) {
		add_option( 'avf_event_detail_page_path', '/anlassdetail/' );
	}

	AVF_Internal_Endpoint_Resolver::ensure_default_options();
	AVF_Event_Detail_Router::register_rewrite();
	AVF_Internal_Portal_Router::register_rewrite();
	flush_rewrite_rules();
	update_option( AVF_EVENTS_INTEGRATION_DB_VERSION_OPTION, AVF_EVENTS_INTEGRATION_VERSION, false );
}
register_activation_hook( __FILE__, 'avf_events_integration_activate' );

/**
 * Flushes rewrite rules only when the plugin version changes.
 *
 * @return void
 */
function avf_events_integration_maybe_upgrade() {
	$installed_version = get_option( AVF_EVENTS_INTEGRATION_DB_VERSION_OPTION, '' );

	if ( AVF_EVENTS_INTEGRATION_VERSION === $installed_version ) {
		return;
	}

	AVF_Internal_Endpoint_Resolver::ensure_default_options();
	AVF_Event_Detail_Router::register_rewrite();
	AVF_Internal_Portal_Router::register_rewrite();
	flush_rewrite_rules();
	update_option( AVF_EVENTS_INTEGRATION_DB_VERSION_OPTION, AVF_EVENTS_INTEGRATION_VERSION, false );
}
add_action( 'init', 'avf_events_integration_maybe_upgrade', 20 );

/**
 * Bootstraps the plugin components once all plugins are loaded.
 */
function avf_events_integration_init() {
	AVF_Events_Settings::instance();
	AVF_Internal_Portal_Router::instance();
	AVF_Upcoming_Events_Shortcode::instance();
	AVF_Events_Ajax::instance();
	AVF_Events_List_Shortcode::instance();
	AVF_Events_Calendar_Actions_Shortcode::instance();
	AVF_Event_Detail_Router::instance();
	AVF_Event_Detail_Shortcode::instance();
	AVF_Event_Signup_Handler::instance();
}
add_action( 'plugins_loaded', 'avf_events_integration_init' );

/**
 * Runs one central server-side request against the internal Django API.
 *
 * @param string $method HTTP method.
 * @param string $path   Relative Django path.
 * @param array  $args   Request arguments.
 * @return array|WP_Error
 */
function avf_internal_api_request( $method, $path, array $args = array() ) {
	return AVF_Internal_Endpoint_Resolver::instance()->request( $method, $path, $args );
}

/**
 * Returns the stable local WordPress route for the internal area.
 *
 * @param string $path Optional internal Django path.
 * @return string
 */
function avf_internal_portal_url( $path = '/' ) {
	return AVF_Internal_Portal_Router::get_portal_url( $path );
}
