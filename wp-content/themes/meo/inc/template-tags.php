<?php
/**
 * Template tags — the reusable pieces of the manifest design language.
 *
 * @package MEO
 */

defined( 'ABSPATH' ) || exit;

/**
 * Is WooCommerce loaded?
 *
 * Every Woo-touching branch in the theme goes through this, so the theme
 * still renders (as a plain site) if the plugin is deactivated.
 *
 * @return bool
 */
function meo_is_woocommerce_active() {
	return class_exists( 'WooCommerce' );
}

/**
 * Print a dashed tear-line divider.
 *
 * @param string $modifier Optional BEM modifier suffix: 'tight' or 'flush'.
 */
function meo_tear_line( $modifier = '' ) {
	$class = 'meo-tear';
	if ( $modifier ) {
		$class .= ' meo-tear--' . sanitize_html_class( $modifier );
	}
	printf( '<hr class="%s" role="presentation" />', esc_attr( $class ) );
}

/**
 * Inline SVG icon.
 *
 * Icons are inlined rather than sprited because there are only a handful and
 * they need `stroke: currentColor` to follow the theme tokens.
 *
 * @param string $name Icon key.
 * @return string Markup, or '' for an unknown key.
 */
function meo_icon( $name ) {
	$paths = array(
		'truck'  => '<path d="M1 3h13v10H1z"/><path d="M14 6h3.5L21 9.5V13h-7z"/><circle cx="6" cy="17" r="2"/><circle cx="17" cy="17" r="2"/>',
		'receipt' => '<path d="M5 2h14v20l-3-2-3 2-3-2-3 2z"/><path d="M9 7h6M9 11h6"/>',
		'return' => '<path d="M3 9h12a5 5 0 0 1 0 10h-6"/><path d="M7 5 3 9l4 4"/>',
		'sun'    => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
		'moon'   => '<path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a6.6 6.6 0 0 0 10.5 10.5z"/>',
		'cart'   => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.4 11.4a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.6L21 7H6"/>',
		'menu'   => '<path d="M3 6h18M3 12h18M3 18h18"/>',
		'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
	);

	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}

	return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" stroke-linecap="round" stroke-linejoin="round">'
		. $paths[ $name ] . '</svg>';
}

/**
 * The four storefront categories, in display order.
 *
 * Slugs must match what scripts/seed-catalog.sh creates in WooCommerce.
 * Descriptions live here rather than in the term description so the homepage
 * copy stays in version control.
 *
 * @return array<int, array<string, string>>
 */
function meo_categories() {
	return array(
		array(
			'slug'  => 'kitchen-bar',
			'name'  => __( 'Kitchen & Bar', 'meo' ),
			'desc'  => __( 'Brewing, serving, pouring. Things used daily and washed often.', 'meo' ),
			'code'  => 'KB',
		),
		array(
			'slug'  => 'desk-study',
			'name'  => __( 'Desk & Study', 'meo' ),
			'desc'  => __( 'Trays, organisers and small objects that keep a surface calm.', 'meo' ),
			'code'  => 'DS',
		),
		array(
			'slug'  => 'home-textiles',
			'name'  => __( 'Home Textiles', 'meo' ),
			'desc'  => __( 'Linen, wool and cotton. Weights and weaves listed per item.', 'meo' ),
			'code'  => 'HT',
		),
		array(
			'slug'  => 'lighting',
			'name'  => __( 'Lighting', 'meo' ),
			'desc'  => __( 'Warm, low-glare lamps. Bulb type and lumen output disclosed.', 'meo' ),
			'code'  => 'LT',
		),
	);
}

/**
 * The trust strip items.
 *
 * @return array<int, array<string, string>>
 */
function meo_trust_items() {
	return array(
		array(
			'icon'  => 'truck',
			'title' => __( 'Tracked shipping', 'meo' ),
			'body'  => __( 'Every order ships with a tracking number, emailed when the label is created. 6–14 business days to most Canadian addresses.', 'meo' ),
		),
		array(
			'icon'  => 'receipt',
			'title' => __( 'Duties disclosed', 'meo' ),
			'body'  => __( 'Prices are in CAD. Any import duty or brokerage is shown at checkout before you pay — never billed on delivery.', 'meo' ),
		),
		array(
			'icon'  => 'return',
			'title' => __( '30-day returns', 'meo' ),
			'body'  => __( 'Unused and in its packaging, send it back within 30 days of delivery. We pay return postage on anything that arrives damaged.', 'meo' ),
		),
	);
}

/**
 * Stock status pill for a product.
 *
 * @param WC_Product|null $product Product; falls back to the global.
 * @return string Markup, or '' when there is no product.
 */
function meo_stock_badge( $product = null ) {
	if ( ! meo_is_woocommerce_active() ) {
		return '';
	}

	$product = $product ? $product : wc_get_product();
	if ( ! $product instanceof WC_Product ) {
		return '';
	}

	if ( ! $product->is_in_stock() ) {
		$modifier = 'out';
		$label    = __( 'Out of stock', 'meo' );
	} elseif ( $product->is_on_backorder( 1 ) ) {
		$modifier = 'backorder';
		$label    = __( 'On backorder', 'meo' );
	} else {
		$qty = $product->get_stock_quantity();

		/**
		 * Below this count a product reads as "low stock" rather than "in stock".
		 *
		 * @param int        $threshold Default 6.
		 * @param WC_Product $product   Product being rendered.
		 */
		$threshold = (int) apply_filters( 'meo_low_stock_threshold', 6, $product );

		if ( null !== $qty && $qty <= $threshold ) {
			$modifier = 'low';
			/* translators: %d: units remaining. */
			$label = sprintf( _n( '%d left', '%d left', $qty, 'meo' ), $qty );
		} else {
			$modifier = 'in';
			$label    = __( 'In stock', 'meo' );
		}
	}

	return sprintf(
		'<span class="meo-stock meo-stock--%1$s">%2$s</span>',
		esc_attr( $modifier ),
		esc_html( $label )
	);
}

/**
 * SKU for a product, falling back to a derived code.
 *
 * Products imported from a supplier feed may arrive without a SKU set; showing
 * "—" in a SKU slot looks broken, so derive a stable stand-in from the ID.
 *
 * @param WC_Product|null $product Product; falls back to the global.
 * @return string
 */
function meo_product_sku( $product = null ) {
	if ( ! meo_is_woocommerce_active() ) {
		return '';
	}

	$product = $product ? $product : wc_get_product();
	if ( ! $product instanceof WC_Product ) {
		return '';
	}

	$sku = $product->get_sku();

	return $sku ? $sku : sprintf( 'MEO-%05d', $product->get_id() );
}

/**
 * Is this product one of the seeded placeholders?
 *
 * seed-catalog.sh sets the `_meo_placeholder` meta on everything it creates,
 * so the storefront can flag it and so a real import can find and remove them.
 *
 * @param WC_Product|null $product Product; falls back to the global.
 * @return bool
 */
function meo_is_placeholder( $product = null ) {
	if ( ! meo_is_woocommerce_active() ) {
		return false;
	}

	$product = $product ? $product : wc_get_product();
	if ( ! $product instanceof WC_Product ) {
		return false;
	}

	return 'yes' === $product->get_meta( '_meo_placeholder' );
}

/**
 * Primary category name for a product, for the card's kicker line.
 *
 * @param WC_Product $product Product.
 * @return string
 */
function meo_product_category_name( $product ) {
	$terms = get_the_terms( $product->get_id(), 'product_cat' );

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return '';
	}

	return $terms[0]->name;
}
