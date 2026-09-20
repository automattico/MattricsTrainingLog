#!/bin/sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
COMMON_SH_PATH="$SCRIPT_DIR/common.sh"
# shellcheck disable=SC1090
. "$COMMON_SH_PATH"

require_command curl
require_command php

base_url=${MATTRICS_FOUNDATION_BASE_URL:-http://127.0.0.1:8081}
expected_mode=${1:-canonical}

status_json=$(curl -fsS "$base_url/api/runtime-status.php")
data_json=$(curl -fsS "$base_url/api/data.php")

php -r '
$status = json_decode($argv[1], true);
$data = json_decode($argv[2], true);
$expectedMode = $argv[3];
if (!is_array($status) || !is_array($data)) {
    fwrite(STDERR, "Runtime check failed: endpoint output was not valid JSON.\n");
    exit(1);
}
if (($status["runtimeMode"] ?? "") !== "foundation") {
    fwrite(STDERR, "Runtime check failed: runtimeMode is not foundation.\n");
    exit(1);
}
if (($expectedMode === "canonical" && ($data["meta"]["source"] ?? "") !== "canonical")
    || ($expectedMode === "fallback" && ($data["meta"]["source"] ?? "") === "canonical")) {
    fwrite(STDERR, "Runtime check failed: data source did not match expected mode.\n");
    exit(1);
}
if ($expectedMode === "canonical" && ($status["canonicalReadStatus"] ?? "") !== "ready") {
    fwrite(STDERR, "Runtime check failed: canonical runtime is not ready.\n");
    exit(1);
}
if ($expectedMode === "fallback" && !in_array(($data["meta"]["source"] ?? ""), ["cache", "legacy"], true)) {
    fwrite(STDERR, "Runtime check failed: fallback mode did not return a legacy-backed source.\n");
    exit(1);
}
echo "Foundation runtime check passed.\n";
echo "  runtimeMode=" . ($status["runtimeMode"] ?? "unknown") . "\n";
echo "  canonicalReadStatus=" . ($status["canonicalReadStatus"] ?? "unknown") . "\n";
echo "  dataSource=" . ($data["meta"]["source"] ?? "unknown") . "\n";
' "$status_json" "$data_json" "$expected_mode"
