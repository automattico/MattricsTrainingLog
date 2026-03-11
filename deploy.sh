#!/bin/sh
set -eu

PUBLIC_REQUIRED_FILES="
public/index.html
public/assets/css/main.css
public/assets/js/core.js
public/assets/js/data.js
public/api/data.php
"
SENSITIVE_PATHS="
private/config.php
public/config.js
.env.local
"
PHP_LINT_FILES="
public/api/bootstrap.php
public/api/data.php
public/api/ai.php
private/config.php
"

if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "Git status before deploy:"
  git status --short --untracked-files=all
  echo

  echo "Git diff summary:"
  git diff --stat --compact-summary HEAD -- . ':(exclude).env.local' ':(exclude)private/config.php' ':(exclude)public/config.js' || true
  echo

  tracked_changes=$(git status --short --untracked-files=no -- . ':(exclude)private/config.php' ':(exclude)public/config.js' ':(exclude).env.local')
  if [ -n "$tracked_changes" ] && [ "${ALLOW_DIRTY_DEPLOY:-0}" != "1" ]; then
    echo "Refusing to deploy: tracked files have uncommitted changes." >&2
    echo "Commit first, or rerun with ALLOW_DIRTY_DEPLOY=1 if you really want to deploy a dirty tree." >&2
    exit 1
  fi

  for sensitive_path in $SENSITIVE_PATHS; do
    if git ls-files --error-unmatch "$sensitive_path" >/dev/null 2>&1; then
      echo "Refusing to deploy: sensitive file is tracked by git: $sensitive_path" >&2
      exit 1
    fi
  done

  if git grep -nEI '(api[_-]?key|secret|password|token)[[:space:]]*[:=][[:space:]]*["'"'"'\''][^"'"'"'\'']{12,}["'"'"'\'']' -- public ':!public/config.js' >/dev/null 2>&1; then
    echo "Refusing to deploy: tracked public files contain a secret-like assignment. Review git grep matches before deploying." >&2
    git grep -nEI '(api[_-]?key|secret|password|token)[[:space:]]*[:=][[:space:]]*["'"'"'\''][^"'"'"'\'']{12,}["'"'"'\'']' -- public ':!public/config.js' || true
    exit 1
  fi
fi

if [ -f ".env.local" ]; then
  # shellcheck disable=SC1091
  . ./.env.local
fi

required_vars="
SFTP_HOST
SFTP_PORT
SFTP_USER
SFTP_REMOTE_DIR
SFTP_PASSWORD
"

for var in $required_vars; do
  eval "value=\${$var:-}"
  if [ -z "$value" ]; then
    echo "Missing required variable: $var" >&2
    exit 1
  fi
done

for path in $PUBLIC_REQUIRED_FILES private/config.php; do
  if [ ! -f "$path" ]; then
    echo "Missing required file: $path" >&2
    exit 1
  fi
done

echo "Deploy target:"
echo "  Host: $SFTP_HOST:$SFTP_PORT"
echo "  Public dir: $SFTP_REMOTE_DIR"

SFTP_REMOTE_PRIVATE_DIR=${SFTP_REMOTE_PRIVATE_DIR:-/mattrics-private}
echo "  Private dir: $SFTP_REMOTE_PRIVATE_DIR"
echo

echo "PHP syntax check:"
for php_file in $PHP_LINT_FILES; do
  php -l "$php_file" >/dev/null
  echo "  OK $php_file"
done
echo

lftp -u "$SFTP_USER","$SFTP_PASSWORD" "sftp://$SFTP_HOST:$SFTP_PORT" <<EOF
set ssl:verify-certificate yes
set net:max-retries 2
set net:timeout 20
mkdir -p "$SFTP_REMOTE_DIR"
mkdir -p "$SFTP_REMOTE_DIR/api"
mkdir -p "$SFTP_REMOTE_PRIVATE_DIR"
rm -f "$SFTP_REMOTE_DIR/config.js"
mirror --reverse --delete --verbose \
  --exclude-glob config.js \
  --exclude-glob .htpasswd \
  --exclude-glob .well-known \
  public "$SFTP_REMOTE_DIR"
put -O "$SFTP_REMOTE_PRIVATE_DIR" private/config.php
bye
EOF

if [ -n "${DEPLOY_URL:-}" ]; then
  echo "Smoke check:"
  curl -fsSI -L --max-time 10 "$DEPLOY_URL" >/dev/null
  echo "  OK $DEPLOY_URL"
else
  echo "Smoke check skipped: DEPLOY_URL is not set."
fi

echo "Deploy finished."
