#!/usr/bin/env bash
#
# Install or refresh the Canadian GST/HST rate table.
#
# Usage:
#   npm run setup:tax                      # rates only, collection stays OFF
#   MEO_TAX_ENABLED=yes npm run setup:tax  # rates + start charging tax
#
# Read docs/canadian-tax.md before enabling. Charging GST/HST without a CRA
# registration number is not permitted.

set -euo pipefail

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
# shellcheck source=lib/common.sh
source "${SCRIPT_DIR}/lib/common.sh"

require_wp
require_woocommerce

step "Configuring Canadian tax"
if [ "${MEO_TAX_ENABLED:-}" = "yes" ]; then
	wp_php configure-tax.php enable
else
	wp_php configure-tax.php
fi
