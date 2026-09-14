<?php
/**
 * Theme supports, menus and image sizes.
 *
 * @package MEO
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register theme features.
 */
function meo_setup() {
	load_theme_textdomain( 'meo', MEO_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'custom-logo', array(
		'height'      => 48,
		'width'       => 180,
		'flex-height' => true,
		'flex-width'  => true,
	) );
	add_theme_support( 'html5', array(
		'search-form',
		'comment-form',
		'comment-list',
		'gallery',
		'caption',
		'style',
		'script',
	) );

	register_nav_menus( array(
		'primary'      => __( 'Primary navigation', 'meo' ),
		'footer-shop'  => __( 'Footer — Shop', 'meo' ),
		'footer-help'  => __( 'Footer — Help', 'meo' ),
	) );

	/*
	 * WooCommerce. Declaring gallery support here rather than in
	 * inc/woocommerce.php keeps every add_theme_support call in one place.
	 */
	add_theme_support( 'woocommerce', array(
		'thumbnail_image_width' => 600,
		'single_image_width'    => 1000,
		'product_grid'          => array(
			'default_columns' => 3,
			'min_columns'     => 2,
			'max_columns'     => 4,
		),
	) );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
}
add_action( 'after_setup_theme', 'meo_setup' );

/**
 * Content width, used by WordPress for oEmbed sizing.
 */
function meo_content_width() {
	$GLOBALS['content_width'] = 1200;
}
add_action( 'after_setup_theme', 'meo_content_width', 0 );

/**
 * Widget areas.
 */
function meo_widgets_init() {
	register_sidebar( array(
		'name'          => __( 'Shop sidebar', 'meo' ),
		'id'            => 'shop-sidebar',
		'description'   => __( 'Shown on shop and product archive pages.', 'meo' ),
		'before_widget' => '<section id="%1$s" class="meo-widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h3 class="meo-label">',
		'after_title'   => '</h3>',
	) );
}
add_action( 'widgets_init', 'meo_widgets_init' );

/**
 * Add the current colour scheme to <html> before first paint.
 *
 * The toggle stores a preference in localStorage; without this inline script
 * the page would paint in the OS scheme and then flip, which is a visible
 * flash. Printed in wp_head so it runs before <body> exists.
 */
function meo_color_scheme_script() {
	?>
	<script>
	(function () {
		try {
			var stored = localStorage.getItem('meo-theme');
			if (stored === 'dark' || stored === 'light') {
				document.documentElement.setAttribute('data-theme', stored);
			}
		} catch (e) {
			/* Private mode or blocked site data: fall back to prefers-color-scheme. */
		}
	})();
	</script>
	<?php
}
add_action( 'wp_head', 'meo_color_scheme_script', 1 );
