#!/bin/sh
set -eu

url=${MATTRICS_FOUNDATION_URL:-http://127.0.0.1:8081/index.html}

command -v curl >/dev/null 2>&1 || {
  printf '%s\n' "check-foundation-runtime: curl is required" >&2
  exit 1
}

curl -fsS --max-time 10 "$url" >/dev/null
printf '%s\n' "Foundation static-server readiness passed: $url"
printf '%s\n' "The Foundation HTTP application is intentionally not migrated to the Mattwarden router."
