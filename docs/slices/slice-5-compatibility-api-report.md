# Slice Report: Slice 5 - Compatibility API

Date: 2026-06-06
Agent/thread: Codex
Branch: current working branch

## Summary

Added a read-only canonical compatibility layer to the current PHP API surface. The existing GET endpoints can now read canonical Postgres data when foundation config is present, while preserving automatic fallback to the legacy snapshot and JSON config paths if canonical reads are disabled or unavailable.

## Files Changed

- `public/api/config-defaults.php`
- `public/api/bootstrap-auth.php`
- `public/api/foundation-read.php`
- `public/api/data.php`
- `public/api/exercises.php`
- `tests/fixtures/run-data-endpoint.php`
- `tests/foundation-compat-api-tests.php`
- `docs/compatibility-api.md`
- `docs/implementation-progress.md`
- `docs/slices/slice-5-compatibility-api-report.md`
- `docs/slices/slice-6-read-only-ui-switch-over-prompt.md`

## Validation

- ✅ `php tests/foundation-compat-api-tests.php`
- ✅ `php tests/exercise-config-tests.php`
- ✅ `php tests/foundation-import-tests.php`
- ✅ `php -l public/api/foundation-read.php`
- ✅ `php -l public/api/data.php`
- ✅ `php -l public/api/exercises.php`
- ✅ `php -l public/api/bootstrap-auth.php`
- ✅ `php -l public/api/config-defaults.php`
- ✅ `php -l tests/fixtures/run-data-endpoint.php`
- ✅ `php -l tests/foundation-compat-api-tests.php`
- ✅ `git status --short` reviewed

## Security Check

- secrets touched: no
- sensitive files modified: no
- notes: This slice added Postgres read helpers and server-side foundation toggles only. It did not read or modify `.env.local`, `private/config.php`, API keys, tokens, tunnel credentials, raw health exports, or database dumps.

## Decisions Made

- Canonical reads stay on the current GET routes instead of adding a separate public API.
- Canonical mode is controlled only by server-side config and env overrides.
- Canonical read failures fall back automatically to the current legacy sources.
- All config mutation routes remain legacy-backed for now.
- Child Hevy exercise/set rows are available through `GET /api/data.php?includeChildren=1`.

## Known Issues

- The working tree already contains unrelated uncommitted changes outside this slice.
- Canonical exercise/activity-type GET responses can lag behind legacy config mutations until the next bootstrap/import.
- The frontend still ignores the additive canonical metadata and child-row payloads.

## Git State

- working tree: dirty before Slice 5; now also includes additive compatibility API code, docs, and tests
- commit: not committed
- push: not pushed
- deploy: not deployed

## Next Slice

Slice 6: Read-Only UI Switch-Over

## Next Slice Prompt

See `docs/slices/slice-6-read-only-ui-switch-over-prompt.md`.
