# Dropshipping supplier integration

Nothing in this project talks to a supplier yet. This document is the map of
where that code goes, and what choosing a connector commits you to.

The whole integration surface is one must-use plugin:

```
wp-content/mu-plugins/
├── meo-dropship-bridge.php        loader (WordPress only auto-loads mu-plugins root files)
└── meo-dropship-bridge/
    └── bootstrap.php              hooks, stubs and TODOs
```

**The theme never calls a supplier API.** It reads what the bridge writes into
WooCommerce. That separation is the point: swapping connectors should never mean
editing a template.

---

## Why a must-use plugin

| | |
| --- | --- |
| **Survives a theme change** | Order fulfilment is not presentation. Redesigning the store must not stop orders reaching the supplier. |
| **Cannot be deactivated by accident** | mu-plugins have no "Deactivate" link in wp-admin. A mis-click should not silently stop fulfilment. |
| **Keeps the theme reviewable** | The theme stays about markup and styling. |

---

## Choosing a connector

The decision that matters for MEO is **transit time to Canada**, because the
storefront makes specific promises.

| Connector | Catalogue | Transit to Canada | Cost | Fit |
| --- | --- | --- | --- | --- |
| **AliDropship** / DropshipMe | AliExpress, enormous | Typically **15–30 days** | One-time licence | Cheap inventory, but **breaks the site's "6–14 business days" claim** |
| **Spocket** | Curated, has CA/US/EU suppliers | 3–14 days from CA/US suppliers | Monthly subscription | Matches the storefront copy; higher unit cost |
| **Printful / Printify** | Print-on-demand only | 5–12 days, has Canadian facilities | Per-item | Only relevant if the catalogue moves to POD |
| **Custom CSV / REST importer** | Whatever you negotiate | Depends on supplier | Build time | No lock-in, no monthly fee; order push and tracking pull become yours to maintain |

### The copy that has to stay true

These claims are hardcoded in the storefront. Whatever you pick, **re-check each
one against the supplier's real numbers** and edit if they no longer hold:

| Claim | Where it lives |
| --- | --- |
| "6–14 business days" | `themes/meo/template-parts/hero.php`, `template-parts/about.php`, `footer.php`, `inc/template-tags.php` |
| "Tracked shipping on every order" | `inc/template-tags.php` → `meo_trust_items()` |
| "Duties disclosed at checkout, never billed on delivery" | `inc/template-tags.php`, `template-parts/hero.php` |
| "30-day returns" | `inc/template-tags.php`, the Shipping & Returns page |
| "Flat $12, free over $95" | `themes/meo/header.php` announcement bar, Shipping & Returns page |

The duties promise is the one with teeth. If you source from AliExpress and do
*not* collect duty at checkout, Canadian customers get billed by the carrier at
the door — which is precisely what the site says will not happen. Either
implement landed-cost calculation or change the copy.

---

## What to implement

Four functions in `bootstrap.php` are stubbed and marked `TODO`.

### 1. Push order → supplier

```php
add_action( 'woocommerce_order_status_processing', 'meo_dropship_push_order' );
```

Fires when payment is captured. Implementation needs to:

1. Map each line item to the supplier's product ID, stored as
   `_meo_supplier_product_id` on the product.
2. POST the order and shipping address to the supplier.
3. Store the returned reference: `$order->update_meta_data( MEO_DROPSHIP_ORDER_REF_META, $ref )`.
4. **On failure, fail loudly.** Add an order note and leave the order in
   `processing` so a human sees it. A dropship store that loses an order
   silently is worse than one that throws an error.

The stub already guards against double-pushing — the hook can fire more than
once for the same order.

### 2. Pull tracking ← supplier

```php
add_action( 'meo_dropship_hourly_sync', 'meo_dropship_sync_tracking' );
```

Scheduled through **Action Scheduler** (bundled with WooCommerce), not WP-Cron.
WP-Cron only runs when someone visits the site and does not retry failures; a
store with quiet nights will just not sync. Action Scheduler has a real queue
and retries.

Write results back through the helper rather than setting meta directly:

```php
meo_dropship_record_tracking( $order, '1Z999AA10123456784', 'UPS' );
```

It stores the number, adds a **customer-visible** order note, marks the order
completed (which triggers Woo's completed-order email), and saves once.

The tracking number then renders on the customer's order page in IBM Plex Mono,
consistent with the manifest motif — see `meo_dropship_render_tracking()`.

### 3. Sync stock ← supplier

```php
add_action( 'meo_dropship_daily_sync', 'meo_dropship_sync_stock' );
```

**This is the one that will bite you.** Stock drift — selling something the
supplier no longer has — is the most common dropshipping failure, and it
produces refunds and chargebacks rather than a quiet error. Run at least daily;
hourly if the supplier's API allows it.

### 4. Import products

Not hooked, because it is a one-off you drive from WP-CLI rather than a
recurring job. Whatever it does, it should:

- set `_meo_supplier_product_id` on each product so order push can map it back;
- set a real SKU (the theme falls back to `MEO-00000`-style derived codes, which
  works but is not meaningful to a supplier);
- **not** set `_meo_placeholder`, so real products are never caught by the purge.

---

## Removing the placeholder catalogue

The six seeded products all carry `_meo_placeholder = yes`. While that meta is
set they render a red **Placeholder** flag on the card and a warning on the
product page — deliberately hard to miss, so they cannot be mistaken for live
inventory.

When the real feed is in:

```bash
npm run wp -- eval 'echo meo_dropship_purge_placeholders();'
```

It only deletes products carrying that meta, so anything imported or
hand-created is untouched. Deletion is permanent (no trash) — these are seed
rows, not customer data.

---

## Testing an integration without a supplier

The bridge stays inert until a connector says it is ready:

```php
add_filter( 'meo_dropship_connector_active', '__return_true' );
```

Until that returns true, every push and sync returns early. That means you can
develop the connector against a live store without risk of it firing at a
supplier mid-build — and it means the bridge is safe to ship as-is today.

---

## Before you go live

- [ ] Connector chosen, and its real transit times checked against the site copy
- [ ] Order push implemented, with loud failure handling
- [ ] Tracking sync implemented and verified end-to-end on a test order
- [ ] Stock sync running at least daily
- [ ] Duty/brokerage either collected at checkout, or the copy corrected
- [ ] `meo_dropship_connector_active` returns true
- [ ] Placeholder products purged
- [ ] A real order placed, fulfilled and tracked start to finish
