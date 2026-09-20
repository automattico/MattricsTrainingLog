# Slice Report: Slice 9 - Exercise Config DB Migration

Date: 2026-06-07
Agent/thread: Codex
Branch: current working branch

## Summary

Switched browser-facing exercise/activity-type config mutations onto canonical Postgres records whenever foundation mode is available. `public/api/exercises.php` now resolves config lookups against canonical data for mutation routes, rewrites the legacy JSON catalogs as compatibility shadows after canonical writes, keeps the unknown review queue on the legacy JSON path, and preserves automatic legacy fallback when canonical write initialization is unavailable.

## Files Changed

- `public/api/exercises.php`
- `public/api/foundation-write.php`
- `tests/foundation-config-write-tests.php`
- `docs/compatibility-api.md`
- `docs/implementation-progress.md`
- `docs/slices/slice-9-exercise-config-db-migration-report.md`
- `docs/slices/slice-10-concept2-erg-import-prompt.md`

## Validation

- ✅ `node public/tests/exercise-config-tests.js`
- ✅ `php tests/exercise-config-tests.php`
- ✅ `php tests/foundation-compat-api-tests.php`
- ✅ `php tests/foundation-config-write-tests.php`
- ✅ `php -l public/api/foundation-write.php`
- ✅ `php -l public/api/exercises.php`
- ✅ `git status --short` reviewed

## Security Check

- secrets touched: no
- sensitive files modified: no
- notes: This slice changed canonical config mutation code, compatibility docs, and focused tests only. It did not read or modify `.env.local`, `private/config.php`, tokens, tunnel credentials, raw health exports, or database dumps. Browser-facing responses continue to expose app-safe source/status metadata only.

## Decisions Made

- Canonical Postgres config rows are now the intended source of truth for browser-facing exercise/activity-type writes when canonical mode is available.
- Canonical write routes preserve the current `public/api/exercises.php` request and response shapes instead of adding a second browser-facing config API.
- Legacy JSON config catalogs are now compatibility shadows after canonical writes rather than the primary source in canonical mode.
- Unknown review data intentionally remains on the legacy JSON path in this slice.

## Known Issues

- The working tree already contains unrelated uncommitted changes outside this slice.
- Canonical config shadow-sync can still fail independently after a successful canonical DB write, leaving the legacy JSON copies temporarily stale until the next successful shadow sync or bootstrap run.
- The unknown review queue is still JSON-backed and has not moved into canonical storage yet.
- The browser still depends on the compatibility PHP endpoints rather than a dedicated DB-native config API.

## Git State

- working tree: dirty before Slice 9; now also includes additive canonical config-write code, focused regression tests, and slice docs
- commit: not committed
- push: not pushed
- deploy: not deployed

## Next Slice

Slice 10: Concept2 Erg Import

## Next Slice Prompt

See `docs/slices/slice-10-concept2-erg-import-prompt.md`.
