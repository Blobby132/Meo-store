<?php
/**
 * Asset loading.
 *
 * @package MEO
 */

defined( 'ABSPATH' ) || exit;

/**
 * Google Fonts URL for the three families.
 *
 * Fraunces is loaded as a variable font with the SOFT/WONK axes so
 * `font-variation-settings` in the CSS actually has something to vary.
 *
 * NOTE: this hits fonts.googleapis.com on every page load. Before launch,
 * self-host these (wp-content/themes/meo/assets/fonts/ + @font-face) — it is
 * faster, removes a third-party request, and avoids the GDPR/PIPEDA question
 * of leaking visitor IPs to Google. See docs/design-tokens.md.
 *
 * @return string
 */
function meo_fonts_url() {
	$families = array(
		'Fraunces:opsz,wght,SOFT,WONK@9..144,400..700,0..100,0..1',
		'IBM+Plex+Sans:wght@400;500;600',
		'IBM+Plex+Mono:wght@400;500;600',
	);

	return add_query_arg(
		array(
			'family'  => implode( '&family=', $families ),
			'display' => 'swap',
		),
		'https://fonts.googleapis.com/css2'
	);
}

/**
 * Preconnect to the font hosts so the font request starts earlier.
 *
 * @param array  $urls           URLs to print.
 * @param string $relation_type  Relation being processed.
 * @return array
 */
function meo_resource_hints( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type ) {
		$urls[] = array( 'href' => 'https://fonts.googleapis.com' );
		$urls[] = array(
			'href'        => 'https://fonts.gstatic.com',
			'crossorigin' => 'anonymous',
		);
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'meo_resource_hints', 10, 2 );

/**
 * Enqueue front-end assets.
 *
 * Stylesheets are separate handles rather than one bundle so the cascade
 * order is declared in code (each depends on the previous) and so a single
 * layer can be swapped without touching the others.
 */
function meo_enqueue_assets() {
	wp_enqueue_style( 'meo-fonts', meo_fonts_url(), array(), null );

	$layers = array(
		'meo-tokens'      => array( 'tokens.css', array( 'meo-fonts' ) ),
		'meo-base'        => array( 'base.css', array( 'meo-tokens' ) ),
		'meo-layout'      => array( 'layout.css', array( 'meo-base' ) ),
		'meo-components'  => array( 'components.css', array( 'meo-layout' ) ),
	);

	foreach ( $layers as $handle => $layer ) {
		list( $file, $deps ) = $layer;
		wp_enqueue_style(
			$handle,
			MEO_URI . '/assets/css/' . $file,
			$deps,
			meo_asset_version( '/assets/css/' . $file )
		);
	}

	// WooCommerce layer only where Woo is active, to keep other pages lean.
	if ( meo_is_woocommerce_active() ) {
		wp_enqueue_style(
			'meo-woocommerce',
			MEO_URI . '/assets/css/woocommerce.css',
			array( 'meo-components' ),
			meo_asset_version( '/assets/css/woocommerce.css' )
		);
	}

	// The theme's stylesheet header — registered so child themes can depend on it.
	wp_register_style( 'meo-style', get_stylesheet_uri(), array( 'meo-components' ), MEO_VERSION );

	wp_enqueue_script(
		'meo-theme',
		MEO_URI . '/assets/js/theme.js',
		array(),
		meo_asset_version( '/assets/js/theme.js' ),
		true
	);

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'meo_enqueue_assets' );

/**
 * Cache-busting version for a theme asset.
 *
 * Uses filemtime in local/dev so edits show up without a hard refresh, and the
 * theme version in production so the string is stable across deploys.
 *
 * @param string $relative_path Path relative to the theme root, leading slash.
 * @return string
 */
function meo_asset_version( $relative_path ) {
	$file = MEO_DIR . $relative_path;

	if ( wp_get_environment_type() !== 'production' && file_exists( $file ) ) {
		return (string) filemtime( $file );
	}

	return MEO_VERSION;
}

/**
 * Load the token layer in the block editor too, so editor previews match.
 */
function meo_editor_assets() {
	wp_enqueue_style( 'meo-fonts', meo_fonts_url(), array(), null );
	wp_enqueue_style(
		'meo-editor-tokens',
		MEO_URI . '/assets/css/tokens.css',
		array( 'meo-fonts' ),
		meo_asset_version( '/assets/css/tokens.css' )
	);
}
add_action( 'enqueue_block_editor_assets', 'meo_editor_assets' );
