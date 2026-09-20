#!/bin/sh
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$ROOT"

check_only=0
[ "${1:-}" = "--check" ] && check_only=1

die() { printf 'predeploy-guard: %s\n' "$*" >&2; exit 1; }

required_paths='public/index.html
public/assets/css/main.css
public/assets/js/app.js
api/session.php
api/data.php
api/settings.php
api/connectors.php
api/exercises.php
api/ai.php
lib/bootstrap.php
private/config.example.php
private/data/activity-type-configs.json
private/data/exercise-configs.json
private/data/exercise-dataset.json
private/data/exercise-unknowns.json'

printf '%s\n' "$required_paths" | while IFS= read -r path; do
  [ -f "$path" ] || die "missing required file: $path"
done

for forbidden in public/api public/views public/tests public/.htaccess public/index.php public/config.js public/config.example.js lib/WebAuthn; do
  [ ! -e "$forbidden" ] || die "obsolete deploy path still exists: $forbidden"
done

if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  for sensitive in .env.local private/config.php; do
    if git ls-files --error-unmatch "$sensitive" >/dev/null 2>&1; then
      die "sensitive file is tracked by git: $sensitive"
    fi
  done

  if [ "$check_only" -eq 0 ] && [ "${ALLOW_DIRTY_DEPLOY:-0}" != "1" ]; then
    changes=$(git status --short --untracked-files=no)
    [ -z "$changes" ] || die "tracked files are dirty; commit first or explicitly set ALLOW_DIRTY_DEPLOY=1"
  fi
fi

php tests/mattwarden-contract-tests.php
printf '%s\n' "Predeploy source guard passed."
