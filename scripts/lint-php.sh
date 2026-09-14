#!/usr/bin/env bash
#
# Syntax-check every PHP file in the project.
#
# Runs against the host's PHP (no container needed), so it works before the
# environment is up and in CI.
#
# Usage: npm run lint:php

set -euo pipefail

cd "$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"

if ! command -v php >/dev/null 2>&1; then
	echo "php not found on PATH — skipping lint." >&2
	exit 0
fi

fail=0
count=0

while IFS= read -r -d '' file; do
	count=$((count + 1))
	if ! php -l "$file" >/dev/null 2>&1; then
		echo "FAIL  $file"
		php -l "$file" || true
		fail=1
	fi
done < <(find wp-content scripts -name '*.php' -type f -print0)

if [ "$fail" -eq 0 ]; then
	echo "OK — ${count} PHP file(s), no syntax errors."
else
	echo "PHP syntax errors found." >&2
	exit 1
fi
