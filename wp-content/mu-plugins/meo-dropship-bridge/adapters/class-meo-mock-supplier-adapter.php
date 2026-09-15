<?php
/**
 * Mock supplier adapter — reads committed fixtures, sends nothing anywhere.
 *
 * Exists so the whole bridge can be proven end to end before a real wholesale
 * account exists. It is shaped like the Grosche feed we expect (a CSV of
 * kitchenware with wholesale and MSRP columns, shipping from Ontario), so the
 * real adapter should be mostly a matter of swapping where the rows come from.
 *
 * It never opens a socket. push_order() writes what it WOULD have sent to the
 * WooCommerce log and returns a plausible reference.
 *
 * @package MEO\Dropship
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fixture-backed adapter used for development and testing.
 */
class MEO_Mock_Supplier_Adapter implements MEO_Supplier_Adapter {

	/**
	 * Where the fixtures live.
	 *
	 * @var string
	 */
	private $fixture_dir;

	/**
	 * Map of MEO category slug to the two-letter code used in SKUs.
	 *
	 * @var array<string, string>
	 */
	private $category_codes = array(
		'kitchen-bar'   => 'KB',
		'desk-study'    => 'DS',
		'home-textiles' => 'HT',
		'lighting'      => 'LT',
	);

	/**
	 * Constructor.
	 *
	 * @param string $fixture_dir Optional override, for tests.
	 */
	public function __construct( $fixture_dir = '' ) {
		$this->fixture_dir = $fixture_dir ? rtrim( $fixture_dir, '/' ) : __DIR__ . '/../fixtures';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function get_slug() {
		return 'mock';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Mock supplier (fixtures)', 'meo' );
	}

	/**
	 * Parse the fixture CSV into normalised product rows.
	 *
	 * @return array<int, array<string, mixed>>
	 * @throws MEO_Supplier_Exception When the fixture is missing or unreadable.
	 */
	public function import_products() {
		$path = $this->fixture_dir . '/mock-products.csv';
		$rows = $this->read_csv( $path );

		$products = array();

		foreach ( $rows as $line => $row ) {
			foreach ( array( 'supplier_sku', 'title', 'msrp_cad' ) as $required ) {
				if ( empty( $row[ $required ] ) ) {
					throw new MEO_Supplier_Exception(
						sprintf( 'mock-products.csv row %d is missing "%s".', $line + 2, $required )
					);
				}
			}

			$category = isset( $row['category'] ) ? $row['category'] : 'kitchen-bar';

			$products[] = array(
				'supplier_product_id' => $row['supplier_sku'],
				'sku'                 => $this->derive_sku( $row['supplier_sku'], $category ),
				'name'                => $row['title'],
				'short_description'   => isset( $row['short_description'] ) ? $row['short_description'] : '',
				'description'         => isset( $row['description'] ) ? $row['description'] : '',
				/*
				 * Retail price is the supplier's MSRP. A real adapter may want
				 * margin logic instead; that belongs behind this filter rather
				 * than inside the import loop, so pricing policy stays in one
				 * place across suppliers.
				 */
				'price'               => $this->retail_price( $row ),
				'cost_price'          => isset( $row['wholesale_price_cad'] ) ? $row['wholesale_price_cad'] : '',
				'stock_quantity'      => isset( $row['stock'] ) ? (int) $row['stock'] : 0,
				'weight'              => isset( $row['weight_kg'] ) ? $row['weight_kg'] : '',
				'length'              => isset( $row['length_cm'] ) ? $row['length_cm'] : '',
				'width'               => isset( $row['width_cm'] ) ? $row['width_cm'] : '',
				'height'              => isset( $row['height_cm'] ) ? $row['height_cm'] : '',
				'category_slug'       => $category,
				'ships_from'          => isset( $row['ships_from'] ) ? $row['ships_from'] : '',
			);
		}

		return $products;
	}

	/**
	 * Pretend to place an order, and log exactly what would have been sent.
	 *
	 * @param WC_Order                         $order The paid order.
	 * @param array<int, WC_Order_Item_Product> $items Line items this adapter owns.
	 * @return string Generated supplier reference.
	 * @throws MEO_Supplier_Exception When the failure switch is on, or no items map.
	 */
	public function push_order( $order, $items ) {
		/*
		 * Test hook for the failure path. Order loss is the worst thing this
		 * bridge can do, so the recovery behaviour needs to be exercisable on
		 * demand rather than only when something genuinely breaks:
		 *
		 *   wp option update meo_dropship_mock_fail_push yes
		 */
		if ( 'yes' === get_option( 'meo_dropship_mock_fail_push', 'no' ) ) {
			throw new MEO_Supplier_Exception(
				'Mock supplier: simulated failure (meo_dropship_mock_fail_push is on).'
			);
		}

		if ( empty( $items ) ) {
			throw new MEO_Supplier_Exception( 'Mock supplier: no line items to push.' );
		}

		$lines = array();
		foreach ( $items as $item ) {
			$product = $item->get_product();

			$lines[] = array(
				'supplier_product_id' => $product ? $product->get_meta( MEO_DROPSHIP_PRODUCT_REF_META ) : '',
				'sku'                 => $product ? $product->get_sku() : '',
				'name'                => $item->get_name(),
				'quantity'            => $item->get_quantity(),
			);
		}

		$payload = array(
			'supplier'        => $this->get_slug(),
			'store_order_id'  => $order->get_id(),
			'store_order_key' => $order->get_order_number(),
			'currency'        => $order->get_currency(),
			'placed_at'       => $order->get_date_created() ? $order->get_date_created()->format( 'c' ) : '',
			'ship_to'         => array(
				'name'      => trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() ),
				'address_1' => $order->get_shipping_address_1(),
				'address_2' => $order->get_shipping_address_2(),
				'city'      => $order->get_shipping_city(),
				'state'     => $order->get_shipping_state(),
				'postcode'  => $order->get_shipping_postcode(),
				'country'   => $order->get_shipping_country(),
				'phone'     => $order->get_billing_phone(),
			),
			'lines'           => $lines,
		);

		$reference = sprintf(
			'MOCK-%d-%s',
			$order->get_id(),
			strtoupper( substr( md5( $order->get_id() . '|' . $this->get_slug() ), 0, 6 ) )
		);

		$logger = wc_get_logger();
		$logger->info(
			sprintf(
				"WOULD PUSH order #%d to %s as %s\n%s",
				$order->get_id(),
				$this->get_label(),
				$reference,
				wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
			),
			array( 'source' => 'meo-dropship' )
		);

		return $reference;
	}

	/**
	 * Hand out canned shipments, one per reference asked about.
	 *
	 * @param array<int, string> $order_refs Supplier order references.
	 * @return array<string, array<string, string>>
	 * @throws MEO_Supplier_Exception When the fixture is missing or malformed.
	 */
	public function fetch_tracking_updates( $order_refs ) {
		if ( empty( $order_refs ) ) {
			return array();
		}

		$data = $this->read_json( $this->fixture_dir . '/mock-tracking.json' );

		if ( empty( $data['shipments'] ) || ! is_array( $data['shipments'] ) ) {
			throw new MEO_Supplier_Exception( 'mock-tracking.json has no "shipments" array.' );
		}

		$shipments = array_values( $data['shipments'] );
		$updates   = array();
		$i         = 0;

		foreach ( $order_refs as $ref ) {
			$shipment = $shipments[ $i % count( $shipments ) ];
			++$i;

			if ( empty( $shipment['tracking'] ) ) {
				continue;
			}

			$updates[ $ref ] = array(
				'tracking' => (string) $shipment['tracking'],
				'carrier'  => isset( $shipment['carrier'] ) ? (string) $shipment['carrier'] : '',
			);
		}

		return $updates;
	}

	/**
	 * Read canned stock levels.
	 *
	 * @return array<string, int>
	 * @throws MEO_Supplier_Exception When the fixture is missing or malformed.
	 */
	public function fetch_stock_updates() {
		$data = $this->read_json( $this->fixture_dir . '/mock-stock.json' );

		if ( ! isset( $data['stock'] ) || ! is_array( $data['stock'] ) ) {
			throw new MEO_Supplier_Exception( 'mock-stock.json has no "stock" object.' );
		}

		$stock = array();
		foreach ( $data['stock'] as $supplier_id => $qty ) {
			$stock[ (string) $supplier_id ] = (int) $qty;
		}

		return $stock;
	}

	/**
	 * Retail price for a feed row.
	 *
	 * @param array<string, string> $row Feed row.
	 * @return string
	 */
	private function retail_price( $row ) {
		$msrp = isset( $row['msrp_cad'] ) ? $row['msrp_cad'] : '0';

		/**
		 * Filter the retail price derived from a supplier feed row.
		 *
		 * Margin policy lives here so it is consistent across suppliers rather
		 * than reimplemented in each adapter.
		 *
		 * @param string                $price Retail price, as a decimal string.
		 * @param array<string, string> $row   The raw feed row.
		 * @param string                $slug  Adapter slug.
		 */
		return (string) apply_filters( 'meo_dropship_retail_price', $msrp, $row, $this->get_slug() );
	}

	/**
	 * Build a MEO SKU from a supplier SKU.
	 *
	 * Reuses the numeric tail of the supplier's own SKU rather than inventing a
	 * sequence, so the two are traceable to each other by eye when someone is
	 * reconciling an invoice against an order. Falls back to a checksum when a
	 * supplier SKU carries no digits.
	 *
	 * @param string $supplier_sku Supplier's SKU.
	 * @param string $category     MEO category slug.
	 * @return string
	 */
	private function derive_sku( $supplier_sku, $category ) {
		$code = isset( $this->category_codes[ $category ] ) ? $this->category_codes[ $category ] : 'XX';

		if ( preg_match( '/(\d+)(?!.*\d)/', $supplier_sku, $m ) ) {
			$number = (int) $m[1];
		} else {
			$number = crc32( $supplier_sku ) % 10000;
		}

		return sprintf( 'MEO-%s-%04d', $code, $number % 10000 );
	}

	/**
	 * Read a CSV fixture into an array of associative rows.
	 *
	 * @param string $path Absolute path.
	 * @return array<int, array<string, string>>
	 * @throws MEO_Supplier_Exception When the file cannot be read or parsed.
	 */
	private function read_csv( $path ) {
		if ( ! is_readable( $path ) ) {
			throw new MEO_Supplier_Exception( sprintf( 'Fixture not readable: %s', $path ) );
		}

		$handle = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $handle ) {
			throw new MEO_Supplier_Exception( sprintf( 'Could not open fixture: %s', $path ) );
		}

		try {
			$header = fgetcsv( $handle );
			if ( ! $header ) {
				throw new MEO_Supplier_Exception( sprintf( 'Fixture has no header row: %s', $path ) );
			}

			$rows = array();
			// phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			while ( false !== ( $line = fgetcsv( $handle ) ) ) {
				// fgetcsv yields array( null ) for a blank line; skip those.
				if ( array( null ) === $line || array() === $line ) {
					continue;
				}

				if ( count( $line ) !== count( $header ) ) {
					throw new MEO_Supplier_Exception(
						sprintf(
							'Fixture %s: row has %d columns, header has %d.',
							basename( $path ),
							count( $line ),
							count( $header )
						)
					);
				}

				$rows[] = array_combine( $header, $line );
			}

			return $rows;
		} finally {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		}
	}

	/**
	 * Read a JSON fixture.
	 *
	 * @param string $path Absolute path.
	 * @return array<string, mixed>
	 * @throws MEO_Supplier_Exception When the file cannot be read or parsed.
	 */
	private function read_json( $path ) {
		if ( ! is_readable( $path ) ) {
			throw new MEO_Supplier_Exception( sprintf( 'Fixture not readable: %s', $path ) );
		}

		$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $raw ) {
			throw new MEO_Supplier_Exception( sprintf( 'Could not read fixture: %s', $path ) );
		}

		$data = json_decode( $raw, true );
		if ( null === $data ) {
			throw new MEO_Supplier_Exception(
				sprintf( 'Fixture %s is not valid JSON: %s', basename( $path ), json_last_error_msg() )
			);
		}

		return $data;
	}
}
