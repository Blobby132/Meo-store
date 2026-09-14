<?php
/**
 * Site footer — styled as a packing slip.
 *
 * The document numbers here are cosmetic (they render the brand motif, not a
 * real order). Anything that must be accurate — company name, GST/HST number
 * once registered, policy links — is pulled from options or menus.
 *
 * @package MEO
 */

defined( 'ABSPATH' ) || exit;
?>
</main><!-- #meo-main -->

<footer class="meo-footer">
	<div class="meo-footer__slipbar">
		<div class="meo-wrap">
			<span><?php esc_html_e( 'Packing slip — MEO supply co.', 'meo' ); ?></span>
			<span><?php esc_html_e( 'Ships from Canada', 'meo' ); ?></span>
		</div>
	</div>

	<div class="meo-wrap">
		<div class="meo-footer__cols">
			<div class="meo-footer__col meo-footer__about">
				<h3><?php esc_html_e( 'Shipper', 'meo' ); ?></h3>
				<p>
					<?php
					esc_html_e(
						'MEO sources home and desk goods and ships them to Canadian addresses. Small catalogue, plainly described: materials, dimensions and lead times are listed on every product page.',
						'meo'
					);
					?>
				</p>
			</div>

			<div class="meo-footer__col">
				<h3><?php esc_html_e( 'Catalogue', 'meo' ); ?></h3>
				<?php
				if ( has_nav_menu( 'footer-shop' ) ) {
					wp_nav_menu( array(
						'theme_location' => 'footer-shop',
						'container'      => false,
						'depth'          => 1,
						'fallback_cb'    => false,
					) );
				} else {
					echo '<ul>';
					foreach ( meo_categories() as $cat ) {
						printf(
							'<li><a href="%1$s">%2$s</a></li>',
							esc_url( meo_is_woocommerce_active() ? get_term_link( $cat['slug'], 'product_cat' ) : home_url( '/' ) ),
							esc_html( $cat['name'] )
						);
					}
					echo '</ul>';
				}
				?>
			</div>

			<div class="meo-footer__col">
				<h3><?php esc_html_e( 'Help', 'meo' ); ?></h3>
				<?php
				if ( has_nav_menu( 'footer-help' ) ) {
					wp_nav_menu( array(
						'theme_location' => 'footer-help',
						'container'      => false,
						'depth'          => 1,
						'fallback_cb'    => false,
					) );
				} else {
					?>
					<ul>
						<li><a href="<?php echo esc_url( home_url( '/shipping-returns/' ) ); ?>"><?php esc_html_e( 'Shipping &amp; returns', 'meo' ); ?></a></li>
						<li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>"><?php esc_html_e( 'About MEO', 'meo' ); ?></a></li>
						<li><a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>"><?php esc_html_e( 'Privacy', 'meo' ); ?></a></li>
						<?php if ( meo_is_woocommerce_active() ) : ?>
							<li><a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>"><?php esc_html_e( 'Order status', 'meo' ); ?></a></li>
						<?php endif; ?>
					</ul>
					<?php
				}
				?>
			</div>

			<div class="meo-footer__col">
				<h3><?php esc_html_e( 'Summary', 'meo' ); ?></h3>
				<div class="meo-footer__totals">
					<div class="meo-spec__row">
						<span class="meo-spec__key"><?php esc_html_e( 'Currency', 'meo' ); ?></span>
						<span class="meo-spec__val"><?php echo esc_html( meo_is_woocommerce_active() ? get_woocommerce_currency() : 'CAD' ); ?></span>
					</div>
					<div class="meo-spec__row">
						<span class="meo-spec__key"><?php esc_html_e( 'Ships to', 'meo' ); ?></span>
						<span class="meo-spec__val"><?php esc_html_e( 'Canada', 'meo' ); ?></span>
					</div>
					<div class="meo-spec__row">
						<span class="meo-spec__key"><?php esc_html_e( 'Lead time', 'meo' ); ?></span>
						<span class="meo-spec__val"><?php esc_html_e( '6–14 days', 'meo' ); ?></span>
					</div>
					<div class="meo-spec__row">
						<span class="meo-spec__key"><?php esc_html_e( 'Returns', 'meo' ); ?></span>
						<span class="meo-spec__val"><?php esc_html_e( '30 days', 'meo' ); ?></span>
					</div>
					<?php
					/*
					 * GST/HST line.
					 *
					 * Only printed once a registration number is saved, because
					 * showing a blank or fake business number on an invoice-styled
					 * footer would be misleading. Below the $30,000 CAD small-supplier
					 * threshold there is no number to show — see docs/canadian-tax.md.
					 *
					 * Set with:
					 *   wp option update meo_gst_hst_number "12345 6789 RT0001"
					 */
					$gst_number = get_option( 'meo_gst_hst_number', '' );
					if ( $gst_number ) :
						?>
						<div class="meo-spec__row">
							<span class="meo-spec__key"><?php esc_html_e( 'GST/HST no.', 'meo' ); ?></span>
							<span class="meo-spec__val"><?php echo esc_html( $gst_number ); ?></span>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<div class="meo-footer__bottom">
			<span>
				<?php
				printf(
					/* translators: %1$s: year, %2$s: site name. */
					esc_html__( '© %1$s %2$s', 'meo' ),
					esc_html( gmdate( 'Y' ) ),
					esc_html( get_bloginfo( 'name' ) )
				);
				?>
			</span>
			<span><?php esc_html_e( 'Prices in CAD. Taxes calculated at checkout.', 'meo' ); ?></span>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
