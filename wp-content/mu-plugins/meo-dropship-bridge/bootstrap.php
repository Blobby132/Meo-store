<?php
/**
 * MEO Dropship Bridge — supplier integration surface.
 *
 * ===========================================================================
 * STATUS: scaffolding, proven against fixtures. No real supplier is wired up.
 * ===========================================================================
 *
 * The approved plan (docs/connector-decision.md) is to source directly from
 * Canadian suppliers — Grosche and GFurn to start — rather than a US-based
 * dropship marketplace, because the September 2026 Canada–US surtax applies
 * below the de minimis thresholds and would otherwise break the storefront's
 * promise that duty is never billed on delivery.
 *
 * Wholesale accounts and feed formats are a business-side step still in
 * progress. So everything here is built against a fixture-backed mock adapter
 * (adapters/class-meo-mock-supplier-adapter.php), which exercises the whole
 * path without opening a socket. Adding a real supplier should be: write one
 * adapter class, register it, enable it. See docs/supplier-adapter.md.
 *
 * The bridge is INERT as shipped: no adapter is enabled, so
 * meo_dropship_connector_active() returns false and every entry point returns
 * early. Enabling is a deliberate, per-environment act.
 *
 * Why a must-use plugin:
 *   - Fulfilment must survive a theme change. Orders are not presentation.
 *   - mu-plugins cannot be deactivated from wp-admin by accident.
 *   - It keeps the theme reviewable on its own terms.
 *
 * @package MEO\Dropship
 */

defined( 'ABSPATH' ) || exit;

define( 'MEO_DROPSHIP_VERSION', '0.2.0' );
define( 'MEO_DROPSHIP_DIR', __DIR__ );

/**
 * Order meta key holding the supplier order references.
 *
 * Stores an array of adapter slug => supplier's own reference. An array rather
 * than a scalar because an order can span two suppliers — which is also why the
 * storefront tells customers such an order arrives as two tracked parcels.
 */
const MEO_DROPSHIP_ORDER_REF_META = '_meo_supplier_order_ref';

/**
 * Product meta key holding the supplier's product identifier.
 */
const MEO_DROPSHIP_PRODUCT_REF_META = '_meo_supplier_product_id';

/**
 * Product meta key naming which adapter owns a product.
 *
 * Stock sync needs to know who to ask about a given product, and with more
 * than one supplier the product ID alone does not say.
 */
const MEO_DROPSHIP_SUPPLIER_SLUG_META = '_meo_supplier_slug';

/**
 * Order meta key holding recorded shipments, keyed by adapter slug.
 */
const MEO_DROPSHIP_SHIPMENTS_META = '_meo_supplier_shipments';

/**
 * Option holding the enabled adapter slugs. Empty by default — ships inert.
 */
const MEO_DROPSHIP_ENABLED_OPTION = 'meo_dropship_enabled_adapters';

/**
 * WooCommerce log source for everything this bridge writes.
 */
const MEO_DROPSHIP_LOG_SOURCE = 'meo-dropship';

require_once MEO_DROPSHIP_DIR . '/interface-supplier-adapter.php';
require_once MEO_DROPSHIP_DIR . '/adapters/class-meo-mock-supplier-adapter.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once MEO_DROPSHIP_DIR . '/cli.php';
}

/* -------------------------------------------------------------------------
 * Logging
 * ---------------------------------------------------------------------- */

/**
 * Write to the WooCommerce log (WooCommerce → Status → Logs).
 *
 * Wrapped so callers need not null-check wc_get_logger(), which is unavailable
 * on requests where WooCommerce has not loaded.
 *
 * @param string $message Message.
 * @param string $level   One of WooCommerce's log levels.
 */
function meo_dropship_log( $message, $level = 'info' ) {
	if ( ! function_exists( 'wc_get_logger' ) ) {
		return;
	}

	$logger = wc_get_logger();
	if ( ! $logger ) {
		return;
	}

	$logger->log( $level, $message, array( 'source' => MEO_DROPSHIP_LOG_SOURCE ) );
}

/* -------------------------------------------------------------------------
 * Adapter registry
 * ---------------------------------------------------------------------- */

/**
 * Every adapter the bridge knows about, whether enabled or not.
 *
 * Not statically cached: the filter must stay open to late registration, and
 * constructing these is trivial.
 *
 * @return array<string, MEO_Supplier_Adapter> Keyed by slug.
 */
function meo_dropship_adapters() {
	$adapters = array();

	$mock                            = new MEO_Mock_Supplier_Adapter();
	$adapters[ $mock->get_slug() ]   = $mock;

	/**
	 * Register additional supplier adapters.
	 *
	 * A real supplier is added here:
	 *
	 *     add_filter( 'meo_dropship_adapters', function ( $adapters ) {
	 *         $grosche = new MEO_Grosche_Supplier_Adapter();
	 *         $adapters[ $grosche->get_slug() ] = $grosche;
	 *         return $adapters;
	 *     } );
	 *
	 * @param array<string, MEO_Supplier_Adapter> $adapters Keyed by slug.
	 */
	$adapters = apply_filters( 'meo_dropship_adapters', $adapters );

	// Drop anything that does not honour the contract rather than fataling later.
	foreach ( $adapters as $slug => $adapter ) {
		if ( ! $adapter instanceof MEO_Supplier_Adapter ) {
			unset( $adapters[ $slug ] );
			meo_dropship_log( sprintf( 'Ignoring "%s": not a MEO_Supplier_Adapter.', $slug ), 'warning' );
		}
	}

	return $adapters;
}

/**
 * One adapter by slug, enabled or not.
 *
 * Deliberately looks at all registered adapters, not just enabled ones: an
 * order already pushed to a supplier still needs its tracking pulled even if
 * that supplier has since been switched off.
 *
 * @param string $slug Adapter slug.
 * @return MEO_Supplier_Adapter|null
 */
function meo_dropship_get_adapter( $slug ) {
	$adapters = meo_dropship_adapters();

	return isset( $adapters[ $slug ] ) ? $adapters[ $slug ] : null;
}

/**
 * Slugs of the adapters currently switched on.
 *
 * @return array<int, string>
 */
function meo_dropship_enabled_adapter_slugs() {
	$slugs = get_option( MEO_DROPSHIP_ENABLED_OPTION, array() );

	if ( ! is_array( $slugs ) ) {
		$slugs = array();
	}

	return array_values( array_filter( array_map( 'sanitize_key', $slugs ) ) );
}

/**
 * The adapters currently switched on AND registered.
 *
 * @return array<string, MEO_Supplier_Adapter>
 */
function meo_dropship_enabled_adapters() {
	$all     = meo_dropship_adapters();
	$enabled = array();

	foreach ( meo_dropship_enabled_adapter_slugs() as $slug ) {
		if ( isset( $all[ $slug ] ) ) {
			$enabled[ $slug ] = $all[ $slug ];
		}
	}

	return $enabled;
}

/**
 * Is a supplier connector wired up yet?
 *
 * Everything downstream checks this, so the bridge is inert until an adapter
 * is explicitly enabled:
 *
 *     wp meo-dropship enable mock
 *
 * @return bool
 */
function meo_dropship_connector_active() {
	$active = array() !== meo_dropship_enabled_adapters();

	/**
	 * Override whether the bridge is live.
	 *
	 * @param bool $active True when at least one adapter is enabled.
	 */
	return (bool) apply_filters( 'meo_dropship_connector_active', $active );
}

/* -------------------------------------------------------------------------
 * Order reference and shipment accessors
 * ---------------------------------------------------------------------- */

/**
 * Supplier order references recorded on an order.
 *
 * Tolerates the pre-0.2.0 scalar form so an order pushed under the old shape
 * is not re-pushed after an upgrade — double-shipping a customer is exactly
 * the sort of thing a migration should not cause.
 *
 * @param WC_Order $order Order.
 * @return array<string, string> Adapter slug => supplier reference.
 */
function meo_dropship_get_order_refs( $order ) {
	$refs = $order->get_meta( MEO_DROPSHIP_ORDER_REF_META );

	if ( is_array( $refs ) ) {
		return $refs;
	}

	if ( is_string( $refs ) && '' !== $refs ) {
		return array( 'legacy' => $refs );
	}

	return array();
}

/**
 * Shipments recorded against an order.
 *
 * @param WC_Order $order Order.
 * @return array<string, array<string, string>> Adapter slug => shipment.
 */
function meo_dropship_get_shipments( $order ) {
	$shipments = $order->get_meta( MEO_DROPSHIP_SHIPMENTS_META );

	return is_array( $shipments ) ? $shipments : array();
}

/**
 * Group an order's line items by the adapter that owns them.
 *
 * Items without supplier meta — hand-created products, or the seeded
 * placeholders — are skipped rather than treated as an error. A store can
 * legitimately sell both.
 *
 * @param WC_Order $order Order.
 * @return array<string, array<int, WC_Order_Item_Product>> Adapter slug => items.
 */
function meo_dropship_group_items_by_adapter( $order ) {
	$groups = array();

	foreach ( $order->get_items() as $item ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			continue;
		}

		$product = $item->get_product();
		if ( ! $product instanceof WC_Product ) {
			continue;
		}

		$slug        = (string) $product->get_meta( MEO_DROPSHIP_SUPPLIER_SLUG_META );
		$supplier_id = (string) $product->get_meta( MEO_DROPSHIP_PRODUCT_REF_META );

		if ( '' === $slug || '' === $supplier_id ) {
			continue;
		}

		$groups[ $slug ][] = $item;
	}

	return $groups;
}

/* -------------------------------------------------------------------------
 * 1. Push order → supplier
 * ---------------------------------------------------------------------- */

/**
 * Push a paid order to its suppliers for fulfilment.
 *
 * Fires on `woocommerce_order_status_processing` — the point where WooCommerce
 * considers payment captured and stock reduced.
 *
 * An order spanning two suppliers is pushed to each separately and records one
 * reference per supplier, so a partial failure leaves the successful half
 * fulfilled and only retries the half that failed.
 *
 * @param int $order_id WooCommerce order ID.
 */
function meo_dropship_push_order( $order_id ) {
	if ( ! meo_dropship_connector_active() ) {
		return;
	}

	$order = wc_get_order( $order_id );
	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$groups = meo_dropship_group_items_by_adapter( $order );
	if ( array() === $groups ) {
		return; // Nothing on this order is supplier-backed.
	}

	$refs   = meo_dropship_get_order_refs( $order );
	$dirty  = false;

	foreach ( $groups as $slug => $items ) {
		// Never push the same order to the same supplier twice — this hook can
		// fire more than once for one order.
		if ( isset( $refs[ $slug ] ) ) {
			continue;
		}

		$adapter = meo_dropship_get_adapter( $slug );

		if ( ! $adapter ) {
			meo_dropship_record_push_failure(
				$order,
				$slug,
				sprintf( 'No adapter is registered for supplier "%s".', $slug )
			);
			continue;
		}

		try {
			$reference = $adapter->push_order( $order, $items );

			if ( ! is_string( $reference ) || '' === trim( $reference ) ) {
				throw new MEO_Supplier_Exception( 'Adapter returned an empty order reference.' );
			}

			$refs[ $slug ] = trim( $reference );
			$dirty         = true;

			$order->add_order_note(
				sprintf(
					/* translators: 1: supplier name, 2: supplier order reference. */
					__( 'Sent to %1$s for fulfilment. Supplier reference: %2$s', 'meo' ),
					$adapter->get_label(),
					$refs[ $slug ]
				)
			);

			meo_dropship_log(
				sprintf( 'Order #%d pushed to %s as %s.', $order->get_id(), $slug, $refs[ $slug ] )
			);
		} catch ( Throwable $e ) {
			/*
			 * Throwable, not just MEO_Supplier_Exception: a TypeError or a
			 * failed HTTP library inside an adapter must not escape and leave
			 * a paid order in limbo with no note explaining why.
			 */
			meo_dropship_record_push_failure( $order, $slug, $e->getMessage(), $adapter );
		}
	}

	if ( $dirty ) {
		$order->update_meta_data( MEO_DROPSHIP_ORDER_REF_META, $refs );
		$order->save();
	}
}
add_action( 'woocommerce_order_status_processing', 'meo_dropship_push_order', 10, 1 );

/**
 * Record a failed push loudly and leave the order alone.
 *
 * Deliberately does NOT change the order status. The order stays in
 * `processing`, which is what the fulfilment queue is filtered on, so a human
 * finds it. A dropship store that loses an order quietly is worse than one
 * that errors loudly.
 *
 * The note is private (customer-invisible): it names internal systems and is
 * addressed to whoever is working the queue, not the buyer.
 *
 * @param WC_Order                  $order   Order that failed to push.
 * @param string                    $slug    Adapter slug.
 * @param string                    $message Failure detail.
 * @param MEO_Supplier_Adapter|null $adapter Adapter, when one was resolved.
 */
function meo_dropship_record_push_failure( $order, $slug, $message, $adapter = null ) {
	$label = $adapter ? $adapter->get_label() : $slug;

	$order->add_order_note(
		sprintf(
			/* translators: 1: supplier name, 2: error detail. */
			__( 'FULFILMENT FAILED — this order was NOT sent to %1$s. %2$s Left in processing for manual handling; do not mark it complete until it has actually been placed.', 'meo' ),
			$label,
			$message
		),
		0 // Private note. The customer must not see internal failure detail.
	);

	meo_dropship_log(
		sprintf( 'PUSH FAILED for order #%d to "%s": %s', $order->get_id(), $slug, $message ),
		'error'
	);

	/**
	 * A push to a supplier failed.
	 *
	 * Hook for alerting — email, Slack, an ops dashboard. Nothing is attached
	 * by default, because an unmonitored alert is worse than none.
	 *
	 * @param WC_Order $order   The order.
	 * @param string   $slug    Adapter slug.
	 * @param string   $message Failure detail.
	 */
	do_action( 'meo_dropship_push_failed', $order, $slug, $message );
}

/* -------------------------------------------------------------------------
 * 2. Pull tracking ← supplier
 * ---------------------------------------------------------------------- */

/**
 * Pull tracking numbers for orders already sent to a supplier.
 *
 * Scheduled hourly via Action Scheduler (bundled with WooCommerce) rather than
 * WP-Cron: Action Scheduler survives traffic lulls and retries failed runs,
 * which WP-Cron does not.
 *
 * @return int Number of shipments recorded.
 */
function meo_dropship_sync_tracking() {
	if ( ! meo_dropship_connector_active() ) {
		return 0;
	}

	/**
	 * How many processing orders one tracking sync examines.
	 *
	 * @param int $limit Default 100.
	 */
	$limit = (int) apply_filters( 'meo_dropship_tracking_batch_size', 100 );

	/*
	 * Queried by status and filtered in PHP rather than with a meta query:
	 * order meta lookups differ between legacy post storage and HPOS, and a
	 * store this size has few open orders. Correctness over cleverness.
	 */
	$orders = wc_get_orders(
		array(
			'status'  => array( 'processing' ),
			'limit'   => $limit,
			'orderby' => 'date',
			'order'   => 'ASC',
		)
	);

	// Adapter slug => [ supplier reference => WC_Order ].
	$awaiting = array();

	foreach ( $orders as $order ) {
		if ( ! $order instanceof WC_Order ) {
			continue;
		}

		$shipments = meo_dropship_get_shipments( $order );

		foreach ( meo_dropship_get_order_refs( $order ) as $slug => $ref ) {
			if ( isset( $shipments[ $slug ] ) ) {
				continue; // Already have tracking for this supplier's parcel.
			}

			$awaiting[ $slug ][ $ref ] = $order;
		}
	}

	$recorded = 0;

	foreach ( $awaiting as $slug => $by_ref ) {
		$adapter = meo_dropship_get_adapter( $slug );
		if ( ! $adapter ) {
			continue;
		}

		try {
			$updates = $adapter->fetch_tracking_updates( array_keys( $by_ref ) );
		} catch ( Throwable $e ) {
			// Transient supplier failure. Log and move on — the next hourly
			// run retries, and no order state has changed.
			meo_dropship_log(
				sprintf( 'Tracking sync failed for "%s": %s', $slug, $e->getMessage() ),
				'error'
			);
			continue;
		}

		if ( ! is_array( $updates ) ) {
			continue;
		}

		foreach ( $updates as $ref => $update ) {
			if ( ! isset( $by_ref[ $ref ] ) || empty( $update['tracking'] ) ) {
				continue;
			}

			meo_dropship_record_tracking(
				$by_ref[ $ref ],
				$update['tracking'],
				isset( $update['carrier'] ) ? $update['carrier'] : '',
				$slug
			);

			++$recorded;
		}
	}

	if ( $recorded > 0 ) {
		meo_dropship_log( sprintf( 'Tracking sync recorded %d shipment(s).', $recorded ) );
	}

	return $recorded;
}
add_action( 'meo_dropship_hourly_sync', 'meo_dropship_sync_tracking' );

/**
 * Record a tracking number against an order.
 *
 * Adapters and manual entry should both go through this rather than writing
 * meta directly, so the customer-facing side stays consistent.
 *
 * The order is only completed once EVERY supplier on it has reported a
 * shipment. Completing a two-supplier order when the first parcel ships would
 * fire the completed-order email while half the order is still sitting at the
 * other supplier.
 *
 * @param WC_Order $order         Order to update.
 * @param string   $tracking      Carrier tracking number.
 * @param string   $carrier       Carrier name.
 * @param string   $supplier_slug Adapter slug, when known.
 */
function meo_dropship_record_tracking( $order, $tracking, $carrier = '', $supplier_slug = '' ) {
	if ( ! $order instanceof WC_Order || '' === trim( (string) $tracking ) ) {
		return;
	}

	$tracking = sanitize_text_field( $tracking );
	$carrier  = sanitize_text_field( $carrier );
	$slug     = $supplier_slug ? sanitize_key( $supplier_slug ) : 'supplier';

	$shipments = meo_dropship_get_shipments( $order );

	// Idempotent: re-reporting the same shipment must not add a second note.
	if ( isset( $shipments[ $slug ]['tracking'] ) && $shipments[ $slug ]['tracking'] === $tracking ) {
		return;
	}

	$shipments[ $slug ] = array(
		'tracking' => $tracking,
		'carrier'  => $carrier,
	);

	$order->update_meta_data( MEO_DROPSHIP_SHIPMENTS_META, $shipments );

	// Keep the single-value keys populated for anything reading them directly.
	if ( ! $order->get_meta( '_meo_tracking_number' ) ) {
		$order->update_meta_data( '_meo_tracking_number', $tracking );
		$order->update_meta_data( '_meo_tracking_carrier', $carrier );
	}

	$order->add_order_note(
		sprintf(
			/* translators: 1: carrier, 2: tracking number. */
			__( 'Supplier shipped. Carrier: %1$s. Tracking: %2$s', 'meo' ),
			$carrier ? $carrier : __( 'unspecified', 'meo' ),
			$tracking
		),
		1 // Customer-visible note.
	);

	$refs = meo_dropship_get_order_refs( $order );

	// No refs means tracking was entered by hand for an order that was never
	// pushed; treat that as complete, matching the pre-0.2.0 behaviour.
	$outstanding = array_diff_key( $refs, $shipments );

	if ( array() === $outstanding ) {
		$order->update_status( 'completed' ); // Saves the pending meta too.
	} else {
		$order->save();

		meo_dropship_log(
			sprintf(
				'Order #%d partially shipped; still awaiting: %s',
				$order->get_id(),
				implode( ', ', array_keys( $outstanding ) )
			)
		);
	}
}

/* -------------------------------------------------------------------------
 * 3. Sync stock ← supplier
 * ---------------------------------------------------------------------- */

/**
 * Sync stock levels from every enabled supplier.
 *
 * Stock drift — selling something the supplier no longer has — is the most
 * common way a dropship store loses money, and it surfaces as refunds and
 * chargebacks rather than as an error in a log. Runs daily.
 *
 * Products absent from a supplier's response are left untouched rather than
 * zeroed, so a truncated or partially-failed feed cannot empty the shop.
 *
 * @return int Number of products whose stock changed.
 */
function meo_dropship_sync_stock() {
	if ( ! meo_dropship_connector_active() ) {
		return 0;
	}

	$changed = 0;

	foreach ( meo_dropship_enabled_adapters() as $slug => $adapter ) {
		try {
			$stock = $adapter->fetch_stock_updates();
		} catch ( Throwable $e ) {
			meo_dropship_log(
				sprintf( 'Stock sync failed for "%s": %s', $slug, $e->getMessage() ),
				'error'
			);
			continue;
		}

		if ( ! is_array( $stock ) || array() === $stock ) {
			continue;
		}

		foreach ( meo_dropship_get_products_for_adapter( $slug ) as $product ) {
			$supplier_id = (string) $product->get_meta( MEO_DROPSHIP_PRODUCT_REF_META );

			if ( '' === $supplier_id || ! array_key_exists( $supplier_id, $stock ) ) {
				continue;
			}

			$qty = (int) $stock[ $supplier_id ];

			if ( $product->get_manage_stock() && (int) $product->get_stock_quantity() === $qty ) {
				continue;
			}

			$product->set_manage_stock( true );
			$product->set_stock_quantity( $qty );
			$product->set_stock_status( $qty > 0 ? 'instock' : 'outofstock' );
			$product->save();

			++$changed;
		}
	}

	if ( $changed > 0 ) {
		wc_delete_product_transients();
		meo_dropship_log( sprintf( 'Stock sync updated %d product(s).', $changed ) );
	}

	return $changed;
}
add_action( 'meo_dropship_daily_sync', 'meo_dropship_sync_stock' );

/**
 * Every product owned by one adapter.
 *
 * @param string $slug Adapter slug.
 * @return array<int, WC_Product>
 */
function meo_dropship_get_products_for_adapter( $slug ) {
	$ids = get_posts(
		array(
			'post_type'      => 'product',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => MEO_DROPSHIP_SUPPLIER_SLUG_META,
					'value' => $slug,
				),
			),
		)
	);

	$products = array();

	foreach ( $ids as $id ) {
		$product = wc_get_product( $id );
		if ( $product instanceof WC_Product ) {
			$products[] = $product;
		}
	}

	return $products;
}

/* -------------------------------------------------------------------------
 * 4. Import products
 * ---------------------------------------------------------------------- */

/**
 * Import one adapter's catalogue into WooCommerce.
 *
 * Not hooked to a schedule — this is a one-off you drive from WP-CLI:
 *
 *     wp meo-dropship import mock
 *
 * Matches on SKU, so re-running updates in place. Imported products carry
 * `_meo_supplier_product_id` and `_meo_supplier_slug`, and deliberately do NOT
 * carry `_meo_placeholder` — real inventory must never be caught by
 * meo_dropship_purge_placeholders().
 *
 * @param string $slug Adapter slug.
 * @return array{created:int,updated:int,errors:array<int,string>}
 */
function meo_dropship_import_products( $slug ) {
	$result = array(
		'created' => 0,
		'updated' => 0,
		'errors'  => array(),
	);

	$adapter = meo_dropship_get_adapter( $slug );

	if ( ! $adapter ) {
		$result['errors'][] = sprintf( 'No adapter registered for "%s".', $slug );
		return $result;
	}

	try {
		$rows = $adapter->import_products();
	} catch ( Throwable $e ) {
		$result['errors'][] = sprintf( 'Feed could not be read: %s', $e->getMessage() );
		meo_dropship_log( sprintf( 'Import failed for "%s": %s', $slug, $e->getMessage() ), 'error' );
		return $result;
	}

	foreach ( $rows as $index => $row ) {
		foreach ( array( 'supplier_product_id', 'sku', 'name', 'price' ) as $required ) {
			if ( empty( $row[ $required ] ) ) {
				$result['errors'][] = sprintf( 'Row %d is missing "%s"; skipped.', $index + 1, $required );
				continue 2;
			}
		}

		$existing_id = wc_get_product_id_by_sku( $row['sku'] );
		$product     = $existing_id ? wc_get_product( $existing_id ) : new WC_Product_Simple();

		if ( ! $product instanceof WC_Product ) {
			$result['errors'][] = sprintf( 'Could not load product for SKU %s; skipped.', $row['sku'] );
			continue;
		}

		$product->set_name( $row['name'] );
		$product->set_sku( $row['sku'] );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product->set_regular_price( (string) $row['price'] );

		if ( isset( $row['description'] ) ) {
			$product->set_description( $row['description'] );
		}
		if ( isset( $row['short_description'] ) ) {
			$product->set_short_description( $row['short_description'] );
		}

		if ( isset( $row['stock_quantity'] ) ) {
			$qty = (int) $row['stock_quantity'];
			$product->set_manage_stock( true );
			$product->set_stock_quantity( $qty );
			$product->set_stock_status( $qty > 0 ? 'instock' : 'outofstock' );
			$product->set_backorders( 'no' );
		}

		foreach ( array( 'weight', 'length', 'width', 'height' ) as $dimension ) {
			if ( ! empty( $row[ $dimension ] ) ) {
				$setter = 'set_' . $dimension;
				$product->{$setter}( (string) $row[ $dimension ] );
			}
		}

		$product->set_tax_status( 'taxable' );
		$product->set_tax_class( '' );

		if ( ! empty( $row['category_slug'] ) ) {
			$term_id = meo_dropship_ensure_category( $row['category_slug'] );
			if ( $term_id ) {
				$product->set_category_ids( array( $term_id ) );
			}
		}

		$product->update_meta_data( MEO_DROPSHIP_PRODUCT_REF_META, (string) $row['supplier_product_id'] );
		$product->update_meta_data( MEO_DROPSHIP_SUPPLIER_SLUG_META, $slug );

		if ( ! empty( $row['cost_price'] ) ) {
			// Kept for margin reporting; never displayed on the storefront.
			$product->update_meta_data( '_meo_supplier_cost', (string) $row['cost_price'] );
		}
		if ( ! empty( $row['ships_from'] ) ) {
			$product->update_meta_data( '_meo_ships_from', (string) $row['ships_from'] );
		}

		/*
		 * Belt and braces: if this SKU was previously a seeded placeholder,
		 * importing real supplier data over it must clear the flag, or the
		 * purge would later delete a live product.
		 */
		$product->delete_meta_data( '_meo_placeholder' );
		$product->delete_meta_data( '_meo_placeholder_note' );

		$product->save();

		if ( $existing_id ) {
			++$result['updated'];
		} else {
			++$result['created'];
		}
	}

	if ( $result['created'] || $result['updated'] ) {
		wc_delete_product_transients();
	}

	meo_dropship_log(
		sprintf(
			'Import "%s": %d created, %d updated, %d error(s).',
			$slug,
			$result['created'],
			$result['updated'],
			count( $result['errors'] )
		)
	);

	return $result;
}

/**
 * Get a product category term ID by slug, creating it if absent.
 *
 * A supplier feed naming a category MEO does not have yet should not silently
 * import uncategorised products.
 *
 * @param string $slug Category slug.
 * @return int Term ID, or 0 on failure.
 */
function meo_dropship_ensure_category( $slug ) {
	$term = get_term_by( 'slug', $slug, 'product_cat' );

	if ( $term && ! is_wp_error( $term ) ) {
		return (int) $term->term_id;
	}

	// Prefer the theme's own label for a known category.
	$name = ucwords( str_replace( '-', ' ', $slug ) );

	if ( function_exists( 'meo_categories' ) ) {
		foreach ( meo_categories() as $category ) {
			if ( $category['slug'] === $slug ) {
				$name = $category['name'];
				break;
			}
		}
	}

	$created = wp_insert_term( $name, 'product_cat', array( 'slug' => $slug ) );

	if ( is_wp_error( $created ) ) {
		meo_dropship_log(
			sprintf( 'Could not create category "%s": %s', $slug, $created->get_error_message() ),
			'error'
		);
		return 0;
	}

	return (int) $created['term_id'];
}

/* -------------------------------------------------------------------------
 * Scheduling
 * ---------------------------------------------------------------------- */

/**
 * Register the recurring sync jobs with Action Scheduler.
 *
 * Guarded on as_has_scheduled_action so repeated admin loads do not stack
 * duplicate schedules. Also unschedules when every adapter is switched off, so
 * a disabled bridge does not leave jobs running against nothing.
 */
function meo_dropship_schedule_jobs() {
	if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
		return; // WooCommerce (and therefore Action Scheduler) not loaded yet.
	}

	$hooks = array(
		'meo_dropship_hourly_sync' => HOUR_IN_SECONDS,
		'meo_dropship_daily_sync'  => DAY_IN_SECONDS,
	);

	if ( ! meo_dropship_connector_active() ) {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			foreach ( array_keys( $hooks ) as $hook ) {
				if ( as_has_scheduled_action( $hook ) ) {
					as_unschedule_all_actions( $hook );
				}
			}
		}
		return;
	}

	foreach ( $hooks as $hook => $interval ) {
		if ( ! as_has_scheduled_action( $hook ) ) {
			as_schedule_recurring_action( time() + $interval, $interval, $hook );
		}
	}
}
add_action( 'init', 'meo_dropship_schedule_jobs', 20 );

/* -------------------------------------------------------------------------
 * Customer-facing output
 * ---------------------------------------------------------------------- */

/**
 * Show tracking numbers on the customer's order page.
 *
 * Uses the mono/manifest treatment — tracking numbers are exactly the kind of
 * value the design language sets in IBM Plex Mono. Renders one block per
 * parcel, because the storefront tells customers a two-supplier order arrives
 * as two separately tracked parcels and the order page has to bear that out.
 *
 * @param WC_Order $order Order being viewed.
 */
function meo_dropship_render_tracking( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$shipments = meo_dropship_get_shipments( $order );

	// Fall back to the single-value keys for orders predating shipment meta.
	if ( array() === $shipments ) {
		$tracking = $order->get_meta( '_meo_tracking_number' );

		if ( ! $tracking ) {
			return;
		}

		$shipments = array(
			'supplier' => array(
				'tracking' => $tracking,
				'carrier'  => $order->get_meta( '_meo_tracking_carrier' ),
			),
		);
	}

	// Drop any shipment without a tracking number BEFORE counting, so "Parcel
	// 1 of 2" can never be printed against a list that renders only one row.
	$shipments = array_filter(
		$shipments,
		static function ( $shipment ) {
			return ! empty( $shipment['tracking'] );
		}
	);

	if ( array() === $shipments ) {
		return;
	}

	$total    = count( $shipments );
	$multiple = $total > 1;
	$index    = 0;
	?>
	<section class="meo-spec" style="margin-top: var(--meo-space-m);">
		<?php foreach ( $shipments as $shipment ) : ?>
			<?php ++$index; ?>
			<?php if ( $multiple ) : ?>
				<div class="meo-spec__row">
					<span class="meo-spec__key">
						<?php
						printf(
							/* translators: 1: parcel number, 2: total parcels. */
							esc_html__( 'Parcel %1$d of %2$d', 'meo' ),
							(int) $index,
							(int) $total
						);
						?>
					</span>
					<span class="meo-spec__val"></span>
				</div>
			<?php endif; ?>

			<div class="meo-spec__row">
				<span class="meo-spec__key"><?php esc_html_e( 'Carrier', 'meo' ); ?></span>
				<span class="meo-spec__val">
					<?php echo esc_html( ! empty( $shipment['carrier'] ) ? $shipment['carrier'] : __( 'Tracked parcel', 'meo' ) ); ?>
				</span>
			</div>

			<div class="meo-spec__row">
				<span class="meo-spec__key"><?php esc_html_e( 'Tracking', 'meo' ); ?></span>
				<span class="meo-spec__val"><?php echo esc_html( $shipment['tracking'] ); ?></span>
			</div>
		<?php endforeach; ?>
	</section>
	<?php
}
add_action( 'woocommerce_order_details_after_order_table', 'meo_dropship_render_tracking' );

/* -------------------------------------------------------------------------
 * Placeholder cleanup
 * ---------------------------------------------------------------------- */

/**
 * Remove the seeded placeholder products.
 *
 * Call this once from WP-CLI when the real supplier feed is in:
 *
 *     wp eval 'echo meo_dropship_purge_placeholders();'
 *
 * Only touches products carrying `_meo_placeholder`, so anything imported by
 * an adapter or created by hand is left alone. Deletes permanently (no trash)
 * because these are seed rows, not customer data.
 *
 * @return int Number of products removed.
 */
function meo_dropship_purge_placeholders() {
	$ids = get_posts(
		array(
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
		)
	);

	foreach ( $ids as $id ) {
		wp_delete_post( $id, true );
	}

	return count( $ids );
}
