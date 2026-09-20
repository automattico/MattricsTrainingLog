#!/bin/sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
COMMON_SH_PATH="$SCRIPT_DIR/scripts/common.sh"
if [ ! -f "$COMMON_SH_PATH" ]; then
  COMMON_SH_PATH="$SCRIPT_DIR/common.sh"
fi
# shellcheck disable=SC1090
. "$COMMON_SH_PATH"

cd "$PROJECT_ROOT"

require_command php

database_url=${MATTRICS_FOUNDATION_DATABASE_URL:-${DATABASE_URL:-${1:-}}}
user_key=${MATTRICS_FOUNDATION_USER_KEY:-legacy-local-user}
display_name=${MATTRICS_FOUNDATION_DISPLAY_NAME:-Legacy Local User}
timezone_name=${MATTRICS_FOUNDATION_TIMEZONE:-Europe/Berlin}
private_root=${MATTRICS_PRIVATE_RUNTIME_DIR:-$PROJECT_ROOT/private}

if [ -z "$database_url" ]; then
  log_error "Missing canonical database URL. Set MATTRICS_FOUNDATION_DATABASE_URL or DATABASE_URL."
  exit 1
fi

php scripts/bootstrap-foundation.php \
  --database-url="$database_url" \
  --user-key="$user_key" \
  --display-name="$display_name" \
  --timezone="$timezone_name" \
  --private-root="$private_root"

if [ -f "$private_root/import-hevy/workouts.csv" ]; then
  php scripts/import-hevy.php \
    --database-url="$database_url" \
    --user-key="$user_key" \
    --display-name="$display_name" \
    --timezone="$timezone_name" \
    --private-root="$private_root" \
    --input=import-hevy/workouts.csv
else
  log_info "No Hevy import file found at $private_root/import-hevy/workouts.csv; skipping direct Hevy import."
fi

if [ -f "$private_root/import-garmin/Activities.csv" ]; then
  php scripts/import-garmin.php \
    --database-url="$database_url" \
    --user-key="$user_key" \
    --display-name="$display_name" \
    --timezone="$timezone_name" \
    --private-root="$private_root" \
    --input=import-garmin/Activities.csv
else
  log_info "No Garmin import file found at $private_root/import-garmin/Activities.csv; skipping direct Garmin import."
fi

php scripts/sync-live-connectors.php \
  --database-url="$database_url" \
  --user-key="$user_key" \
  --display-name="$display_name" \
  --timezone="$timezone_name" \
  --private-root="$private_root"
