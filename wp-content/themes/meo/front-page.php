<?php
/**
 * Homepage.
 *
 * Section order mirrors the storefront reference: hero + manifest, trust strip,
 * category grid, product grid, about/story, then the packing-slip footer.
 *
 * If a static page is assigned as the front page in Settings > Reading, its
 * editor content is rendered between the category grid and the about section,
 * so the homepage stays partly editable without touching this file.
 *
 * @package MEO
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<?php get_template_part( 'template-parts/hero' ); ?>

<?php get_template_part( 'template-parts/trust-strip' ); ?>

<div class="meo-wrap"><?php meo_tear_line(); ?></div>

<?php get_template_part( 'template-parts/category-grid' ); ?>

<?php
// Editor content from the assigned front page, when there is any.
if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();

		$meo_front_content = get_the_content();
		if ( trim( $meo_front_content ) !== '' ) :
			?>
			<section class="meo-section meo-section--tight">
				<div class="meo-wrap meo-entry">
					<?php the_content(); ?>
				</div>
			</section>
			<?php
		endif;
	endwhile;
endif;
?>

<?php if ( meo_is_woocommerce_active() ) : ?>
	<div class="meo-wrap"><?php meo_tear_line(); ?></div>

	<section class="meo-section" id="meo-catalogue">
		<div class="meo-wrap">
			<div class="meo-section-head">
				<p class="meo-label"><?php esc_html_e( 'Section 02 — Line items', 'meo' ); ?></p>
				<h2><?php esc_html_e( 'In the current consignment', 'meo' ); ?></h2>
				<p><?php esc_html_e( 'Six items, priced in CAD. SKU and stock status shown on each.', 'meo' ); ?></p>
			</div>

			<?php
			/*
			 * Rendered through Woo's [products] shortcode so each card goes
			 * through woocommerce/content-product.php — same markup as the shop
			 * archive, one place to maintain.
			 */
			echo do_shortcode( '[products limit="6" columns="3" visibility="visible" orderby="menu_order title" order="ASC"]' );
			?>

			<p class="meo-catalogue-more">
				<a class="meo-btn meo-btn--ghost" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">
					<?php esc_html_e( 'See the full catalogue', 'meo' ); ?>
				</a>
			</p>
		</div>
	</section>
<?php endif; ?>

<div class="meo-wrap"><?php meo_tear_line(); ?></div>

<?php get_template_part( 'template-parts/about' ); ?>

<?php
get_footer();
