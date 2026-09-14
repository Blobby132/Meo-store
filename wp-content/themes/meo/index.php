<?php
/**
 * Fallback template — used for the blog index, archives and anything without
 * a more specific template.
 *
 * @package MEO
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="meo-wrap meo-section">
	<?php if ( have_posts() ) : ?>
		<div class="meo-section-head">
			<p class="meo-label"><?php esc_html_e( 'Index', 'meo' ); ?></p>
			<h1><?php echo esc_html( get_the_archive_title() ? wp_strip_all_tags( get_the_archive_title() ) : get_bloginfo( 'name' ) ); ?></h1>
		</div>

		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article <?php post_class( 'meo-entry' ); ?>>
				<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
				<p class="meo-label"><?php echo esc_html( get_the_date() ); ?></p>
				<?php the_excerpt(); ?>
			</article>
			<?php meo_tear_line( 'tight' ); ?>
			<?php
		endwhile;

		the_posts_pagination( array( 'class' => 'woocommerce-pagination' ) );
	else :
		?>
		<div class="meo-section-head">
			<h1><?php esc_html_e( 'Nothing filed here yet', 'meo' ); ?></h1>
			<p><?php esc_html_e( 'This index is empty.', 'meo' ); ?></p>
		</div>
	<?php endif; ?>
</div>

<?php
get_footer();
