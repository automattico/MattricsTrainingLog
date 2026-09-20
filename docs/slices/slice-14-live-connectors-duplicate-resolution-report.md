# Slice Report: Slice 14 - Live Hevy Connector, Garmin Groundwork, and Duplicate Metadata Overlay

Date: 2026-06-09
Agent/thread: Codex
Branch: current working branch

## Summary

Added the first private live-connector contract under `private/storage/live-connectors.json`, implemented a real Hevy live sync path through `scripts/sync-live-connectors.php`, reserved Garmin live connector/source scaffolding without adding a brittle Garmin fetcher, and updated canonical duplicate resolution so direct Hevy/Garmin winners can display legacy Strava/Google Sheet `Name` and `Type` while keeping direct-source metrics, IDs, and child-row provenance.

## Files Changed

- `scripts/lib/foundation-connectors.php`
- `scripts/lib/foundation-import.php`
- `scripts/sync-live-connectors.php`
- `scripts/foundation-runtime-entrypoint.sh`
- `scripts/foundation-runtime-prepare.sh`
- `public/api/foundation-read.php`
- `public/api/foundation-diagnostics.php`
- `public/api/runtime-status.php`
- `docker/postgres/initdb/001_canonical_schema.sql`
- `docker/postgres/migrations/20260609_001_live_connectors.sql`
- `tests/foundation-import-tests.php`
- `tests/foundation-runtime-status-tests.php`
- `tests/foundation-compat-api-tests.php`
- `tests/foundation-import-diagnostics-tests.php`
- `docs/compatibility-api.md`
- `docs/docker-postgres-foundation.md`
- `docs/postgres-canonical-schema.md`
- `docs/implementation-progress.md`
- `docs/slices/slice-14-live-connectors-duplicate-resolution-report.md`
- `docs/slices/slice-15-connector-admin-and-incremental-hevy-sync-prompt.md`

## Validation

- ✅ `php -l scripts/lib/foundation-connectors.php`
- ✅ `php -l scripts/lib/foundation-import.php`
- ✅ `php -l scripts/sync-live-connectors.php`
- ✅ `php -l public/api/foundation-read.php`
- ✅ `php -l public/api/foundation-diagnostics.php`
- ✅ `php -l public/api/runtime-status.php`
- ✅ `php -l tests/foundation-import-tests.php`
- ✅ `php -l tests/foundation-compat-api-tests.php`
- ✅ `php -l tests/foundation-import-diagnostics-tests.php`
- ✅ `php -l tests/foundation-runtime-status-tests.php`
- ✅ `node public/tests/exercise-config-tests.js`
- ✅ `php tests/foundation-runtime-status-tests.php` (`1` integration block skipped because local Postgres was unreachable)
- ✅ `php tests/foundation-import-tests.php` (`1` integration block skipped because local Postgres was unreachable)
- ✅ `php tests/foundation-compat-api-tests.php` (`1` integration block skipped because local Postgres was unreachable)
- ✅ `php tests/foundation-config-write-tests.php` (`1` integration block skipped because local Postgres was unreachable)
- ✅ `php tests/foundation-import-diagnostics-tests.php` (`1` integration block skipped because local Postgres was unreachable)
- ✅ `git status --short` reviewed

## Security Check

- secrets touched: no
- sensitive files modified: no
- notes: This slice added private connector-store scaffolding and app-safe connector diagnostics only. It did not read or modify `.env.local`, `private/config.php`, raw health exports, database dumps, or real credential values. Browser-facing and diagnostic responses expose only booleans, statuses, counts, source keys, and timestamps.

## Decisions Made

- Connector secrets stay file-backed in private runtime storage instead of being moved into canonical SQL tables.
- Hevy live sync reuses the existing canonical workout importer instead of creating a second direct-write path.
- Garmin remains groundwork-only in this slice; the repo now reserves source, batch, and status contracts for Garmin live without implementing an unstable fetcher.
- Deterministic Hevy/Garmin dedupe signatures were shifted away from editable Strava-facing title/type fields so legacy metadata overlay can work even after Strava edits.

## Known Issues

- Validation requiring a live local Postgres foundation runtime was skipped in this environment because `127.0.0.1:5433` was unavailable.
- The live Hevy sync is still full-history rather than incremental.
- Connector secrets still require operator-managed private file edits; no local-only mutation endpoint or admin UI exists yet.
- Garmin live fetching remains intentionally deferred.

## Git State

- working tree: dirty before Slice 14; now also includes additive live-connector, duplicate-resolution, test, and documentation changes
- commit: not committed
- push: not pushed
- deploy: not deployed

## Next Slice

Slice 15: Connector Admin Surface and Incremental Hevy Sync

## Next Slice Prompt

See `docs/slices/slice-15-connector-admin-and-incremental-hevy-sync-prompt.md`.
