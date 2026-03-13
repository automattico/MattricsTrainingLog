#!/bin/sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
COMMON_SH_PATH="$SCRIPT_DIR/common.sh"
# shellcheck disable=SC1091
. "$SCRIPT_DIR/common.sh"

CHECK_ONLY=0
if [ "${1:-}" = "--check" ]; then
  CHECK_ONLY=1
fi

load_env
set_defaults

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
"

cd "$PROJECT_ROOT"

if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  tracked_changes=$(git status --short --untracked-files=no -- . ':(exclude)private/config.php' ':(exclude)public/config.js' ':(exclude).env.local')
  if [ -n "$tracked_changes" ] && [ "$CHECK_ONLY" -ne 1 ] && [ "${ALLOW_DIRTY_DEPLOY:-0}" != "1" ]; then
    log_error "Refusing to continue: tracked files have uncommitted changes."
    log_error "Commit first, or rerun with ALLOW_DIRTY_DEPLOY=1 if you really want to proceed with a dirty tree."
    exit 1
  fi

  for sensitive_path in $SENSITIVE_PATHS; do
    if git ls-files --error-unmatch "$sensitive_path" >/dev/null 2>&1; then
      log_error "Refusing to continue: sensitive file is tracked by git: $sensitive_path"
      exit 1
    fi
  done

  if git grep -nEI '(api[_-]?key|secret|password|token)[[:space:]]*[:=][[:space:]]*["'"'"'\''][^"'"'"'\'']{12,}["'"'"'\'']' -- public ':!public/config.js' >/dev/null 2>&1; then
    log_error "Refusing to continue: tracked public files contain a secret-like assignment."
    git grep -nEI '(api[_-]?key|secret|password|token)[[:space:]]*[:=][[:space:]]*["'"'"'\''][^"'"'"'\'']{12,}["'"'"'\'']' -- public ':!public/config.js' || true
    exit 1
  fi
fi

for path in $PUBLIC_REQUIRED_FILES private/config.php; do
  if [ "$path" = "private/config.php" ] && [ "$CHECK_ONLY" -eq 1 ] && [ ! -f "$path" ]; then
    require_file "private/config.example.php"
    continue
  fi
  require_file "$path"
done

if command -v php >/dev/null 2>&1; then
  for php_file in $PHP_LINT_FILES; do
    php -l "$php_file" >/dev/null
  done
  if [ -f "private/config.php" ]; then
    php -l "private/config.php" >/dev/null
  elif [ "$CHECK_ONLY" -eq 1 ]; then
    php -l "private/config.example.php" >/dev/null
  fi
elif [ "$CHECK_ONLY" -ne 1 ]; then
  log_error "Missing required command: php"
  exit 1
fi

if [ "$CHECK_ONLY" -eq 1 ]; then
  log_info "Predeploy guard check passed."
else
  log_info "Predeploy guard passed."
fi
