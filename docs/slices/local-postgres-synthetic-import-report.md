# Slice report · Local Postgres and synthetic imports

**23 November 2026 · MattricsTrainingLog · `main`**

## Summary

`ST-POSTGRES` and its two tasks are verified. The native PostgreSQL 18.3 cluster is running on `127.0.0.1:55432` with a dedicated `mattrics_slice` database seeded with one synthetic Garmin workout and one synthetic Hevy workout. Setup uses the installed Postgres tools and PHP's `pdo_pgsql`.

## Foundation comparison

The Foundation schema and CLI scripts from `b97cab4^` (`93a01d0`) used a checked migration ledger, UUID activities, per-source activity keys, import batches, and child exercise and set rows. The current PHP read contract still uses those table names, the user lookup, legacy worksheet fields, batch timestamps, and optional strength children. The slice reuses the retained migration runner with an explicit local migration directory, the PHP user and batch helpers, Hevy REST and structured-set mapping, and the canonical dashboard response.

The historical Garmin CSV importer generated IDs from mutable display and measurement fields. The new decoded-session adapter uses an external ID from the source and keeps original timestamps, units, and the provider-shaped JSON in a separate source record. The historical Hevy mapper dropped template IDs. This slice preserves template IDs and provided set values beside the existing derived load fields. One baseline migration adds the compatibility tables and explicit source links. Health observations and catalog administration remain separate.

## Database and migrations

- ✅ `001_workouts.sql` applied in a fresh database. Its second run applied zero versions and preserved the version, SHA-256 checksum, and application timestamp.
- ✅ Native stop, restart, and a second start succeeded. The stopped and restarted cluster retained the applied version and imported fixtures.
- ✅ The private directory is `/tmp/mattrics-st-postgres-$(id -u)` with mode 700. Database files live in `data/` beneath it. The generated login stays in its mode-600 local `site/private/config.php`. Administrative commands use the private peer-authenticated socket.

## Import and API readback

- ✅ The synthetic Garmin source retained the original `+02:00` timestamps, metric units, external ID, and decoded session. Postgres contained the normalized UTC start and end, 31 elapsed minutes, 1,800 timer seconds, 5.04 km, 42 m ascent, and 148/167 bpm.
- ✅ The synthetic Hevy REST-shaped record retained its offset, original payload and units, and separate exercise and set rows. The isolated local Mattwarden test router served `GET /api/data?includeChildren=1` over HTTP with canonical metadata and no fallback warning. It returned the edited workout, both template IDs, and structured set type, weight, reps, RPE, time, and distance.
- ✅ The seeded database also returned two canonical workouts through the local HTTP route, including the Hevy template ID. Integration tests use a fresh temporary database on the same cluster and remove it after the run.

## Replay and matching

- ✅ An identical Hevy import preserved the source, workout, exercise, and set IDs. Editing the workout title, exercise title, weight, and reps updated the same records. Postgres rejected a direct duplicate within the same provider account and allowed the same external ID in another account.
- ✅ A synthetic Garmin and Strava pair carried the same SHA-256 and exactly equal session start and end after UTC normalization. Two source records and a saved evidence link resolved to one canonical workout. The API returned Strava's display labels with Garmin measurements. The reverse import order also preserved the canonical ID and made Garmin the measurement source.
- ✅ Similar records with a different digest or different session boundary remained separate in the database and API response. The fixture starts at the decoded FIT boundary with synthetic digests. Binary FIT decoding, fuzzy matching, and manual review remain open.

## Dry run and failed batch

- ✅ Compared full table contents before and after a dry run. Workout, source, link, strength, and run bookkeeping rows were identical.
- ✅ The third row in a batch violated a numeric check (`23514`). The preceding update and insert rolled back. A failed run recorded the row number and SQLSTATE with zero applied workouts.

## Application and planning checks

- ✅ `scripts/local-postgres.sh test` passed 22 assertions.
- ✅ `scripts/prod-gate.sh` passed the source guard, PHP and shell lint, PHP suites, dev-router integration, and JavaScript suites. Native loopback access was needed for the router tests and local Postgres.
- ✅ The planning validator, planning report fixture, and development and staging builds passed. The story and both tasks show as verified. The authorized provider sample, backup and restore, and release checks remain open.

## Start, import, rerun, and stop

Run from the repository root with `initdb`, `pg_ctl`, `pg_isready`, `psql`, `openssl`, and PHP with `pdo_pgsql` on the local machine.

```sh
scripts/local-postgres.sh start
scripts/local-postgres.sh migrate
scripts/local-postgres.sh migrate
scripts/local-postgres.sh import --dry-run
scripts/local-postgres.sh import
scripts/local-postgres.sh test
```

`import` replays the bundled fixtures in the seeded database. `test` provisions a fresh database for both migration passes and all import and API assertions. To stop or resume the same cluster, run:

```sh
scripts/local-postgres.sh stop
scripts/local-postgres.sh start
```

To read back the seeded database manually, start the test router in one terminal and stop it with Ctrl-C when finished:

```sh
mattrics_site="/tmp/mattrics-st-postgres-$(id -u)/site"
MATTWARDEN_TEST_SITE_DIR="$mattrics_site" MATTWARDEN_TEST_AUTHENTICATED=1 \
  php -S 127.0.0.1:55433 -t "$mattrics_site/public" scripts/dev-router.php
```

```sh
curl --fail 'http://127.0.0.1:55433/api/data?includeChildren=1'
```

Planning checks:

```sh
ruby tools/planning-report/planning.rb validate
ruby tools/planning-report/test.rb
ruby tools/planning-report/planning.rb build development
ruby tools/planning-report/planning.rb build staging
```

## Files changed

- `scripts/local-postgres.sh` and `scripts/postgres/` provision, migrate, and import into the isolated cluster.
- `lib/foundation-import.php` retains Hevy template IDs and structured source values. `lib/foundation-read.php` reads linked workouts and their child fields when the explicit local configuration is enabled.
- `tests/postgres/integration.php` and `tests/fixtures/postgres-workouts.json` hold the assertions and synthetic input.
- The architecture brief, implementation progress, three backlog records, and this slice report document the result and handoff.

## Security and Git state

- The only credential written was the generated local database credential outside the repository. Repository secret files and real archives were untouched. The isolated response exposes workout fields and template IDs. The fixtures and generated planning reports contain no private workout data or credentials.
- Existing working-tree changes were preserved. Slice changes remain uncommitted on `main`. No push or deployment took place. The existing service on port 5432, hosted database, production read configuration, and scheduled sync were left alone.

## Next slice

[First authorized direct workout](next-first-direct-workout-prompt.md)
