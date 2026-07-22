<?php
/**
 * WP-CLI commands for the AV Froburger homepage rebuild.
 *
 * @package AVF_Homepage_Layout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manage the AV Froburger homepage Elementor structure.
 */
class AVF_Homepage_CLI_Command {

	/**
	 * Analyzes the current homepage Elementor structure without changing anything.
	 *
	 * ## EXAMPLES
	 *
	 *     wp avf homepage analyze
	 *
	 * @subcommand analyze
	 */
	public function analyze( $args, $assoc_args ) {
		$builder = new AVF_Homepage_Builder();

		$verified = $builder->verify_frontpage();
		if ( is_wp_error( $verified ) ) {
			WP_CLI::error( $verified->get_error_message() );
		}

		$data = $builder->analyze();
		if ( is_wp_error( $data ) ) {
			WP_CLI::error( $data->get_error_message() );
		}

		WP_CLI::log( wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	}

	/**
	 * Rebuilds the homepage's Elementor structure below the hero.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Analyze and show the planned changes without modifying anything.
	 *
	 * ## EXAMPLES
	 *
	 *     wp avf homepage rebuild --dry-run
	 *     wp avf homepage rebuild
	 *
	 * @subcommand rebuild
	 */
	public function rebuild( $args, $assoc_args ) {
		$builder = new AVF_Homepage_Builder();

		$verified = $builder->verify_frontpage();
		if ( is_wp_error( $verified ) ) {
			WP_CLI::error( $verified->get_error_message() );
		}

		if ( isset( $assoc_args['dry-run'] ) ) {
			$plan = $builder->build_plan();
			if ( is_wp_error( $plan ) ) {
				WP_CLI::error( $plan->get_error_message() );
			}

			WP_CLI::log( '=== DRY RUN: geplante Änderungen (es wurde nichts verändert) ===' );
			WP_CLI::log( 'Frontpage-ID geprüft: OK (Post ' . $builder->get_post_id() . ')' );
			WP_CLI::log( 'Top-Level-Elemente aktuell: ' . $plan['top_level_count_now'] );
			WP_CLI::log( 'Hero gefunden: ' . ( $plan['hero_found'] ? 'ja (ID ' . $plan['hero_id'] . ')' : 'NEIN' ) );
			WP_CLI::log( 'Wird beibehalten: ' . implode( ', ', $plan['elements_to_keep'] ) );
			WP_CLI::log( 'Wird entfernt und ersetzt: ' . implode( ', ', $plan['elements_to_remove'] ) );
			WP_CLI::log( 'Neue Top-Level-Anzahl nach Umbau: ' . $plan['new_top_level_count'] . ' (Hero + Hauptinhalt)' );
			WP_CLI::log( 'Global Classes, die sichergestellt werden: ' . implode( ', ', $plan['global_classes_to_ensure'] ) );
			WP_CLI::log( 'Umbenennung: ' . avf_homepage_cli_rename_summary( $plan ) );
			WP_CLI::success( 'Dry-Run abgeschlossen. Keine Änderung an der Seite vorgenommen.' );
			return;
		}

		WP_CLI::log( 'Starte Neuaufbau der Startseite (Post ' . $builder->get_post_id() . ')...' );

		$result = $builder->rebuild();
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		WP_CLI::log( 'Backup-Zeitstempel: ' . $result['backup']['timestamp'] );
		WP_CLI::log( 'Hero-ID (unverändert übernommen): ' . $result['hero_id'] );
		WP_CLI::log( 'Neue Hauptinhalt-ID: ' . $result['main_id'] );
		WP_CLI::log( 'Top-Level-Elemente nach Umbau: ' . $result['top_level_count'] );
		WP_CLI::success( 'Startseite erfolgreich neu aufgebaut.' );
	}

	/**
	 * Restores the homepage from a previously written backup.
	 *
	 * ## OPTIONS
	 *
	 * --backup=<backup>
	 * : Backup timestamp (e.g. 2026-07-21_191838) or a full path to a
	 * *-elementor-data-before-*.json file.
	 *
	 * ## EXAMPLES
	 *
	 *     wp avf homepage restore --backup=2026-07-21_191838
	 *
	 * @subcommand restore
	 */
	public function restore( $args, $assoc_args ) {
		if ( empty( $assoc_args['backup'] ) ) {
			WP_CLI::error( '--backup=<timestamp-or-file> ist erforderlich.' );
		}

		$builder = new AVF_Homepage_Builder();
		$result  = $builder->restore( $assoc_args['backup'] );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		WP_CLI::success( 'Startseite wiederhergestellt aus: ' . $result['restored_from'] );
	}
}

/**
 * Small local helper kept outside the class to avoid polluting the
 * WP-CLI command's public method list (which becomes the subcommand list).
 *
 * @param array $plan
 * @return string
 */
function avf_homepage_cli_rename_summary( array $plan ) {
	$out = array();
	foreach ( $plan['global_class_rename'] as $gid => $info ) {
		$out[] = $gid . ': "' . $info['from'] . '" -> "' . $info['to'] . '"';
	}
	return implode( ', ', $out );
}
