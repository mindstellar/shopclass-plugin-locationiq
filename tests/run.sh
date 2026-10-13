#!/usr/bin/env bash
# Unit tests: no database, no network. They load core's geocode classes from
# SHOPCLASS_CORE, or ../osclass.
set -u
cd "$(dirname "$0")/.."
fail=0
for t in tests/*.php; do
    echo "── $t"
    php "$t" | tail -3
    [ "${PIPESTATUS[0]}" -eq 0 ] || fail=1
done
exit "$fail"
