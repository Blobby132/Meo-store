<?php
/**
 * Configure Canadian GST/HST in WooCommerce.
 *
 * Run through WP-CLI:
 *   wp eval-file wp-content/meo-scripts/php/configure-tax.php          # rates only
 *   wp eval-file wp-content/meo-scripts/php/configure-tax.php enable   # rates + start charging
 * or, from the repo root:
 *   npm run setup:tax
 *   MEO_TAX_ENABLED=yes npm run setup:tax
 *
 * ===========================================================================
 * TAX COLLECTION IS OFF BY DEFAULT. THAT IS DELIBERATE.
 * ===========================================================================
 * In Canada you may not charge GST/HST until you are registered with the CRA
 * and have a GST/HST number. Collecting it while unregistered is not a
 * paperwork slip — it is collecting money you have no authority to collect.
 *
 * Registration becomes MANDATORY once you stop being a "small supplier":
 *
 *   - Your worldwide taxable revenue exceeds $30,000 CAD over the previous
 *     FOUR consecutive calendar quarters (a rolling window, not a fiscal year);
 *     OR
 *   - It exceeds $30,000 CAD in a SINGLE calendar quarter.
 *
 * The two cases have different deadlines. Cross the threshold within one
 * quarter and you are treated as registered from the moment of the sale that
 * pushed you over, and must start charging on THAT sale. Cross it over four
 * quarters and you have a one-month grace period before registration takes
 * effect. Voluntary registration below the threshold is allowed and is often
 * worth it, because it lets you claim input tax credits on business purchases.
 *
 * This script therefore installs the rate table (harmless while inactive) but
 * leaves `woocommerce_calc_taxes` off until you pass `enable`.
 *
 * Full explanation, including PST/QST/RST which this script does NOT handle:
 *   docs/canadian-tax.md
 *
 * NOT TAX ADVICE. Rates change. Verify against the CRA before you launch.
 * ===========================================================================
 *
 * @package MEO
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WC_Tax' ) ) {
	WP_CLI::error( 'WooCommerce is not active. Run `npm run setup` first.' );
}

/*
 * `enable` may arrive as a WP-CLI argument or as the MEO_TAX_ENABLED env var.
 * $args is provided by `wp eval-file`.
 */
$enable = false;
if ( isset( $args ) && is_array( $args ) && in_array( 'enable', $args, true ) ) {
	$enable = true;
}
if ( 'yes' === getenv( 'MEO_TAX_ENABLED' ) ) {
	$enable = true;
}

/*
 * ---------------------------------------------------------------------------
 * Rate table — verified against CRA published rates as of 2026-09.
 * ---------------------------------------------------------------------------
 * HST provinces charge a single combined rate; the federal 5% is inside it.
 * GST provinces charge 5% federally, and several ALSO levy a separate
 * provincial tax (BC PST 7%, SK PST 6%, MB RST 7%, QC QST 9.975%) which is a
 * different registration with different thresholds and is NOT set up here.
 *
 * Nova Scotia's HST dropped from 15% to 14% on 2025-04-01. Re-check before
 * launch — provincial rates move more often than you would expect.
 */
$rates = array(
	// Harmonized Sales Tax provinces.
	array( 'state' => 'ON', 'rate' => '13.0000', 'name' => 'HST' ),
	array( 'state' => 'NB', 'rate' => '15.0000', 'name' => 'HST' ),
	array( 'state' => 'NL', 'rate' => '15.0000', 'name' => 'HST' ),
	array( 'state' => 'PE', 'rate' => '15.0000', 'name' => 'HST' ),
	array( 'state' => 'NS', 'rate' => '14.0000', 'name' => 'HST' ),

	// GST-only provinces and territories.
	array( 'state' => 'AB', 'rate' => '5.0000', 'name' => 'GST' ),
	array( 'state' => 'BC', 'rate' => '5.0000', 'name' => 'GST' ),
	array( 'state' => 'MB', 'rate' => '5.0000', 'name' => 'GST' ),
	array( 'state' => 'QC', 'rate' => '5.0000', 'name' => 'GST' ),
	array( 'state' => 'SK', 'rate' => '5.0000', 'name' => 'GST' ),
	array( 'state' => 'NT', 'rate' => '5.0000', 'name' => 'GST' ),
	array( 'state' => 'NU', 'rate' => '5.0000', 'name' => 'GST' ),
	array( 'state' => 'YT', 'rate' => '5.0000', 'name' => 'GST' ),
);

global $wpdb;

/*
 * Idempotency: clear any existing Canadian rates in the standard class before
 * inserting, so re-running does not stack duplicate rows (WooCommerce happily
 * applies two 13% ON rates and bills 26%).
 */
$existing = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT tax_rate_id FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_country = %s AND tax_rate_class = %s",
		'CA',
		''
	)
);

if ( $existing ) {
	foreach ( $existing as $rate_id ) {
		WC_Tax::_delete_tax_rate( (int) $rate_id );
	}
	WP_CLI::log( sprintf( 'Cleared %d existing Canadian standard-class rate(s).', count( $existing ) ) );
}

WP_CLI::log( '' );
WP_CLI::log( 'Inserting GST/HST rates' );

$order = 0;
foreach ( $rates as $rate ) {
	WC_Tax::_insert_tax_rate( array(
		'tax_rate_country'  => 'CA',
		'tax_rate_state'    => $rate['state'],
		'tax_rate'          => $rate['rate'],
		'tax_rate_name'     => $rate['name'],
		'tax_rate_priority' => 1,
		'tax_rate_compound' => 0,
		// Shipping is taxable at the same rate in every province.
		'tax_rate_shipping' => 1,
		'tax_rate_order'    => $order++,
		'tax_rate_class'    => '',
	) );

	WP_CLI::log( sprintf( '  CA-%s  %6s%%  %s', $rate['state'], rtrim( rtrim( $rate['rate'], '0' ), '.' ), $rate['name'] ) );
}

/*
 * ---------------------------------------------------------------------------
 * Store + tax display settings
 * ---------------------------------------------------------------------------
 * Canadian retail convention is tax-EXCLUSIVE display: the shelf price is
 * pre-tax and GST/HST is added at checkout. Shoppers expect that, and showing
 * tax-inclusive prices would make MEO look more expensive than competitors at
 * a glance while confusing the invoice.
 */
$settings = array(
	'woocommerce_default_country'          => 'CA:ON',
	'woocommerce_currency'                 => 'CAD',
	'woocommerce_currency_pos'             => 'left',
	'woocommerce_price_thousand_sep'       => ',',
	'woocommerce_price_decimal_sep'        => '.',
	'woocommerce_price_num_decimals'       => '2',

	'woocommerce_prices_include_tax'       => 'no',
	// Tax follows the delivery address — correct for CA, where the rate is the
	// customer's province, not the seller's.
	'woocommerce_tax_based_on'             => 'shipping',
	'woocommerce_shipping_tax_class'       => 'inherit',
	'woocommerce_tax_round_at_subtotal'    => 'no',
	'woocommerce_tax_display_shop'         => 'excl',
	'woocommerce_tax_display_cart'         => 'excl',
	// Itemized so the invoice names "GST" or "HST" rather than a bare "Tax"
	// line — required detail on a compliant Canadian receipt once registered.
	'woocommerce_tax_total_display'        => 'itemized',
	'woocommerce_default_customer_address' => 'base',
);

foreach ( $settings as $key => $value ) {
	update_option( $key, $value );
}

WP_CLI::log( '' );
WP_CLI::log( 'Store settings' );
WP_CLI::log( '  Base country ......... CA:ON' );
WP_CLI::log( '  Currency ............. CAD ($ before amount)' );
WP_CLI::log( '  Prices entered ....... excluding tax' );
WP_CLI::log( '  Tax calculated on .... customer shipping address' );
WP_CLI::log( '  Displayed ............ excluding tax, itemized at checkout' );

/*
 * The switch that actually starts charging tax.
 */
if ( $enable ) {
	update_option( 'woocommerce_calc_taxes', 'yes' );
	WP_CLI::log( '' );
	WP_CLI::success( 'Tax calculation is ENABLED. GST/HST will be charged at checkout.' );
	WP_CLI::warning( 'Only correct if you are registered with the CRA and hold a GST/HST number.' );
	WP_CLI::log( 'Put the number on invoices and in the footer with:' );
	WP_CLI::log( '  wp option update meo_gst_hst_number "12345 6789 RT0001"' );
} else {
	update_option( 'woocommerce_calc_taxes', 'no' );
	WP_CLI::log( '' );
	WP_CLI::success( 'Rate table installed. Tax calculation is OFF.' );
	WP_CLI::log( '' );
	WP_CLI::log( 'You are treated as a small supplier until taxable revenue exceeds' );
	WP_CLI::log( '$30,000 CAD over four rolling quarters, or $30,000 in a single quarter.' );
	WP_CLI::log( 'Until then you must NOT charge GST/HST. Once registered, turn it on with:' );
	WP_CLI::log( '' );
	WP_CLI::log( '  MEO_TAX_ENABLED=yes npm run setup:tax' );
	WP_CLI::log( '' );
	WP_CLI::log( 'Details and the PST/QST/RST gap: docs/canadian-tax.md' );
}
