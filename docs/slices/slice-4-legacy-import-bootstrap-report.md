# Slice Report: Slice 4 - Legacy Import Bootstrap

Date: 2026-06-06
Agent/thread: Codex
Branch: current working branch

## Summary

Added a local-only bootstrap/import path for the canonical Postgres schema. The new tooling reads the existing private config catalogs and cached legacy activity snapshot, upserts canonical config/activity rows, backfills Hevy exercise/set rows, preserves provenance metadata, and leaves the current static/PHP app behavior unchanged.

## Files Changed

- `scripts/bootstrap-foundation.php`
- `scripts/lib/foundation-import.php`
- `scripts/lib/hevy-import-parser.php`
- `tests/foundation-import-tests.php`
- `docs/docker-postgres-foundation.md`
- `docs/postgres-canonical-schema.md`
- `docs/implementation-progress.md`
- `docs/slices/slice-4-legacy-import-bootstrap-report.md`
- `docs/slices/slice-5-compatibility-api-prompt.md`

## Validation

- ✅ `php tests/foundation-import-tests.php`
- ✅ `php -l scripts/bootstrap-foundation.php`
- ✅ `php -l scripts/lib/foundation-import.php`
- ✅ `php -l scripts/lib/hevy-import-parser.php`
- ✅ `php -l tests/foundation-import-tests.php`
- ✅ `docker compose -f docker-compose.postgres.yml --profile foundation config`
- ❌ Disposable Docker/Postgres validation could not run because the local Docker daemon was unavailable.
- ❌ Local fallback Postgres bootstrap via `initdb` could not run in this environment because shared-memory creation was not permitted.
- ✅ `git status --short` reviewed

## Security Check

- secrets touched: no
- sensitive files modified: no
- notes: This slice read the existing private catalog/snapshot files as migration inputs but did not modify them, print raw health payloads, or touch `.env.local`, `private/config.php`, tokens, tunnel credentials, or database dumps.

## Decisions Made

- The canonical bootstrap path is a standalone CLI script rather than a live API endpoint.
- Importer code reuses the existing config validators from `public/api/exercise-config-repository.php` without coupling to `bootstrap.php`.
- Hevy child rows are rebuilt per imported activity on rerun to keep legacy backfills idempotent.
- Source provenance is tracked with relative private file references and stable file-state batch keys instead of storing raw payload blobs in Postgres.

## Known Issues

- The working tree already contains unrelated uncommitted changes outside this slice.
- End-to-end database import validation is still pending on a machine with either a working Docker daemon or a permissive local Postgres runtime.
- The current app still reads JSON/private snapshot sources and does not use Postgres yet.

## Git State

- working tree: dirty before Slice 4; now also includes additive bootstrap/import code, tests, and docs
- commit: not committed
- push: not pushed
- deploy: not deployed

## Next Slice

Slice 5: Compatibility API

## Next Slice Prompt

See `docs/slices/slice-5-compatibility-api-prompt.md`.
