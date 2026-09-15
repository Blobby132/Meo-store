<?php
/**
 * WP-CLI commands for the dropship bridge.
 *
 * Loaded only under WP-CLI. These are the operator surface: the bridge has no
 * admin screens, deliberately — it is small enough that a settings page would
 * be more code than the thing it configures.
 *
 *     wp meo-dropship status
 *     wp meo-dropship enable mock
 *     wp meo-dropship import mock
 *     wp meo-dropship push 123
 *     wp meo-dropship sync-tracking
 *     wp meo-dropship sync-stock
 *     wp meo-dropship disable mock
 *
 * @package MEO\Dropship
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Manage MEO's supplier bridge.
 */
class MEO_Dropship_CLI {

	/**
	 * Show which adapters are registered and which are switched on.
	 *
	 * ## EXAMPLES
	 *
	 *     wp meo-dropship status
	 *
	 * @return void
	 */
	public function status() {
		$all     = meo_dropship_adapters();
		$enabled = meo_dropship_enabled_adapter_slugs();

		WP_CLI::log( '' );
		WP_CLI::log( sprintf( 'Bridge active: %s', meo_dropship_connector_active() ? 'YES' : 'no (inert)' ) );
		WP_CLI::log( '' );

		$rows = array();
		foreach ( $all as $slug => $adapter ) {
			$rows[] = array(
				'slug'     => $slug,
				'label'    => $adapter->get_label(),
				'enabled'  => in_array( $slug, $enabled, true ) ? 'yes' : 'no',
				'products' => count( meo_dropship_get_products_for_adapter( $slug ) ),
			);
		}

		if ( $rows ) {
			WP_CLI\Utils\format_items( 'table', $rows, array( 'slug', 'label', 'enabled', 'products' ) );
		} else {
			WP_CLI::log( 'No adapters registered.' );
		}

		$orphans = array_diff( $enabled, array_keys( $all ) );
		if ( $orphans ) {
			WP_CLI::warning(
				sprintf( 'Enabled but not registered: %s', implode( ', ', $orphans ) )
			);
		}

		WP_CLI::log( '' );
	}

	/**
	 * Switch an adapter on.
	 *
	 * ## OPTIONS
	 *
	 * <slug>
	 * : Adapter slug, e.g. mock.
	 *
	 * @param array<int, string> $args Positional args.
	 * @return void
	 */
	public function enable( $args ) {
		$slug = sanitize_key( $args[0] );

		if ( ! meo_dropship_get_adapter( $slug ) ) {
			WP_CLI::error( sprintf( 'No adapter registered for "%s".', $slug ) );
		}

		$enabled = meo_dropship_enabled_adapter_slugs();

		if ( in_array( $slug, $enabled, true ) ) {
			WP_CLI::success( sprintf( '"%s" is already enabled.', $slug ) );
			return;
		}

		$enabled[] = $slug;
		update_option( MEO_DROPSHIP_ENABLED_OPTION, array_values( array_unique( $enabled ) ) );

		WP_CLI::success( sprintf( 'Enabled "%s". The bridge will now push orders to it.', $slug ) );

		if ( 'mock' === $slug ) {
			WP_CLI::warning( 'The mock adapter sends nothing anywhere. Never enable it in production.' );
		}
	}

	/**
	 * Switch an adapter off.
	 *
	 * ## OPTIONS
	 *
	 * <slug>
	 * : Adapter slug.
	 *
	 * @param array<int, string> $args Positional args.
	 * @return void
	 */
	public function disable( $args ) {
		$slug    = sanitize_key( $args[0] );
		$enabled = meo_dropship_enabled_adapter_slugs();

		update_option(
			MEO_DROPSHIP_ENABLED_OPTION,
			array_values( array_diff( $enabled, array( $slug ) ) )
		);

		WP_CLI::success( sprintf( 'Disabled "%s".', $slug ) );
	}

	/**
	 * Import an adapter's catalogue into WooCommerce.
	 *
	 * ## OPTIONS
	 *
	 * <slug>
	 * : Adapter slug.
	 *
	 * @param array<int, string> $args Positional args.
	 * @return void
	 */
	public function import( $args ) {
		$slug   = sanitize_key( $args[0] );
		$result = meo_dropship_import_products( $slug );

		foreach ( $result['errors'] as $error ) {
			WP_CLI::warning( $error );
		}

		if ( $result['errors'] && ! $result['created'] && ! $result['updated'] ) {
			WP_CLI::error( 'Import failed; nothing was written.' );
		}

		WP_CLI::success(
			sprintf( 'Imported from "%s": %d created, %d updated.', $slug, $result['created'], $result['updated'] )
		);
	}

	/**
	 * Push one order to its suppliers now, without waiting for a status change.
	 *
	 * ## OPTIONS
	 *
	 * <order_id>
	 * : WooCommerce order ID.
	 *
	 * @param array<int, string> $args Positional args.
	 * @return void
	 */
	public function push( $args ) {
		$order_id = (int) $args[0];
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			WP_CLI::error( sprintf( 'No such order: %d', $order_id ) );
		}

		meo_dropship_push_order( $order_id );

		// Re-read: push_order writes meta through its own instance.
		$order = wc_get_order( $order_id );
		$refs  = meo_dropship_get_order_refs( $order );

		if ( array() === $refs ) {
			WP_CLI::warning( 'No supplier references recorded. Check the order notes and the log.' );
			return;
		}

		foreach ( $refs as $slug => $ref ) {
			WP_CLI::log( sprintf( '  %-12s %s', $slug, $ref ) );
		}

		WP_CLI::success( sprintf( 'Order #%d pushed.', $order_id ) );
	}

	/**
	 * Run the tracking sync now.
	 *
	 * ## EXAMPLES
	 *
	 *     wp meo-dropship sync-tracking
	 *
	 * @subcommand sync-tracking
	 * @return void
	 */
	public function sync_tracking() {
		if ( ! meo_dropship_connector_active() ) {
			WP_CLI::error( 'Bridge is inert — no adapter enabled. Run: wp meo-dropship enable <slug>' );
		}

		$count = meo_dropship_sync_tracking();

		WP_CLI::success( sprintf( 'Recorded %d shipment(s).', $count ) );
	}

	/**
	 * Run the stock sync now.
	 *
	 * ## EXAMPLES
	 *
	 *     wp meo-dropship sync-stock
	 *
	 * @subcommand sync-stock
	 * @return void
	 */
	public function sync_stock() {
		if ( ! meo_dropship_connector_active() ) {
			WP_CLI::error( 'Bridge is inert — no adapter enabled. Run: wp meo-dropship enable <slug>' );
		}

		$count = meo_dropship_sync_stock();

		WP_CLI::success( sprintf( 'Updated stock on %d product(s).', $count ) );
	}
}

WP_CLI::add_command( 'meo-dropship', 'MEO_Dropship_CLI' );
