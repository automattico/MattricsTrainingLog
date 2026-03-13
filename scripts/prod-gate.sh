#!/bin/sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
# shellcheck disable=SC1091
. "$SCRIPT_DIR/common.sh"

load_env
set_defaults

cd "$PROJECT_ROOT"

require_command git
require_command curl

if command -v php >/dev/null 2>&1; then
  php -v >/dev/null
fi

if command -v lftp >/dev/null 2>&1; then
  lftp --version >/dev/null
fi

if [ -f ".env.local" ]; then
  require_vars SFTP_HOST SFTP_PORT SFTP_USER SFTP_REMOTE_DIR
  require_any_auth
fi

log_info "Production gate passed."
