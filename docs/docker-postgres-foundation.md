# Docker/Postgres Foundation

This document defines the additive local-only foundation for the future Mattrics Docker/Postgres runtime. It remains reversible. It does not replace the current static/PHP production model, and it does not change the existing local Docker workflow in `docker-compose.yml`.

## Purpose

- create a parallel local foundation for future Postgres-backed work
- define service, storage, and schema bootstrap boundaries for migration work
- keep current production deploy and current local PHP workflow unchanged

## Non-Goals

- no production cutover
- no deploy script changes
- no application code changes for database reads or writes
- no replacement of the existing `docker-compose.yml`

## Local Runtime Paths

Current path:

- `docker-compose.yml`
- serves the current PHP app on `http://localhost:8080`
- matches the existing static/PHP architecture

Foundation path added by Slice 2:

- `docker-compose.postgres.yml`
- defines a future `foundation-app` service boundary
- defines a local `postgres` service boundary
- keeps both services behind the optional `foundation` profile
- starts a separate local runtime on `http://localhost:8081` when the `foundation` profile is used
- does not replace the current app runtime on `http://localhost:8080`

## Service Boundaries

`foundation-app`

- built from `docker/runtime/Dockerfile`
- includes PHP Postgres extensions only
- now serves the existing `public/` app and PHP endpoints on local port `8081`
- generates a local-only private runtime config under `/srv/mattrics/private-runtime/config.php`
- keeps the repo `private/` directory mounted read-only at `/srv/mattrics/private-legacy`
- copies legacy snapshot/config/import files into the writable private runtime volume only when missing
- exposes `GET /api/runtime-status.php` and `GET /api/import-diagnostics.php` as local-only runtime diagnostic surfaces

`postgres`

- uses `postgres:16-alpine`
- exposes local port `5433`
- stores database files in the named volume `mattrics_foundation_postgres_data`
- runs fresh-volume bootstrap SQL from `docker/postgres/initdb/`
- later schema upgrades for existing local volumes run through forward-only SQL files in `docker/postgres/migrations/`
- uses placeholder-only local credentials that must never be reused for production

## Storage Boundaries

`mattrics_foundation_postgres_data`

- persistent local Postgres storage
- separate from the current static/PHP runtime and separate from `public/`

`mattrics_foundation_private_runtime`

- persistent local private runtime/config storage for future server-side state
- separate from the current host `private/` directory so Slice 2 does not repurpose existing private files
- now stores the generated local runtime config, copied compatibility data/cache files, auth state, connector-secret scaffolding under `storage/`, and local-only runtime writes for the foundation app

`./private` mounted as `/srv/mattrics/private-legacy` in `foundation-app`

- read-only legacy/private reference path
- available for later migration slices that need compatibility access to existing private runtime inputs
- not treated as the new canonical runtime storage location

## Safety Rules

- `public/` remains the only deployable web root
- no secrets belong in the compose file, image, or docs
- placeholder values in `docker-compose.postgres.yml` are local-only markers, not real credentials
- raw imports, runtime-private payloads, and credentials stay outside canonical SQL bootstrap files
- current `deploy.sh` flow remains unchanged

## Validation

Validate the foundation config without starting containers:

```sh
docker compose -f docker-compose.postgres.yml --profile foundation config
```

Validate canonical schema bootstrap against a disposable Postgres project and volume:

```sh
docker compose -f docker-compose.postgres.yml -p mattrics-foundation-validate --profile foundation up -d postgres
docker compose -f docker-compose.postgres.yml -p mattrics-foundation-validate --profile foundation exec -T postgres psql -U mattrics -d mattrics -c '\dt mattrics.*'
docker compose -f docker-compose.postgres.yml -p mattrics-foundation-validate --profile foundation down -v
```

If a later slice needs to start the full foundation stack locally, use the profile explicitly:

```sh
docker compose -f docker-compose.postgres.yml --profile foundation up --build
```

That foundation stack now serves the app locally at:

```sh
http://localhost:8081
```

Stop it with:

```sh
docker compose -f docker-compose.postgres.yml --profile foundation down
```

Add `-v` only when intentionally deleting the local foundation volumes.

## Slice 10 Local Runtime Readiness

Slice 10 turns the dormant foundation container into a real local app runtime while preserving the current deploy model.

Runtime behavior:

- `foundation-app` serves the same PHP + vanilla JS app as the current local stack
- runtime config is generated from environment variables into the private runtime volume, not checked into the repo
- canonical mode still uses the existing server-side config keys:
  - `foundation_database_url`
  - `foundation_user_key`
- browser-facing endpoints remain:
  - `public/api/data.php`
  - `public/api/exercises.php`
- the local-only diagnostic endpoints are:
  - `public/api/runtime-status.php`
  - `public/api/import-diagnostics.php`

Useful env defaults in the foundation stack:

- `MATTRICS_RUNTIME_MODE=foundation`
- `MATTRICS_CONFIG=/srv/mattrics/private-runtime/config.php`
- `MATTRICS_SITE_ORIGIN=http://localhost:8081`
- `MATTRICS_AUTH_REQUIRE_HTTPS=0`
- `MATTRICS_FOUNDATION_DATABASE_URL=postgres://mattrics:mattrics-local-only-change-me@postgres:5432/mattrics`
- `MATTRICS_FOUNDATION_USER_KEY=legacy-local-user`

Intentional prep/import path before relying on canonical reads:

```sh
docker compose -f docker-compose.postgres.yml --profile foundation exec -T foundation-app sh -lc ./scripts/foundation-runtime-prepare.sh
```

That prep path:

- applies pending foundation migrations through the existing bootstrap/import scripts
- seeds canonical config/activity rows from the writable private runtime copy
- imports direct Hevy workouts from `import-hevy/workouts.csv` when present
- imports direct Garmin activities from `import-garmin/Activities.csv` when present
- runs `scripts/sync-live-connectors.php` so live Hevy can sync from the private connector store and Garmin live status stays visible even without a fetcher
- does not introduce any new browser-facing write route

Local runtime verification:

```sh
./scripts/check-foundation-runtime.sh canonical
```

To confirm fallback behavior after intentionally breaking canonical access or stopping Postgres while the app container is still reachable:

```sh
./scripts/check-foundation-runtime.sh fallback
```

The runtime-status endpoint returns app-safe status metadata only. It does not expose credentials, database URLs, raw private paths, or import payload contents.

The import-diagnostics endpoint also stays app-safe only. It reports per-source counts, timestamps, unresolved activity-type samples, selected-vs-suppressed activity totals, metadata-overlay counts, and live-connector status booleans without exposing raw payload contents, private paths, database URLs, or credential values.

Slice 15 adds a third local-only operator endpoint:

```sh
GET /api/connectors.php
POST /api/connectors.php
```

That connector-admin endpoint is authenticated, CSRF-protected, and hidden from non-local non-foundation requests. It returns app-safe connector status, timestamps, sync strategy, overlap window, cursor timestamps, and fetch counts only.

Recommended local operator flow after starting the foundation stack:

1. Run `docker compose -f docker-compose.postgres.yml --profile foundation exec -T foundation-app sh -lc ./scripts/foundation-runtime-prepare.sh`
2. Open `http://localhost:8081/?view=connectors` to set/test the Hevy connector without editing private files directly
3. Inspect `http://localhost:8081/api/runtime-status.php`
4. Inspect `http://localhost:8081/api/import-diagnostics.php`
5. Compare `selectedActivityCount`, `suppressedActivityCount`, `nonDeterministicActivityCount`, and `metadataOverlayCount` to see whether direct Hevy/Garmin imports are beating legacy snapshot rows and when legacy Strava-edited titles/types are being overlaid onto direct-source winners

## Slice 14 Live Connector Groundwork

Slice 14 adds the first private live-connector contract:

- connector secrets and app-private sync state live in `private/storage/live-connectors.json`
- Hevy uses a real server-side API-key sync path through `php scripts/sync-live-connectors.php`
- Garmin currently uses the same private connector store and canonical source registry but remains groundwork-only in this slice
- diagnostics and runtime-status expose only app-safe connector booleans, statuses, and timestamps

## Slice 15 Connector Admin And Incremental Hevy Sync

Slice 15 extends the live-connector groundwork with:

- a new dedicated authenticated `Connectors` app view
- local-only `GET/POST /api/connectors.php` for Hevy save/clear/test actions
- private connector-store schema version `2` with separate sync/test state
- incremental Hevy live sync cursoring with a 30-day overlap window after the first successful full-history sync
- app-safe runtime-status and import-diagnostics fields showing Hevy sync strategy, cursor timestamps, and recent fetch counts

## Slice 4 Bootstrap

Slice 4 adds a local-only canonical bootstrap runner:

```sh
php scripts/bootstrap-foundation.php
```

Useful flags:

```sh
php scripts/bootstrap-foundation.php --dry-run
php scripts/bootstrap-foundation.php --database-url=postgres://user:pass@127.0.0.1:5433/mattrics
php scripts/bootstrap-foundation.php --user-key=legacy-local-user --display-name="Legacy Local User" --timezone=Europe/Berlin
php scripts/bootstrap-foundation.php --private-root=/absolute/path/to/private
```

Bootstrap behavior:

- applies pending forward-only foundation migrations before importing data
- reads `private/data/exercise-configs.json`
- reads `private/data/activity-type-configs.json`
- reads `private/cache/training-data.json`
- upserts canonical config/activity rows into Postgres
- preserves source metadata and legacy identifiers
- rebuilds Hevy child exercise/set rows per imported activity on rerun
- does not change the current static/PHP runtime path

## Slice 7 Direct Hevy Import

Slice 7 adds a second local-only importer for native Hevy workout exports:

```sh
php scripts/import-hevy.php
```

Useful flags:

```sh
php scripts/import-hevy.php --dry-run
php scripts/import-hevy.php --input=import-hevy/workouts.csv
php scripts/import-hevy.php --database-url=postgres://user:pass@127.0.0.1:5433/mattrics
php scripts/import-hevy.php --user-key=legacy-local-user --display-name="Legacy Local User" --timezone=Europe/Berlin
php scripts/import-hevy.php --private-root=/absolute/path/to/private
```

Direct Hevy import behavior:

- applies pending forward-only foundation migrations before importing data
- reads a native Hevy CSV export from private storage
- supports the observed export header contract only
- groups rows into workouts by title plus start/end timestamps
- preserves UTF-8 workout titles, exercise names, notes, descriptions, umlauts, and emojis
- upserts direct Hevy workouts into canonical activity, activity-exercise, and activity-set tables
- rebuilds a Hevy-compatible description string for compatibility fallback while storing canonical child rows as the primary structure
- uses deterministic hashed source activity IDs so reruns stay idempotent

Input/storage rules:

- keep the raw Hevy export outside `public/`
- default location is `private/import-hevy/workouts.csv`
- the importer rejects files outside the selected private root
- logs and summaries expose only relative private paths, never credentials or absolute private filesystem paths

## Slice 11 Direct Garmin Import

Slice 11 adds a third local-only importer for Garmin Connect activity-list CSV exports:

```sh
php scripts/import-garmin.php
```

Useful flags:

```sh
php scripts/import-garmin.php --dry-run
php scripts/import-garmin.php --input=import-garmin/Activities.csv
php scripts/import-garmin.php --database-url=postgres://user:pass@127.0.0.1:5433/mattrics
php scripts/import-garmin.php --user-key=legacy-local-user --display-name="Legacy Local User" --timezone=Europe/Berlin
php scripts/import-garmin.php --private-root=/absolute/path/to/private
```

Direct Garmin import behavior:

- applies pending forward-only foundation migrations before importing data
- reads the observed Garmin Connect `Activities.csv` export from private storage
- supports both currently observed Garmin Connect `Activities.csv` header variants by requiring the shared core activity columns and safely ignoring additional Garmin-only columns
- maps Garmin activity rows into canonical activity rows only; it does not create child exercise/set rows
- derives deterministic `garmin-csv-...` source activity IDs because the Garmin activity-list CSV does not include a stable exported activity ID
- stores direct Garmin provenance so canonical reads can prefer deterministic direct Garmin rows over matching legacy Garmin snapshot rows
- resolves common Garmin activity-type labels such as `Running`, `Treadmill Running`, `Walking`, `Hiking`, `Cycling`, `Indoor Cycling`, and `Strength Training` through additive aliases on the existing canonical activity-type catalog

Input/storage rules:

- keep the raw Garmin export outside `public/`
- default location is `private/import-garmin/Activities.csv`
- the importer rejects files outside the selected private root
- logs and summaries expose only relative private paths, never credentials or absolute private filesystem paths

## Migration Workflow

- Use `docker/postgres/initdb/` only for fresh database initialization.
- Use `docker/postgres/migrations/` plus `scripts/lib/foundation-migrations.php` for forward-only upgrades on existing local foundation volumes.
- Applied versions are recorded in `mattrics.mattrics_schema_migrations`.
- Foundation write paths apply migrations automatically; browser-facing PHP endpoints do not.
- The local `runtime-status.php` diagnostic endpoint is read-only and does not advance schema state.

## Reset Expectations

- The importer is rerunnable and uses source metadata plus canonical lookup keys to avoid duplicate top-level rows.
- For Hevy-backed activities, child exercise/set rows are deleted and rebuilt from the current description text on each rerun.
- For Garmin-backed activities, reruns update the same canonical top-level rows by deterministic source activity ID and do not create child rows.
- To reset the local foundation database completely, delete the disposable Postgres data store:

```sh
docker compose -f docker-compose.postgres.yml --profile foundation down -v
```

- If you are validating outside Docker with a temporary local Postgres instance, delete that temporary data directory separately.

## Handoff To Slice 13

After Slice 12, the next highest-leverage migration step is activity-type normalization hardening so the new diagnostics surface can drive targeted reduction of unresolved Garmin and legacy activity-type rows without weakening the current conservative dedupe rules or exposing private source payloads.
