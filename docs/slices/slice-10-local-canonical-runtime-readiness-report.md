# Slice Report: Slice 10 - Local Canonical Runtime Readiness

Date: 2026-06-08
Agent/thread: Codex
Branch: current working branch

## Summary

Turned the dormant Docker/Postgres foundation into a real local canonical app runtime. The `foundation-app` service now serves the existing PHP + vanilla JS app on `http://localhost:8081`, generates local-only runtime config into the private runtime volume, keeps repo `private/` mounted read-only as a legacy source, exposes a local-only `runtime-status.php` diagnostic endpoint, and documents an intentional prep/import path through existing bootstrap and Hevy import scripts before canonical reads are expected to be ready.

## Files Changed

- `docker-compose.postgres.yml`
- `public/api/runtime-status.php`
- `scripts/foundation-runtime-entrypoint.sh`
- `scripts/foundation-runtime-prepare.sh`
- `scripts/check-foundation-runtime.sh`
- `scripts/lib/foundation-migrations.php`
- `tests/fixtures/run-runtime-status-endpoint.php`
- `tests/foundation-runtime-status-tests.php`
- `docs/docker-postgres-foundation.md`
- `docs/compatibility-api.md`
- `docs/implementation-progress.md`
- `docs/slices/slice-11-garmin-import-prompt.md`

## Validation

- ✅ `docker compose -f docker-compose.postgres.yml --profile foundation config`
- ✅ `php tests/foundation-runtime-status-tests.php`
- ✅ `php tests/foundation-import-tests.php`
- ✅ `php tests/foundation-compat-api-tests.php`
- ✅ `php tests/foundation-config-write-tests.php`
- ✅ `node public/tests/exercise-config-tests.js`
- ✅ `php -l public/api/runtime-status.php`
- ✅ `php -l tests/foundation-runtime-status-tests.php`
- ✅ `php -l tests/fixtures/run-runtime-status-endpoint.php`
- ✅ `php -l scripts/lib/foundation-migrations.php`
- ✅ `git status --short` reviewed

## Security Check

- secrets touched: no
- sensitive files modified: no
- notes: This slice added local runtime scaffolding, diagnostics, tests, and docs only. It did not read or modify `.env.local`, `private/config.php`, tokens, tunnel credentials, raw health exports, or database dumps. The new diagnostic endpoint returns app-safe status metadata only.

## Decisions Made

- The canonical runtime stays on a separate local port (`8081`) so it does not replace the current `docker-compose.yml` workflow.
- Runtime config is generated from env into the writable private runtime volume instead of relying on checked-in config.
- Legacy `private/` remains mounted read-only as a compatibility seed source; the foundation runtime writes only to its dedicated private runtime volume.
- Runtime readiness uses one local-only diagnostic endpoint instead of changing existing browser-facing activity/config APIs.
- Schema migrations are still intentional and importer-driven; the runtime diagnostic endpoint does not advance schema state.
- Concept2 is no longer the next mandatory migration slice and remains a future connector option.

## Known Issues

- The working tree already contains unrelated uncommitted changes outside this slice.
- Full Docker end-to-end runtime startup was not verified in this run because local Postgres/Docker integration access was unavailable from the sandboxed test environment.
- Canonical integration tests continue to skip when no local Postgres foundation is reachable.
- Unknown review records still remain JSON-backed on the legacy/private compatibility path.

## Git State

- working tree: dirty before Slice 10; now also includes additive local canonical runtime scaffolding, diagnostics, tests, and docs
- commit: not committed
- push: not pushed
- deploy: not deployed

## Next Slice

Slice 11: Garmin Import

## Next Slice Prompt

See `docs/slices/slice-11-garmin-import-prompt.md`.
