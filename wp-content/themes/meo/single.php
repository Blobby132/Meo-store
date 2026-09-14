<?php
/**
 * Single post.
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
				<p class="meo-label"><?php echo esc_html( get_the_date() ); ?></p>
				<h1><?php the_title(); ?></h1>
			</div>

			<?php meo_tear_line( 'tight' ); ?>

			<?php the_content(); ?>
		</article>

		<?php
		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}
	endwhile;
	?>
</div>

<?php
get_footer();
