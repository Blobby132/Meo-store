# Connector decision memo

**Status:** recommendation, awaiting sign-off. Nothing implemented.
**Researched:** 2026-09-15.
**Supersedes:** the candidate table in `docs/dropshipping-integration.md`, which was
a hypothesis written without live research. Three of its four candidates turned
out to be wrong or unusable — see [Corrections](#corrections-to-the-existing-doc).

---

## The finding that reframes the decision

**Canada imposed a surtax on U.S.-origin goods on 2026-09-08 — one week ago.**

The [United States Surtax Order (2026)](https://www.cbsa-asfc.gc.ca/publications/cn-ad/cn26-23-eng.html)
applies **15%, 25% or 50%** of value for duty on specified U.S.-origin goods. Two
provisions matter to MEO:

> "Surtax is applicable on shipments that fall under de minimis thresholds."

> "The imposition of a surtax is not subject to appeal."

So the CUSMA low-value exemptions MEO would otherwise have relied on — CAD $40
duty-and-tax-free, CAD $150 duty-free — **do not shield a U.S.-origin parcel from
the surtax**. The courier collects it at the border and bills the recipient, with
a brokerage fee on top.

That is precisely the outcome the storefront promises will never happen:

> "Any import duty or brokerage is shown at checkout before you pay — never
> billed on delivery." — `inc/template-tags.php`

**Textiles are explicitly named** in the covered goods, so MEO's Home Textiles
category is directly hit. Appliances, consumer goods and glass containers are
also named, which likely catches parts of Kitchen & Bar and Lighting — but the
Order works at tariff-item level, so that needs checking HS code by HS code
before relying on it either way.

### What this does to the shortlist

Sourcing from U.S. suppliers was the obvious way to get fast transit to Canada.
As of last week it is also the way to break MEO's duty promise. Honouring the
promise on a U.S.-sourced parcel now means computing a three-tier surtax by
tariff item, collecting it at checkout, and shipping DDP — a customs-compliance
project, not a plugin install.

**Canadian-domestic fulfilment sidesteps all of it.** Goods already in Canada are
not an import event: no duty, no surtax, no brokerage, no customs delay. The duty
promise becomes true by construction rather than by calculation.

That is now the primary filter, ahead of price and plugin quality.

---

## Candidates

### Eliminated on hard evidence

| Platform | Why it's out |
| --- | --- |
| **Zendrop** | **Discontinued WooCommerce/WordPress support in April 2023.** Now Shopify, TikTok Shop US, ClickFunnels and Wix only. Not a candidate at any price. |
| **Modalyst** | **No WooCommerce integration.** Wix-owned since acquisition; supports Wix, Shopify, BigCommerce. The "Canadian warehouses" claim repeated by comparison blogs is irrelevant without an integration. |
| **Trendsi** | **Women's fast-fashion apparel only.** Does have WooCommerce, but there is no kitchenware, desk, lighting or home-textile inventory. Category mismatch, not a quality problem. |

### Viable, with real trade-offs

| Platform | Cost (USD) | Canadian stock | WooCommerce integration | Verdict |
| --- | --- | --- | --- | --- |
| **Syncee** | Free tier; $39.99/mo (100 products); $59.99/mo (250); $99.99/mo (10,000) | **Yes — explicitly US/CA/EU/AU suppliers** | Active plugin, 1,000+ installs, 49 ratings, tested to WP 7.1 | Best marketplace fit, but see the tracking gap below |
| **Spocket** | $39.99/mo (25 products); $59.99/mo (250); annual Pro ≈$24/mo | **Doubtful.** Its own WooCommerce page names *US and EU only*; its marketing blog claims Canadian suppliers. ~80% US/EU by its own figure | Plugin exists, free, requires a paid account | Surtax-exposed; Canada claim unverified |
| **Wholesale2B** | $29.99/mo to import into an existing store | Mostly US suppliers | Supported; claims automatic tracking sync back | Cheapest subscription, but same U.S.-origin surtax exposure |
| **CJdropshipping** | Free platform, per-order cost | Has a Canada warehouse (marketing claim) | **Plugin last updated 5 years ago, tested to WP 5.8.** Store runs WP 7.x | Would need custom API work — the plugin is effectively abandoned |
| **Printful** | No subscription; per-item | **Yes — real facility, Mississauga ON.** Domestic orders clear no customs | Official plugin, 50,000+ installs, but **2.6★ — 49 of 96 reviews are 1-star** | Print-on-demand only; covers at most 2 of 4 categories |
| **AliDropship** | **$89 one-time**, lifetime licence | No — AliExpress, China origin | WooCommerce version of the plugin exists | Cheapest by far; breaks both transit and duty copy |
| **Direct Canadian suppliers** + custom importer | $0/mo, build time | **Yes, by definition** | Whatever we build on the existing bridge | Best margin and fit; most setup effort |

### The tracking gap nobody advertises

MEO's bridge is built around `meo_dropship_sync_tracking()` pulling tracking
numbers back automatically. **Syncee does not do this on WooCommerce.** From
their own help centre:

> Syncee syncs order status and tracking to **Shopify, Ecwid by Lightspeed,
> BigCommerce, and Wix.** WooCommerce is not included. On unsupported platforms
> the supplier emails tracking to the retailer, who enters it manually.

Spocket's WooCommerce page makes no tracking-sync claim either. Only Wholesale2B
explicitly claims it, and that claim is from its own marketing.

This matters more than it looks: it means **no marketplace removes the bridge
work**. We will be writing `meo_dropship_record_tracking()` plumbing regardless
of which option is chosen — against a marketplace API, a supplier API, or an
email/CSV drop. That substantially weakens the "pay a subscription to get
automation" argument, because the automation we specifically need is the piece
that is missing.

---

## Corrections to the existing doc

`docs/dropshipping-integration.md` sketched four candidates before any research.
For the record, what the live check changed:

| Old claim | Correction |
| --- | --- |
| "Spocket — curated, **has CA**/US/EU suppliers … matches the storefront copy" | Its own WooCommerce integration page names **US and EU only**. Canadian coverage is claimed on its blog and contradicted on its product page. It was the doc's implied favourite; it is now the recommendation's main *rejection*. |
| "AliDropship — typically 15–30 days … breaks the 6–14 claim" | **Correct, and understated.** It also breaks the duty promise: China origin drops the de minimis to CAD $20, so effectively everything is dutiable and billed on delivery. |
| "Printful/Printify — 5–12 days, has Canadian facilities" | **Correct.** Mississauga, ON. Worth adding that the WooCommerce plugin rates 2.6★ with half its reviews at 1 star. |
| "Custom CSV/REST importer — no lock-in, no monthly fee, but order push and tracking pull become yours to maintain" | **Correct — but the caveat applies to every option**, not just this one. No marketplace syncs tracking to WooCommerce, so that work is unavoidable. This removes the main argument that was counting against the custom route. |
| Candidate list of four | Missed **Syncee**, which turns out to be the strongest marketplace option for this store. |

The old doc's framing — that transit time is the decision — was right. It just
had the wrong supplier map, and it predates the surtax.

---

## Recommendation

**Source directly from Canadian suppliers and build the importer on the existing
bridge. Use Syncee's free tier as the discovery tool to find them.**

Concrete starting points found during research, both in MEO's actual categories:

- **Grosche** (Waterloo, ON) — coffee/tea ware, water bottles, kitchen
  accessories. Dropships in Canada and the US. No minimum order. Direct fit for
  Kitchen & Bar.
- **GFurn** (Montreal, QC) — furniture and décor, already distributing through
  Syncee with real-time inventory. Fits Home Textiles and Lighting.

### Why

1. **It makes the existing copy true with zero edits.** Domestic Canadian
   fulfilment means no customs event: the duty promise holds trivially, and
   typical domestic transit of 2–7 business days sits comfortably inside the
   advertised 6–14. Every other option requires either engineering or an apology.
2. **The surtax makes U.S. sourcing actively hostile** to a store that promises
   no surprise charges at the door — and it landed a week ago, so any comparison
   written before September is stale.
3. **No subscription, no lock-in.** $0/mo against $360–$720/yr for a marketplace
   tier. For a store below the $30,000 small-supplier threshold, that is a
   meaningful share of early margin.
4. **The bridge work is the same either way** — see the tracking gap above. We
   are not buying away the integration effort by paying a subscription.
5. **It matches the brand.** The storefront's whole proposition is a small,
   edited, plainly-described catalogue. That is a poor use of a 100-million-SKU
   marketplace and a good use of six real supplier relationships.
6. **Better unit economics.** True wholesale pricing rather than marketplace
   markup, which matters because Canadian-domestic stock already costs more than
   the AliExpress route we are rejecting.

### What it costs us

- **Slower to start.** Wholesale applications, terms negotiation, and a bespoke
  import per supplier, versus clicking import on a marketplace.
- **It forces early GST/HST registration.** Grosche requires a tax ID / HST
  number to open a wholesale account, and that is normal for Canadian wholesale.
  Per `docs/canadian-tax.md` this is *allowed* below the $30,000 threshold and
  usually worth it — voluntary registration unlocks input tax credits on
  supplier invoices — but it is a decision with permanent filing obligations
  attached, and it gets made sooner than planned. **Flagging it explicitly
  because it is a consequence of the connector choice, not an independent one.**
- **Per-supplier integration.** Each supplier means its own feed format. The
  bridge is designed for this — `_meo_supplier_product_id` is already per-product
  — but it is real work.

### Fallback

If direct relationships prove too slow, **Syncee Pro at $59.99/mo filtered to
Canadian suppliers** is the best marketplace option: it is the only one with
both confirmed Canadian supplier coverage and a currently-maintained WooCommerce
plugin. We would still write the tracking sync ourselves.

**Recommend against Spocket** — the option the old doc favoured. Its Canadian
coverage is unconfirmed and contradicted by its own integration page, and its
US/EU network is exactly what the surtax now penalises.

---

## Copy impact by option

What breaks in the storefront under each choice. File locations are in
`docs/dropshipping-integration.md`.

| Option | "6–14 business days" | "Duties disclosed / never billed on delivery" | Other |
| --- | --- | --- | --- |
| **Canadian direct** (recommended) | **Holds.** Domestic transit 2–7 days | **Holds trivially** — no customs event | None. Could arguably tighten the range and make it a selling point |
| **Syncee, CA suppliers only** | **Holds** | **Holds** for genuinely CA-stocked items; breaks the moment an order routes to a US supplier | Needs a hard guard so non-CA suppliers cannot be imported by accident |
| **Spocket / Wholesale2B** (US-sourced) | Probably holds (3–7 days claimed) | **Breaks.** Surtax + GST/HST above $40 collected at the border unless we ship DDP with per-tariff-item surtax calculation | Trust strip would need rewriting to "duties may apply on delivery" — which contradicts the hero |
| **Printful** | **Holds** (2–5 production + 3–5 shipping) | **Holds** — domestic | Only covers part of the catalogue. Desk & Study and most of Kitchen & Bar have no POD equivalent |
| **AliDropship / AliExpress** | **Breaks.** 15–30 days typical | **Breaks.** China origin drops de minimis to CAD $20; effectively everything is dutiable and billed on delivery | Would require rewriting the hero, trust strip, about page and footer. At that point the brand promise is gone |

---

## Not verified — confirm before committing

Honest gaps. None change the recommendation's direction, but two could change its
cost.

1. **Depth of Canadian home-goods inventory on Syncee.** Confirmed that Canadian
   suppliers exist on the platform; *not* confirmed how many carry kitchenware,
   desk goods, textiles and lighting specifically. Worth an hour on the free tier
   before paying for anything.
2. **Grosche's dropship terms.** Their wholesale page says no minimum order but
   that products "come in packs of 4." Unclear whether that applies to dropshipped
   single orders or only to wholesale purchasing. If it binds dropship orders it
   is not true dropshipping and changes the economics.
3. **Exact surtax scope for MEO's categories.** Textiles are confirmed covered.
   Kitchenware and lighting are probable via "consumer goods" / "appliances" /
   "glass containers" but need checking at tariff-item level against Schedules
   1–4. Only matters if a US-sourced option is chosen against this advice.
4. **Whether Spocket has genuine Canadian suppliers.** Its blog says yes, its
   WooCommerce integration page says US/EU. Resolvable with a trial account.
5. **Surtax durability.** This is retaliatory trade policy one week old, and
   remission-order amendments are already being published. It could narrow,
   widen, or be withdrawn. **Re-check before launch** — and note that if it were
   withdrawn, Spocket and Wholesale2B become materially more attractive than
   this memo rates them.

---

## If approved, the next step

Nothing in `bootstrap.php` changes until this is signed off. On approval, the
first implementation slice is:

1. Open wholesale accounts (forces the GST/HST registration decision first).
2. Build the importer for one supplier, setting `_meo_supplier_product_id` and
   real SKUs, and **not** setting `_meo_placeholder`.
3. Implement order push with loud failure handling.
4. Implement tracking via whatever that supplier actually offers — API, CSV drop,
   or parsed email — behind `meo_dropship_record_tracking()`.
5. Only then flip `meo_dropship_connector_active` and purge the placeholders.

---

## Sources

- [CBSA Customs Notice 26-23: United States Surtax Order (2026)](https://www.cbsa-asfc.gc.ca/publications/cn-ad/cn26-23-eng.html)
- [CBSA: Increase to low-value shipment thresholds (CUSMA)](https://www.cbsa-asfc.gc.ca/services/cusma-aceum/lvs-efv-eng.html)
- [PwC Canada: Canada imposes surtaxes on imports of various US-origin goods](https://www.pwc.com/ca/en/services/tax/publications/tax-insights/canada-imposes-surtaxes-us-imports-sep-2026.html)
- [Zendrop Help Centre: e-commerce platform integrations](https://support.zendrop.com/en/articles/8176418-zendrop-e-commerce-platform-integrations)
- [Syncee Help Centre: where to check if the supplier fulfilled the order](https://help.syncee.com/en/articles/8949707-where-can-i-check-if-the-supplier-fulfilled-the-order)
- [Syncee pricing](https://syncee.com/pricing/)
- [Syncee Premium Dropshipping & Wholesale — WordPress.org](https://wordpress.org/plugins/syncee-collective-dropshipping/)
- [Spocket pricing](https://www.spocket.co/pricing)
- [Spocket: WooCommerce dropshipping plugin](https://www.spocket.co/integrations/woocommerce-dropshipping-plugin-for-wordpress)
- [CJdropshipping — WordPress.org](https://wordpress.org/plugins/cjdropshipping/)
- [Printful Integration for WooCommerce — WordPress.org](https://wordpress.org/plugins/printful-shipping-for-woocommerce/)
- [Printful: Canadian fulfillment center](https://www.printful.com/news/printful-to-open-a-canadian-fulfillment-center)
- [AliDropship plugin](https://alidropship.com/woocommerce-dropshipping-plugin/)
- [GROSCHE wholesale & dropshipping program](https://grosche.ca/pages/wholesale-and-dropshipping)
- [Wholesale2B: Canada dropship guide](https://www.wholesale2b.com/canada-dropship-guide.html)
