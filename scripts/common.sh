#!/bin/sh
set -eu

COMMON_SH_PATH=${COMMON_SH_PATH:-$0}
PROJECT_ROOT=$(CDPATH= cd -- "$(dirname -- "$COMMON_SH_PATH")/.." && pwd)

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

require_key_auth() {
  # SSH key auth only. The key path may be a public key: with IdentitiesOnly the
  # private half is then looked up in the SSH agent (Bitwarden SSH agent).
  if [ -n "${SFTP_PASSWORD:-}" ]; then
    log_error "SFTP_PASSWORD is set — this deploy uses SSH key auth only; remove it from .env.local"
    exit 1
  fi
  if [ -z "${SFTP_KEY_PATH:-}" ]; then
    log_error "Missing deploy auth: set SFTP_KEY_PATH (see .env.example)"
    exit 1
  fi
  require_file "$SFTP_KEY_PATH"
  if ! grep -q '^[^#[:space:]]' "$SFTP_KNOWN_HOSTS" 2>/dev/null; then
    log_error "No host key pinned in $SFTP_KNOWN_HOSTS — run:"
    log_error "  ssh-keyscan -p $SFTP_PORT $SFTP_HOST >> deploy/known_hosts"
    log_error "  ssh-keygen -lf deploy/known_hosts   # compare with the konsoleH fingerprint"
    exit 1
  fi
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
  : "${SFTP_KNOWN_HOSTS:=$PROJECT_ROOT/deploy/known_hosts}"
  case "$SFTP_KNOWN_HOSTS" in /*) : ;; *) SFTP_KNOWN_HOSTS="$PROJECT_ROOT/$SFTP_KNOWN_HOSTS" ;; esac
  case "${SFTP_KEY_PATH:-}" in ''|/*) : ;; *) SFTP_KEY_PATH="$PROJECT_ROOT/$SFTP_KEY_PATH" ;; esac
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
  # -l passes the user because lftp does not hand it to the connect-program itself.
  # StrictHostKeyChecking=yes + the pinned known_hosts file make an unexpected host key fatal.
  connect="ssh -a -x -l $SFTP_USER -o BatchMode=yes -o StrictHostKeyChecking=yes -o UserKnownHostsFile=$SFTP_KNOWN_HOSTS -o IdentitiesOnly=yes -i $SFTP_KEY_PATH"
  if [ -n "${SFTP_IDENTITY_AGENT:-}" ]; then
    connect="$connect -o IdentityAgent=$SFTP_IDENTITY_AGENT"
  fi
  printf '%s\n' \
    "set sftp:connect-program \"$connect\"" \
    "open \"sftp://$SFTP_USER@$SFTP_HOST:$SFTP_PORT\""
}
