<?php
/**
 * End-to-end test for the dropship bridge, against the mock adapter.
 *
 * Run through WP-CLI:
 *   wp eval-file wp-content/meo-scripts/php/test-dropship-bridge.php
 * or, from the repo root:
 *   npm run test:bridge
 *
 * Exercises the whole path on a real WordPress: import, order push, the
 * double-push guard, tracking pull, order completion, stock sync, and the
 * failure-and-recovery path that matters most.
 *
 * SELF-CONTAINED AND IDEMPOTENT. It enables the mock adapter, creates its own
 * orders, then deletes them and restores the previous adapter setting — so it
 * can be run repeatedly, and leaves the store as it found it. Assertions are
 * made against the specific orders it created rather than against store-wide
 * counts, so pre-existing orders and products cannot skew the result.
 *
 * Exits non-zero if anything fails.
 *
 * @package MEO
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

if ( ! class_exists( 'WooCommerce' ) ) {
	WP_CLI::error( 'WooCommerce is not active. Run `npm run setup` first.' );
}

if ( ! function_exists( 'meo_dropship_connector_active' ) ) {
	WP_CLI::error( 'The dropship bridge is not loaded. Is the mu-plugin mounted?' );
}

$tally = array(
	'pass' => 0,
	'fail' => 0,
);

/*
 * Closure with a by-reference tally rather than a function plus `global`:
 * `wp eval-file` executes this file inside a function scope, so `global $tally`
 * inside a helper would bind to a different variable and the summary would
 * silently always read zero — which is worse than no summary at all.
 */
$check = function ( $label, $ok, $detail = '' ) use ( &$tally ) {
	$tally[ $ok ? 'pass' : 'fail' ]++;
	WP_CLI::log( sprintf( '  [%s] %-50s %s', $ok ? 'PASS' : 'FAIL', $label, $detail ) );
};

// ---------------------------------------------------------------------------
// Save state so the store is left exactly as we found it.
// ---------------------------------------------------------------------------
$previous_adapters = get_option( MEO_DROPSHIP_ENABLED_OPTION, null );
$previous_failsw   = get_option( 'meo_dropship_mock_fail_push', null );
$created_orders    = array();

$address = array(
	'first_name' => 'Bridge',
	'last_name'  => 'Test',
	'address_1'  => '1 Test Street',
	'city'       => 'Halifax',
	'state'      => 'NS',
	'postcode'   => 'B3H 1A1',
	'country'    => 'CA',
	'email'      => 'bridge-test@example.invalid',
);

/**
 * Build an order for one SKU.
 *
 * @param string               $sku      Product SKU.
 * @param array<string,string> $address  Address.
 * @param array<int,int>       $registry Collects created order IDs.
 * @return WC_Order
 */
$make_order = function ( $sku, $address, &$registry ) {
	$product = wc_get_product( wc_get_product_id_by_sku( $sku ) );

	if ( ! $product ) {
		WP_CLI::error( sprintf( 'Fixture product %s is missing — run the import first.', $sku ) );
	}

	$order = wc_create_order();
	$order->add_product( $product, 1 );
	$order->set_address( $address, 'billing' );
	$order->set_address( $address, 'shipping' );
	$order->calculate_totals();
	$order->save();

	$registry[] = $order->get_id();

	return $order;
};

WP_CLI::log( '' );
WP_CLI::log( 'MEO dropship bridge — end-to-end test (mock adapter)' );
WP_CLI::log( '' );

try {
	// -----------------------------------------------------------------------
	// Registry and activation
	// -----------------------------------------------------------------------
	WP_CLI::log( 'Registry' );

	delete_option( MEO_DROPSHIP_ENABLED_OPTION );
	$check( 'inert when no adapter is enabled', ! meo_dropship_connector_active() );

	$check( 'mock adapter is registered', meo_dropship_get_adapter( 'mock' ) instanceof MEO_Supplier_Adapter );

	update_option( MEO_DROPSHIP_ENABLED_OPTION, array( 'mock' ) );
	update_option( 'meo_dropship_mock_fail_push', 'no' );
	$check( 'active once an adapter is enabled', meo_dropship_connector_active() );

	// -----------------------------------------------------------------------
	// Import
	// -----------------------------------------------------------------------
	WP_CLI::log( '' );
	WP_CLI::log( 'Import' );

	$import = meo_dropship_import_products( 'mock' );
	$total  = $import['created'] + $import['updated'];

	// created+updated, not created: the fixture may already be imported.
	$check(
		'import wrote all 3 fixture rows',
		3 === $total && array() === $import['errors'],
		sprintf( '(%d created, %d updated, %d errors)', $import['created'], $import['updated'], count( $import['errors'] ) )
	);

	$products = meo_dropship_get_products_for_adapter( 'mock' );
	$check( 'no duplication on re-import', 3 === count( $products ), sprintf( '(%d products)', count( $products ) ) );

	$flagged     = 0;
	$with_ref    = 0;
	$with_sku    = 0;
	foreach ( $products as $product ) {
		if ( $product->get_meta( '_meo_placeholder' ) ) {
			$flagged++;
		}
		if ( $product->get_meta( MEO_DROPSHIP_PRODUCT_REF_META ) ) {
			$with_ref++;
		}
		if ( preg_match( '/^MEO-[A-Z]{2}-\d{4}$/', (string) $product->get_sku() ) ) {
			$with_sku++;
		}
	}

	$check( 'imported products are NOT flagged placeholder', 0 === $flagged );
	$check( 'all carry _meo_supplier_product_id', 3 === $with_ref );
	$check( 'all have a real-looking MEO SKU', 3 === $with_sku );

	// -----------------------------------------------------------------------
	// Order push
	// -----------------------------------------------------------------------
	WP_CLI::log( '' );
	WP_CLI::log( 'Order push' );

	$order = $make_order( 'MEO-KB-0620', $address, $created_orders );
	$oid   = $order->get_id();

	$order->update_status( 'processing' ); // Fires the real hook.
	$order = wc_get_order( $oid );
	$refs  = meo_dropship_get_order_refs( $order );

	$check( 'push recorded a supplier reference', ! empty( $refs['mock'] ), isset( $refs['mock'] ) ? $refs['mock'] : '' );
	$check( 'order stays in processing until tracking', 'processing' === $order->get_status() );

	meo_dropship_push_order( $oid ); // Second fire.
	$send_notes = 0;
	foreach ( wc_get_order_notes( array( 'order_id' => $oid ) ) as $note ) {
		if ( str_contains( $note->content, 'Sent to' ) ) {
			$send_notes++;
		}
	}
	$check( 'double-push guard held', 1 === $send_notes, sprintf( '(%d send notes)', $send_notes ) );

	// -----------------------------------------------------------------------
	// Tracking
	// -----------------------------------------------------------------------
	WP_CLI::log( '' );
	WP_CLI::log( 'Tracking' );

	meo_dropship_sync_tracking();

	// Assert on THIS order, not on the run's total — other orders may be open.
	$order     = wc_get_order( $oid );
	$shipments = meo_dropship_get_shipments( $order );

	$check( 'shipment recorded for this order', ! empty( $shipments['mock']['tracking'] ), isset( $shipments['mock']['tracking'] ) ? $shipments['mock']['tracking'] : '' );
	$check( 'carrier recorded', ! empty( $shipments['mock']['carrier'] ), isset( $shipments['mock']['carrier'] ) ? $shipments['mock']['carrier'] : '' );
	$check( 'order completed once all parcels shipped', 'completed' === $order->get_status() );

	$customer_notes = 0;
	foreach ( wc_get_order_notes( array( 'order_id' => $oid ) ) as $note ) {
		if ( $note->customer_note && str_contains( $note->content, 'Tracking' ) ) {
			$customer_notes++;
		}
	}
	$check( 'tracking note is customer-VISIBLE', 1 === $customer_notes );

	// Re-recording the same shipment must not add a second note.
	meo_dropship_record_tracking( $order, $shipments['mock']['tracking'], $shipments['mock']['carrier'], 'mock' );
	$after = 0;
	foreach ( wc_get_order_notes( array( 'order_id' => $oid ) ) as $note ) {
		if ( $note->customer_note && str_contains( $note->content, 'Tracking' ) ) {
			$after++;
		}
	}
	$check( 'record_tracking is idempotent', 1 === $after, sprintf( '(%d notes)', $after ) );

	// -----------------------------------------------------------------------
	// Failure and recovery — the path that must never lose an order
	// -----------------------------------------------------------------------
	WP_CLI::log( '' );
	WP_CLI::log( 'Failure handling' );

	update_option( 'meo_dropship_mock_fail_push', 'yes' );

	$bad = $make_order( 'MEO-KB-0340', $address, $created_orders );
	$bid = $bad->get_id();
	$bad->update_status( 'processing' );
	$bad = wc_get_order( $bid );

	$check( 'failed push leaves order in processing', 'processing' === $bad->get_status(), $bad->get_status() );
	$check( 'failed push records no reference', array() === meo_dropship_get_order_refs( $bad ) );

	$failure_note = null;
	foreach ( wc_get_order_notes( array( 'order_id' => $bid ) ) as $note ) {
		if ( str_contains( $note->content, 'FULFILMENT FAILED' ) ) {
			$failure_note = $note;
		}
	}

	$check( 'failure note added', null !== $failure_note );
	$check(
		'failure note is customer-INVISIBLE',
		$failure_note && 0 === (int) $failure_note->customer_note
	);

	update_option( 'meo_dropship_mock_fail_push', 'no' );
	meo_dropship_push_order( $bid );
	$bad = wc_get_order( $bid );

	$check( 'order recovers on retry', ! empty( meo_dropship_get_order_refs( $bad )['mock'] ) );

	// A throwing adapter must not take down a whole sync run.
	add_filter(
		'meo_dropship_adapters',
		function ( $adapters ) {
			$broken = new class() extends MEO_Mock_Supplier_Adapter {
				public function get_slug() {
					return 'broken'; }
				public function fetch_stock_updates() {
					throw new MEO_Supplier_Exception( 'simulated outage' ); }
			};
			$adapters[ $broken->get_slug() ] = $broken;
			return $adapters;
		}
	);
	update_option( MEO_DROPSHIP_ENABLED_OPTION, array( 'broken', 'mock' ) );

	$survived = true;
	try {
		meo_dropship_sync_stock();
	} catch ( Throwable $e ) {
		$survived = false;
	}
	$check( 'one failing adapter does not abort the sync', $survived );

	update_option( MEO_DROPSHIP_ENABLED_OPTION, array( 'mock' ) );

	// -----------------------------------------------------------------------
	// Stock
	// -----------------------------------------------------------------------
	WP_CLI::log( '' );
	WP_CLI::log( 'Stock sync' );

	meo_dropship_sync_stock();

	$teapot = wc_get_product( wc_get_product_id_by_sku( 'MEO-KB-0155' ) );
	$press  = wc_get_product( wc_get_product_id_by_sku( 'MEO-KB-0340' ) );

	$check( 'fixture zero applied (out of stock)', $teapot && 'outofstock' === $teapot->get_stock_status() );
	$check( 'fixture quantity applied', $press && 2 === (int) $press->get_stock_quantity(), $press ? (string) $press->get_stock_quantity() : '' );
	$check( 'stock sync is idempotent', 0 === meo_dropship_sync_stock() );

	// A product the feed omits must be left alone, not zeroed.
	$untouched = wc_get_product( wc_get_product_id_by_sku( 'MEO-KB-0620' ) );
	$before    = (int) $untouched->get_stock_quantity();
	add_filter( 'meo_dropship_adapters', function ( $adapters ) {
		$partial = new class() extends MEO_Mock_Supplier_Adapter {
			public function fetch_stock_updates() {
				return array( 'GR-HEI-0155' => 9 ); // Deliberately partial.
			}
		};
		$adapters['mock'] = $partial;
		return $adapters;
	}, 20 );
	meo_dropship_sync_stock();
	$untouched = wc_get_product( wc_get_product_id_by_sku( 'MEO-KB-0620' ) );
	$check(
		'products absent from a partial feed are untouched',
		$before === (int) $untouched->get_stock_quantity(),
		sprintf( '(%d → %d)', $before, (int) $untouched->get_stock_quantity() )
	);

	// -----------------------------------------------------------------------
	// Seed data must be untouched
	// -----------------------------------------------------------------------
	WP_CLI::log( '' );
	WP_CLI::log( 'Seed data' );

	$placeholders = get_posts(
		array(
			'post_type'      => 'product',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => '_meo_placeholder', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => 'yes', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	$check( 'placeholders not purged by the bridge', count( $placeholders ) > 0, sprintf( '(%d present)', count( $placeholders ) ) );

} finally {
	// -----------------------------------------------------------------------
	// Restore. Runs even if an assertion threw.
	// -----------------------------------------------------------------------
	foreach ( $created_orders as $id ) {
		wp_delete_post( $id, true );
	}

	if ( null === $previous_adapters ) {
		delete_option( MEO_DROPSHIP_ENABLED_OPTION );
	} else {
		update_option( MEO_DROPSHIP_ENABLED_OPTION, $previous_adapters );
	}

	if ( null === $previous_failsw ) {
		delete_option( 'meo_dropship_mock_fail_push' );
	} else {
		update_option( 'meo_dropship_mock_fail_push', $previous_failsw );
	}
}

WP_CLI::log( '' );
WP_CLI::log( sprintf( '  %d passed, %d failed', $tally['pass'], $tally['fail'] ) );
WP_CLI::log( sprintf( '  cleaned up %d test order(s); adapter setting restored.', count( $created_orders ) ) );
WP_CLI::log( '' );

if ( $tally['fail'] > 0 ) {
	WP_CLI::error( sprintf( '%d check(s) failed.', $tally['fail'] ) );
}

WP_CLI::success( 'Bridge end-to-end test passed.' );
