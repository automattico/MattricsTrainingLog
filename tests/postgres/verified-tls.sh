#!/bin/sh
set -eu
repo=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
tmp=$(mktemp -d /tmp/mattrics-tls.XXXXXX)
cleanup() {
    if [ -f "$tmp/db/postmaster.pid" ]; then
        pg_ctl -D "$tmp/db" -m immediate -w stop >/dev/null
    fi
    rm -r "$tmp"
}
trap cleanup EXIT
trap 'exit 1' HUP INT TERM
umask 077
port=$(php -r '$s=stream_socket_server("tcp://127.0.0.1:0"); if ($s === false) exit(1); echo substr(strrchr(stream_socket_get_name($s, false), ":"), 1);')
initdb -A trust -D "$tmp/db" >/dev/null
mkdir -p "$tmp/test site/private"
openssl req -new -x509 -nodes -days 1 -newkey rsa:2048 -subj /CN=localhost \
    -addext subjectAltName=DNS:localhost -keyout "$tmp/db/server.key" -out "$tmp/db/server.crt" 2>/dev/null
chmod 600 "$tmp/db/server.key"
cp "$tmp/db/server.crt" "$tmp/test site/private/postgres-ca.pem"
pg_ctl -D "$tmp/db" -l "$tmp/postgres.log" -o "-h 127.0.0.1 -p $port -k $tmp -c ssl=on" -w start >/dev/null
php -r '
define("MATTWARDEN_SITE_DIR", $argv[2]);
require $argv[1] . "/lib/foundation-read.php";
$url = static fn(string $host): string => "postgres://" . rawurlencode($argv[4]) . "@$host:" . $argv[3] . "/postgres";
$pdo = mattrics_foundation_read_connect($url("localhost"));
if ((int) $pdo->query("SELECT 1")->fetchColumn() !== 1 || !$pdo->query(
    "SELECT ssl FROM pg_catalog.pg_stat_ssl WHERE pid = pg_backend_pid()"
)->fetchColumn()) {
    throw new RuntimeException("The application connection did not use TLS.");
}
try {
    mattrics_foundation_read_connect($url("127.0.0.1"));
    throw new LogicException("The application accepted an incorrect certificate hostname.");
} catch (PDOException $failure) {
    if (!str_contains($failure->getMessage(), "does not match host name")) {
        throw new RuntimeException("Wrong-host control failed for an unexpected reason.");
    }
}
echo "Application Postgres connection verified TLS and rejected the wrong hostname.\n";
' "$repo" "$tmp/test site" "$port" "$(id -un)"
php -r '
$url = "postgres://" . rawurlencode($argv[3]) . "@localhost:" . $argv[2] . "/postgres";
file_put_contents($argv[1], "<?php return " . var_export(["foundation_database_url" => $url], true) . ";");
' "$tmp/config.php" "$port" "$(id -un)"
chmod 600 "$tmp/config.php"
php "$repo/scripts/postgres/hosted.php" probe "$tmp/config.php" "$tmp/db/server.crt" localhost postgres
php "$repo/scripts/postgres/hosted.php" migrate "$tmp/config.php" "$tmp/db/server.crt" localhost postgres
[ "$(php "$repo/scripts/postgres/hosted.php" migrate "$tmp/config.php" "$tmp/db/server.crt" localhost postgres)" = 'No pending migrations.' ]
printf '%s\n' 'Verified hosted migration runner and migration replay against the disposable TLS server.'
