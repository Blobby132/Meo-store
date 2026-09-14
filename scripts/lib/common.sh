#!/usr/bin/env bash
#
# Shared helpers for the MEO setup scripts.
#
# Every script sources this and calls `wp` — never the wp-env command directly —
# so the same scripts run against wp-env locally and against a real host over
# SSH by exporting MEO_WP.
#
#   Local (default):  wp-env's CLI container
#   Remote:           MEO_WP="wp --path=/var/www/html" bash scripts/setup-store.sh
#   Docker-compose:   MEO_WP="docker compose run --rm wpcli wp" bash scripts/setup-store.sh

set -euo pipefail

# Path of ./scripts as seen from INSIDE the container (see .wp-env.json mappings).
# Override when running somewhere the repo is mounted elsewhere.
MEO_SCRIPTS_PATH="${MEO_SCRIPTS_PATH:-wp-content/meo-scripts}"

# Colours, but only when attached to a terminal.
if [ -t 1 ]; then
	C_BOLD=$'\033[1m'; C_DIM=$'\033[2m'; C_OK=$'\033[32m'; C_WARN=$'\033[33m'; C_ERR=$'\033[31m'; C_OFF=$'\033[0m'
else
	C_BOLD=""; C_DIM=""; C_OK=""; C_WARN=""; C_ERR=""; C_OFF=""
fi

step()  { printf '\n%s==>%s %s%s%s\n' "$C_OK" "$C_OFF" "$C_BOLD" "$*" "$C_OFF"; }
info()  { printf '    %s%s%s\n' "$C_DIM" "$*" "$C_OFF"; }
warn()  { printf '%s[warn]%s %s\n' "$C_WARN" "$C_OFF" "$*" >&2; }
fail()  { printf '%s[error]%s %s\n' "$C_ERR" "$C_OFF" "$*" >&2; exit 1; }

# Run a WP-CLI command.
wp() {
	if [ -n "${MEO_WP:-}" ]; then
		# Word splitting is intentional: MEO_WP carries a command prefix.
		# shellcheck disable=SC2086
		command ${MEO_WP} "$@"
	else
		npx --yes wp-env run --quiet cli wp "$@"
	fi
}

# Run a PHP file through WP-CLI, resolving it to the in-container path.
#
# Passing structured data as CLI arguments gets mangled by the layers between
# here and the container, so anything with quotes, ampersands or newlines goes
# through a file instead.
wp_php() {
	local script="$1"; shift
	wp eval-file "${MEO_SCRIPTS_PATH}/php/${script}" "$@"
}

# Abort early with a useful message if WordPress is not reachable.
require_wp() {
	if ! wp core is-installed --quiet 2>/dev/null; then
		fail "WordPress is not reachable or not installed.
    Start the environment first:  npm run env:start
    Or point the scripts at another install:  MEO_WP=\"wp --path=/path/to/wp\" ..."
	fi
}

# Abort if WooCommerce is not active.
require_woocommerce() {
	if ! wp plugin is-active woocommerce --quiet 2>/dev/null; then
		fail "WooCommerce is not active. Run: npm run setup"
	fi
}
