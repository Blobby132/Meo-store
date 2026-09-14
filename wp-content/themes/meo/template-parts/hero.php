<?php
/**
 * Homepage hero — headline plus the shipping manifest card.
 *
 * @package MEO
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="meo-hero">
	<div class="meo-wrap meo-hero__grid">
		<div class="meo-hero__copy">
			<p class="meo-hero__kicker"><?php esc_html_e( 'Consignment 001 — now shipping', 'meo' ); ?></p>

			<h1><?php esc_html_e( 'Goods for the kitchen, the desk and the quiet end of the day.', 'meo' ); ?></h1>

			<p class="meo-hero__lede">
				<?php
				esc_html_e(
					'A small, edited catalogue of home and desk objects, shipped to Canadian addresses with tracking on every order. Materials and measurements listed plainly, duties disclosed before you pay.',
					'meo'
				);
				?>
			</p>

			<div class="meo-hero__actions">
				<a class="meo-btn" href="<?php echo esc_url( meo_is_woocommerce_active() ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) ); ?>">
					<?php esc_html_e( 'Browse the catalogue', 'meo' ); ?>
				</a>
				<a class="meo-btn meo-btn--ghost" href="#meo-about">
					<?php esc_html_e( 'How MEO works', 'meo' ); ?>
				</a>
			</div>
		</div>

		<?php
		/*
		 * The manifest card. Values are illustrative brand copy, not a live
		 * order — hence the generic consignment number. `aria-hidden` is NOT
		 * used: the content is readable and meaningful, so it stays in the
		 * accessibility tree as a described region.
		 */
		?>
		<aside class="meo-manifest" aria-label="<?php esc_attr_e( 'Shipping summary', 'meo' ); ?>">
			<div class="meo-manifest__bar">
				<span><?php esc_html_e( 'Shipping manifest', 'meo' ); ?></span>
				<span>MEO-CA-001</span>
			</div>

			<div class="meo-manifest__body">
				<div class="meo-manifest__row">
					<span class="meo-manifest__key"><?php esc_html_e( 'Origin', 'meo' ); ?></span>
					<span class="meo-manifest__val"><?php esc_html_e( 'Supplier warehouse', 'meo' ); ?></span>
				</div>
				<div class="meo-manifest__row">
					<span class="meo-manifest__key"><?php esc_html_e( 'Destination', 'meo' ); ?></span>
					<span class="meo-manifest__val"><?php esc_html_e( 'Canada — all provinces', 'meo' ); ?></span>
				</div>
				<div class="meo-manifest__row">
					<span class="meo-manifest__key"><?php esc_html_e( 'Transit', 'meo' ); ?></span>
					<span class="meo-manifest__val"><?php esc_html_e( '6–14 business days', 'meo' ); ?></span>
				</div>
				<div class="meo-manifest__row">
					<span class="meo-manifest__key"><?php esc_html_e( 'Carrier', 'meo' ); ?></span>
					<span class="meo-manifest__val"><?php esc_html_e( 'Tracked parcel', 'meo' ); ?></span>
				</div>
				<div class="meo-manifest__row">
					<span class="meo-manifest__key"><?php esc_html_e( 'Duties', 'meo' ); ?></span>
					<span class="meo-manifest__val"><strong><?php esc_html_e( 'Disclosed at checkout', 'meo' ); ?></strong></span>
				</div>
				<div class="meo-manifest__row">
					<span class="meo-manifest__key"><?php esc_html_e( 'Returns', 'meo' ); ?></span>
					<span class="meo-manifest__val"><?php esc_html_e( '30 days from delivery', 'meo' ); ?></span>
				</div>

				<div class="meo-manifest__barcode" role="presentation"></div>
				<p class="meo-manifest__barcode-caption">MEO CA 001</p>
			</div>
		</aside>
	</div>
</section>
