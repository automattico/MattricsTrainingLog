# Database environments

| Environment | Endpoint | Database and login role | Data directory |
|---|---|---|---|
| Dev | `127.0.0.1:55432` | `mattrics_slice` | `/tmp/mattrics-st-postgres-$(id -u)/data` |
| Staging | `127.0.0.1:55434` | `mattrics_staging` | `$HOME/Library/Application Support/MattricsTrainingLog-Postgres/data` |
| Production | PostgreSQL hostname from konsoleH and port from Hetzner | Final name and login from konsoleH | Managed by Hetzner |

Run the local commands from the repository root with native PostgreSQL client and server tools on `PATH`, plus OpenSSL, PHP with `pdo_pgsql`, and Node.js for the dashboard analysis checks. Use a session permitted to open local Postgres sockets, bind loopback for the integration test router, and write to the staging directory. The scripts use their own loopback ports and private admin sockets.

## Dev

Use the existing dev cluster. If it is stopped, run `scripts/local-postgres.sh start` before these checks.

```sh
scripts/local-postgres.sh migrate
scripts/local-postgres.sh migrate
scripts/local-postgres.sh test
```

Expect zero pending migrations on the previously migrated dev database. The integration test also creates a scratch database, applies the baseline, checks the ledger version, checksum and timestamp on replay, then exercises imports and dashboard reads before dropping the scratch database.

## Staging

```sh
scripts/local-postgres.sh --staging start
scripts/local-postgres.sh --staging migrate
scripts/local-postgres.sh --staging migrate
scripts/local-postgres.sh --staging import --dry-run
scripts/local-postgres.sh --staging import
scripts/local-postgres.sh --staging test
scripts/local-postgres.sh --staging restore-check
scripts/local-postgres.sh --staging stop
scripts/local-postgres.sh --staging start
scripts/local-postgres.sh --staging migrate
```

The first start creates a mode-700 private directory under Application Support, a peer-authenticated Unix socket, and a SCRAM login restricted to a loopback listener. The login is stored in a mode-600 local config. Leave the staging database reserved for the bundled synthetic fixtures.

The rehearsal creates a custom-format archive in a new private `recovery.*` directory beside the cluster data. It restores transactionally into a new database on the staging cluster. It checks two workouts, two source records, two source links, two exercises, and three sets, including their link targets, template IDs, weight, reps, RPE, time, and distance. It then compares counts and ordered row fingerprints for all canonical-schema tables. A successful run prints and retains the archive path and restored database for inspection. Each rerun creates its own archive and database. Keep database files, dumps, and fixture fingerprints outside Git.

## Production TLS probe

After the owner confirms the empty final database, **Require SSL**, and the five `MATTRICS_PROD_PG*` settings in the ignored `.env.local`, save the public CA PEM block from [Hetzner’s PostgreSQL guide](https://docs.hetzner.com/managed/databases/postgresql/#encrypted-access) outside Git. Use the database hostname from [konsoleH connection details](https://docs.hetzner.com/managed/databases/general/connection-details-database/).

Put each setting on a separate `KEY=value` line. The probe reads values literally and supports surrounding single or double quotes.

```sh
scripts/verify-production-postgres.sh /absolute/path/to/hetzner-sqlca.pem
```

The probe uses libpq `verify-full` with that certificate and sets read-only transactions for the connection. It runs `SELECT 1` and reads the session’s TLS version and cipher from `pg_stat_ssl`. The output reports connection status without the database identifiers or credentials. Schema changes, imports, scheduled jobs, and dashboard cutover require separate approval.

### Verify the application path and prepare the schema

The application reads its CA from `MATTWARDEN_SITE_DIR/private/postgres-ca.pem`. Keep it outside the managed upload set. A hosted connection requires `verify-full`. Local development and staging use the existing numeric loopback addresses. Run the following from the reviewed revision on a private machine with access to the confirmed target. Use a mode-600 PHP configuration file outside the checkout with `foundation_database_url`, and pass the expected hostname and database explicitly.

```sh
php scripts/postgres/hosted.php probe "$private_config" "$ca_file" "$expected_host" "$expected_database"
```

After approval for schema changes, run the same command with `migrate`. Re-run it and verify zero pending versions. Keep preparation separate from changing the web application's read configuration. Use the role authorized to create the schema and extensions and a separately scoped application role.

## Historical import preview

The historical preview uses `mattrics_history` on the native loopback development cluster. It uses its own source records, settings for the isolated API audit, and archive checkpoints. Python 3.9+ with the standard library and the existing PHP/Postgres tooling are sufficient. Use the original, authorized archives from a private directory outside the repository.

```sh
scripts/local-postgres.sh start
python3 -B scripts/postgres/archive_preview.py garmin "$garmin_archive"
python3 -B scripts/postgres/archive_preview.py strava "$strava_archive"

python3 -B scripts/postgres/archive_preview.py garmin "$garmin_archive" --import-local
python3 -B scripts/postgres/archive_preview.py strava "$strava_archive" --import-local
scripts/local-postgres.sh history-report
scripts/local-postgres.sh history-api-audit
scripts/local-postgres.sh history-compare-legacy
```

Dry runs print aggregate counts, reasons and date coverage without connecting to Postgres. Import writes batches of up to 100 records. Add `--max-batches 1` to stop after a committed batch. Repeat the same command without the limit to resume. An unchanged completed archive writes zero further batches. A changed decoded manifest for the same archive digest requires a fresh preview so partially imported records keep consistent identities. The legacy comparison reads the existing local training-data cache if present.

Use this review workflow inside a private terminal. The queue includes opaque source-record IDs, elapsed and start deltas, and decision state. `history-review exact` lists existing exact pairs eligible for split. Original files and input diagnostics with their locators stay private.

```sh
scripts/local-postgres.sh history-review
scripts/local-postgres.sh history-review exact
scripts/local-postgres.sh history-decide keep_separate "$garmin_record_uuid" "$strava_record_uuid"
```

The decision action also accepts `link` for two independent candidate workouts, `split` for an exact pair, and `undo` for a saved decision. Re-run the history report and dashboard API audit after decisions. The audit uses an ephemeral authenticated local test router.
