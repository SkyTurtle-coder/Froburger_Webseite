<?php
/**
 * Centralized, once-only asset enqueue for the v1-powered components
 * (upcoming/past lists, calendar actions, event detail). The legacy
 * [avf_upcoming_events] shortcode keeps its own separate enqueue_assets()
 * method and stylesheet untouched - this class exists specifically so the
 * new components don't each duplicate their own wp_enqueue_style()/script()
 * calls.
 *
 * @package AVF_Events_Integration
 */

defined( 'ABSPATH' ) || exit;

class AVF_Events_Assets {

	/**
	 * Guards against enqueuing more than once when multiple new shortcodes
	 * render on the same page.
	 *
	 * @var bool
	 */
	private static $enqueued = false;

	/**
	 * Enqueues the shared stylesheet and script for the new v1 components.
	 * Always cache-busts on the files' own modification time, matching the
	 * convention already used by the other AVF plugins in this project.
	 *
	 * @return void
	 */
	public static function enqueue() {
		if ( self::$enqueued ) {
			return;
		}
		self::$enqueued = true;

		$css_path    = AVF_EVENTS_INTEGRATION_DIR . 'assets/css/events-lists.css';
		$css_version = file_exists( $css_path ) ? (string) filemtime( $css_path ) : AVF_EVENTS_INTEGRATION_VERSION;

		wp_enqueue_style(
			'avf-events-lists',
			AVF_EVENTS_INTEGRATION_URL . 'assets/css/events-lists.css',
			array(),
			$css_version
		);

		$js_path    = AVF_EVENTS_INTEGRATION_DIR . 'assets/js/events-lists.js';
		$js_version = file_exists( $js_path ) ? (string) filemtime( $js_path ) : AVF_EVENTS_INTEGRATION_VERSION;

		wp_enqueue_script(
			'avf-events-lists',
			AVF_EVENTS_INTEGRATION_URL . 'assets/js/events-lists.js',
			array(),
			$js_version,
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);

		wp_localize_script(
			'avf-events-lists',
			'avfEventsAjax',
			array(
				'url'    => admin_url( 'admin-ajax.php' ),
				'action' => AVF_Events_Ajax::ACTION,
			)
		);
	}
}
