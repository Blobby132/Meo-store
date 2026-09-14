<?php
/**
 * 404 — written in the manifest voice rather than a generic apology.
 *
 * @package MEO
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="meo-wrap meo-wrap--narrow meo-section">
	<div class="meo-section-head">
		<p class="meo-label"><?php esc_html_e( 'Status 404 — not in this consignment', 'meo' ); ?></p>
		<h1><?php esc_html_e( 'That page is not on the manifest.', 'meo' ); ?></h1>
		<p><?php esc_html_e( 'The address exists but nothing is filed against it. It may have been removed, or the link may be mistyped.', 'meo' ); ?></p>
	</div>

	<?php meo_tear_line( 'tight' ); ?>

	<?php get_search_form(); ?>

	<p style="margin-top: var(--meo-space-m);">
		<a class="meo-btn" href="<?php echo esc_url( meo_is_woocommerce_active() ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) ); ?>">
			<?php esc_html_e( 'Back to the catalogue', 'meo' ); ?>
		</a>
	</p>
</div>

<?php
get_footer();
