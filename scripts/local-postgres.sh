#!/bin/sh
set -eu
umask 077

repo=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
uid=$(id -u)
root="/tmp/mattrics-st-postgres-$uid"
port=55432
database=mattrics_slice
if [ "${1:-}" = --staging ]; then
    root="$HOME/Library/Application Support/MattricsTrainingLog-Postgres"
    port=55434
    database=mattrics_staging
    shift
fi
data="$root/data"
config="$root/site/private/config.php"
action=${1:-}
if [ "$#" -gt 0 ]; then shift; fi

die() { printf '%s\n' "$*" >&2; exit 1; }

admin() {
    # Ignore inherited libpq connection environment and startup files.
    env -i PATH="$PATH" psql -X -v ON_ERROR_STOP=1 -h "$root" -p "$port" -U "$(id -un)" -d postgres "$@"
}

restore_check() (
    [ "$database" = mattrics_staging ] || die 'The restore rehearsal requires --staging.'
    backup_dir=$(mktemp -d "$root/recovery.XXXXXXXX")
    restore_db="mattrics_restore_$(date -u +%Y%m%d%H%M%S)_$$"
    archive="$backup_dir/staging.dump"
    check_sql="$repo/tests/postgres/verify-synthetic-restore.sql"

    admin -At -d "$database" -f "$check_sql" > "$backup_dir/source-check"
    env -i PATH="$PATH" pg_dump -w -h "$root" -p "$port" -U "$(id -un)" \
        -Fc -f "$archive" "$database"
    admin -c "CREATE DATABASE $restore_db OWNER $database TEMPLATE template0" >/dev/null
    trap 'admin -c "DROP DATABASE $restore_db" >/dev/null' EXIT
    trap 'exit 1' HUP INT TERM
    env -i PATH="$PATH" pg_restore -w -h "$root" -p "$port" -U "$(id -un)" \
        --no-owner --role="$database" --single-transaction -d "$restore_db" "$archive"
    admin -At -d "$restore_db" -f "$check_sql" > "$backup_dir/restore-check"
    cmp -s "$backup_dir/source-check" "$backup_dir/restore-check" ||
        die 'Restored rows differ from the staging snapshot.'
    trap - EXIT HUP INT TERM
    printf 'Verified synthetic counts and row contents (workouts, source records, links, exercises, sets).\n'
    printf 'Restore database: %s\nBackup: %s\n' "$restore_db" "$archive"
)

if [ "$action" = start ] && [ ! -e "$root" ]; then
    mkdir -m 700 "$root"
fi
[ ! -L "$root" ] && [ -d "$root" ] && [ -O "$root" ] &&
    [ "$(php -r 'echo decoct(fileperms($argv[1]) & 0777);' "$root")" = 700 ] ||
    die "Start a private local cluster with $0 start (expected a mode-700 directory at $root)."

case "$action" in
    start)
        if ! pg_ctl -D "$data" status >/dev/null 2>&1 &&
            env -i PATH="$PATH" pg_isready -h 127.0.0.1 -p "$port" -q; then
            die "Loopback port $port is occupied."
        fi
        if [ ! -s "$data/PG_VERSION" ]; then
            initdb -D "$data" -U "$(id -un)" --auth-local=peer --auth-host=scram-sha-256 --locale=C -E UTF8 >/dev/null
        fi
        if ! pg_ctl -D "$data" status >/dev/null 2>&1; then
            pg_ctl -D "$data" -l "$root/postgres.log" -o "-h 127.0.0.1 -p $port -k \"$root\"" -w start
        fi
        mkdir -p "$root/site/private" "$root/site/public"
        if [ ! -f "$config" ]; then
            ln -sfn "$repo/api" "$root/site/api"
            ln -sfn "$repo/lib" "$root/site/lib"
            password=$(openssl rand -hex 24)
            admin >/dev/null <<SQL
SET log_min_error_statement = 'panic';
\set VERBOSITY terse
SELECT CASE WHEN EXISTS (SELECT FROM pg_roles WHERE rolname = '$database')
    THEN 'ALTER ROLE' ELSE 'CREATE ROLE' END || ' $database LOGIN PASSWORD ' || quote_literal('$password')
\gexec
SQL
            # A restart after an interrupted setup can reuse the database.
            if [ "$(admin -Atc "SELECT count(*) FROM pg_database WHERE datname = '$database'")" = 0 ]; then
                admin -c "CREATE DATABASE $database OWNER $database" >/dev/null
            fi
            cat > "$config.new" <<PHP
<?php
return [
    'foundation_database_url' => 'postgres://$database:$password@127.0.0.1:$port/$database',
    'foundation_user_key' => 'synthetic-local',
    'foundation_linked_workouts' => true,
];
PHP
            mv "$config.new" "$config"
        fi
        printf 'Local Postgres at 127.0.0.1:%s, database %s\n' "$port" "$database"
        ;;
    stop)
        if pg_ctl -D "$data" status >/dev/null 2>&1; then
            pg_ctl -D "$data" -m fast -w stop
        fi
        ;;
    migrate|import)
        [ -f "$config" ] || die 'Run start first.'
        exec env -i PATH="$PATH" php "$repo/scripts/postgres/cli.php" "$config" "$action" "$@"
        ;;
    history-import|history-report|history-review|history-decide|history-compare-legacy)
        [ "$database" = mattrics_slice ] && [ -f "$config" ] || die 'Start the loopback development cluster first.'
        if [ "$action" = history-import ] &&
            [ "$(admin -Atc "SELECT count(*) FROM pg_database WHERE datname = 'mattrics_history'")" = 0 ]; then
            admin -c "CREATE DATABASE mattrics_history OWNER mattrics_slice TEMPLATE template0" >/dev/null
            admin -c "ALTER DATABASE mattrics_history SET log_min_messages = 'panic'" >/dev/null
            admin -c "ALTER DATABASE mattrics_history SET log_min_error_statement = 'panic'" >/dev/null
        fi
        exec env -i PATH="$PATH" php "$repo/scripts/postgres/archive-cli.php" "$config" "$action" "$@"
        ;;
    history-api-audit)
        [ "$database" = mattrics_slice ] && [ -f "$config" ] || die 'Start the loopback development cluster first.'
        exec env -i PATH="$PATH" php "$repo/tests/postgres/history-api-audit.php" "$config"
        ;;
    test)
        [ -f "$config" ] || die 'Run start first.'
        test_db="mattrics_test_${uid}_$$"
        admin -c "CREATE DATABASE $test_db OWNER $database" >/dev/null
        trap 'admin -c "DROP DATABASE $test_db" >/dev/null' EXIT
        trap 'exit 1' HUP INT TERM
        env -i PATH="$PATH" php "$repo/tests/postgres/integration.php" "$config" "$test_db"
        ;;
    history-restore-check)
        [ "$database" = mattrics_slice ] || die 'Use the loopback development cluster.'
        backup=$(mktemp -d "$root/history-recovery.XXXXXXXX")
        restored="mattrics_restore_${uid}_$$"
        catalog="$repo/private/data"
        mkdir "$backup/catalog" "$backup/restored-catalog"
        for name in exercise-configs activity-type-configs exercise-unknowns exercise-dataset; do
            cp "$catalog/$name.json" "$backup/catalog/$name.json"
        done
        env -i PATH="$PATH" pg_dump -w -h "$root" -p "$port" -U "$(id -un)" \
            -Fc -f "$backup/history.dump" mattrics_history
        admin -c "CREATE DATABASE $restored OWNER mattrics_slice TEMPLATE template0" >/dev/null
        env -i PATH="$PATH" pg_restore -w -h "$root" -p "$port" -U "$(id -un)" \
            --no-owner --role=mattrics_slice --single-transaction -d "$restored" "$backup/history.dump"
        for db in mattrics_history "$restored"; do
            admin -At -d "$db" -f "$repo/tests/postgres/table-fingerprints.sql" > "$backup/$db.check"
        done
        cmp -s "$backup/mattrics_history.check" "$backup/$restored.check" || die 'Database reconciliation failed.'
        for source in "$backup/catalog/"*.json; do
            name=${source##*/}
            cp "$source" "$backup/restored-catalog/$name"
            cmp -s "$catalog/$name" "$backup/restored-catalog/$name" || die 'Private catalog changed during backup.'
        done
        env -i PATH="$PATH" php "$repo/tests/postgres/history-recovery.php" "$config" "$restored" "$backup"
        printf 'Verified isolated restore in %s with private backup at %s\n' "$restored" "$backup"
        ;;
    restore-check) restore_check ;;
    *) die "Usage: $0 [--staging] {start|stop|migrate|import [--dry-run]|test|restore-check|history-restore-check|history-import|history-report|history-review|history-decide|history-compare-legacy|history-api-audit}" ;;
esac
