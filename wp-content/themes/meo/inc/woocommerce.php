<?php
/**
 * WooCommerce integration.
 *
 * Strategy: the theme restyles Woo from scratch rather than overriding its
 * stylesheet, so the three classic Woo sheets are dequeued. Loop markup is
 * replaced via woocommerce/content-product.php, but the standard action hooks
 * still fire there — third-party plugins that hook the loop keep working.
 *
 * @package MEO
 */

defined( 'ABSPATH' ) || exit;

/**
 * Bail out of this whole file if WooCommerce is not active.
 *
 * The theme is usable without Woo (it degrades to a plain site), so nothing
 * below should run or error in that case.
 */
if ( ! function_exists( 'meo_is_woocommerce_active' ) || ! meo_is_woocommerce_active() ) {
	return;
}

/**
 * Drop WooCommerce's classic stylesheets.
 *
 * `wc-blocks-style` is deliberately left alone: the block-based Cart and
 * Checkout introduced in Woo 8 depend on it, and removing it breaks them.
 * assets/css/woocommerce.css styles those blocks on top of it.
 *
 * @param array $styles Woo's registered styles.
 * @return array
 */
function meo_dequeue_woocommerce_styles( $styles ) {
	unset( $styles['woocommerce-general'] );
	unset( $styles['woocommerce-layout'] );
	unset( $styles['woocommerce-smallscreen'] );

	return $styles;
}
add_filter( 'woocommerce_enqueue_styles', 'meo_dequeue_woocommerce_styles' );

/**
 * Products per row in the shop loop.
 *
 * @return int
 */
function meo_loop_columns() {
	return 3;
}
add_filter( 'loop_shop_columns', 'meo_loop_columns' );

/**
 * Products per page.
 *
 * @return int
 */
function meo_products_per_page() {
	return 12;
}
add_filter( 'loop_shop_per_page', 'meo_products_per_page', 20 );

/**
 * Rebuild the loop hook layout.
 *
 * content-product.php draws its own anchor, title, price and stock line, so
 * the default callbacks for those are unhooked to avoid duplicates. Everything
 * else (thumbnail, sale flash, add-to-cart) stays on its normal hook so the
 * template can just `do_action` and plugins land where they expect.
 */
function meo_rearrange_loop_hooks() {
	remove_action( 'woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open', 10 );
	remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5 );
	remove_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10 );
	remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
	remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );

	// Sale flash is re-emitted inside the media box with our own classes.
	remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 );

	// Default breadcrumb position is above the wrapper; we place it ourselves.
	remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
}
add_action( 'init', 'meo_rearrange_loop_hooks' );

/**
 * Sale badge markup, matching the placeholder flag styling.
 *
 * @param string     $html    Default markup.
 * @param WP_Post    $post    Post object.
 * @param WC_Product $product Product.
 * @return string
 */
function meo_sale_flash( $html, $post, $product ) {
	return '<span class="meo-flag meo-flag--sale">' . esc_html__( 'On sale', 'meo' ) . '</span>';
}
add_filter( 'woocommerce_sale_flash', 'meo_sale_flash', 10, 3 );

/**
 * Breadcrumb separator and wrapper, in the manifest voice.
 *
 * @param array $args Breadcrumb args.
 * @return array
 */
function meo_breadcrumb_args( $args ) {
	$args['delimiter']   = ' <span aria-hidden="true">/</span> ';
	$args['wrap_before'] = '<nav class="woocommerce-breadcrumb meo-mono" aria-label="' . esc_attr__( 'Breadcrumb', 'meo' ) . '">';
	$args['wrap_after']  = '</nav>';
	$args['home']        = __( 'Index', 'meo' );

	return $args;
}
add_filter( 'woocommerce_breadcrumb_defaults', 'meo_breadcrumb_args' );

/**
 * Show the stock badge on the single product page, under the price.
 */
function meo_single_stock_badge() {
	$badge = meo_stock_badge();
	if ( $badge ) {
		echo '<p class="meo-single-stock">' . wp_kses_post( $badge ) . '</p>';
	}
}
add_action( 'woocommerce_single_product_summary', 'meo_single_stock_badge', 11 );

/**
 * Flag placeholder products on the single product page.
 *
 * Deliberately conspicuous: these are seed data and must never be mistaken
 * for live inventory. Removed automatically once the real feed replaces them.
 */
function meo_single_placeholder_notice() {
	if ( ! meo_is_placeholder() ) {
		return;
	}

	echo '<p class="meo-placeholder-note meo-mono">'
		. esc_html__( 'Placeholder listing — sample data, not live inventory. Replace with the supplier feed before launch.', 'meo' )
		. '</p>';
}
add_action( 'woocommerce_single_product_summary', 'meo_single_placeholder_notice', 4 );

/**
 * Replace Woo's default stock text with ours (the pill covers it).
 *
 * @param string     $html    Default markup.
 * @param string     $text    Availability text.
 * @param WC_Product $product Product.
 * @return string
 */
function meo_availability_html( $html, $text, $product ) {
	return '';
}
add_filter( 'woocommerce_get_stock_html', 'meo_availability_html', 10, 3 );

/**
 * Cart item count for the header badge.
 *
 * Wrapped so header.php stays free of Woo globals and null checks — the cart
 * object does not exist on some admin/AJAX requests.
 *
 * @return int
 */
function meo_cart_count() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return 0;
	}

	return (int) WC()->cart->get_cart_contents_count();
}

/**
 * Keep the header cart badge in sync after AJAX add-to-cart.
 *
 * @param array $fragments Fragments keyed by selector.
 * @return array
 */
function meo_cart_fragment( $fragments ) {
	ob_start();
	?>
	<span class="meo-cart-count" data-meo-cart-count><?php echo esc_html( (string) meo_cart_count() ); ?></span>
	<?php
	$fragments['span[data-meo-cart-count]'] = ob_get_clean();

	return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'meo_cart_fragment' );

/**
 * Canadian storefront defaults.
 *
 * Only applied when the option is unset, so a deliberate change in wp-admin
 * is never clobbered on the next page load. The authoritative tax setup lives
 * in scripts/configure-tax.sh — see docs/canadian-tax.md.
 */
function meo_currency_position() {
	return 'left';
}
add_filter( 'woocommerce_currency_pos', 'meo_currency_position' );

/**
 * -------------------------------------------------------------------------
 * DROPSHIPPING SUPPLIER INTEGRATION
 * -------------------------------------------------------------------------
 *
 * Nothing here talks to a supplier. The integration surface is deliberately
 * isolated in the must-use plugin at:
 *
 *     wp-content/mu-plugins/meo-dropship-bridge/
 *
 * so that swapping connectors (AliDropship, DropshipMe, Spocket, Printful,
 * a custom CSV/API importer) never means editing the theme. See that plugin's
 * README and docs/dropshipping-integration.md for the hook list.
 * -------------------------------------------------------------------------
 */
