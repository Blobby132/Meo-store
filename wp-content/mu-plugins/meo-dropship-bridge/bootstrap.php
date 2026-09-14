<?php
/**
 * MEO Dropship Bridge — supplier integration surface.
 *
 * ===========================================================================
 * THIS FILE INTENTIONALLY TALKS TO NO SUPPLIER.
 * ===========================================================================
 *
 * It exists so that choosing a dropshipping connector later is a contained
 * decision. Everything a supplier integration needs to touch — order push,
 * tracking pull, stock sync, product import — is stubbed here behind actions
 * and filters. The theme never calls a supplier API; it reads the data this
 * bridge writes to WooCommerce.
 *
 * Why a must-use plugin rather than theme code:
 *   - Fulfilment must survive a theme change. Orders are not presentation.
 *   - mu-plugins cannot be deactivated from wp-admin by accident.
 *   - It keeps the theme reviewable on its own terms.
 *
 * ---------------------------------------------------------------------------
 * TODO (supplier choice — deferred, see docs/dropshipping-integration.md)
 * ---------------------------------------------------------------------------
 * Pick ONE connector and implement the four methods marked TODO below.
 * Candidates, with the tradeoff that matters for a Canadian store:
 *
 *   AliDropship / DropshipMe  — AliExpress catalogue, cheapest inventory,
 *                               longest transit to Canada (often 15–30 days).
 *                               Conflicts with the "6–14 business days" copy
 *                               currently in the hero and footer.
 *   Spocket                   — has CA/US/EU suppliers, so transit times match
 *                               the storefront copy. Higher unit cost, monthly fee.
 *   Printful / Printify       — print-on-demand only; has Canadian fulfilment.
 *                               Irrelevant unless the catalogue moves to POD.
 *   Custom CSV / REST importer— no vendor lock-in, no monthly fee, but order
 *                               push and tracking pull become yours to maintain.
 *
 * Whichever is chosen, the storefront's shipping and duties copy must be
 * re-checked against its real transit times. Those claims appear in:
 *   - wp-content/themes/meo/template-parts/hero.php      (manifest card)
 *   - wp-content/themes/meo/inc/template-tags.php        (meo_trust_items)
 *   - wp-content/themes/meo/template-parts/about.php     (spec table)
 *   - wp-content/themes/meo/footer.php                   (summary block)
 * ---------------------------------------------------------------------------
 *
 * @package MEO\Dropship
 */

defined( 'ABSPATH' ) || exit;

define( 'MEO_DROPSHIP_VERSION', '0.1.0' );

/**
 * Order meta key holding the supplier's own order reference.
 */
const MEO_DROPSHIP_ORDER_REF_META = '_meo_supplier_order_ref';

/**
 * Product meta key holding the supplier's product identifier.
 */
const MEO_DROPSHIP_PRODUCT_REF_META = '_meo_supplier_product_id';

/**
 * Is a supplier connector wired up yet?
 *
 * Everything downstream checks this, so the bridge is inert until a connector
 * declares itself by returning true from this filter.
 *
 * @return bool
 */
function meo_dropship_connector_active() {
	/**
	 * Flip to true from the connector implementation once it can push orders.
	 *
	 * @param bool $active Default false.
	 */
	return (bool) apply_filters( 'meo_dropship_connector_active', false );
}

/**
 * Push a paid order to the supplier for fulfilment.
 *
 * Fires on `woocommerce_order_status_processing` — that is the point where
 * WooCommerce considers payment captured and stock reduced.
 *
 * TODO: implement against the chosen connector.
 *   1. Map each order line to the supplier's product ID
 *      ( $item->get_product()->get_meta( MEO_DROPSHIP_PRODUCT_REF_META ) ).
 *   2. POST the order + shipping address to the supplier.
 *   3. Store the returned reference on the order:
 *      $order->update_meta_data( MEO_DROPSHIP_ORDER_REF_META, $ref );
 *      $order->save();
 *   4. On failure: DO NOT silently swallow it. Add an order note and leave the
 *      order in processing so a human sees it. A dropship store that loses an
 *      order quietly is worse than one that errors loudly.
 *
 * @param int $order_id WooCommerce order ID.
 */
function meo_dropship_push_order( $order_id ) {
	if ( ! meo_dropship_connector_active() ) {
		return;
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	// Never push the same order twice — this hook can fire more than once.
	if ( $order->get_meta( MEO_DROPSHIP_ORDER_REF_META ) ) {
		return;
	}

	/**
	 * Hand off to the connector.
	 *
	 * @param WC_Order $order The paid order.
	 */
	do_action( 'meo_dropship_push_order', $order );
}
add_action( 'woocommerce_order_status_processing', 'meo_dropship_push_order', 10, 1 );

/**
 * Pull tracking numbers for orders already sent to the supplier.
 *
 * Scheduled hourly via Action Scheduler (bundled with WooCommerce) rather than
 * WP-Cron: Action Scheduler survives traffic lulls and retries failed runs,
 * which WP-Cron does not.
 *
 * TODO: implement — fetch tracking for each order carrying a supplier ref,
 * then write it back with meo_dropship_record_tracking().
 */
function meo_dropship_sync_tracking() {
	if ( ! meo_dropship_connector_active() ) {
		return;
	}

	/**
	 * Connector pulls tracking for in-flight orders.
	 */
	do_action( 'meo_dropship_sync_tracking' );
}
add_action( 'meo_dropship_hourly_sync', 'meo_dropship_sync_tracking' );

/**
 * Record a tracking number against an order and mark it shipped.
 *
 * Connectors should call this rather than writing meta directly, so the
 * customer-facing side stays consistent.
 *
 * @param WC_Order $order    Order to update.
 * @param string   $tracking Carrier tracking number.
 * @param string   $carrier  Carrier name.
 */
function meo_dropship_record_tracking( $order, $tracking, $carrier = '' ) {
	if ( ! $order instanceof WC_Order || '' === $tracking ) {
		return;
	}

	$order->update_meta_data( '_meo_tracking_number', sanitize_text_field( $tracking ) );
	$order->update_meta_data( '_meo_tracking_carrier', sanitize_text_field( $carrier ) );
	$order->add_order_note(
		sprintf(
			/* translators: 1: carrier, 2: tracking number. */
			__( 'Supplier shipped. Carrier: %1$s. Tracking: %2$s', 'meo' ),
			$carrier ? $carrier : __( 'unspecified', 'meo' ),
			$tracking
		),
		1 // Customer-visible note.
	);
	$order->update_status( 'completed' );
	$order->save();
}

/**
 * Sync stock levels from the supplier.
 *
 * TODO: implement. Stock drift is the most common dropshipping failure —
 * selling something the supplier no longer has. Run this at least daily.
 */
function meo_dropship_sync_stock() {
	if ( ! meo_dropship_connector_active() ) {
		return;
	}

	/**
	 * Connector updates stock for supplier-backed products.
	 */
	do_action( 'meo_dropship_sync_stock' );
}
add_action( 'meo_dropship_daily_sync', 'meo_dropship_sync_stock' );

/**
 * Register the recurring sync jobs with Action Scheduler.
 *
 * Guarded on as_has_scheduled_action so repeated admin loads do not stack
 * duplicate schedules.
 */
function meo_dropship_schedule_jobs() {
	if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
		return; // WooCommerce (and therefore Action Scheduler) not loaded yet.
	}

	if ( ! meo_dropship_connector_active() ) {
		return;
	}

	if ( ! as_has_scheduled_action( 'meo_dropship_hourly_sync' ) ) {
		as_schedule_recurring_action( time() + HOUR_IN_SECONDS, HOUR_IN_SECONDS, 'meo_dropship_hourly_sync' );
	}

	if ( ! as_has_scheduled_action( 'meo_dropship_daily_sync' ) ) {
		as_schedule_recurring_action( time() + DAY_IN_SECONDS, DAY_IN_SECONDS, 'meo_dropship_daily_sync' );
	}
}
add_action( 'init', 'meo_dropship_schedule_jobs', 20 );

/**
 * Show the tracking number on the customer's order page.
 *
 * Uses the mono/manifest treatment — tracking numbers are exactly the kind of
 * value the design language sets in IBM Plex Mono.
 *
 * @param WC_Order $order Order being viewed.
 */
function meo_dropship_render_tracking( $order ) {
	$tracking = $order->get_meta( '_meo_tracking_number' );
	if ( ! $tracking ) {
		return;
	}

	$carrier = $order->get_meta( '_meo_tracking_carrier' );
	?>
	<section class="meo-spec" style="margin-top: var(--meo-space-m);">
		<div class="meo-spec__row">
			<span class="meo-spec__key"><?php esc_html_e( 'Carrier', 'meo' ); ?></span>
			<span class="meo-spec__val"><?php echo esc_html( $carrier ? $carrier : __( 'Tracked parcel', 'meo' ) ); ?></span>
		</div>
		<div class="meo-spec__row">
			<span class="meo-spec__key"><?php esc_html_e( 'Tracking', 'meo' ); ?></span>
			<span class="meo-spec__val"><?php echo esc_html( $tracking ); ?></span>
		</div>
	</section>
	<?php
}
add_action( 'woocommerce_order_details_after_order_table', 'meo_dropship_render_tracking' );

/**
 * Remove the seeded placeholder products.
 *
 * Call this once from WP-CLI when the real supplier feed is ready:
 *
 *     wp eval 'meo_dropship_purge_placeholders();'
 *
 * Only touches products carrying `_meo_placeholder`, so anything imported or
 * hand-created is left alone. Deletes permanently (no trash) because these are
 * seed rows, not customer data.
 *
 * @return int Number of products removed.
 */
function meo_dropship_purge_placeholders() {
	$ids = get_posts( array(
		'post_type'      => 'product',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'   => '_meo_placeholder',
				'value' => 'yes',
			),
		),
	) );

	foreach ( $ids as $id ) {
		wp_delete_post( $id, true );
	}

	return count( $ids );
}
