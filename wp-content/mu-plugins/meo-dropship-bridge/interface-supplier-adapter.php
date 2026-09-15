<?php
/**
 * The supplier adapter contract.
 *
 * One adapter per supplier relationship. MEO's approved plan sources directly
 * from Canadian suppliers (Grosche, GFurn — see docs/connector-decision.md),
 * which means more than one feed, each in whatever shape that supplier happens
 * to publish. This interface is the narrow waist between "whatever the supplier
 * sends" and "what WooCommerce needs".
 *
 * Deliberately four methods and no more. The bridge already knows what it
 * needs; this is not a plugin framework.
 *
 * @package MEO\Dropship
 */

defined( 'ABSPATH' ) || exit;

/**
 * Thrown by an adapter when an operation fails.
 *
 * Adapters signal failure by throwing rather than returning WP_Error, because
 * a returned error can be ignored by forgetting to check it. An uncaught
 * throw cannot be. Given that the failure mode here is "a paid order never
 * reaches the supplier", making the failure impossible to overlook is worth
 * departing from the usual WordPress convention.
 */
class MEO_Supplier_Exception extends RuntimeException {}

/**
 * What every supplier adapter must provide.
 */
interface MEO_Supplier_Adapter {

	/**
	 * Machine name, stored on products so stock sync knows who owns what.
	 *
	 * Must be stable for the life of the supplier relationship — changing it
	 * orphans every product already imported under the old slug.
	 *
	 * @return string Lowercase, hyphenated. e.g. 'grosche'.
	 */
	public function get_slug();

	/**
	 * Human-readable name, used in order notes and logs.
	 *
	 * @return string
	 */
	public function get_label();

	/**
	 * Fetch the supplier's catalogue, normalised for WooCommerce.
	 *
	 * Returns rows in the shape documented in docs/supplier-adapter.md. The
	 * adapter's whole job is turning the supplier's format — CSV, JSON, XML,
	 * a paginated REST API — into that one shape. Everything downstream is
	 * shared, so nothing else needs to know what the supplier sends.
	 *
	 * Required keys per row: supplier_product_id, sku, name, price.
	 *
	 * @return array<int, array<string, mixed>>
	 * @throws MEO_Supplier_Exception When the feed cannot be read or parsed.
	 */
	public function import_products();

	/**
	 * Send a paid order to the supplier for fulfilment.
	 *
	 * Only called with line items this adapter actually owns — an order split
	 * across two suppliers is pushed to each separately, which is also why the
	 * storefront tells customers such an order arrives as two parcels.
	 *
	 * MUST be effectively idempotent or the caller's guard must hold: the
	 * bridge will not call this twice for the same (order, adapter) pair, but
	 * a retry after a timeout is still possible.
	 *
	 * @param WC_Order                     $order The paid order.
	 * @param array<int, WC_Order_Item_Product> $items Line items this adapter owns.
	 * @return string Supplier's own order reference. Must be non-empty.
	 * @throws MEO_Supplier_Exception On any failure to place the order.
	 */
	public function push_order( $order, $items );

	/**
	 * Ask the supplier which of these orders have shipped.
	 *
	 * Called on the hourly sync with references this adapter previously
	 * returned from push_order(). Refs the supplier has not shipped yet are
	 * simply omitted from the return — that is not an error.
	 *
	 * @param array<int, string> $order_refs Supplier order references to check.
	 * @return array<string, array<string, string>> Keyed by order ref, each
	 *         value having 'tracking' and optionally 'carrier'.
	 * @throws MEO_Supplier_Exception When the supplier cannot be reached.
	 */
	public function fetch_tracking_updates( $order_refs );

	/**
	 * Current stock levels for this supplier's catalogue.
	 *
	 * Stock drift — selling something the supplier no longer has — is the most
	 * common way a dropship store loses money, and it surfaces as refunds and
	 * chargebacks rather than as an error in a log. This runs daily.
	 *
	 * @return array<string, int> Keyed by supplier_product_id, value is units
	 *         on hand. Products absent from the return are left untouched
	 *         rather than zeroed, so a partial feed cannot empty the shop.
	 * @throws MEO_Supplier_Exception When the supplier cannot be reached.
	 */
	public function fetch_stock_updates();
}
