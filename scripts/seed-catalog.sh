#!/usr/bin/env bash
#
# Re-seed the MEO catalogue (categories + placeholder products) on its own.
#
# Usage: npm run setup:catalog

set -euo pipefail

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
# shellcheck source=lib/common.sh
source "${SCRIPT_DIR}/lib/common.sh"

require_wp
require_woocommerce

step "Seeding catalogue"
wp_php seed-catalog.php
