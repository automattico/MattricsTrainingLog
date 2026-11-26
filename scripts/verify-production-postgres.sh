#!/bin/sh
set -eu

if [ "$#" -ne 1 ] || [ ! -s "$1" ]; then
    printf 'Usage: %s /path/to/hetzner-sqlca.pem\n' "$0" >&2
    exit 1
fi

repo=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
ca_dir=$(CDPATH= cd -- "$(dirname -- "$1")" && pwd)
exec env -i PATH="$PATH" LC_ALL=C /bin/sh -s "$repo/.env.local" "$ca_dir/$(basename -- "$1")" <<'PROBE'
set -eu
die() { printf '%s\n' "$*" >&2; exit 1; }
[ -f "$1" ] && [ -r "$1" ] || die 'Production connection settings are missing.'

trim() {
    trimmed=${1#"${1%%[![:space:]]*}"}
    printf '%s' "${trimmed%"${trimmed##*[![:space:]]}"}"
}

# Load only the connection assignments. Their values are literal, including shell
# metacharacters in passwords. Ignore unrelated lines in the local environment file.
while IFS= read -r line || [ -n "$line" ]; do
    line=$(trim "$line")
    case "$line" in export\ *) line=${line#export } ;; esac
    key=${line%%=*}
    case "$key" in
        MATTRICS_PROD_PGHOST|MATTRICS_PROD_PGPORT|MATTRICS_PROD_PGDATABASE|MATTRICS_PROD_PGUSER|MATTRICS_PROD_PGPASSWORD)
            [ "$line" != "$key" ] || continue
            value=$(trim "${line#*=}")
            case "$value" in
                \'*\'|\"*\") value=${value#?}; value=${value%?} ;;
            esac
            export "$key=$value"
            ;;
    esac
done < "$1"

[ -n "${MATTRICS_PROD_PGHOST:-}" ] && [ -n "${MATTRICS_PROD_PGPORT:-}" ] &&
    [ -n "${MATTRICS_PROD_PGDATABASE:-}" ] && [ -n "${MATTRICS_PROD_PGUSER:-}" ] &&
    [ -n "${MATTRICS_PROD_PGPASSWORD:-}" ] || die 'Production connection settings are incomplete.'
case "$MATTRICS_PROD_PGHOST" in
    *[!a-zA-Z0-9.-]*|.*|*.) die 'Expected a single DNS hostname from konsoleH.' ;;
esac
case "$MATTRICS_PROD_PGPORT" in
    *[!0-9]*) die 'Expected a numeric Postgres port.' ;;
esac

# Start libpq with only these settings so inherited defaults cannot
# override hostname verification or select another endpoint.
export PGHOST="$MATTRICS_PROD_PGHOST" PGPORT="$MATTRICS_PROD_PGPORT"
export PGDATABASE="$MATTRICS_PROD_PGDATABASE" PGUSER="$MATTRICS_PROD_PGUSER"
export PGPASSWORD="$MATTRICS_PROD_PGPASSWORD"
export PGSSLMODE=verify-full PGSSLROOTCERT="$2" PGGSSENCMODE=disable
export PGCONNECT_TIMEOUT=8 PGOPTIONS='-c default_transaction_read_only=on -c statement_timeout=8000'
export PGSERVICEFILE=/dev/null PGPASSFILE=/dev/null
unset PGHOSTADDR PGSERVICE PGSSLCERT PGSSLKEY PGSSLNEGOTIATION PGREQUIRESSL

if ! result=$(psql -X -w -At -v ON_ERROR_STOP=1 -c 'SELECT 1' \
    -c 'SELECT version, cipher FROM pg_catalog.pg_stat_ssl WHERE pid = pg_backend_pid() AND ssl' 2>&1); then
    case "$result" in
        *certificate*|*'SSL error'*) die 'Production TLS server verification failed.' ;;
        *'password authentication failed'*) die 'Production database authentication failed.' ;;
        *'could not translate host name'*|*'timeout expired'*|*'Connection refused'*)
            die 'Production database could not be reached.' ;;
        *) die 'Production connection or read-only query failed.' ;;
    esac
fi

tls=$(printf '%s\n' "$result" | awk -F '|' '
    NR == 1 && $0 == "1" { query_ok = 1 }
    NR == 2 && NF == 2 && $1 ~ /^TLSv[0-9.]+$/ && $2 ~ /^[A-Za-z0-9_-]+$/ { tls = $1 " " $2 }
    END { if (NR != 2 || !query_ok || !tls) exit 1; print tls }
') || die 'Production response did not confirm SELECT 1 and TLS status.'
printf 'SELECT 1 passed. Server certificate and hostname verified. TLS: %s\n' "$tls"
PROBE
