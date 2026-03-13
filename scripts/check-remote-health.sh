#!/bin/sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
COMMON_SH_PATH="$SCRIPT_DIR/common.sh"
# shellcheck disable=SC1091
. "$SCRIPT_DIR/common.sh"

load_env
set_defaults

require_command lftp
require_vars SFTP_HOST SFTP_PORT SFTP_USER SFTP_REMOTE_DIR
require_any_auth

tmp_file=$(mktemp)
trap 'rm -f "$tmp_file"' EXIT HUP INT TERM

{
  lftp_base_settings
  lftp_auth_settings
  printf '%s\n' "cls -1 \"$SFTP_REMOTE_DIR\""
  printf '%s\n' "bye"
} >"$tmp_file"

lftp -f "$tmp_file" >/dev/null
log_info "Remote health check passed."
