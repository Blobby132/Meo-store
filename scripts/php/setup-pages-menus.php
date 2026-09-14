<?php
/**
 * Create MEO's content pages and wire up the navigation menus.
 *
 * Run through WP-CLI:
 *   wp eval-file wp-content/meo-scripts/php/setup-pages-menus.php
 *
 * Idempotent: pages are matched by slug and menus by name, so re-running
 * refreshes them rather than duplicating.
 *
 * @package MEO
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Create or update a page by slug.
 *
 * @param string $slug    Page slug.
 * @param string $title   Page title.
 * @param string $content Page content.
 * @return int Page ID, or 0 on failure.
 */
$meo_upsert_page = function ( $slug, $title, $content ) {
	$existing = get_page_by_path( $slug );

	$postarr = array(
		'post_title'   => $title,
		'post_name'    => $slug,
		'post_content' => $content,
		'post_status'  => 'publish',
		'post_type'    => 'page',
	);

	if ( $existing ) {
		$postarr['ID'] = $existing->ID;
		$id            = wp_update_post( $postarr, true );
		$verb          = 'updated';
	} else {
		$id   = wp_insert_post( $postarr, true );
		$verb = 'created';
	}

	if ( is_wp_error( $id ) ) {
		WP_CLI::warning( sprintf( 'Page "%s": %s', $slug, $id->get_error_message() ) );
		return 0;
	}

	WP_CLI::log( sprintf( '  %-8s /%s', $verb, $slug ) );

	return (int) $id;
};

WP_CLI::log( 'Pages' );

$home_id = $meo_upsert_page(
	'home',
	'Home',
	''
);

$about_id = $meo_upsert_page(
	'about',
	'About MEO',
	'<!-- wp:paragraph --><p>MEO is a small Canadian shop for home and desk goods — things for the kitchen, the work surface and the quiet end of the day.</p><!-- /wp:paragraph -->

<!-- wp:heading --><h2>How orders reach you</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>We do not hold a warehouse. Orders are placed with vetted suppliers who ship directly to your address. That keeps the catalogue short and the prices flat, and it has trade-offs we would rather state than bury: transit runs 6 to 14 business days, and an order containing items from two suppliers arrives as two separately tracked parcels.</p><!-- /wp:paragraph -->

<!-- wp:heading --><h2>What we publish about each item</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Every listing states its material, its dimensions, its weight and where it ships from. If a detail is missing, it is missing because we have not confirmed it — we would rather leave a gap than guess.</p><!-- /wp:paragraph -->

<!-- wp:heading --><h2>Prices and tax</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>All prices are in Canadian dollars. Where an item ships from outside Canada, any duty or brokerage is calculated and shown at checkout, so nothing is billed to you on delivery. Sales tax, where it applies, is added at checkout and itemized on your receipt.</p><!-- /wp:paragraph -->'
);

$shipping_id = $meo_upsert_page(
	'shipping-returns',
	'Shipping & Returns',
	'<!-- wp:heading --><h2>Shipping</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Flat $12 CAD tracked shipping to any Canadian address. Free on orders over $95 CAD.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Transit is 6 to 14 business days from the day the order is placed. You receive a tracking number by email when the shipping label is created, which is usually 1 to 3 business days after the order — the parcel may not scan as moving for another day or two after that.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Orders containing items from more than one supplier ship as separate parcels with separate tracking numbers, and may arrive days apart. You are not charged twice for shipping.</p><!-- /wp:paragraph -->

<!-- wp:heading --><h2>Duties and brokerage</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Where an item ships from outside Canada, any applicable duty or brokerage is calculated and collected at checkout. You will not be asked for money at the door.</p><!-- /wp:paragraph -->

<!-- wp:heading --><h2>Returns</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Unused and in its original packaging, any item can be returned within 30 days of delivery for a refund to the original payment method. Email us and we will send return instructions and the address to use.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Return postage is yours unless the item arrived damaged, defective, or was not what you ordered — in those cases we cover it. Refunds are issued within 5 business days of the return arriving.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Items that cannot be returned once opened, for hygiene reasons, are marked as such on their product page.</p><!-- /wp:paragraph -->

<!-- wp:heading --><h2>Something arrived damaged</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Send a photo within 7 days of delivery. We will ship a replacement or refund you, whichever you prefer, and you do not need to return the damaged item unless we ask.</p><!-- /wp:paragraph -->'
);

/*
 * Front page. The theme's front-page.php draws the hero, trust strip, category
 * grid, product grid and about section; the assigned page's editor content is
 * rendered between the category grid and the about section, so this page stays
 * intentionally empty until someone wants to add copy there.
 */
if ( $home_id ) {
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $home_id );
	WP_CLI::log( '  front page set to /home' );
}

/*
 * ---------------------------------------------------------------------------
 * Menus
 * ---------------------------------------------------------------------------
 * The theme falls back to a generated menu when no menu is assigned, so this
 * is about making the menus EDITABLE in wp-admin, not about making the header
 * work at all.
 */
WP_CLI::log( '' );
WP_CLI::log( 'Menus' );

/**
 * Create or reset a menu, returning its term ID.
 *
 * @param string $name Menu name.
 * @return int
 */
$meo_reset_menu = function ( $name ) {
	$menu = wp_get_nav_menu_object( $name );

	if ( $menu ) {
		// Clear items so re-running does not append duplicates.
		$items = wp_get_nav_menu_items( $menu->term_id );
		if ( $items ) {
			foreach ( $items as $item ) {
				wp_delete_post( $item->ID, true );
			}
		}
		return (int) $menu->term_id;
	}

	$id = wp_create_nav_menu( $name );

	return is_wp_error( $id ) ? 0 : (int) $id;
};

$primary_id = $meo_reset_menu( 'Primary' );
$shop_id    = $meo_reset_menu( 'Footer Shop' );
$help_id    = $meo_reset_menu( 'Footer Help' );

$category_slugs = array( 'kitchen-bar', 'desk-study', 'home-textiles', 'lighting' );

// Primary: the four departments, then About.
if ( $primary_id ) {
	$position = 1;

	foreach ( $category_slugs as $slug ) {
		$term = get_term_by( 'slug', $slug, 'product_cat' );
		if ( ! $term || is_wp_error( $term ) ) {
			continue;
		}

		wp_update_nav_menu_item( $primary_id, 0, array(
			'menu-item-title'     => $term->name,
			'menu-item-object'    => 'product_cat',
			'menu-item-object-id' => $term->term_id,
			'menu-item-type'      => 'taxonomy',
			'menu-item-status'    => 'publish',
			'menu-item-position'  => $position++,
		) );
	}

	if ( $about_id ) {
		wp_update_nav_menu_item( $primary_id, 0, array(
			'menu-item-title'     => 'About',
			'menu-item-object'    => 'page',
			'menu-item-object-id' => $about_id,
			'menu-item-type'      => 'post_type',
			'menu-item-status'    => 'publish',
			'menu-item-position'  => $position++,
		) );
	}

	WP_CLI::log( '  Primary ........ departments + About' );
}

// Footer — Shop: the four departments.
if ( $shop_id ) {
	$position = 1;

	foreach ( $category_slugs as $slug ) {
		$term = get_term_by( 'slug', $slug, 'product_cat' );
		if ( ! $term || is_wp_error( $term ) ) {
			continue;
		}

		wp_update_nav_menu_item( $shop_id, 0, array(
			'menu-item-title'     => $term->name,
			'menu-item-object'    => 'product_cat',
			'menu-item-object-id' => $term->term_id,
			'menu-item-type'      => 'taxonomy',
			'menu-item-status'    => 'publish',
			'menu-item-position'  => $position++,
		) );
	}

	WP_CLI::log( '  Footer Shop .... departments' );
}

// Footer — Help: policies and account.
if ( $help_id ) {
	$position = 1;

	foreach ( array( $shipping_id, $about_id ) as $page_id ) {
		if ( ! $page_id ) {
			continue;
		}

		wp_update_nav_menu_item( $help_id, 0, array(
			'menu-item-title'     => get_the_title( $page_id ),
			'menu-item-object'    => 'page',
			'menu-item-object-id' => $page_id,
			'menu-item-type'      => 'post_type',
			'menu-item-status'    => 'publish',
			'menu-item-position'  => $position++,
		) );
	}

	if ( function_exists( 'wc_get_page_id' ) ) {
		$account_id = wc_get_page_id( 'myaccount' );
		if ( $account_id > 0 ) {
			wp_update_nav_menu_item( $help_id, 0, array(
				'menu-item-title'     => 'Order status',
				'menu-item-object'    => 'page',
				'menu-item-object-id' => $account_id,
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
				'menu-item-position'  => $position++,
			) );
		}
	}

	WP_CLI::log( '  Footer Help .... policies + account' );
}

// Assign menus to the theme's registered locations.
set_theme_mod( 'nav_menu_locations', array(
	'primary'     => $primary_id,
	'footer-shop' => $shop_id,
	'footer-help' => $help_id,
) );

WP_CLI::log( '' );
WP_CLI::success( 'Pages and menus configured.' );
