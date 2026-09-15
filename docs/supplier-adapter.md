# Writing a supplier adapter

Handoff doc. The bridge is built and proven end to end against fixtures; what
remains is one class per real supplier.

**Read `docs/connector-decision.md` first** for why MEO sources direct from
Canadian suppliers rather than a marketplace.

---

## Current state

| | |
| --- | --- |
| Bridge | Implemented. Order push, tracking pull, stock sync, product import. |
| Adapter | One — `mock`, backed by committed fixtures. Sends nothing anywhere. |
| Live? | **No.** No adapter is enabled, so `meo_dropship_connector_active()` is `false` and every entry point returns early. |
| Placeholders | **Still present.** The six seeded products are untouched, by design. |

Adding Grosche should be: write one class, register it, enable it.

```
wp-content/mu-plugins/meo-dropship-bridge/
├── bootstrap.php                          hooks, order push, syncs, import
├── interface-supplier-adapter.php         the contract + MEO_Supplier_Exception
├── cli.php                                wp meo-dropship ...
├── adapters/
│   └── class-meo-mock-supplier-adapter.php   ← copy this
└── fixtures/                              mock feed data
```

---

## The contract

Four methods. `bootstrap.php` handles everything else — WooCommerce writes,
order notes, status transitions, idempotency, scheduling, failure recovery.

```php
interface MEO_Supplier_Adapter {
    public function get_slug();                             // 'grosche'
    public function get_label();                            // 'Grosche'
    public function import_products();                      // → normalised rows
    public function push_order( $order, $items );           // → supplier ref
    public function fetch_tracking_updates( $order_refs );  // → ref ⇒ tracking
    public function fetch_stock_updates();                  // → supplier id ⇒ qty
}
```

**Signal failure by throwing `MEO_Supplier_Exception`**, not by returning an
error. A returned error can be ignored by forgetting to check it; an uncaught
throw cannot. The caller catches `Throwable`, so a `TypeError` or a dead HTTP
client is handled the same way.

### `get_slug()`

Stored on every product this adapter imports. **Must never change** — changing
it orphans everything already imported under the old slug.

### `import_products()`

Return an array of rows in this shape. Turning the supplier's format — CSV,
JSON, XML, a paginated REST API — into this is the adapter's whole job.

| Key | Required | Notes |
| --- | --- | --- |
| `supplier_product_id` | **yes** | The supplier's own ID. Order push maps line items back through this. |
| `sku` | **yes** | MEO's SKU. Must be unique across all suppliers — it is the import's match key. |
| `name` | **yes** | |
| `price` | **yes** | Retail, CAD, decimal string. |
| `description` | no | |
| `short_description` | no | |
| `cost_price` | no | Stored as `_meo_supplier_cost` for margin reporting; never displayed. |
| `stock_quantity` | no | Enables stock management when present. |
| `weight`, `length`, `width`, `height` | no | kg and cm. |
| `category_slug` | no | `kitchen-bar`, `desk-study`, `home-textiles`, `lighting`. Created if missing. |
| `ships_from` | no | Stored as `_meo_ships_from`. |

**SKU generation.** The mock derives `MEO-KB-0620` from supplier SKU
`GR-MIL-0620` — category code plus the supplier's own numeric tail, so the two
are traceable by eye when reconciling an invoice. Reuse `derive_sku()` unless
the supplier's scheme makes it collide. **SKUs must be unique across suppliers**;
if Grosche and GFurn both yield `MEO-KB-0620`, the second import overwrites the
first. Prefix the category code per supplier if that becomes a risk.

**Retail price.** The mock uses the supplier's MSRP, filtered through
`meo_dropship_retail_price`. Put margin policy in that filter rather than in
each adapter, so pricing stays consistent across suppliers.

### `push_order( $order, $items )`

`$items` contains **only** the line items this adapter owns — an order split
across two suppliers is pushed to each separately. Return a non-empty reference
string; the bridge stores it and will not push that (order, adapter) pair again.

Throw on any failure. The bridge then adds a private order note, logs an error,
fires `meo_dropship_push_failed`, and **leaves the order in `processing`** so a
human finds it. It does not retry automatically — retry with
`wp meo-dropship push <id>`.

### `fetch_tracking_updates( $order_refs )`

Given references you previously returned, return the ones that have shipped:

```php
array( 'GR-ORD-88213' => array( 'tracking' => '7023...', 'carrier' => 'Canada Post' ) )
```

Omit refs that have not shipped — that is not an error. The bridge completes the
order only once **every** supplier on it has reported, so a two-parcel order does
not fire the completed-order email when the first half ships.

### `fetch_stock_updates()`

Return `supplier_product_id => units`. **Products you omit are left untouched,
not zeroed** — a truncated feed must not empty the shop. To mark something out
of stock, return it explicitly as `0`.

---

## Writing the Grosche adapter

```php
class MEO_Grosche_Supplier_Adapter implements MEO_Supplier_Adapter {
    public function get_slug()  { return 'grosche'; }
    public function get_label() { return 'Grosche'; }
    // ... four methods
}
```

Register and enable:

```php
// In bootstrap.php, or a small file beside it.
add_filter( 'meo_dropship_adapters', function ( $adapters ) {
    $grosche = new MEO_Grosche_Supplier_Adapter();
    $adapters[ $grosche->get_slug() ] = $grosche;
    return $adapters;
} );
```

```bash
wp meo-dropship enable grosche
```

### Credentials

Do **not** commit them. Put them in `wp-config.php` (git-ignored) and read them
with `defined()` guards:

```php
define( 'MEO_GROSCHE_API_KEY', '...' );
define( 'MEO_GROSCHE_ACCOUNT', '...' );
```

If a credential is missing, throw from the constructor or from the first method
that needs it. Failing loudly at setup beats silently pushing nothing.

Use `wp_remote_post()` / `wp_remote_get()`, not cURL directly — they respect
WordPress's HTTP filters, proxy config and timeouts.

---

## What to establish before writing it

From the open questions in `docs/connector-decision.md`, plus what the build
surfaced:

1. **Feed format and transport.** CSV over SFTP? A REST API? A emailed
   spreadsheet? This decides most of `import_products()`.
2. **Does Grosche accept programmatic orders at all?** Many small Canadian
   wholesalers take dropship orders by email or a web portal, with no API. If
   so, `push_order()` writes a structured email and returns a generated
   reference, and tracking comes back by parsed email or manual entry. **The
   bridge supports that** — `meo_dropship_record_tracking()` can be called by
   hand — but it changes what "automated" means, and it should be agreed before
   anyone budgets for an integration.
3. **How tracking is published.** API lookup, a shipment feed, or an email.
4. **The packs-of-4 question.** Grosche's wholesale page says no minimum order
   but that products come in packs of 4. If that binds dropshipped single
   orders it is not true dropshipping, and the economics in the memo change.
5. **Stock feed cadence.** Daily is the floor. Ask whether they publish more
   often.

---

## Testing a new adapter

The mock is the reference implementation — everything below works against it
today, and should work identically against a real one.

```bash
# See what's registered and what's on
wp meo-dropship status

wp meo-dropship enable <slug>
wp meo-dropship import <slug>          # idempotent; re-run updates in place

# Place a test order, then:
wp meo-dropship push <order_id>        # push without waiting for a status change
wp meo-dropship sync-tracking
wp meo-dropship sync-stock

wp meo-dropship disable <slug>
```

Everything the bridge does is logged to **WooCommerce → Status → Logs**, source
`meo-dropship`. The mock logs the full JSON payload it *would* have sent, which
is the quickest way to check your field mapping before pointing at a live API.

### Exercise the failure path deliberately

Order loss is the worst thing this bridge can do, so make it happen on purpose:

```bash
wp option update meo_dropship_mock_fail_push yes
# place an order → confirm: private note, still 'processing', no ref recorded
wp option update meo_dropship_mock_fail_push no
wp meo-dropship push <order_id>        # confirm it recovers
```

A real adapter should have an equivalent switch, or be testable against a
sandbox that can be made to fail.

---

## Going live

In order. Steps 1–4 are safe to do early; 5 and 6 are the point of no return.

1. Wholesale accounts open. **This forces the GST/HST registration decision** —
   Canadian wholesalers require a tax ID. See `docs/canadian-tax.md`.
2. Adapter written and registered.
3. `wp meo-dropship import <slug>` produces correct products on a staging copy.
4. A test order pushed, tracked and completed end to end against the supplier's
   real sandbox or a low-value live order.
5. **Re-check the storefront copy against the supplier's real numbers.** Transit
   time, tracking, and the duty promise are hardcoded in the theme — the file
   list is in `docs/dropshipping-integration.md`. Canadian-domestic supply
   should make all of it true, but verify rather than assume.
6. `wp meo-dropship enable <slug>`, then
   `wp eval 'echo meo_dropship_purge_placeholders();'`.

Do 6 last. Purging placeholders before real products import leaves an empty shop.

---

## Meta keys

| Key | On | Holds |
| --- | --- | --- |
| `_meo_supplier_product_id` | product | Supplier's own product ID |
| `_meo_supplier_slug` | product | Which adapter owns it |
| `_meo_supplier_cost` | product | Wholesale cost, for margin reporting |
| `_meo_ships_from` | product | Origin, e.g. "Waterloo, ON" |
| `_meo_placeholder` | product | Seed data flag — adapters must never set it |
| `_meo_supplier_order_ref` | order | `array( slug => supplier reference )` |
| `_meo_supplier_shipments` | order | `array( slug => array( tracking, carrier ) )` |
| `_meo_tracking_number` / `_meo_tracking_carrier` | order | First parcel, kept for anything reading them directly |

`_meo_supplier_order_ref` was a plain string before v0.2.0.
`meo_dropship_get_order_refs()` still reads that form as `array( 'legacy' => ... )`,
so an order pushed under the old shape is not re-pushed — double-shipping a
customer is not an acceptable migration side effect.

---

## Hooks

| Hook | Type | Use |
| --- | --- | --- |
| `meo_dropship_adapters` | filter | Register an adapter |
| `meo_dropship_connector_active` | filter | Force the bridge on/off |
| `meo_dropship_retail_price` | filter | Margin policy |
| `meo_dropship_tracking_batch_size` | filter | Orders per tracking sync (default 100) |
| `meo_dropship_push_failed` | action | Alerting. Nothing attached by default — an unmonitored alert is worse than none |
