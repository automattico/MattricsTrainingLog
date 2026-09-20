#!/bin/sh
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
cd "$ROOT"

say() { printf '%s\n' "$*"; }
die() { printf 'deploy: %s\n' "$*" >&2; exit 1; }

env_file=${MATTRICS_ENV_FILE:-.env.local}
[ -f "$env_file" ] || die "$env_file missing — copy .env.example and fill it in"
# shellcheck disable=SC1090
. "$env_file"

: "${SFTP_PORT:=22}"
: "${SFTP_KNOWN_HOSTS:=deploy/known_hosts}"
: "${MATTRICS_REMOTE_DIR:=sites/mattrics}"

case "$SFTP_KNOWN_HOSTS" in /*) : ;; *) SFTP_KNOWN_HOSTS="$ROOT/$SFTP_KNOWN_HOSTS" ;; esac
case "${SFTP_KEY_PATH:-}" in '') : ;; /*) : ;; *) SFTP_KEY_PATH="$ROOT/$SFTP_KEY_PATH" ;; esac

for variable in SFTP_HOST SFTP_USER SFTP_KEY_PATH; do
  eval "value=\${$variable:-}"
  [ -n "$value" ] || die "missing $variable in $env_file"
done
[ -z "${SFTP_PASSWORD:-}" ] || die "password authentication is forbidden; remove SFTP_PASSWORD"
[ -f "$SFTP_KEY_PATH" ] || die "SSH key not found: $SFTP_KEY_PATH"
grep -q '^[^#[:space:]]' "$SFTP_KNOWN_HOSTS" 2>/dev/null || die "no host key pinned in $SFTP_KNOWN_HOSTS"
command -v lftp >/dev/null 2>&1 || die "lftp is required"

case "$MATTRICS_REMOTE_DIR" in
  sites/mattrics) : ;;
  public_html*|*/public_html*|/*|*..*|'') die "MATTRICS_REMOTE_DIR must be exactly sites/mattrics and never public_html" ;;
  *) die "MATTRICS_REMOTE_DIR must be exactly sites/mattrics" ;;
esac

say "Running production gate ..."
./scripts/prod-gate.sh

tmp=$(mktemp -d "${TMPDIR:-/tmp}/mattrics-deploy.XXXXXX")
trap 'rm -r "$tmp"' EXIT HUP INT TERM
mkdir -p "$tmp/stage/public" "$tmp/stage/api" "$tmp/stage/lib" "$tmp/stage/private/data"

for path in index.html robots.txt site.webmanifest assets icons; do
  [ -e "public/$path" ] || die "missing required public path: public/$path"
  cp -R "public/$path" "$tmp/stage/public/"
done
for name in session data settings connectors exercises ai; do
  [ -f "api/$name.php" ] || die "missing API endpoint: api/$name.php"
  cp "api/$name.php" "$tmp/stage/api/"
done
for path in lib/*.php; do
  [ -f "$path" ] || die "no shared PHP modules found"
  cp "$path" "$tmp/stage/lib/"
done
cp private/config.example.php "$tmp/stage/private/config.example.php"
for name in activity-type-configs exercise-configs exercise-dataset exercise-unknowns; do
  cp "private/data/$name.json" "$tmp/stage/private/data/$name.json"
done

CONNECT="ssh -a -x -l $SFTP_USER -o BatchMode=yes -o StrictHostKeyChecking=yes -o UserKnownHostsFile=$SFTP_KNOWN_HOSTS -o IdentitiesOnly=yes -i $SFTP_KEY_PATH"
[ -z "${SFTP_IDENTITY_AGENT:-}" ] || CONNECT="$CONNECT -o IdentityAgent=$SFTP_IDENTITY_AGENT"

lftp_head() {
  printf 'set sftp:connect-program "%s"\n' "$CONNECT"
  printf 'set net:max-retries 2\nset net:timeout 30\nset cmd:fail-exit yes\n'
  printf 'open "sftp://%s@%s:%s"\n' "$SFTP_USER" "$SFTP_HOST" "$SFTP_PORT"
}

say "Connecting to $SFTP_HOST ..."
{
  lftp_head
  printf 'cls -1a . > /dev/null\nbye\n'
} > "$tmp/ping.lftp"
lftp -f "$tmp/ping.lftp" 2>"$tmp/ping.err" || {
  sed -n '1,120p' "$tmp/ping.err" >&2
  die "connection failed"
}

say "Uploading application content to $MATTRICS_REMOTE_DIR ..."
{
  lftp_head
  printf 'set cmd:fail-exit no\n'
  printf 'mkdir -p "%s/public"\nmkdir -p "%s/api"\nmkdir -p "%s/lib"\nmkdir -p "%s/private/data"\n' \
    "$MATTRICS_REMOTE_DIR" "$MATTRICS_REMOTE_DIR" "$MATTRICS_REMOTE_DIR" "$MATTRICS_REMOTE_DIR"
  printf 'set cmd:fail-exit yes\n'
  for tree in public api lib; do
    printf 'mirror --reverse --delete --verbose=1 "%s/stage/%s" "%s/%s"\n' "$tmp" "$tree" "$MATTRICS_REMOTE_DIR" "$tree"
  done
  printf 'mirror --reverse --only-missing --verbose=1 "%s/stage/private" "%s/private"\n' "$tmp" "$MATTRICS_REMOTE_DIR"
  printf 'chmod 700 "%s" "%s/public" "%s/api" "%s/lib" "%s/private" "%s/private/data"\n' \
    "$MATTRICS_REMOTE_DIR" "$MATTRICS_REMOTE_DIR" "$MATTRICS_REMOTE_DIR" "$MATTRICS_REMOTE_DIR" "$MATTRICS_REMOTE_DIR" "$MATTRICS_REMOTE_DIR"
  for tree in public api lib; do
    find "$tmp/stage/$tree" -type d | while IFS= read -r directory; do
      relative=${directory#"$tmp/stage/$tree"}
      [ -z "$relative" ] || printf 'chmod 700 "%s/%s%s"\n' "$MATTRICS_REMOTE_DIR" "$tree" "$relative"
    done
    find "$tmp/stage/$tree" -type f | while IFS= read -r file; do
      relative=${file#"$tmp/stage/$tree/"}
      printf 'chmod 600 "%s/%s/%s"\n' "$MATTRICS_REMOTE_DIR" "$tree" "$relative"
    done
  done
  printf 'chmod 600 "%s/private/config.example.php"\n' "$MATTRICS_REMOTE_DIR"
  for name in activity-type-configs exercise-configs exercise-dataset exercise-unknowns; do
    printf 'chmod 600 "%s/private/data/%s.json"\n' "$MATTRICS_REMOTE_DIR" "$name"
  done
  printf 'bye\n'
} > "$tmp/upload.lftp"
lftp -f "$tmp/upload.lftp"

say "Verifying managed remote trees ..."
for tree in public api lib; do
  {
    lftp_head
    printf 'find "%s/%s"\nbye\n' "$MATTRICS_REMOTE_DIR" "$tree"
  } > "$tmp/verify-$tree.lftp"
  lftp -f "$tmp/verify-$tree.lftp" > "$tmp/remote-$tree" 2>"$tmp/verify-$tree.err" || {
    sed -n '1,120p' "$tmp/verify-$tree.err" >&2
    die "could not list remote $tree tree"
  }
  find "$tmp/stage/$tree" -type f | sed "s|$tmp/stage/$tree/||" | sort > "$tmp/expected-$tree"
  sed "s|^$MATTRICS_REMOTE_DIR/$tree/||" "$tmp/remote-$tree" | grep -v '^$' | grep -v '/$' | sort > "$tmp/actual-$tree"
  diff "$tmp/expected-$tree" "$tmp/actual-$tree" > "$tmp/diff-$tree" || {
    sed -n '1,160p' "$tmp/diff-$tree"
    die "remote $tree tree does not match the allowlist"
  }
done

{
  lftp_head
  printf 'cls -1 "%s/private/config.example.php"\n' "$MATTRICS_REMOTE_DIR"
  for name in activity-type-configs exercise-configs exercise-dataset exercise-unknowns; do
    printf 'cls -1 "%s/private/data/%s.json"\n' "$MATTRICS_REMOTE_DIR" "$name"
  done
  printf 'bye\n'
} > "$tmp/verify-private.lftp"
lftp -f "$tmp/verify-private.lftp" >/dev/null || die "required private seed files are missing"

say "Deploy complete. Managed remote trees match the explicit allowlist."
