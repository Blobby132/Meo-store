<?php
/**
 * About / story section.
 *
 * @package MEO
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="meo-section" id="meo-about">
	<div class="meo-wrap">
		<div class="meo-section-head">
			<p class="meo-label"><?php esc_html_e( 'Section 03 — Terms', 'meo' ); ?></p>
			<h2><?php esc_html_e( 'What MEO actually is', 'meo' ); ?></h2>
		</div>

		<div class="meo-about__grid">
			<div class="meo-about__body">
				<p>
					<?php
					esc_html_e(
						'MEO is a small Canadian shop for home and desk goods. We do not hold a warehouse. Orders are placed with vetted suppliers who ship directly to you, which is why our catalogue stays short and our prices stay flat.',
						'meo'
					);
					?>
				</p>
				<p>
					<?php
					esc_html_e(
						'That arrangement has trade-offs and we would rather state them than bury them. Transit runs 6 to 14 business days, not two. An order with items from two suppliers arrives in two parcels, each tracked separately. Where an item ships from outside Canada, any duty or brokerage is calculated and shown at checkout — you will not be billed at the door.',
						'meo'
					);
					?>
				</p>
				<p>
					<?php
					esc_html_e(
						'Every listing states its material, its dimensions and where it ships from. If a detail is missing from a product page, it is missing because we have not confirmed it, and we will say so rather than guess.',
						'meo'
					);
					?>
				</p>
			</div>

			<div class="meo-spec">
				<div class="meo-spec__row">
					<span class="meo-spec__key"><?php esc_html_e( 'Registered in', 'meo' ); ?></span>
					<span class="meo-spec__val"><?php esc_html_e( 'Canada', 'meo' ); ?></span>
				</div>
				<div class="meo-spec__row">
					<span class="meo-spec__key"><?php esc_html_e( 'Currency', 'meo' ); ?></span>
					<span class="meo-spec__val"><?php echo esc_html( meo_is_woocommerce_active() ? get_woocommerce_currency() : 'CAD' ); ?></span>
				</div>
				<div class="meo-spec__row">
					<span class="meo-spec__key"><?php esc_html_e( 'Departments', 'meo' ); ?></span>
					<span class="meo-spec__val"><?php echo esc_html( (string) count( meo_categories() ) ); ?></span>
				</div>
				<div class="meo-spec__row">
					<span class="meo-spec__key"><?php esc_html_e( 'Fulfilment', 'meo' ); ?></span>
					<span class="meo-spec__val"><?php esc_html_e( 'Supplier direct', 'meo' ); ?></span>
				</div>
				<div class="meo-spec__row">
					<span class="meo-spec__key"><?php esc_html_e( 'Transit', 'meo' ); ?></span>
					<span class="meo-spec__val"><?php esc_html_e( '6–14 business days', 'meo' ); ?></span>
				</div>
				<div class="meo-spec__row">
					<span class="meo-spec__key"><?php esc_html_e( 'Returns window', 'meo' ); ?></span>
					<span class="meo-spec__val"><?php esc_html_e( '30 days', 'meo' ); ?></span>
				</div>
				<div class="meo-spec__row">
					<span class="meo-spec__key"><?php esc_html_e( 'Free shipping over', 'meo' ); ?></span>
					<span class="meo-spec__val">$95.00</span>
				</div>
			</div>
		</div>
	</div>
</section>
