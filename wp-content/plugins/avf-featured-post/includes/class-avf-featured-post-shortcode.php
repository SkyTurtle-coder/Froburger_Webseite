<?php
/**
 * Registers and renders the [avf_featured_post] shortcode.
 *
 * @package AVF_Featured_Post
 */

defined( 'ABSPATH' ) || exit;

class AVF_Featured_Post_Shortcode {

	const EXCERPT_WORDS = 38;

	/**
	 * Singleton instance.
	 *
	 * @var AVF_Featured_Post_Shortcode|null
	 */
	private static $instance = null;

	/**
	 * Returns the singleton instance, creating it on first call.
	 *
	 * @return AVF_Featured_Post_Shortcode
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
		add_shortcode( 'avf_featured_post', array( $this, 'render' ) );
	}

	/**
	 * Shortcode callback for [avf_featured_post].
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'eyebrow'   => __( 'Rückblick', 'avf-featured-post' ),
				'link_text' => __( 'Bericht lesen', 'avf-featured-post' ),
				'fallback'  => 'latest',
			),
			$atts,
			'avf_featured_post'
		);

		$eyebrow   = $this->sanitize_text_attr( $atts['eyebrow'], __( 'Rückblick', 'avf-featured-post' ) );
		$link_text = $this->sanitize_text_attr( $atts['link_text'], __( 'Bericht lesen', 'avf-featured-post' ) );
		$fallback  = $this->sanitize_fallback( $atts['fallback'] );

		$query = $this->get_sticky_query();

		if ( null === $query && 'latest' === $fallback ) {
			$query = $this->get_latest_query();
		}

		if ( null === $query || ! $query->have_posts() ) {
			return '';
		}

		$query->the_post();

		$html = $this->render_html( $eyebrow, $link_text );

		wp_reset_postdata();

		if ( '' === $html ) {
			return '';
		}

		$this->enqueue_assets();

		return $html;
	}

	/**
	 * Sanitizes a free-text shortcode attribute, falling back to a default when empty.
	 *
	 * @param mixed  $value   Raw attribute value.
	 * @param string $default Default value used when sanitized value is empty.
	 * @return string
	 */
	private function sanitize_text_attr( $value, $default ) {
		$value = is_string( $value ) ? sanitize_text_field( $value ) : '';

		return '' === $value ? $default : $value;
	}

	/**
	 * Sanitizes the fallback attribute, resetting invalid values to "latest".
	 *
	 * @param mixed $value Raw attribute value.
	 * @return string
	 */
	private function sanitize_fallback( $value ) {
		$value = is_string( $value ) ? sanitize_key( $value ) : '';

		return in_array( $value, array( 'latest', 'none' ), true ) ? $value : 'latest';
	}

	/**
	 * Reads and normalizes the sticky post IDs from the WordPress option.
	 *
	 * @return int[]
	 */
	private function get_sticky_ids() {
		$ids = get_option( 'sticky_posts', array() );

		if ( ! is_array( $ids ) ) {
			return array();
		}

		$ids = array_map( 'absint', $ids );
		$ids = array_filter( $ids );
		$ids = array_unique( $ids );

		return array_values( $ids );
	}

	/**
	 * Runs a query limited to published sticky posts, newest first.
	 *
	 * @return WP_Query|null Query with a matching post, or null if none found.
	 */
	private function get_sticky_query() {
		$sticky_ids = $this->get_sticky_ids();

		if ( empty( $sticky_ids ) ) {
			return null;
		}

		$query = new WP_Query(
			array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => 1,
				'post__in'            => $sticky_ids,
				'orderby'             => 'date',
				'order'               => 'DESC',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);

		if ( ! $query->have_posts() ) {
			return null;
		}

		return $query;
	}

	/**
	 * Runs a query for the latest published post, used as a fallback.
	 *
	 * @return WP_Query
	 */
	private function get_latest_query() {
		return new WP_Query(
			array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => 1,
				'orderby'             => 'date',
				'order'               => 'DESC',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);
	}

	/**
	 * Builds the excerpt text: manual excerpt first, otherwise trimmed content.
	 *
	 * @return string
	 */
	private function build_excerpt() {
		if ( has_excerpt() ) {
			$excerpt = get_the_excerpt();
		} else {
			$excerpt = strip_shortcodes( get_the_content() );
		}

		$excerpt = wp_strip_all_tags( $excerpt );

		return wp_trim_words( $excerpt, self::EXCERPT_WORDS );
	}

	/**
	 * Resolves the image alt text: attachment alt meta, falling back to the post title.
	 *
	 * @param int    $thumbnail_id Attachment ID.
	 * @param string $post_title   Post title used as fallback.
	 * @return string
	 */
	private function get_image_alt( $thumbnail_id, $post_title ) {
		$alt = get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true );
		$alt = is_string( $alt ) ? trim( $alt ) : '';

		return '' === $alt ? $post_title : $alt;
	}

	/**
	 * Resolves the image caption: attachment caption, falling back to the post title.
	 *
	 * @param int    $thumbnail_id Attachment ID.
	 * @param string $post_title   Post title used as fallback.
	 * @return string
	 */
	private function get_image_caption( $thumbnail_id, $post_title ) {
		$caption = wp_get_attachment_caption( $thumbnail_id );
		$caption = is_string( $caption ) ? trim( $caption ) : '';

		return '' === $caption ? $post_title : $caption;
	}

	/**
	 * Enqueues the shortcode stylesheet, only when the shortcode actually renders.
	 *
	 * @return void
	 */
	private function enqueue_assets() {
		$css_path = AVF_FEATURED_POST_DIR . 'assets/css/featured-post.css';
		$version  = AVF_FEATURED_POST_VERSION;

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && file_exists( $css_path ) ) {
			$version = (string) filemtime( $css_path );
		}

		wp_enqueue_style(
			'avf-featured-post',
			AVF_FEATURED_POST_URL . 'assets/css/featured-post.css',
			array(),
			$version
		);
	}

	/**
	 * Renders the featured post markup. Expects the post loop to be set up
	 * (i.e. called right after WP_Query::the_post()).
	 *
	 * @param string $eyebrow   Sanitized eyebrow text.
	 * @param string $link_text Sanitized link text.
	 * @return string
	 */
	private function render_html( $eyebrow, $link_text ) {
		$post_id = absint( get_the_ID() );

		if ( 0 === $post_id ) {
			return '';
		}

		$title       = get_the_title( $post_id );
		$permalink   = get_permalink( $post_id );
		$excerpt     = $this->build_excerpt();
		$date        = get_the_date( '', $post_id );
		$categories  = get_the_category( $post_id );
		$category    = ( ! empty( $categories ) && is_array( $categories ) ) ? $categories[0]->name : '';
		$has_image   = has_post_thumbnail( $post_id );
		$section_id  = 'avf-featured-post-title-' . $post_id;

		$image_html    = '';
		$image_caption = '';

		if ( $has_image ) {
			$thumbnail_id = get_post_thumbnail_id( $post_id );
			$alt          = $this->get_image_alt( $thumbnail_id, $title );
			$image_caption = $this->get_image_caption( $thumbnail_id, $title );

			$image_html = get_the_post_thumbnail(
				$post_id,
				'large',
				array(
					'alt'      => $alt,
					'loading'  => 'lazy',
					'decoding' => 'async',
				)
			);
		}

		$section_class = 'avf-featured-post';
		if ( ! $has_image ) {
			$section_class .= ' avf-featured-post--without-image';
		}

		ob_start();
		?>
		<section class="<?php echo esc_attr( $section_class ); ?>" aria-labelledby="<?php echo esc_attr( $section_id ); ?>">
			<div class="avf-featured-post__inner">

				<?php if ( $has_image ) : ?>
					<figure class="avf-featured-post__media">
						<a
							class="avf-featured-post__image-link"
							href="<?php echo esc_url( $permalink ); ?>"
							aria-label="<?php echo esc_attr( sprintf( /* translators: %s: post title */ __( '%s lesen', 'avf-featured-post' ), $title ) ); ?>"
						>
							<?php echo wp_kses_post( $image_html ); ?>
						</a>

						<?php if ( '' !== $image_caption ) : ?>
							<figcaption class="avf-featured-post__caption">
								<?php echo esc_html( $image_caption ); ?>
							</figcaption>
						<?php endif; ?>
					</figure>
				<?php endif; ?>

				<div class="avf-featured-post__content">
					<?php if ( '' !== $eyebrow ) : ?>
						<p class="avf-featured-post__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
					<?php endif; ?>

					<h2 class="avf-featured-post__title" id="<?php echo esc_attr( $section_id ); ?>">
						<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
					</h2>

					<?php if ( '' !== $excerpt ) : ?>
						<p class="avf-featured-post__excerpt"><?php echo esc_html( $excerpt ); ?></p>
					<?php endif; ?>

					<p class="avf-featured-post__meta">
						<?php echo esc_html( $date ); ?>
						<?php if ( '' !== $category ) : ?>
							&nbsp;·&nbsp;<?php echo esc_html( $category ); ?>
						<?php endif; ?>
					</p>

					<a class="avf-featured-post__link" href="<?php echo esc_url( $permalink ); ?>">
						<?php echo esc_html( $link_text ); ?>
						<span aria-hidden="true">→</span>
					</a>
				</div>

			</div>
		</section>
		<?php
		return ob_get_clean();
	}
}
