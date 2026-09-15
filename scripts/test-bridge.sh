#!/usr/bin/env bash
#
# End-to-end test of the dropship bridge against the mock adapter.
#
# Self-contained: enables the mock adapter, creates its own orders, then
# deletes them and restores the previous setting. Safe to re-run.
#
# Usage: npm run test:bridge

set -euo pipefail

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
# shellcheck source=lib/common.sh
source "${SCRIPT_DIR}/lib/common.sh"

require_wp
require_woocommerce

step "Testing the dropship bridge"
wp_php test-dropship-bridge.php
