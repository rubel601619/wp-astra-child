<?php

namespace Theme\Children\Shortcodes;

defined( 'ABSPATH' ) || exit;

use Theme\Children\PostTypes\Services_Post_Type;
use WP_Query;

/**
 * `[services]` shortcode.
 *
 * Renders the published Services custom post type in a responsive card grid
 * with WordPress native pagination.
 *
 * Supported attributes:
 *
 *     [services posts_per_page="9" order="DESC" orderby="menu_order"]
 *
 * - posts_per_page: Number of services per page (default 9, max 100).
 * - order:          ASC or DESC (default DESC).
 * - orderby:        menu_order (default), date, title, ID, modified or rand.
 *
 * @since 1.0.0
 */
final class Services_Shortcode {

	/**
	 * Shortcode tag.
	 */
	const SHORTCODE = 'services';

	/**
	 * Services post type slug.
	 */
	const POST_TYPE = 'services';

	/**
	 * Stylesheet handle.
	 */
	const CSS_HANDLE = 'astra-child-services-shortcode';

	/**
	 * Stylesheet version.
	 */
	const CSS_VERSION = '1.0.0';

	/**
	 * Default shortcode attributes.
	 *
	 * @return array
	 */
	private static function get_defaults() {
		return array(
			'posts_per_page' => 9,
			'order'          => 'DESC',
			'orderby'        => 'menu_order',
		);
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts( self::get_defaults(), $atts, self::SHORTCODE );

		wp_enqueue_style( self::CSS_HANDLE );

		$posts_per_page = self::sanitize_posts_per_page( $atts['posts_per_page'] );
		$order          = self::sanitize_order( $atts['order'] );
		$orderby        = self::sanitize_orderby( $atts['orderby'] );

		$query = new WP_Query( self::build_query_args( $posts_per_page, $order, $orderby ) );

		ob_start();
		self::render_grid( $query );
		self::render_pagination( $query );
		$output = ob_get_clean();

		wp_reset_postdata();

		return (string) $output;
	}

	/**
	 * Build the WP_Query arguments.
	 *
	 * Menu order sorts first, with date (newest first) as the tie-breaker.
	 *
	 * @param int    $posts_per_page Number of services per page.
	 * @param string $order          ASC or DESC.
	 * @param string $orderby        Sort column.
	 * @return array
	 */
	private static function build_query_args( $posts_per_page, $order, $orderby ) {
		$args = array(
			'post_type'           => self::POST_TYPE,
			'post_status'         => 'publish',
			'posts_per_page'      => $posts_per_page,
			'paged'               => self::get_paged(),
			'ignore_sticky_posts' => true,
		);

		if ( 'menu_order' === $orderby ) {
			$args['orderby'] = array(
				'menu_order' => $order,
				'date'       => 'DESC',
			);
		} else {
			$args['orderby'] = $orderby;
			$args['order']   = $order;
		}

		return $args;
	}

	/**
	 * Render the services card grid.
	 *
	 * @param WP_Query $query The services query.
	 * @return void
	 */
	private static function render_grid( WP_Query $query ) {
		if ( ! $query->have_posts() ) {
			printf(
				'<p class="services-shortcode__empty">%s</p>',
				esc_html__( 'No services found.', 'astra-child' )
			);
			return;
		}
		?>
		<div class="services-shortcode">
			<div class="services-grid">
				<?php
				while ( $query->have_posts() ) {
					$query->the_post();
					self::render_card();
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a single service card.
	 *
	 * @return void
	 */
	private static function render_card() {
		$permalink = get_permalink();
		$title     = get_the_title();
		$excerpt   = self::get_card_excerpt();
		$button    = self::get_card_button( $permalink );
		?>
		<article class="service-card">
			<a class="service-card__media" href="<?php echo esc_url( $permalink ); ?>" tabindex="-1" aria-hidden="true">
				<?php
				if ( has_post_thumbnail() ) {
					the_post_thumbnail( 'medium_large', array( 'loading' => 'lazy' ) );
				} else {
					printf(
						'<span class="service-card__placeholder" aria-hidden="true">%s</span>',
						esc_html( mb_substr( $title, 0, 1 ) )
					);
				}
				?>
			</a>

			<div class="service-card__body">
				<h3 class="service-card__title">
					<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
				</h3>

				<?php if ( '' !== $excerpt ) : ?>
					<p class="service-card__excerpt"><?php echo esc_html( $excerpt ); ?></p>
				<?php endif; ?>

				<a class="service-card__link" href="<?php echo esc_url( $button['href'] ); ?>"<?php echo $button['target']; ?>>
					<?php echo esc_html( $button['label'] ); ?>
					<?php if ( $button['sr_only'] ) : ?>
						<span class="services-shortcode__sr-only"><?php printf( esc_html__( 'about %s', 'astra-child' ), esc_html( $title ) ); ?></span>
					<?php endif; ?>
				</a>
				<a class="service-card__link" href="<?php echo esc_url( $button['href'] ); ?>"<?php echo $button['target']; ?>>
					<?php echo esc_html( $button['label'] ); ?>
					<?php if ( $button['sr_only'] ) : ?>
						<span class="services-shortcode__sr-only"><?php printf( esc_html__( 'about %s', 'astra-child' ), esc_html( $title ) ); ?></span>
					<?php endif; ?>
				</a>
			</div>
		</article>
		<?php
	}

	/**
	 * Get the card button details.
	 *
	 * Uses the "Button information" meta box when a URL is set, otherwise
	 * falls back to the service permalink with a "Read More" label.
	 *
	 * @param string $permalink Service permalink.
	 * @return array{ href: string, label: string, target: string, sr_only: bool }
	 */
	private static function get_card_button( $permalink ) {
		$url    = get_post_meta( get_the_ID(), Services_Post_Type::META_URL, true );
		$text   = get_post_meta( get_the_ID(), Services_Post_Type::META_TEXT, true );
		$newtab = get_post_meta( get_the_ID(), Services_Post_Type::META_NEW_TAB, true );

		if ( '' !== $url ) {
			return array(
				'href'    => $url,
				'label'   => '' !== $text ? $text : __( 'Read More', 'astra-child' ),
				'target'  => '1' === $newtab ? ' target="_blank" rel="noopener noreferrer"' : '',
				'sr_only' => false,
			);
		}

		return array(
			'href'    => $permalink,
			'label'   => __( 'Read More', 'astra-child' ),
			'target'  => '',
			'sr_only' => true,
		);
	}

	/**
	 * Render the pagination below the grid.
	 *
	 * @param WP_Query $query The services query.
	 * @return void
	 */
	private static function render_pagination( WP_Query $query ) {
		if ( $query->max_num_pages < 2 ) {
			return;
		}

		$links = paginate_links(
			array(
				'total'     => (int) $query->max_num_pages,
				'current'   => self::get_paged(),
				'prev_text' => __( 'Previous', 'astra-child' ),
				'next_text' => __( 'Next', 'astra-child' ),
				'type'      => 'list',
				'end_size'  => 1,
				'mid_size'  => 1,
			)
		);

		if ( ! $links ) {
			return;
		}

		printf(
			'<nav class="services-pagination" aria-label="%1$s">%2$s</nav>',
			esc_attr__( 'Services pagination', 'astra-child' ),
			wp_kses_post( $links )
		);
	}

	/**
	 * Get the current page number.
	 *
	 * Uses the `paged` query var (works on archives and static pages). Falls
	 * back to the `page` query var on the front page.
	 *
	 * @return int
	 */
	private static function get_paged() {
		$paged = get_query_var( 'paged' ) ? (int) get_query_var( 'paged' ) : 1;

		if ( is_front_page() ) {
			$page = get_query_var( 'page' ) ? (int) get_query_var( 'page' ) : 1;
			$paged = max( $paged, $page );
		}

		return max( 1, $paged );
	}

	/**
	 * Get a short excerpt (20-30 words) for the card.
	 *
	 * @return string
	 */
	private static function get_card_excerpt() {
		$excerpt = trim( get_the_excerpt() );

		if ( '' === $excerpt ) {
			return '';
		}

		return wp_trim_words( $excerpt, 25, ' &hellip;' );
	}

	/**
	 * Sanitize the posts_per_page attribute.
	 *
	 * @param mixed $value Attribute value.
	 * @return int
	 */
	private static function sanitize_posts_per_page( $value ) {
		return max( 1, min( 100, absint( $value ) ) );
	}

	/**
	 * Sanitize the order attribute.
	 *
	 * @param mixed $value Attribute value.
	 * @return string
	 */
	private static function sanitize_order( $value ) {
		return ( 'ASC' === strtoupper( (string) $value ) ) ? 'ASC' : 'DESC';
	}

	/**
	 * Sanitize the orderby attribute.
	 *
	 * @param mixed $value Attribute value.
	 * @return string
	 */
	private static function sanitize_orderby( $value ) {
		$allowed = array( 'menu_order', 'date', 'title', 'ID', 'modified', 'rand' );
		$value   = strtolower( (string) $value );

		return in_array( $value, $allowed, true ) ? $value : 'menu_order';
	}
}
