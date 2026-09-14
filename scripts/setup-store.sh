#!/usr/bin/env bash
#
# One-shot setup for the MEO store.
#
# Brings a blank WordPress to a working MEO storefront: WooCommerce installed,
# the MEO theme active, Woo's own pages created, four categories, six
# placeholder products, Canadian GST/HST rates installed (but NOT charged),
# and the navigation menus wired up.
#
# Safe to re-run — every step is idempotent.
#
# Usage:
#   npm run setup
#   MEO_TAX_ENABLED=yes npm run setup      # also switch tax collection ON
#
# Prerequisites: the environment is running (npm run env:start).

set -euo pipefail

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
# shellcheck source=lib/common.sh
source "${SCRIPT_DIR}/lib/common.sh"

step "Checking the environment"
require_wp
info "WordPress is up: $(wp option get home 2>/dev/null || echo 'unknown')"

step "WooCommerce"
if wp plugin is-installed woocommerce --quiet 2>/dev/null; then
	info "already installed"
else
	wp plugin install woocommerce
fi

if wp plugin is-active woocommerce --quiet 2>/dev/null; then
	info "already active"
else
	wp plugin activate woocommerce
fi

step "MEO theme"
if wp theme is-active meo --quiet 2>/dev/null; then
	info "already active"
else
	wp theme activate meo
fi

step "Permalinks"
# Woo needs pretty permalinks for product and category URLs to resolve.
wp rewrite structure '/%postname%/' --hard
wp rewrite flush --hard
info "set to /%postname%/"

step "WooCommerce pages (shop, cart, checkout, account)"
# Creates any Woo page that does not exist yet; leaves existing ones alone.
wp wc tool run install_pages --user=1 >/dev/null 2>&1 \
	|| warn "Could not run install_pages — create Woo pages from WooCommerce > Status > Tools if the shop 404s."
info "done"

step "Catalogue — categories and placeholder products"
wp_php seed-catalog.php

step "Canadian GST/HST"
if [ "${MEO_TAX_ENABLED:-}" = "yes" ]; then
	wp_php configure-tax.php enable
else
	wp_php configure-tax.php
fi

step "Pages and navigation"
wp_php setup-pages-menus.php

step "Flushing caches"
wp rewrite flush --hard
wp transient delete --all >/dev/null 2>&1 || true
info "done"

SITE_URL="$(wp option get home 2>/dev/null || echo 'http://localhost:8888')"

cat <<SUMMARY

$(printf '%s' "$C_BOLD")MEO store is set up.$(printf '%s' "$C_OFF")

  Storefront   ${SITE_URL}
  Admin        ${SITE_URL}/wp-admin/   (wp-env default login: admin / password)

  Catalogue    4 categories, 6 products — $(printf '%s' "$C_WARN")ALL PLACEHOLDERS$(printf '%s' "$C_OFF")
  Tax          GST/HST rates installed; collection $(
	if [ "${MEO_TAX_ENABLED:-}" = "yes" ]; then printf 'ENABLED'; else printf 'OFF (small-supplier default)'; fi
)

  Next:
    - Read docs/canadian-tax.md before turning tax collection on.
    - Read docs/dropshipping-integration.md to choose a supplier connector.
    - Replace the placeholders:  wp eval 'meo_dropship_purge_placeholders();'

SUMMARY
