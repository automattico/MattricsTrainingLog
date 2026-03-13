#!/bin/sh
set -eu

PROJECT_ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)

load_env() {
  if [ -f "$PROJECT_ROOT/.env.local" ]; then
    # shellcheck disable=SC1091
    . "$PROJECT_ROOT/.env.local"
  fi
}

log_info() {
  printf '%s\n' "$*"
}

log_error() {
  printf '%s\n' "$*" >&2
}

require_command() {
  if ! command -v "$1" >/dev/null 2>&1; then
    log_error "Missing required command: $1"
    exit 1
  fi
}

require_file() {
  if [ ! -f "$1" ]; then
    log_error "Missing required file: $1"
    exit 1
  fi
}

require_any_auth() {
  if [ -n "${SFTP_KEY_PATH:-}" ]; then
    require_file "$SFTP_KEY_PATH"
    return
  fi

  if [ -n "${SFTP_PASSWORD:-}" ]; then
    return
  fi

  log_error "Missing deploy auth: set SFTP_KEY_PATH or SFTP_PASSWORD"
  exit 1
}

require_vars() {
  for var in "$@"; do
    eval "value=\${$var:-}"
    if [ -z "$value" ]; then
      log_error "Missing required variable: $var"
      exit 1
    fi
  done
}

set_defaults() {
  : "${SFTP_PORT:=22}"
  : "${SFTP_REMOTE_PRIVATE_DIR:=/mattrics-private}"
}

sftp_open_target() {
  printf 'sftp://%s:%s' "$SFTP_HOST" "$SFTP_PORT"
}

lftp_base_settings() {
  printf '%s\n' \
    "set ssl:verify-certificate yes" \
    "set net:max-retries 2" \
    "set net:timeout 20" \
    "set cmd:fail-exit yes"
}

lftp_auth_settings() {
  if [ -n "${SFTP_KEY_PATH:-}" ]; then
    printf '%s\n' \
      "set sftp:connect-program \"ssh -a -x -i $SFTP_KEY_PATH\"" \
      "open \"sftp://$SFTP_USER@$SFTP_HOST:$SFTP_PORT\""
    return
  fi

  printf '%s\n' "open -u \"$SFTP_USER\",\"$SFTP_PASSWORD\" \"$(sftp_open_target)\""
}
