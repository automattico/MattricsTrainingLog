#!/bin/sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
COMMON_SH_PATH="$SCRIPT_DIR/scripts/common.sh"
# shellcheck disable=SC1091
. "$SCRIPT_DIR/scripts/common.sh"

load_env
set_defaults

cd "$PROJECT_ROOT"

require_command lftp
require_command php
require_vars SFTP_HOST SFTP_PORT SFTP_USER SFTP_REMOTE_DIR
require_any_auth

log_info "Git status before deploy:"
git status --short --untracked-files=all || true
log_info ""
log_info "Git diff summary:"
git diff --stat --compact-summary HEAD -- . ':(exclude).env.local' ':(exclude)private/config.php' ':(exclude)public/config.js' || true
log_info ""

"$PROJECT_ROOT/scripts/prod-gate.sh"
"$PROJECT_ROOT/scripts/predeploy-guard.sh"

log_info "Deploy target:"
log_info "  Host: $SFTP_HOST:$SFTP_PORT"
log_info "  Public dir: $SFTP_REMOTE_DIR"
log_info "  Private dir: $SFTP_REMOTE_PRIVATE_DIR"

tmp_file=$(mktemp)
trap 'rm -f "$tmp_file"' EXIT HUP INT TERM

{
  lftp_base_settings
  lftp_auth_settings
  printf '%s\n' "set cmd:fail-exit no"
  printf '%s\n' "mkdir \"$SFTP_REMOTE_DIR\""
  printf '%s\n' "mkdir \"$SFTP_REMOTE_DIR/api\""
  printf '%s\n' "mkdir \"$SFTP_REMOTE_PRIVATE_DIR\""
  printf '%s\n' "set cmd:fail-exit yes"
  printf '%s\n' "rm -f \"$SFTP_REMOTE_DIR/config.js\""
  printf '%s\n' "mirror --reverse --delete --verbose --exclude-glob config.js --exclude-glob .htpasswd --exclude-glob .well-known public \"$SFTP_REMOTE_DIR\""
  printf '%s\n' "put -O \"$SFTP_REMOTE_PRIVATE_DIR\" private/config.php"
  printf '%s\n' "bye"
} >"$tmp_file"

lftp -f "$tmp_file"

"$PROJECT_ROOT/scripts/smoke-test.sh"

log_info "Deploy finished."
