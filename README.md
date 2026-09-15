# MEO

Self-hosted WordPress + WooCommerce storefront for **MEO** — a Canadian
dropshipping store for home and desk goods.

Design language: a shipping-manifest motif. Dashed tear lines, a packing-slip
footer, IBM Plex Mono for every price, SKU and tracking number.

---

## Quick start

Requires **Docker Desktop** (running) and **Node 18+**.

```bash
npm install         # installs @wordpress/env
npm run env:start   # WordPress + WooCommerce on http://localhost:8888
npm run setup       # theme, categories, products, tax rates, menus
```

Then open:

| | |
| --- | --- |
| Storefront | http://localhost:8888 |
| Admin | http://localhost:8888/wp-admin/ — `admin` / `password` |

`npm run setup` is idempotent. Re-run it any time.

### Without Docker Desktop

`docker-compose.yml` is a fallback stack — see the header comment in that file.
The setup scripts run against either environment:

```bash
MEO_WP="docker compose run --rm wpcli wp" \
MEO_SCRIPTS_PATH=/var/www/html/wp-content/meo-scripts \
  bash scripts/setup-store.sh
```

---

## What's in here

```
.wp-env.json                  dev environment (WordPress + WooCommerce + mappings)
docker-compose.yml            fallback stack
package.json                  npm scripts

wp-content/themes/meo/        the theme — classic PHP templates + theme.json
├── assets/css/               tokens → base → layout → components → woocommerce
├── inc/                      setup, enqueue, template tags, WooCommerce integration
├── template-parts/           hero, trust strip, category grid, about
└── woocommerce/              template overrides (product card, content wrappers)

wp-content/mu-plugins/
└── meo-dropship-bridge/      supplier integration surface
    ├── interface-supplier-adapter.php   the adapter contract
    ├── adapters/                        one class per supplier (mock only, so far)
    └── fixtures/                        sample feed the mock reads

scripts/                      WP-CLI setup, mounted into the container
├── setup-store.sh            orchestrator
└── php/                      seed catalogue, configure tax, pages + menus

docs/
├── canadian-tax.md           GST/HST, the $30k threshold, what is NOT set up
├── connector-decision.md     why Canadian-direct, and what was rejected
├── supplier-adapter.md       how to write a real supplier adapter
├── dropshipping-integration.md   how the bridge works (partly superseded)
└── design-tokens.md          the design system
```

---

## npm scripts

| Command | Does |
| --- | --- |
| `npm run env:start` | Start WordPress + WooCommerce |
| `npm run env:stop` | Stop it |
| `npm run env:clean` | Wipe the database (keeps containers) |
| `npm run env:destroy` | Remove containers and volumes entirely |
| `npm run setup` | Full store setup — safe to re-run |
| `npm run setup:catalog` | Re-seed categories + placeholder products only |
| `npm run setup:tax` | Install/refresh GST/HST rates only |
| `npm run reset` | Wipe and rebuild from scratch |
| `npm run wp -- <args>` | Any WP-CLI command, e.g. `npm run wp -- plugin list` |
| `npm run test:bridge` | End-to-end test of the dropship bridge (mock adapter) |
| `npm run lint:php` | Syntax-check every PHP file |

---

## Three things to know

### 1. Every product is a placeholder

The six seeded products — pour-over dripper, walnut desk tray, linen throw,
weighted lap blanket, amber reading lamp, terrazzo planter — are **sample data**.
Prices, stock levels and lead times are invented. They exist to exercise the
design (in stock / low stock / out of stock / on sale) with realistic text
lengths.

Each carries `_meo_placeholder = yes`, which renders a red **Placeholder** flag
on the card and a warning on the product page, and makes them removable in one
call:

```bash
npm run wp -- eval 'echo meo_dropship_purge_placeholders();'
```

Only products with that meta are deleted. **Do not launch with these live.**

### 2. Tax collection is OFF by default

GST/HST rates for all 13 provinces and territories are installed, but
WooCommerce is **not charging tax**. That is deliberate: in Canada you may not
charge GST/HST until you are registered with the CRA.

Registration becomes mandatory once taxable revenue exceeds **$30,000 CAD**,
either over four rolling quarters or in a single quarter. The single-quarter
case attaches to the sale that crosses the line — you must charge on *that*
sale.

Once registered:

```bash
MEO_TAX_ENABLED=yes npm run setup:tax
npm run wp -- option update meo_gst_hst_number "12345 6789 RT0001"
```

PST, QST and RST are **separate regimes and are not set up**. Saskatchewan in
particular has no small-supplier threshold. Read
[`docs/canadian-tax.md`](docs/canadian-tax.md) before selling.

### 3. No real supplier is connected

`wp-content/mu-plugins/meo-dropship-bridge/` holds the full integration surface
— order push, tracking pull, stock sync, product import. It is **implemented and
tested**, but only against a fixture-backed `mock` adapter that sends nothing
anywhere.

The bridge is **inert as shipped**: no adapter is enabled, so
`meo_dropship_connector_active()` is `false` and every entry point returns early.

```bash
npm run test:bridge          # 27 checks, end to end, cleans up after itself
npm run wp -- meo-dropship status
```

Supplier choice is settled — direct from Canadian suppliers (Grosche, GFurn),
because the September 2026 Canada–US surtax applies below the de minimis
thresholds and would otherwise break the site's duty-disclosure promise. See
[`docs/connector-decision.md`](docs/connector-decision.md) for the reasoning and
[`docs/supplier-adapter.md`](docs/supplier-adapter.md) for what a real adapter
has to fill in.

---

## Before launch

- [x] Choose a supplier connector (see `docs/connector-decision.md`)
- [x] Build the supplier-adapter scaffolding, proven against a mock feed
- [ ] Write the real Grosche/GFurn adapter (see `docs/supplier-adapter.md`)
- [ ] Verify shipping/duties copy against the supplier's real numbers
- [ ] Purge the placeholder products
- [ ] Register for GST/HST if over the threshold, then enable tax collection
- [ ] Decide on PST/QST/RST
- [ ] Self-host the fonts (currently loaded from Google — see `docs/design-tokens.md`)
- [ ] Configure a real payment gateway
- [ ] Set up transactional email (WP's default `mail()` lands in spam)
- [ ] Add a privacy policy and terms
- [ ] Pick a host and set up backups
