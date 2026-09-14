<?php
/**
 * Seed the MEO catalogue: 4 product categories + 6 placeholder products.
 *
 * Run through WP-CLI:
 *   wp eval-file wp-content/meo-scripts/php/seed-catalog.php
 * or, from the repo root:
 *   npm run setup:catalog
 *
 * IDEMPOTENT. Matching is by SKU (products) and slug (categories), so re-running
 * updates in place instead of creating duplicates.
 *
 * ---------------------------------------------------------------------------
 * EVERY PRODUCT HERE IS PLACEHOLDER DATA.
 * ---------------------------------------------------------------------------
 * Prices, stock levels and lead times are invented. They exist to exercise the
 * storefront design (in stock / low stock / out of stock / on sale) and to give
 * the layout realistic text lengths.
 *
 * Each one carries the meta `_meo_placeholder = yes`, which:
 *   - renders a red "Placeholder" flag on the product card and page, and
 *   - lets the real import find and remove them in one call:
 *       wp eval 'meo_dropship_purge_placeholders();'
 *
 * Do not launch with these live. See docs/dropshipping-integration.md.
 * ---------------------------------------------------------------------------
 *
 * @package MEO
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

if ( ! class_exists( 'WooCommerce' ) ) {
	WP_CLI::error( 'WooCommerce is not active. Run `npm run setup` first.' );
}

/*
 * Categories. Slugs must stay in step with meo_categories() in
 * wp-content/themes/meo/inc/template-tags.php — the homepage grid looks terms
 * up by these slugs.
 */
$categories = array(
	'kitchen-bar'   => array(
		'name' => 'Kitchen & Bar',
		'desc' => 'Brewing, serving and pouring. Things used daily and washed often.',
	),
	'desk-study'    => array(
		'name' => 'Desk & Study',
		'desc' => 'Trays, organisers and small objects that keep a working surface calm.',
	),
	'home-textiles' => array(
		'name' => 'Home Textiles',
		'desc' => 'Linen, wool and cotton. Weights and weaves listed per item.',
	),
	'lighting'      => array(
		'name' => 'Lighting',
		'desc' => 'Warm, low-glare lamps. Bulb type and lumen output disclosed.',
	),
);

$term_ids = array();

WP_CLI::log( 'Categories' );

foreach ( $categories as $slug => $data ) {
	$existing = get_term_by( 'slug', $slug, 'product_cat' );

	if ( $existing && ! is_wp_error( $existing ) ) {
		wp_update_term( $existing->term_id, 'product_cat', array(
			'name'        => $data['name'],
			'description' => $data['desc'],
		) );
		$term_ids[ $slug ] = (int) $existing->term_id;
		WP_CLI::log( sprintf( '  updated  %-14s %s', $slug, $data['name'] ) );
	} else {
		$created = wp_insert_term( $data['name'], 'product_cat', array(
			'slug'        => $slug,
			'description' => $data['desc'],
		) );

		if ( is_wp_error( $created ) ) {
			WP_CLI::warning( sprintf( 'Could not create "%s": %s', $slug, $created->get_error_message() ) );
			continue;
		}

		$term_ids[ $slug ] = (int) $created['term_id'];
		WP_CLI::log( sprintf( '  created  %-14s %s', $slug, $data['name'] ) );
	}
}

/*
 * Products.
 *
 * Stock levels are chosen to exercise each state the storefront renders:
 *   dripper 24 -> in stock      lamp 15 -> in stock + on sale
 *   tray    12 -> in stock      blanket  4 -> LOW stock (<= 6)
 *   throw    8 -> in stock      planter  0 -> OUT of stock
 */
$products = array(
	array(
		'sku'       => 'MEO-KB-0142',
		'name'      => 'Ceramic Pour-Over Dripper',
		'cat'       => 'kitchen-bar',
		'price'     => '34.00',
		'stock'     => 24,
		'weight'    => '0.42',
		'dims'      => array( '12', '12', '9' ),
		'short'     => 'Glazed stoneware cone for #2 paper filters. Brews one cup at a time.',
		'desc'      => 'A single-cup cone dripper in glazed stoneware, heavy enough that it does not shift when you pour. Interior ribs hold the paper filter off the wall so water drains through the bed evenly instead of channelling down one side.

Takes a standard #2 paper filter. The base sits on mugs and carafes with a rim between 70 and 95 mm across. Dishwasher safe; the glaze does not stain from coffee oils.

Stoneware is fired, not coated, so small variations in glaze thickness are normal and not a defect.',
	),
	array(
		'sku'       => 'MEO-DS-0318',
		'name'      => 'Walnut Desk Tray',
		'cat'       => 'desk-study',
		'price'     => '48.00',
		'stock'     => 12,
		'weight'    => '0.55',
		'dims'      => array( '24', '15', '3' ),
		'short'     => 'Solid black walnut, milled from one piece. Felt pads underneath.',
		'desc'      => 'A shallow catch-all tray milled from a single piece of solid black walnut — no joins, so nothing to separate as the wood moves with the seasons.

The walls are 8 mm and the interior floor is flat, which means it holds keys, cards and a phone without them sliding into a pile in one corner. Four felt pads on the underside keep it off the desk surface.

Finished with a hardwax oil rather than a film lacquer. It will mark, and the marks sand out with fine paper and a fresh coat of oil. Grain and colour vary between pieces; walnut darkens toward chocolate with light exposure.',
	),
	array(
		'sku'       => 'MEO-HT-0227',
		'name'      => 'Linen Throw',
		'cat'       => 'home-textiles',
		'price'     => '89.00',
		'stock'     => 8,
		'weight'    => '0.90',
		'dims'      => array( '35', '28', '10' ),
		'short'     => '100% European flax, 180 gsm, stonewashed. 130 × 170 cm.',
		'desc'      => 'Woven from 100% European flax at 180 gsm and stonewashed before it ships, so it arrives already soft rather than needing a season of use to get there.

Finished at 130 × 170 cm — long enough to cover a two-seat sofa or to sleep under on a warm night, and not so heavy that it holds heat. Edges are turned and double-stitched.

Machine wash cold, tumble dry low. Linen relaxes and softens with every wash and creases readily; if a flat, pressed look matters to you, this is not the right fabric.',
	),
	array(
		'sku'       => 'MEO-HT-0451',
		'name'      => 'Weighted Lap Blanket',
		'cat'       => 'home-textiles',
		'price'     => '124.00',
		'stock'     => 4,
		'weight'    => '2.30',
		'dims'      => array( '40', '32', '14' ),
		'short'     => '2.3 kg of glass-bead fill in quilted cotton. 90 × 120 cm.',
		'desc'      => 'Sized for a chair rather than a bed: 90 × 120 cm, 2.3 kg. It covers your lap and legs while you are sitting and stays put when you stand up, which a full-size weighted blanket does not.

The fill is glass microbead, quilted into 12 cm squares so the weight stays distributed instead of pooling at one end. The shell is a 240 gsm cotton twill.

Spot clean the shell, or use a removable duvet cover over it. Do not tumble dry — heat degrades the quilting thread and the beads migrate once a seam opens. Not intended for children under three.',
	),
	array(
		'sku'         => 'MEO-LT-0089',
		'name'        => 'Amber Reading Lamp',
		'cat'         => 'lighting',
		'price'       => '96.00',
		'regular'     => '118.00',
		'stock'       => 15,
		'weight'      => '1.40',
		'dims'        => array( '18', '18', '42' ),
		'short'       => 'Amber glass shade, brushed brass stem. Ships with a 2700 K LED.',
		'desc'        => 'A small table lamp for reading beside, not under. The amber glass shade cuts glare and pushes a warm pool of light downward, so it lights a book without lighting the whole room.

Brushed brass stem, 42 cm tall, on a weighted base. Takes an E26 bulb up to 60 W and ships with a 2700 K LED rated 450 lumens. Inline rocker switch on a 1.8 m cord — you do not have to reach under the shade to turn it off.

Certified for 120 V. Brass is unlacquered and will patina; polish it back with a brass cleaner if you would rather it stayed bright.',
		'featured'    => true,
	),
	array(
		'sku'       => 'MEO-DS-0503',
		'name'      => 'Terrazzo Planter',
		'cat'       => 'desk-study',
		'price'     => '42.00',
		'stock'     => 0,
		'weight'    => '1.10',
		'dims'      => array( '14', '14', '13' ),
		'short'     => 'Cast terrazzo, 14 cm. Drainage hole, cork plug and matching saucer.',
		'desc'      => 'Cast terrazzo, 14 cm across and 13 cm deep — the right size for a small pothos, a snake plant or a herb that has outgrown its nursery pot.

There is a real drainage hole in the base with a cork plug, plus a matching saucer, so it works either as a planter or as a cachepot depending on whether you leave the plug in.

The chip pattern is set when the terrazzo is poured and cannot be repeated, so no two are alike and the one you receive will not match the photograph exactly. Terrazzo is porous; seal it if you plan to keep it on untreated wood.',
	),
);

WP_CLI::log( '' );
WP_CLI::log( 'Products' );

$created = 0;
$updated = 0;

foreach ( $products as $spec ) {
	$existing_id = wc_get_product_id_by_sku( $spec['sku'] );
	$product     = $existing_id ? wc_get_product( $existing_id ) : new WC_Product_Simple();

	$product->set_name( $spec['name'] );
	$product->set_sku( $spec['sku'] );
	$product->set_status( 'publish' );
	$product->set_catalog_visibility( 'visible' );
	$product->set_description( $spec['desc'] );
	$product->set_short_description( $spec['short'] );

	// A sale price is only meaningful alongside a higher regular price.
	if ( isset( $spec['regular'] ) ) {
		$product->set_regular_price( $spec['regular'] );
		$product->set_sale_price( $spec['price'] );
	} else {
		$product->set_regular_price( $spec['price'] );
		$product->set_sale_price( '' );
	}

	$product->set_manage_stock( true );
	$product->set_stock_quantity( $spec['stock'] );
	$product->set_stock_status( $spec['stock'] > 0 ? 'instock' : 'outofstock' );
	$product->set_backorders( 'no' );

	$product->set_weight( $spec['weight'] );
	$product->set_length( $spec['dims'][0] );
	$product->set_width( $spec['dims'][1] );
	$product->set_height( $spec['dims'][2] );

	$product->set_featured( ! empty( $spec['featured'] ) );

	// Shippable goods, so taxable at the standard rate. See configure-tax.php.
	$product->set_tax_status( 'taxable' );
	$product->set_tax_class( '' );

	if ( isset( $term_ids[ $spec['cat'] ] ) ) {
		$product->set_category_ids( array( $term_ids[ $spec['cat'] ] ) );
	}

	// The flag that makes these removable in one call, and visible as fake.
	$product->update_meta_data( '_meo_placeholder', 'yes' );
	$product->update_meta_data( '_meo_placeholder_note', 'Seed data from scripts/php/seed-catalog.php. Replace with the supplier feed.' );

	$product->save();

	if ( $existing_id ) {
		++$updated;
		WP_CLI::log( sprintf( '  updated  %-13s %-28s $%s', $spec['sku'], $spec['name'], $spec['price'] ) );
	} else {
		++$created;
		WP_CLI::log( sprintf( '  created  %-13s %-28s $%s', $spec['sku'], $spec['name'], $spec['price'] ) );
	}
}

wc_delete_product_transients();

WP_CLI::log( '' );
WP_CLI::success( sprintf(
	'Catalogue seeded: %d categories, %d products created, %d updated. ALL PRODUCTS ARE PLACEHOLDERS.',
	count( $term_ids ),
	$created,
	$updated
) );
WP_CLI::log( 'Remove them later with:  wp eval \'meo_dropship_purge_placeholders();\'' );
