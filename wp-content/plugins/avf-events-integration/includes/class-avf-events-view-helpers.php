<?php
/**
 * Shared, stateless view-building helpers for the v1-powered shortcodes
 * (upcoming/past lists, event detail). Deliberately separate from
 * AVF_Upcoming_Events_Shortcode's own build_event_view()/build_detail_url() -
 * that class and its output are left untouched for the legacy homepage
 * shortcode; this class serves only the new components, and both draw on
 * the same normalized event shape produced by AVF_Events_API_Client.
 *
 * @package AVF_Events_Integration
 */

defined( 'ABSPATH' ) || exit;

class AVF_Events_View_Helpers {

	/**
	 * Builds a presentation-ready view from a normalized event array.
	 * Returns null if start_at cannot be parsed - the same graceful-skip
	 * behaviour as the legacy shortcode uses for unparsable dates, so a
	 * single malformed event never breaks the whole list.
	 *
	 * @param array $event Normalized event (from AVF_Events_API_Client).
	 * @return array|null
	 */
	public static function build_view( array $event ) {
		try {
			$start = new DateTimeImmutable( $event['start_at'] );
		} catch ( Exception $e ) {
			return null;
		}

		$tz       = wp_timezone();
		$start_ts = $start->getTimestamp();

		$day        = wp_date( 'j', $start_ts, $tz );
		$month_year = wp_date( 'F Y', $start_ts, $tz );
		$date_part  = wp_date( 'd.m.Y', $start_ts, $tz );
		$time_part  = wp_date( 'H:i', $start_ts, $tz );

		if ( false === $day || false === $month_year || false === $date_part || false === $time_part ) {
			return null;
		}

		// A multi-day badge is derived from start/end dates rather than a
		// dedicated API field - Django's v1 events currently expose no
		// category/tag field at all (verified live against all four v1
		// routes; see readme.txt "API-Vertrag"). This is the one honest,
		// data-driven "Kennzeichnung" available without inventing data.
		$is_multi_day  = false;
		$end_date_part = '';

		try {
			$end = new DateTimeImmutable( $event['end_at'] );

			$start_ymd = wp_date( 'Y-m-d', $start_ts, $tz );
			$end_ymd   = wp_date( 'Y-m-d', $end->getTimestamp(), $tz );

			if ( false !== $start_ymd && false !== $end_ymd && $start_ymd !== $end_ymd ) {
				$is_multi_day  = true;
				$end_date_part = wp_date( 'd.m.Y', $end->getTimestamp(), $tz );
			}
		} catch ( Exception $e ) {
			// end_at is required upstream (normalize_event()), but an
			// unparsable value here only disables the multi-day badge, it
			// must not hide the event.
		}

		return array(
			'id'                => $event['id'],
			'title'             => $event['title'],
			'slug'              => $event['slug'],
			'short_description' => $event['short_description'],
			'location_name'     => $event['location_name'],
			'detail_path'       => $event['detail_path'],
			'source_url'        => $event['source_url'],
			'day'               => $day,
			'month_year'        => $month_year,
			'date_part'         => $date_part,
			'time_part'         => $time_part,
			'end_date_part'     => $end_date_part,
			'is_multi_day'      => $is_multi_day,
			'datetime_attr'     => $event['start_at'],
		);
	}

	/**
	 * Converts a list of normalized events into views, silently dropping any
	 * with an unparsable date.
	 *
	 * @param array $events Normalized events.
	 * @return array Views.
	 */
	public static function build_views( array $events ) {
		$views = array();

		foreach ( $events as $event ) {
			$view = self::build_view( $event );
			if ( null !== $view ) {
				$views[] = $view;
			}
		}

		return $views;
	}

	/**
	 * Builds the detail link for an event: prefers Django's own absolute
	 * source_url (the fuller, canonical event page) when present, otherwise
	 * falls back to the WordPress-internal anchor convention already used by
	 * the legacy shortcode (avf_events_page_path + "#event-{slug}"). Reuses
	 * the existing "avf_event_detail_url" filter either way, so developer
	 * overrides keep working across both old and new components.
	 *
	 * @param array $view Event view (from build_view()).
	 * @return string
	 */
	public static function build_detail_url( array $view ) {
		if ( '' !== $view['source_url'] ) {
			$validated = esc_url_raw( $view['source_url'] );
			if ( '' !== $validated ) {
				return apply_filters( 'avf_event_detail_url', $validated, $view );
			}
		}

		$page_path = get_option( 'avf_events_page_path', '/anlaesse/' );
		if ( ! is_string( $page_path ) || '' === $page_path ) {
			$page_path = '/anlaesse/';
		}

		$url = home_url( $page_path . '#event-' . $view['slug'] );

		return apply_filters( 'avf_event_detail_url', $url, $view );
	}

	/**
	 * Validates and returns initial/step shortcode attributes, resetting
	 * invalid values to sane defaults. Shared by every paginated shortcode
	 * so the validation rules (and their limits) live in exactly one place.
	 *
	 * @param mixed $value   Raw attribute value.
	 * @param int   $default Default when invalid.
	 * @param int   $min     Minimum allowed value.
	 * @param int   $max     Maximum allowed value.
	 * @return int
	 */
	public static function sanitize_count( $value, $default, $min, $max ) {
		if ( ! is_numeric( $value ) ) {
			return $default;
		}

		$value = (int) $value;

		if ( $value < $min || $value > $max ) {
			return $default;
		}

		return $value;
	}
}
