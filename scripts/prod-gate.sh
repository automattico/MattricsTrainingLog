#!/bin/sh
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$ROOT"

say() { printf '%s\n' "$*"; }
die() { printf 'prod-gate: %s\n' "$*" >&2; exit 1; }

command -v php >/dev/null 2>&1 || die "php is required"
command -v node >/dev/null 2>&1 || die "node is required"

say "Running predeploy source guard ..."
./scripts/predeploy-guard.sh --check

say "Linting PHP ..."
find api lib scripts tests -type f -name '*.php' -print | sort | while IFS= read -r file; do
  php -l "$file" >/dev/null
done

say "Checking shell syntax ..."
find . -maxdepth 2 -type f -name '*.sh' -print | sort | while IFS= read -r file; do
  sh -n "$file"
done

say "Running PHP tests ..."
for test_file in tests/*.php; do
  php "$test_file"
done

say "Running JavaScript tests ..."
for test_file in tests/js/*.js; do
  node "$test_file"
done

say "Production gate passed."
