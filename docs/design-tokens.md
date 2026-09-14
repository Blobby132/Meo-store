# Design system

Everything visual is driven from CSS custom properties in
`wp-content/themes/meo/assets/css/tokens.css`. No component file hardcodes a
colour. Re-skinning the store means editing that one file.

---

## Stylesheet order

Enqueued as separate handles in `inc/enqueue.php`, each depending on the
previous, so the cascade order is declared in code rather than implied:

```
tokens → base → layout → components → woocommerce
```

| Layer | Contains |
| --- | --- |
| `tokens.css` | Colour, type scale, spacing, geometry, motion. Light + dark. |
| `base.css` | Reset, typography, and the shared motif primitives (`.meo-tear`, `.meo-mono`, `.meo-label`) |
| `layout.css` | Page shell, header, nav, responsive breakpoints |
| `components.css` | Buttons, hero + manifest card, trust strip, category grid, about, footer |
| `woocommerce.css` | Product cards, shop loop, single product, cart, checkout |

`woocommerce.css` only loads when WooCommerce is active.

---

## Colour

### Light (default)

| Token | Value | Use |
| --- | --- | --- |
| `--meo-bg` | `#EEE8DC` | Page ground — warm paper |
| `--meo-surface` | `#F8F4EA` | Cards, panels |
| `--meo-surface-2` | `#E4DCC9` | Wells, table headers, recessed areas |
| `--meo-ink` | `#16231C` | Headings, body |
| `--meo-ink-muted` | `#46523F` | Secondary copy, meta |
| `--meo-accent` | `#B4842A` | Gold — rules, borders, large display |
| `--meo-accent-ink` | `#7A5714` | Gold **text** at normal size |
| `--meo-secondary` | `#4E617A` | Slate blue — links |

### The two golds

`#B4842A` on the cream ground measures **2.7:1**. That is fine for a 2px rule or
40px display type and fails for 14px body copy, which needs 4.5:1.

So there are two:

- `--meo-accent` **paints** — borders, rules, fills, large type
- `--meo-accent-ink` **reads** — any gold text at normal size (5.4:1)

In dark mode the brightened gold clears 9.5:1 on its own, so both tokens
collapse to the same value there.

### Measured contrast (light)

| Pair | Ratio | WCAG |
| --- | --- | --- |
| `--meo-ink` on `--meo-bg` | 14.2:1 | AAA |
| `--meo-ink-muted` on `--meo-bg` | 6.8:1 | AA |
| `--meo-accent-ink` on `--meo-bg` | 5.4:1 | AA |
| `--meo-secondary` on `--meo-bg` | 5.2:1 | AA |
| `--meo-accent` on `--meo-bg` | 2.7:1 | **Non-text only** |

### Dark

Warm charcoal-green (`#12160F`), not neutral grey — the paper goes dark but
stays organic. The gold family brightens to `#E3B45A` (9.5:1).

Applied two ways, and both work:

1. `@media (prefers-color-scheme: dark)` — the OS setting
2. `[data-theme="dark"]` on `<html>` — the explicit toggle

The media query is guarded with `:not([data-theme="light"])` so an explicit
light choice beats a dark OS. Every token is defined on bare `:root` first, so
no colour has its only definition inside a media query.

The toggle writes to `localStorage`; an inline script in `wp_head` (priority 1)
applies the stored value before first paint, so there is no flash of the wrong
theme. Both the read and the write are wrapped in `try/catch` — `localStorage`
throws in private mode, and a colour preference is never worth breaking a page
over.

---

## Type

| Family | Token | Used for |
| --- | --- | --- |
| **Fraunces** | `--meo-font-display` | Headlines, product titles, category names |
| **IBM Plex Sans** | `--meo-font-body` | Body copy, UI, buttons |
| **IBM Plex Mono** | `--meo-font-mono` | Prices, SKUs, order and tracking numbers, field labels |

The mono family carries the packing-slip motif. Anything that would appear on a
real shipping document is set in it — and always with
`font-variant-numeric: tabular-nums`, so price columns and SKU digits align
vertically.

Fraunces loads as a variable font with its `SOFT` and `WONK` axes. Headings set
`font-variation-settings: "SOFT" 0, "WONK" 0` — crisp rather than quirky — with
`opsz` tracking the rendered size.

Sizes are a fluid `clamp()` scale, `--meo-step--1` through `--meo-step-5`.

### Self-host before launch

Fonts currently load from `fonts.googleapis.com` on every page view. Before
launch, self-host them into `assets/fonts/` with `@font-face`. It is faster, it
removes a third-party request, and it avoids the PIPEDA/GDPR question of leaking
visitor IPs to Google. The URL is built in `meo_fonts_url()` in
`inc/enqueue.php`.

---

## Motifs

| Motif | Implementation |
| --- | --- |
| **Tear line** | `.meo-tear` — a `repeating-linear-gradient`, not `border: dashed`. Browsers give no control over native dash/gap ratio; this reads dash and gap from tokens. `.meo-tear-v` is the vertical variant used in the trust strip. |
| **Manifest card** | `.meo-manifest` in the hero — dark header bar with a document number, dotted key/value rows, decorative barcode. Rotated `-0.5deg` so it reads as a slip resting on the page; the rotation is dropped under 980px. |
| **Mono labels** | `.meo-label` — uppercase, letterspaced, above a value, like a form field. |
| **SKU line** | Every product card shows its SKU and department. |
| **Stock pill** | `.meo-stock` with a status dot — in / low (≤ 6, filterable) / out / backorder. |
| **Packing-slip footer** | Dark slip bar, four columns, a totals block with currency and lead time, a dashed tear line above the legal row. |
| **Placeholder flag** | `.meo-flag--placeholder` — deliberately loud red on seeded products. |

Radius is `2px` throughout. This is stationery, not a SaaS dashboard.

---

## Theme.json

`theme.json` registers the same palette, font families and spacing scale for the
**block editor**, so content authored in the editor uses the store's tokens
rather than WordPress defaults. It is intentionally a mirror of `tokens.css`,
not the source — the theme is classic PHP templates, and `theme.json` exists so
editor previews match the front end.

**Changing a colour means editing both files.** They are short and adjacent;
that was judged a better trade than generating one from the other.

---

## Responsive

Single fluid column at every breakpoint, with a minimum 16px side gutter from
`.meo-wrap`. Breakpoints:

| Width | Change |
| --- | --- |
| ≤ 980px | Hero and about stack; manifest un-rotates; footer to 2 columns; trust strip wraps |
| ≤ 860px | Mobile nav (hamburger, Escape and outside-click close); single product stacks |
| ≤ 560px | Footer to 1 column; hero buttons full width; manifest rows stack |

Tables and the cart get their own `overflow-x: auto` containers, so the page
body never scrolls sideways.

`prefers-reduced-motion: reduce` collapses all animation and transition
durations.

---

## Accessibility notes

- Interactive targets are ≥ 44px tall (36px for compact secondary buttons).
- `:focus-visible` gets a 2px `--meo-accent-ink` outline with offset.
- The card title link is stretched over the whole card with `::after`; the media
  link is `aria-hidden` with `tabindex="-1"` so the card is **one** stop in the
  tab order, not two.
- The cart badge uses fixed dark ink on gold, in both themes — it must not flip
  with the theme or it would go light-on-gold and fail contrast.
- Add-to-cart sits above the stretched link (`z-index: 2`) so it stays clickable.
