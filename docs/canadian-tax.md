# Canadian sales tax for MEO

**This is not tax advice.** It is a working note on how the store is configured
and what the configuration assumes. Rates and thresholds change. Verify against
the CRA, and talk to an accountant before you launch.

---

## The short version

1. GST/HST rates for all 13 provinces and territories are installed in
   WooCommerce by `scripts/php/configure-tax.php`.
2. **Tax collection is switched OFF by default.** You may not charge GST/HST
   until you are registered with the CRA.
3. Registration becomes mandatory once you exceed the **$30,000 CAD
   small-supplier threshold**.
4. PST, QST and RST are *separate* provincial regimes and are **not** set up.
   See [What is not covered](#what-is-not-covered).

---

## The $30,000 small-supplier threshold

You are a "small supplier" — and therefore *not required* to register for
GST/HST — until your worldwide taxable revenue crosses **$30,000 CAD**. There
are two independent ways to cross it, and they have different consequences:

| How you cross it | When registration takes effect | What you must do |
| --- | --- | --- |
| Over the **previous four consecutive calendar quarters** (a rolling window — not your fiscal year) | You stop being a small supplier one month after the end of the quarter in which you crossed | Register by that date; start charging from then |
| In a **single calendar quarter** | Immediately, on the sale that pushed you over | You must charge GST/HST on **that very sale** and register within 29 days |

The single-quarter case is the one that catches people. A good month can put you
over with no warning, and the obligation attaches to the transaction itself, not
to the date you get around to registering.

Revenue counts **before expenses** and includes shipping you charge the
customer. Zero-rated supplies count toward the threshold; exempt supplies do not.

### Registering voluntarily below the threshold

Allowed, and often worth it. Once registered you can claim **input tax credits**
— the GST/HST you paid on business purchases (supplier invoices, software,
advertising, this server) comes back to you. Under the threshold you are paying
that tax and eating it.

The trade-off is filing obligations: returns on a schedule, forever, whether or
not you made a sale. For a store with real supplier costs it usually nets out
positive. Run the numbers before deciding.

---

## What the configuration does

`scripts/php/configure-tax.php` installs:

### Rate table

| Province / territory | Tax | Rate |
| --- | --- | --- |
| Ontario | HST | 13% |
| New Brunswick | HST | 15% |
| Newfoundland and Labrador | HST | 15% |
| Prince Edward Island | HST | 15% |
| Nova Scotia | HST | 14% |
| Alberta, BC, Manitoba, Quebec, Saskatchewan | GST | 5% |
| Northwest Territories, Nunavut, Yukon | GST | 5% |

Rates as published by the CRA, checked 2026-09. Nova Scotia dropped from 15% to
14% on 2025-04-01 — provincial rates move, so re-check before launch.

In HST provinces the federal 5% is *inside* the combined rate; you do not add
GST on top.

### Settings

| Setting | Value | Why |
| --- | --- | --- |
| Base country | `CA:ON` | Change in WooCommerce → Settings → General if you operate elsewhere |
| Currency | CAD, symbol before the amount | |
| Prices entered | **excluding** tax | Canadian retail convention — the shelf price is pre-tax |
| Tax based on | **Customer shipping address** | The rate is the buyer's province, not yours |
| Shipping tax class | Inherit from item | Shipping is taxable at the same rate in every province |
| Round at subtotal | No | Round per line — matches how invoices are normally checked |
| Display in shop / cart | Excluding tax | Tax is added and itemized at checkout |
| Tax total display | **Itemized** | The receipt names "GST" or "HST" rather than a bare "Tax" line, which a compliant Canadian invoice needs |

---

## Turning tax collection on

Once you are registered and hold a GST/HST number:

```bash
MEO_TAX_ENABLED=yes npm run setup:tax
```

Then record the number so it appears in the packing-slip footer and on invoices:

```bash
npm run wp -- option update meo_gst_hst_number "12345 6789 RT0001"
```

The footer only prints a GST/HST line when that option is set — an invoice-styled
footer showing a blank or invented business number would be actively misleading,
so it stays hidden until there is a real one.

To verify the switch took effect:

```bash
npm run wp -- option get woocommerce_calc_taxes     # expect: yes
npm run wp -- wc tax list --user=1                  # expect: 13 rates
```

---

## What is *not* covered

### PST, QST and RST

Four provinces levy their own sales tax **in addition to** the 5% GST. These are
separate registrations with their own thresholds and their own returns, and this
project does not configure them:

| Province | Tax | Rate | Note |
| --- | --- | --- | --- |
| British Columbia | PST | 7% | Registration required for out-of-province sellers over $10,000 in BC sales |
| Saskatchewan | PST | 6% | Registration required from the **first** taxable sale — no threshold |
| Manitoba | RST | 7% | Threshold applies |
| Quebec | QST | 9.975% | Separate Revenu Québec registration |

Saskatchewan is the sharp edge: there is no small-supplier grace. If you ship a
taxable good into Saskatchewan, the registration obligation can attach
immediately.

If you need these, add them as **priority 2** rates in WooCommerce so they stack
on top of the 5% GST rather than replacing it. Getting priority wrong is the
classic WooCommerce tax bug — two priority-1 rates means only one applies, and
two rates at the same priority for the same region silently doubles the charge.

### Import duties

Handled separately from sales tax. Where goods ship from outside Canada, duty
and brokerage need to be calculated and disclosed at checkout — the storefront
copy promises exactly that ("Duties disclosed", "never billed on delivery"), so
whatever supplier connector you choose has to actually deliver on it. See
`docs/dropshipping-integration.md`.

### Digital / cross-border rules

MEO ships physical goods to Canadian addresses. If that changes — digital
products, or selling into the US or EU — the rules change substantially and this
configuration will not cover them.

---

## Re-checking the setup

```bash
# Every Canadian rate currently installed
npm run wp -- wc tax list --user=1

# Is tax actually being charged?
npm run wp -- option get woocommerce_calc_taxes

# What address is tax calculated from?
npm run wp -- option get woocommerce_tax_based_on
```

Re-running `npm run setup:tax` clears the existing Canadian standard-class rates
before inserting, so it will never stack duplicates.
