#!/bin/sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
COMMON_SH_PATH="$SCRIPT_DIR/common.sh"
# shellcheck disable=SC1091
. "$SCRIPT_DIR/common.sh"

load_env
set_defaults

require_command curl

if [ -z "${DEPLOY_URL:-}" ]; then
  log_info "Smoke test skipped: DEPLOY_URL is not set."
  exit 0
fi

curl -fsSI -L --max-time 10 "$DEPLOY_URL" >/dev/null
log_info "Smoke test passed for $DEPLOY_URL"
