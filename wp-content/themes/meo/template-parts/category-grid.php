<?php
/**
 * Category grid — four numbered cards, read as manifest line items.
 *
 * Counts come from the live product_cat terms when WooCommerce is active, so
 * the grid stays honest as the catalogue changes.
 *
 * @package MEO
 */

defined( 'ABSPATH' ) || exit;

$meo_cats = meo_categories();
?>
<section class="meo-section" id="meo-categories">
	<div class="meo-wrap">
		<div class="meo-section-head">
			<p class="meo-label"><?php esc_html_e( 'Section 01 — Contents', 'meo' ); ?></p>
			<h2><?php esc_html_e( 'Four departments', 'meo' ); ?></h2>
			<p><?php esc_html_e( 'The whole catalogue, sorted the way it ships.', 'meo' ); ?></p>
		</div>

		<div class="meo-cats">
			<?php foreach ( $meo_cats as $index => $cat ) : ?>
				<?php
				$term  = meo_is_woocommerce_active() ? get_term_by( 'slug', $cat['slug'], 'product_cat' ) : null;
				$link  = ( $term && ! is_wp_error( $term ) ) ? get_term_link( $term ) : home_url( '/' );
				$count = ( $term && ! is_wp_error( $term ) ) ? (int) $term->count : 0;

				if ( is_wp_error( $link ) ) {
					$link = home_url( '/' );
				}
				?>
				<a class="meo-cat" href="<?php echo esc_url( $link ); ?>">
					<span class="meo-cat__index">
						<?php
						printf(
							/* translators: 1: zero-padded line number, 2: two-letter department code. */
							esc_html__( 'LINE %1$s / %2$s', 'meo' ),
							esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ),
							esc_html( $cat['code'] )
						);
						?>
					</span>

					<h3 class="meo-cat__name"><?php echo esc_html( $cat['name'] ); ?></h3>
					<p class="meo-cat__desc"><?php echo esc_html( $cat['desc'] ); ?></p>

					<span class="meo-cat__count">
						<?php
						if ( $count > 0 ) {
							/* translators: %d: number of products in the category. */
							printf( esc_html( _n( '%d item', '%d items', $count, 'meo' ) ), esc_html( (string) $count ) );
						} else {
							esc_html_e( 'Restocking', 'meo' );
						}
						?>
					</span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
