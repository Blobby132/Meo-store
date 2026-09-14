<?php
/**
 * Product card in the shop loop.
 *
 * Overrides woocommerce/templates/content-product.php.
 *
 * The card is restructured (media box, SKU line, title, price + stock footer,
 * cart action) but every standard loop hook still fires, so plugins that hook
 * the loop land in a sensible place. The default callbacks that would collide
 * with our own markup are unhooked in inc/woocommerce.php, not here.
 *
 * @package MEO
 * @version 3.6.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( empty( $product ) || ! $product->is_visible() ) {
	return;
}

$meo_category = meo_product_category_name( $product );
?>
<li <?php wc_product_class( 'meo-card', $product ); ?>>
	<?php do_action( 'woocommerce_before_shop_loop_item' ); ?>

	<a class="meo-card__media" href="<?php echo esc_url( get_permalink() ); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( meo_is_placeholder( $product ) ) : ?>
			<span class="meo-flag meo-flag--placeholder"><?php esc_html_e( 'Placeholder', 'meo' ); ?></span>
		<?php elseif ( $product->is_on_sale() ) : ?>
			<?php woocommerce_show_product_loop_sale_flash(); ?>
		<?php endif; ?>

		<?php
		if ( has_post_thumbnail() ) {
			/*
			 * woocommerce_template_loop_product_thumbnail is still attached to
			 * this hook, so the image (and anything a plugin adds beside it)
			 * renders here inside our media box.
			 */
			do_action( 'woocommerce_before_shop_loop_item_title' );
		} else {
			/*
			 * No image yet — common while the catalogue is placeholder data or
			 * mid-import. Show the SKU on hatched paper rather than Woo's grey
			 * placeholder, which looks like a broken image.
			 */
			?>
			<span class="meo-card__placeholder">
				<span><?php esc_html_e( 'No image', 'meo' ); ?></span>
				<span><?php echo esc_html( meo_product_sku( $product ) ); ?></span>
			</span>
			<?php
		}
		?>
	</a>

	<div class="meo-card__body">
		<p class="meo-card__sku">
			<span class="meo-card__code"><?php echo esc_html( meo_product_sku( $product ) ); ?></span>
			<?php if ( $meo_category ) : ?>
				<span class="meo-card__cat"><?php echo esc_html( $meo_category ); ?></span>
			<?php endif; ?>
		</p>

		<h2 class="meo-card__title woocommerce-loop-product__title">
			<a href="<?php echo esc_url( get_permalink() ); ?>"><?php the_title(); ?></a>
		</h2>

		<?php do_action( 'woocommerce_shop_loop_item_title' ); ?>

		<div class="meo-card__foot">
			<?php if ( $product->get_price_html() ) : ?>
				<span class="price"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
			<?php endif; ?>

			<?php echo wp_kses_post( meo_stock_badge( $product ) ); ?>
		</div>

		<?php do_action( 'woocommerce_after_shop_loop_item_title' ); ?>

		<div class="meo-card__cart">
			<?php do_action( 'woocommerce_after_shop_loop_item' ); ?>
		</div>
	</div>
</li>
