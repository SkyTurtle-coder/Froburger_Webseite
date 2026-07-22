<?php
/**
 * Standalone maintenance entry point, equivalent to `wp avf homepage rebuild`.
 *
 * Intended to be run through WP-CLI's `wp eval-file`, e.g.:
 *
 *   wp eval-file wp-content/plugins/avf-homepage-layout/tools/rebuild-homepage.php
 *   wp eval-file wp-content/plugins/avf-homepage-layout/tools/rebuild-homepage.php dry-run
 *
 * This file intentionally does NOT bootstrap WordPress itself (loading
 * wp-load.php from an arbitrary plugin subfolder is fragile across
 * installs); `wp eval-file` already provides a fully booted WordPress
 * environment. Using the documented `wp avf homepage rebuild` command
 * directly is the primary, preferred way to run this — this script
 * exists as the plain-PHP fallback entry point requested alongside it.
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AVF_Homepage_Builder' ) ) {
	echo "AVF_Homepage_Builder is not available. Make sure the avf-homepage-layout plugin is active.\n";
	return;
}

$dry_run = isset( $args[0] ) && 'dry-run' === $args[0]; // phpcs:ignore

$builder = new AVF_Homepage_Builder();

$verified = $builder->verify_frontpage();
if ( is_wp_error( $verified ) ) {
	echo 'ABORT: ' . $verified->get_error_message() . "\n";
	return;
}

if ( $dry_run ) {
	$plan = $builder->build_plan();
	if ( is_wp_error( $plan ) ) {
		echo 'ABORT: ' . $plan->get_error_message() . "\n";
		return;
	}
	echo wp_json_encode( $plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";
	echo "Dry run complete. Nothing was changed.\n";
	return;
}

$result = $builder->rebuild();
if ( is_wp_error( $result ) ) {
	echo 'FAILED: ' . $result->get_error_message() . "\n";
	return;
}

echo wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";
echo "Homepage rebuilt successfully.\n";
