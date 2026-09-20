#!/usr/bin/env bash
set -euo pipefail

repo_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
host=${MATTRICS_DEV_HOST:-127.0.0.1}
port=${MATTRICS_DEV_PORT:-8080}
origin=${MATTRICS_DEV_ORIGIN:-http://127.0.0.1:$port}
site_dir=$(mktemp -d "${TMPDIR:-/tmp}/mattrics-dev.XXXXXX")

cleanup() {
  rm -r "$site_dir"
}
trap cleanup EXIT INT TERM

mkdir -p "$site_dir/public" "$site_dir/api" "$site_dir/lib" "$site_dir/private"
cp -R "$repo_root/public/." "$site_dir/public/"
cp -R "$repo_root/api/." "$site_dir/api/"
cp -R "$repo_root/lib/." "$site_dir/lib/"
if [[ -f "$repo_root/private/config.php" ]]; then
  cp "$repo_root/private/config.php" "$site_dir/private/config.php"
else
  cp "$repo_root/private/config.example.php" "$site_dir/private/config.php"
fi
cp -R "$repo_root/private/data" "$site_dir/private/data"
if [[ -d "$repo_root/private/cache" ]]; then
  cp -R "$repo_root/private/cache" "$site_dir/private/cache"
fi
if [[ -f "$repo_root/private/user-settings.json" ]]; then
  cp "$repo_root/private/user-settings.json" "$site_dir/private/user-settings.json"
fi

chmod 700 "$site_dir" "$site_dir/public" "$site_dir/api" "$site_dir/lib" "$site_dir/private" "$site_dir/private/data"
find "$site_dir" -type d -exec chmod 700 {} +
find "$site_dir" -type f -exec chmod 600 {} +

export MATTWARDEN_TEST_SITE_DIR="$site_dir"
export MATTWARDEN_TEST_ORIGIN="$origin"
echo "Mattrics local gate: http://$host:$port"
exec php -S "$host:$port" "$repo_root/scripts/dev-router.php"
