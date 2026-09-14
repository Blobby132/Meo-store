<?php
/**
 * Single page.
 *
 * @package MEO
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="meo-wrap meo-wrap--narrow meo-section">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<article <?php post_class( 'meo-entry' ); ?>>
			<div class="meo-section-head">
				<p class="meo-label"><?php esc_html_e( 'Document', 'meo' ); ?></p>
				<h1><?php the_title(); ?></h1>
			</div>

			<?php meo_tear_line( 'tight' ); ?>

			<?php the_content(); ?>

			<?php
			wp_link_pages( array(
				'before' => '<nav class="meo-page-links meo-mono">',
				'after'  => '</nav>',
			) );
			?>
		</article>
		<?php
	endwhile;
	?>
</div>

<?php
get_footer();
