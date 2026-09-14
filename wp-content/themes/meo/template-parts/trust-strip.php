<?php
/**
 * Trust strip — tracked shipping / duties disclosed / 30-day returns.
 *
 * @package MEO
 */

defined( 'ABSPATH' ) || exit;

$meo_trust_items = meo_trust_items();
?>
<section class="meo-trust" aria-label="<?php esc_attr_e( 'Shipping, duties and returns', 'meo' ); ?>">
	<div class="meo-wrap meo-trust__grid">
		<?php foreach ( $meo_trust_items as $index => $item ) : ?>
			<?php if ( $index > 0 ) : ?>
				<div class="meo-tear-v" role="presentation"></div>
			<?php endif; ?>

			<div class="meo-trust__item">
				<svg class="meo-trust__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"
					stroke-linecap="round" stroke-linejoin="round">
					<?php
					// meo_icon() returns a full <svg>; here we need just the paths,
					// so the wrapper is written inline to carry the sizing class.
					echo wp_kses(
						preg_replace( '#</?svg[^>]*>#', '', meo_icon( $item['icon'] ) ),
						array(
							'path'   => array( 'd' => true ),
							'circle' => array( 'cx' => true, 'cy' => true, 'r' => true ),
						)
					);
					?>
				</svg>

				<div>
					<h3><?php echo esc_html( $item['title'] ); ?></h3>
					<p><?php echo esc_html( $item['body'] ); ?></p>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</section>
