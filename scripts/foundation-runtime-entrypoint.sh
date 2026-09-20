#!/bin/sh
set -eu

PRIVATE_RUNTIME_DIR=${MATTRICS_PRIVATE_RUNTIME_DIR:-/srv/mattrics-runtime}
LEGACY_PRIVATE_DIR=${MATTRICS_LEGACY_PRIVATE_DIR:-/srv/mattrics-legacy}
CONFIG_PATH=$PRIVATE_RUNTIME_DIR/config.php
FOUNDATION_DATABASE_URL=${MATTRICS_FOUNDATION_DATABASE_URL:-${DATABASE_URL:-}}
FOUNDATION_USER_KEY=${MATTRICS_FOUNDATION_USER_KEY:-legacy-local-user}
PORT=${PORT:-8080}

mkdir -p "$PRIVATE_RUNTIME_DIR" "$PRIVATE_RUNTIME_DIR/cache" "$PRIVATE_RUNTIME_DIR/data" "$PRIVATE_RUNTIME_DIR/import-hevy" "$PRIVATE_RUNTIME_DIR/import-garmin" "$PRIVATE_RUNTIME_DIR/storage"

if [ -d "$LEGACY_PRIVATE_DIR" ]; then
  for dir_name in data cache import-hevy import-garmin storage; do
    src_dir="$LEGACY_PRIVATE_DIR/$dir_name"
    dst_dir="$PRIVATE_RUNTIME_DIR/$dir_name"
    if [ -d "$src_dir" ]; then
      mkdir -p "$dst_dir"
      cp -R -n "$src_dir/." "$dst_dir/" 2>/dev/null || true
    fi
  done

  for file_name in user-settings.json exercise-ai.log; do
    src_file="$LEGACY_PRIVATE_DIR/$file_name"
    dst_file="$PRIVATE_RUNTIME_DIR/$file_name"
    if [ -f "$src_file" ] && [ ! -f "$dst_file" ]; then
      cp "$src_file" "$dst_file"
    fi
  done
fi

cat >"$CONFIG_PATH" <<EOF
<?php
declare(strict_types=1);

return [
    'foundation_database_url' => $(php -r 'echo var_export(getenv("MATTRICS_FOUNDATION_DATABASE_URL") ?: getenv("DATABASE_URL") ?: "", true);'),
    'foundation_user_key' => $(php -r 'echo var_export(getenv("MATTRICS_FOUNDATION_USER_KEY") ?: "legacy-local-user", true);'),
];
EOF

echo "Foundation runtime ready."
echo "  Private runtime dir=$PRIVATE_RUNTIME_DIR"
echo "  Canonical user key=$FOUNDATION_USER_KEY"
if [ -n "$FOUNDATION_DATABASE_URL" ]; then
  echo "  Canonical DB URL configured=yes"
else
  echo "  Canonical DB URL configured=no"
fi
echo "  Tip: run scripts/foundation-runtime-prepare.sh before relying on canonical reads."

exec php -S 0.0.0.0:"$PORT" -t public
