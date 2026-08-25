<?php
/**
 * Renders the generic public event detail page.
 *
 * @package AVF_Events_Integration
 */

defined( 'ABSPATH' ) || exit;

class AVF_Event_Detail_Shortcode {

	/**
	 * Singleton instance.
	 *
	 * @var AVF_Event_Detail_Shortcode|null
	 */
	private static $instance = null;

	/**
	 * Returns the singleton instance.
	 *
	 * @return AVF_Event_Detail_Shortcode
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers the shortcode.
	 */
	private function __construct() {
		add_shortcode( 'avf_event_detail', array( $this, 'render' ) );
	}

	/**
	 * Shortcode callback.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts = shortcode_atts( array( 'slug' => '' ), $atts, 'avf_event_detail' );
		$slug = $this->resolve_slug( $atts );

		if ( '' === $slug ) {
			AVF_Events_Assets::enqueue();
			return $this->render_message( __( 'Kein Anlass ausgewählt.', 'avf-events-integration' ), 'error' );
		}

		$client = new AVF_Events_API_Client();
		$event  = $client->get_event_detail( $slug );

		if ( is_wp_error( $event ) ) {
			$http_code = $this->get_http_code( $event );

			if ( 404 === $http_code ) {
				$this->mark_request_not_found();
			}

			AVF_Events_Assets::enqueue();

			return $this->render_message(
				404 === $http_code
					? __( 'Dieser Anlass wurde nicht gefunden.', 'avf-events-integration' )
					: __( 'Die Anlassinformationen sind momentan nicht verfügbar.', 'avf-events-integration' ),
				'error'
			);
		}

		$notice = AVF_Event_Signup_Handler::consume_notice();

		AVF_Events_Assets::enqueue();

		return $this->render_html( $event, $notice );
	}

	/**
	 * Resolves the current event slug.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	private function resolve_slug( array $atts ) {
		$query_var_slug = get_query_var( AVF_Event_Detail_Router::QUERY_VAR );
		$slug           = sanitize_title( is_scalar( $query_var_slug ) ? (string) $query_var_slug : '' );

		if ( '' !== $slug ) {
			return $slug;
		}

		$slug = sanitize_title( (string) $atts['slug'] );
		if ( '' !== $slug ) {
			return $slug;
		}

		if ( isset( $_GET['event'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- optional read-only fallback.
			return sanitize_title( wp_unslash( $_GET['event'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		return '';
	}

	/**
	 * Renders the detail page markup.
	 *
	 * @param array      $event  Normalized event detail payload.
	 * @param array|null $notice Optional flash notice.
	 * @return string
	 */
	private function render_html( array $event, $notice ) {
		$deadline_text       = $this->format_deadline( $event['signup_deadline'] );
		$deadline_relative   = $this->format_deadline_relative( $event['signup_deadline'] );
		$public_columns      = array_values( array_filter( $event['signup_columns'], array( $this, 'is_public_column' ) ) );
		$form_values         = $this->extract_notice_values( $notice );
		$ics_url             = ( new AVF_Events_API_Client() )->get_event_calendar_ics_url( $event['slug'] );
		$has_image           = '' !== $event['image_url'];
		$can_sign_up         = $event['signup_enabled'] && $event['signup_open'];
		$location_map_url    = $this->build_location_map_url( $event['location'] );
		$public_signup_count = count( $event['signups'] );
		$current_url         = $this->cleaned_current_url();
		$notice_is_success   = is_array( $notice ) && isset( $notice['type'] ) && 'success' === $notice['type'];

		ob_start();
		?>
		<article class="avf-event-detail <?php echo esc_attr( $has_image ? 'avf-event-detail--with-image' : 'avf-event-detail--without-image' ); ?> <?php echo esc_attr( $can_sign_up ? 'avf-event-detail--signup-open' : 'avf-event-detail--signup-closed' ); ?>" data-event-title="<?php echo esc_attr( $event['title'] ); ?>">
			<?php if ( is_array( $notice ) && ! empty( $notice['message'] ) ) : ?>
				<div class="avf-event-detail__notice avf-event-detail__notice--<?php echo esc_attr( $notice_is_success ? 'success' : 'error' ); ?>" <?php echo $notice_is_success ? 'role="status" aria-live="polite"' : 'role="alert" aria-live="polite"'; ?> tabindex="-1">
					<?php echo esc_html( $notice['message'] ); ?>
					<?php if ( ! $notice_is_success && ! empty( $notice['debug_status'] ) && current_user_can( 'manage_options' ) ) : ?>
						<div class="avf-event-detail__notice-debug">
							<?php echo esc_html( 'Status: ' . $notice['debug_status'] ); ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<nav class="avf-event-detail__breadcrumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'avf-events-integration' ); ?>">
				<ol class="avf-event-detail__breadcrumb-list">
					<li><a href="<?php echo esc_url( home_url( AVF_Events_View_Helpers::get_events_page_path() ) ); ?>"><?php esc_html_e( 'Anlässe', 'avf-events-integration' ); ?></a></li>
					<li aria-current="page"><?php echo esc_html( $event['title'] ); ?></li>
				</ol>
			</nav>

			<header class="avf-event-detail__header <?php echo esc_attr( $has_image ? 'avf-event-detail__header--with-image' : 'avf-event-detail__header--without-image' ); ?>">
				<div class="avf-event-detail__header-main">
					<p class="avf-event-detail__date"><?php echo esc_html( $this->format_date_range( $event ) ); ?></p>
					<h1 class="avf-event-detail__title"><?php echo esc_html( $event['title'] ); ?></h1>

					<?php if ( '' !== $event['short_description'] ) : ?>
						<p class="avf-event-detail__lead"><?php echo esc_html( $event['short_description'] ); ?></p>
					<?php endif; ?>

					<div class="avf-event-detail__meta">
						<div>
							<span class="avf-event-detail__meta-label"><?php esc_html_e( 'Zeit', 'avf-events-integration' ); ?></span>
							<p class="avf-event-detail__meta-value avf-event-detail__meta-value--time"><?php echo esc_html( $this->format_time_range( $event ) ); ?></p>
						</div>
						<div>
							<span class="avf-event-detail__meta-label"><?php esc_html_e( 'Ort', 'avf-events-integration' ); ?></span>
							<p class="avf-event-detail__meta-value">
								<?php if ( '' !== $location_map_url ) : ?>
									<a href="<?php echo esc_url( $location_map_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $event['location'] ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $event['location'] ); ?>
								<?php endif; ?>
							</p>
						</div>
						<div>
							<span class="avf-event-detail__meta-label"><?php esc_html_e( 'Anmeldeschluss', 'avf-events-integration' ); ?></span>
							<p class="avf-event-detail__meta-value"><?php echo esc_html( $deadline_text ); ?></p>
							<div class="avf-event-detail__meta-subline">
								<span class="avf-event-detail__status-chip avf-event-detail__status-chip--<?php echo esc_attr( $this->get_signup_status_modifier( $event ) ); ?>">
									<span class="avf-event-detail__status-dot" aria-hidden="true"></span>
									<?php echo esc_html( $this->get_signup_status_text( $event, $deadline_relative ) ); ?>
								</span>
							</div>
						</div>
					</div>

					<div class="avf-event-detail__actions">
						<?php if ( $event['signup_enabled'] ) : ?>
							<a class="avf-event-detail__jump" href="#anmelden"><?php esc_html_e( 'Jetzt anmelden', 'avf-events-integration' ); ?></a>
						<?php endif; ?>

						<?php if ( '' !== $ics_url ) : ?>
							<a class="avf-events-tile__ics" href="<?php echo esc_url( $ics_url ); ?>">
								<?php esc_html_e( 'Zum Kalender hinzufügen', 'avf-events-integration' ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>

				<?php if ( $has_image ) : ?>
					<div class="avf-event-detail__image-wrap">
						<img
							class="avf-event-detail__image"
							src="<?php echo esc_url( $event['image_url'] ); ?>"
							alt="<?php echo esc_attr( $event['title'] ); ?>"
							loading="eager"
							fetchpriority="high"
							decoding="async"
						/>
					</div>
				<?php endif; ?>
			</header>

			<?php if ( '' !== trim( $event['description'] ) ) : ?>
				<div class="avf-event-detail__body">
					<?php echo wp_kses_post( wpautop( $event['description'] ) ); ?>
				</div>
			<?php endif; ?>

			<section class="avf-event-detail__signup" id="anmelden" aria-labelledby="avf-public-signup-section-heading">
				<div class="avf-event-detail__signup-intro">
					<h2 id="avf-public-signup-section-heading"><?php esc_html_e( 'Anmeldung', 'avf-events-integration' ); ?></h2>
					<p><?php echo esc_html( $this->get_signup_intro_text( $event ) ); ?></p>
				</div>

				<div class="avf-event-detail__signup-grid">
					<section class="avf-event-detail__table-block" aria-labelledby="avf-public-signups-heading">
						<h3 id="avf-public-signups-heading">
							<?php
							echo esc_html(
								$public_signup_count > 0
									? sprintf( __( 'Öffentliche Anmeldungen (%d)', 'avf-events-integration' ), $public_signup_count )
									: __( 'Öffentliche Anmeldungen', 'avf-events-integration' )
							);
							?>
						</h3>
						<?php echo $this->render_public_signups_table( $event['signups'], $public_columns ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</section>

					<section class="avf-event-detail__form-block" aria-labelledby="avf-public-signup-form-heading">
						<h3 id="avf-public-signup-form-heading"><?php esc_html_e( 'Anmelden', 'avf-events-integration' ); ?></h3>
						<?php
						if ( $event['signup_enabled'] && $event['signup_open'] ) {
							echo $this->render_signup_form( $event, $form_values ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						} else {
							echo wp_kses_post( '<p class="avf-event-detail__form-closed">' . esc_html__( 'Für diesen Anlass ist derzeit keine öffentliche Anmeldung möglich.', 'avf-events-integration' ) . '</p>' );
						}
						?>
					</section>
				</div>
			</section>

			<?php if ( $can_sign_up ) : ?>
				<a class="avf-event-detail__sticky-cta" href="#anmelden"><?php esc_html_e( 'Jetzt anmelden', 'avf-events-integration' ); ?></a>
			<?php endif; ?>

			<?php echo $this->render_structured_data( $event, $current_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</article>
		<?php
		return ob_get_clean();
	}

	/**
	 * Renders the public signups table.
	 *
	 * @param array $signups        Public signup rows.
	 * @param array $public_columns Public column definitions.
	 * @return string
	 */
	private function render_public_signups_table( array $signups, array $public_columns ) {
		if ( empty( $signups ) ) {
			ob_start();
			?>
			<div class="avf-event-detail__empty-state">
				<div class="avf-event-detail__empty-icon" aria-hidden="true">○</div>
				<p class="avf-event-detail__empty-title"><?php esc_html_e( 'Noch niemand angemeldet.', 'avf-events-integration' ); ?></p>
				<p class="avf-event-detail__empty-copy"><?php esc_html_e( 'Sei der Erste.', 'avf-events-integration' ); ?></p>
			</div>
			<?php
			return ob_get_clean();
		}

		ob_start();
		?>
		<div class="avf-event-detail__table-wrap">
			<table class="avf-event-detail__table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Vulgo', 'avf-events-integration' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Anwesend', 'avf-events-integration' ); ?></th>
						<?php foreach ( $public_columns as $column ) : ?>
							<th scope="col"><?php echo esc_html( $column['label'] ); ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $signups as $signup ) : ?>
						<tr>
							<td><?php echo esc_html( $signup['vulgo'] ); ?></td>
							<td><?php echo esc_html( $signup['attending'] ? __( 'Ja', 'avf-events-integration' ) : __( 'Nein', 'avf-events-integration' ) ); ?></td>
							<?php foreach ( $public_columns as $column ) : ?>
								<td><?php echo esc_html( $this->format_public_value( $signup['values'], $column ) ); ?></td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Renders the signup form.
	 *
	 * @param array $event       Normalized event detail payload.
	 * @param array $form_values Previously submitted values.
	 * @return string
	 */
	private function render_signup_form( array $event, array $form_values ) {
		ob_start();
		?>
		<form class="avf-event-detail__form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" data-avf-event-form>
			<input type="hidden" name="action" value="<?php echo esc_attr( AVF_Event_Signup_Handler::ACTION ); ?>" />
			<input type="hidden" name="event_slug" value="<?php echo esc_attr( $event['slug'] ); ?>" />
			<input type="hidden" name="_avf_event_redirect" value="<?php echo esc_url( $this->current_url() ); ?>" />
			<?php wp_nonce_field( AVF_Event_Signup_Handler::NONCE_ACTION, '_avf_event_signup_nonce' ); ?>

			<p class="avf-event-detail__form-note" id="avf-event-form-note"><span aria-hidden="true">*</span> <?php esc_html_e( 'Pflichtfeld', 'avf-events-integration' ); ?></p>

			<div class="avf-event-detail__field avf-event-detail__field--honeypot" aria-hidden="true">
				<label for="avf-event-website"><?php esc_html_e( 'Website', 'avf-events-integration' ); ?></label>
				<input id="avf-event-website" type="text" name="website" value="" tabindex="-1" autocomplete="off" />
			</div>

			<div class="avf-event-detail__field">
				<label for="avf-event-vulgo"><?php echo wp_kses_post( $this->format_field_label( __( 'Vulgo', 'avf-events-integration' ), true ) ); ?></label>
				<input id="avf-event-vulgo" type="text" name="vulgo" maxlength="80" required autocomplete="nickname" data-avf-required-message="<?php esc_attr_e( 'Bitte gib ein Vulgo an.', 'avf-events-integration' ); ?>" aria-describedby="avf-event-vulgo-error" value="<?php echo esc_attr( isset( $form_values['vulgo'] ) ? $form_values['vulgo'] : '' ); ?>" />
				<p class="avf-event-detail__field-error" id="avf-event-vulgo-error" role="alert" hidden></p>
			</div>

			<div class="avf-event-detail__field">
				<label for="avf-event-attending"><?php echo wp_kses_post( $this->format_field_label( __( 'Anwesend', 'avf-events-integration' ), true ) ); ?></label>
				<select id="avf-event-attending" name="attending" required data-avf-required-message="<?php esc_attr_e( 'Bitte gib an, ob du anwesend bist.', 'avf-events-integration' ); ?>" aria-describedby="avf-event-attending-error">
					<option value=""><?php esc_html_e( 'Bitte wählen', 'avf-events-integration' ); ?></option>
					<option value="1" <?php selected( isset( $form_values['attending'] ) ? (string) $form_values['attending'] : '', '1' ); ?>><?php esc_html_e( 'Ja', 'avf-events-integration' ); ?></option>
					<option value="0" <?php selected( isset( $form_values['attending'] ) ? (string) $form_values['attending'] : '', '0' ); ?>><?php esc_html_e( 'Nein', 'avf-events-integration' ); ?></option>
				</select>
				<p class="avf-event-detail__field-error" id="avf-event-attending-error" role="alert" hidden></p>
			</div>

			<?php foreach ( $event['signup_columns'] as $column ) : ?>
				<?php echo $this->render_dynamic_field( $column, $form_values ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endforeach; ?>

			<button class="avf-event-detail__submit" type="submit"><?php esc_html_e( 'Anmeldung senden', 'avf-events-integration' ); ?></button>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Renders one API-driven form field.
	 *
	 * @param array $column      Normalized column definition.
	 * @param array $form_values Previously submitted values.
	 * @return string
	 */
	private function render_dynamic_field( array $column, array $form_values ) {
		$field_id        = 'avf-event-field-' . $column['key'];
		$hint_id         = 'avf-event-field-hint-' . $column['key'];
		$error_id        = 'avf-event-field-error-' . $column['key'];
		$current_value   = isset( $form_values['values'][ $column['key'] ] ) ? $form_values['values'][ $column['key'] ] : ( 'checkbox' === $column['type'] ? false : '' );
		$is_private      = ! $column['public'];
		$describedby_ids = array( $error_id );

		if ( $is_private ) {
			$describedby_ids[] = $hint_id;
		}

		$describedby = implode( ' ', $describedby_ids );

		ob_start();
		?>
		<div class="avf-event-detail__field avf-event-detail__field--<?php echo esc_attr( $column['type'] ); ?>">
			<?php if ( 'checkbox' === $column['type'] ) : ?>
				<label class="avf-event-detail__checkbox">
					<input
						id="<?php echo esc_attr( $field_id ); ?>"
						type="checkbox"
						name="values[<?php echo esc_attr( $column['key'] ); ?>]"
						value="1"
						<?php checked( ! empty( $current_value ) ); ?>
						<?php echo $column['required'] ? 'required' : ''; ?>
						aria-describedby="<?php echo esc_attr( $describedby ); ?>"
						<?php if ( $column['required'] ) : ?>
							data-avf-required-message="<?php echo esc_attr( sprintf( __( '"%s" ist ein Pflichtfeld.', 'avf-events-integration' ), $column['label'] ) ); ?>"
						<?php endif; ?>
					/>
					<span><?php echo wp_kses_post( $this->format_field_label( $column['label'], $column['required'] ) ); ?></span>
				</label>
			<?php else : ?>
				<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo wp_kses_post( $this->format_field_label( $column['label'], $column['required'] ) ); ?></label>
				<?php if ( 'textarea' === $column['type'] ) : ?>
					<textarea
						id="<?php echo esc_attr( $field_id ); ?>"
						name="values[<?php echo esc_attr( $column['key'] ); ?>]"
						rows="4"
						<?php echo $column['required'] ? 'required' : ''; ?>
						aria-describedby="<?php echo esc_attr( $describedby ); ?>"
						placeholder="<?php echo esc_attr( $column['placeholder'] ); ?>"
						<?php if ( $column['required'] ) : ?>
							data-avf-required-message="<?php echo esc_attr( sprintf( __( '"%s" ist ein Pflichtfeld.', 'avf-events-integration' ), $column['label'] ) ); ?>"
						<?php endif; ?>
					><?php echo esc_textarea( (string) $current_value ); ?></textarea>
				<?php elseif ( 'select' === $column['type'] ) : ?>
					<select
						id="<?php echo esc_attr( $field_id ); ?>"
						name="values[<?php echo esc_attr( $column['key'] ); ?>]"
						<?php echo $column['required'] ? 'required' : ''; ?>
						aria-describedby="<?php echo esc_attr( $describedby ); ?>"
						<?php if ( $column['required'] ) : ?>
							data-avf-required-message="<?php echo esc_attr( sprintf( __( 'Bitte wähle "%s" aus.', 'avf-events-integration' ), $column['label'] ) ); ?>"
						<?php endif; ?>
					>
						<option value=""><?php esc_html_e( 'Bitte wählen', 'avf-events-integration' ); ?></option>
						<?php foreach ( $column['options'] as $option ) : ?>
							<option value="<?php echo esc_attr( $option ); ?>" <?php selected( (string) $current_value, $option ); ?>><?php echo esc_html( $option ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php else : ?>
					<input
						id="<?php echo esc_attr( $field_id ); ?>"
						type="text"
						name="values[<?php echo esc_attr( $column['key'] ); ?>]"
						value="<?php echo esc_attr( (string) $current_value ); ?>"
						placeholder="<?php echo esc_attr( $column['placeholder'] ); ?>"
						<?php echo $column['required'] ? 'required' : ''; ?>
						aria-describedby="<?php echo esc_attr( $describedby ); ?>"
						<?php if ( $column['required'] ) : ?>
							data-avf-required-message="<?php echo esc_attr( sprintf( __( '"%s" ist ein Pflichtfeld.', 'avf-events-integration' ), $column['label'] ) ); ?>"
						<?php endif; ?>
					/>
				<?php endif; ?>
			<?php endif; ?>

			<p class="avf-event-detail__field-error" id="<?php echo esc_attr( $error_id ); ?>" role="alert" hidden></p>

			<?php if ( $is_private ) : ?>
				<p id="<?php echo esc_attr( $hint_id ); ?>" class="avf-event-detail__field-hint">
					<?php esc_html_e( 'Diese Angabe ist nur für die Organisatoren sichtbar.', 'avf-events-integration' ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Renders one calm message state.
	 *
	 * @param string $message Message text.
	 * @param string $type    Message type.
	 * @return string
	 */
	private function render_message( $message, $type ) {
		ob_start();
		?>
		<p class="avf-events-empty avf-events-empty--<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $message ); ?></p>
		<?php
		return ob_get_clean();
	}

	/**
	 * Returns the formatted date range for one event.
	 *
	 * @param array $event Normalized event detail payload.
	 * @return string
	 */
	private function format_date_range( array $event ) {
		try {
			$start = new DateTimeImmutable( $event['start_at'] );
			$end   = new DateTimeImmutable( $event['end_at'] );
		} catch ( Exception $e ) {
			return $event['date'];
		}

		$tz         = wp_timezone();
		$start_date = wp_date( 'd.m.Y', $start->getTimestamp(), $tz );
		$end_date   = wp_date( 'd.m.Y', $end->getTimestamp(), $tz );

		if ( $start_date && $end_date && $start_date !== $end_date ) {
			return $start_date . ' - ' . $end_date;
		}

		return $start_date ? $start_date : $event['date'];
	}

	/**
	 * Returns the formatted time range for one event.
	 *
	 * @param array $event Normalized event detail payload.
	 * @return string
	 */
	private function format_time_range( array $event ) {
		if ( '' !== $event['end_time'] ) {
			return $event['start_time'] . ' - ' . $event['end_time'] . ' Uhr';
		}

		return $event['start_time'] . ' Uhr';
	}

	/**
	 * Formats the signup deadline.
	 *
	 * @param string $deadline ISO datetime string.
	 * @return string
	 */
	private function format_deadline( $deadline ) {
		if ( '' === $deadline ) {
			return __( 'Nicht definiert', 'avf-events-integration' );
		}

		try {
			$date = new DateTimeImmutable( $deadline );
		} catch ( Exception $e ) {
			return $deadline;
		}

		return wp_date( 'd.m.Y H:i', $date->getTimestamp(), wp_timezone() ) . ' Uhr';
	}

	/**
	 * Returns one human-friendly relative deadline string.
	 *
	 * @param string $deadline ISO datetime string.
	 * @return string
	 */
	private function format_deadline_relative( $deadline ) {
		if ( '' === $deadline ) {
			return '';
		}

		try {
			$date = new DateTimeImmutable( $deadline );
			$now  = new DateTimeImmutable( 'now', wp_timezone() );
		} catch ( Exception $e ) {
			return '';
		}

		$date = $date->setTimezone( wp_timezone() );

		if ( $date < $now ) {
			return __( 'abgelaufen', 'avf-events-integration' );
		}

		$interval = $now->diff( $date );

		if ( $interval->days >= 2 ) {
			return sprintf( __( 'noch %d Tage', 'avf-events-integration' ), (int) $interval->days );
		}

		if ( 1 === (int) $interval->days ) {
			return __( 'noch 1 Tag', 'avf-events-integration' );
		}

		if ( $interval->h >= 2 ) {
			return sprintf( __( 'noch %d Stunden', 'avf-events-integration' ), (int) $interval->h );
		}

		if ( 1 === (int) $interval->h ) {
			return __( 'noch 1 Stunde', 'avf-events-integration' );
		}

		return __( 'heute', 'avf-events-integration' );
	}

	/**
	 * Returns one public table value.
	 *
	 * @param array $values Signup values.
	 * @param array $column Public column definition.
	 * @return string
	 */
	private function format_public_value( array $values, array $column ) {
		if ( ! array_key_exists( $column['key'], $values ) ) {
			return '-';
		}

		$value = $values[ $column['key'] ];

		if ( is_bool( $value ) ) {
			return $value ? __( 'Ja', 'avf-events-integration' ) : __( 'Nein', 'avf-events-integration' );
		}

		$value = trim( (string) $value );

		return '' !== $value ? $value : '-';
	}

	/**
	 * Returns true when a column is public.
	 *
	 * @param array $column Column definition.
	 * @return bool
	 */
	private function is_public_column( array $column ) {
		return ! empty( $column['public'] );
	}

	/**
	 * Returns preserved form values from a flash notice.
	 *
	 * @param array|null $notice Flash notice.
	 * @return array
	 */
	private function extract_notice_values( $notice ) {
		if ( is_array( $notice ) && isset( $notice['values'] ) && is_array( $notice['values'] ) ) {
			return $notice['values'];
		}

		return array(
			'vulgo'     => '',
			'attending' => '',
			'values'    => array(),
		);
	}

	/**
	 * Returns the current URL without one flash token.
	 *
	 * @return string
	 */
	private function cleaned_current_url() {
		return remove_query_arg( AVF_Event_Signup_Handler::NOTICE_QUERY_ARG, $this->current_url() );
	}

	/**
	 * Returns one field label with an optional required marker.
	 *
	 * @param string $label Field label.
	 * @param bool   $required Whether the field is required.
	 * @return string
	 */
	private function format_field_label( $label, $required ) {
		$label = esc_html( (string) $label );

		if ( ! $required ) {
			return $label;
		}

		return $label . ' <span class="avf-event-detail__required" aria-hidden="true">*</span><span class="screen-reader-text"> ' . esc_html__( 'Pflichtfeld', 'avf-events-integration' ) . '</span>';
	}

	/**
	 * Returns one maps search URL for the location.
	 *
	 * @param string $location Event location text.
	 * @return string
	 */
	private function build_location_map_url( $location ) {
		$location = trim( (string) $location );

		if ( '' === $location ) {
			return '';
		}

		return 'https://www.openstreetmap.org/search?query=' . rawurlencode( $location );
	}

	/**
	 * Returns one signup status modifier.
	 *
	 * @param array $event Normalized event detail payload.
	 * @return string
	 */
	private function get_signup_status_modifier( array $event ) {
		if ( ! $event['signup_enabled'] ) {
			return 'disabled';
		}

		return $event['signup_open'] ? 'open' : 'closed';
	}

	/**
	 * Returns one signup status text.
	 *
	 * @param array  $event Event payload.
	 * @param string $deadline_relative Relative deadline copy.
	 * @return string
	 */
	private function get_signup_status_text( array $event, $deadline_relative ) {
		if ( ! $event['signup_enabled'] ) {
			return __( 'Keine öffentliche Anmeldung', 'avf-events-integration' );
		}

		if ( $event['signup_open'] ) {
			if ( '' !== $deadline_relative ) {
				return sprintf( __( 'Anmeldung offen - %s', 'avf-events-integration' ), $deadline_relative );
			}

			return __( 'Anmeldung offen', 'avf-events-integration' );
		}

		return __( 'Anmeldung geschlossen', 'avf-events-integration' );
	}

	/**
	 * Returns one short intro line for the signup section.
	 *
	 * @param array $event Event payload.
	 * @return string
	 */
	private function get_signup_intro_text( array $event ) {
		if ( ! $event['signup_enabled'] ) {
			return __( 'Für diesen Anlass ist derzeit keine öffentliche Anmeldung verfügbar.', 'avf-events-integration' );
		}

		if ( $event['signup_open'] ) {
			return __( 'Trag dich ein und prüfe direkt, wer bereits zugesagt hat.', 'avf-events-integration' );
		}

		return __( 'Die öffentliche Anmeldung ist für diesen Anlass bereits geschlossen.', 'avf-events-integration' );
	}

	/**
	 * Returns structured data for breadcrumb and event detail.
	 *
	 * @param array  $event Event payload.
	 * @param string $current_url Canonical page URL.
	 * @return string
	 */
	private function render_structured_data( array $event, $current_url ) {
		$schema = array(
			array(
				'@context'        => 'https://schema.org',
				'@type'           => 'BreadcrumbList',
				'itemListElement' => array(
					array(
						'@type'    => 'ListItem',
						'position' => 1,
						'name'     => __( 'Anlässe', 'avf-events-integration' ),
						'item'     => home_url( AVF_Events_View_Helpers::get_events_page_path() ),
					),
					array(
						'@type'    => 'ListItem',
						'position' => 2,
						'name'     => $event['title'],
						'item'     => $current_url,
					),
				),
			),
			array(
				'@context'             => 'https://schema.org',
				'@type'                => 'Event',
				'name'                 => $event['title'],
				'description'          => $event['short_description'],
				'startDate'            => $event['start_at'],
				'endDate'              => $event['end_at'],
				'eventStatus'          => 'https://schema.org/EventScheduled',
				'eventAttendanceMode'  => 'https://schema.org/OfflineEventAttendanceMode',
				'location'             => array(
					'@type' => 'Place',
					'name'  => $event['location'],
				),
				'organizer'            => array(
					'@type' => 'Organization',
					'name'  => 'AV Froburger',
					'url'   => home_url( '/' ),
				),
				'url'                  => $current_url,
				'image'                => '' !== $event['image_url'] ? array( $event['image_url'] ) : array(),
			),
		);

		return '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>';
	}

	/**
	 * Marks the current request as a not-found response.
	 *
	 * @return void
	 */
	private function mark_request_not_found() {
		global $wp_query;

		if ( $wp_query ) {
			$wp_query->set_404();
		}

		status_header( 404 );
		nocache_headers();
	}

	/**
	 * Extracts the HTTP code from a WP_Error.
	 *
	 * @param WP_Error $error Error object.
	 * @return int
	 */
	private function get_http_code( WP_Error $error ) {
		$data = $error->get_error_data();

		return ( is_array( $data ) && isset( $data['http_code'] ) ) ? (int) $data['http_code'] : 0;
	}

	/**
	 * Returns the current absolute URL.
	 *
	 * @return string
	 */
	private function current_url() {
		$scheme = is_ssl() ? 'https://' : 'http://';
		$host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

		return $scheme . $host . $uri;
	}
}
