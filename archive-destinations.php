<?php
/**
 * The template for displaying the Destinations archive.
 *
 * @package Astra-Child
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

wp_enqueue_style('destinations', get_stylesheet_directory_uri() . '/assets/css/destination.css', array(), '1.0.0', 'all');
?>

<?php if ( function_exists( 'astra_page_layout' ) && 'left-sidebar' === astra_page_layout() ) : ?>

	<?php get_sidebar(); ?>

<?php endif; ?>

	<div id="primary" <?php astra_primary_class(); ?>>

		<?php astra_primary_content_top(); ?>

		<?php if ( function_exists( 'astra_archive_header' ) ) : ?>

			<?php astra_archive_header(); ?>

		<?php else : ?>

			<h1 class="page-title ast-archive-title">
				<?php echo esc_html( post_type_archive_title( '', false ) ); ?>
			</h1>

		<?php endif; ?>

		<main id="main">

			<?php if ( have_posts() ) : ?>

				<div class="destinations-grid">

					<?php
					while ( have_posts() ) {
						the_post();
						?>
						<article>
                            <a href="<?php the_permalink(); ?>" class="destination-link">
                                <?php if ( has_post_thumbnail() ) : ?>
                                    <?php the_post_thumbnail( 'post-thumbnail', array( 'loading' => 'lazy' ) ); ?>
                                <?php else : ?>
                                    <img src="<?php echo get_stylesheet_directory_uri(); ?>/assets/img/placeholder.webp" alt="<?php the_title(); ?>" loading="lazy">
                                <?php endif; ?>
                                <h2><?php the_title(); ?></h2>
                            </a>
						</article>

						<?php
					}
					?>

				</div>

				<div class="ast-pagination">

					<?php
					the_posts_pagination(
						array(
							'prev_text'          => __( 'Previous', 'astra-child' ),
							'next_text'          => __( 'Next', 'astra-child' ),
							'screen_reader_text' => __( 'Destinations pagination', 'astra-child' ),
						)
					);
					?>

				</div><!-- .ast-pagination -->

			<?php else : ?>

				<section class="no-results not-found">

					<div class="page-content">

						<p><?php esc_html_e( 'No destinations found.', 'astra-child' ); ?></p>

					</div>

				</section>

			<?php endif; ?>

		</main><!-- #main -->

		<?php astra_primary_content_bottom(); ?>

	</div><!-- #primary -->

<?php if ( function_exists( 'astra_page_layout' ) && 'right-sidebar' === astra_page_layout() ) : ?>

	<?php get_sidebar(); ?>

<?php endif; ?>

<?php get_footer(); ?>
<?php

get_footer();
?>