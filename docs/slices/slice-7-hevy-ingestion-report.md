# Slice Report: Slice 7 - Hevy Ingestion

Date: 2026-06-07
Agent/thread: Codex
Branch: current working branch

## Summary

Added the first direct native Hevy ingestion path into the canonical Postgres foundation. The new CLI importer reads a private Hevy CSV export, preserves UTF-8 workout text including umlauts and emojis, writes canonical activity/exercise/set rows directly, rebuilds Hevy-compatible descriptions for fallback compatibility, and makes canonical reads prefer direct Hevy rows over matching legacy snapshot rows.

## Files Changed

- `scripts/import-hevy.php`
- `scripts/lib/foundation-import.php`
- `public/api/foundation-read.php`
- `tests/foundation-import-tests.php`
- `tests/foundation-compat-api-tests.php`
- `docs/docker-postgres-foundation.md`
- `docs/compatibility-api.md`
- `docs/implementation-progress.md`
- `docs/slices/slice-7-hevy-ingestion-report.md`
- `docs/slices/slice-8-fatigue-from-canonical-data-prompt.md`

## Validation

- ✅ `php -l scripts/import-hevy.php`
- ✅ `php -l scripts/lib/foundation-import.php`
- ✅ `php -l public/api/foundation-read.php`
- ✅ `php tests/foundation-import-tests.php`
- ✅ `php tests/foundation-compat-api-tests.php`
- ✅ `php scripts/import-hevy.php`
- ✅ `git status --short` reviewed

## Security Check

- secrets touched: no
- sensitive files modified: no
- notes: This slice read the private Hevy export at `private/import-hevy/workouts.csv` and imported it into the local canonical foundation database. It did not read or modify `.env.local`, `private/config.php`, tokens, tunnel credentials, or database dumps. The importer exposes relative private paths only and preserves raw health data outside `public/`.

## Decisions Made

- The direct Hevy path is CLI-only in this slice.
- Native Hevy CSV is the only supported direct-import format.
- Workout titles, exercise names, notes, and descriptions are preserved as UTF-8 text while canonical matching and dedupe use hashed UTF-8-safe signatures.
- Direct Hevy rows win over matching legacy snapshot rows when the duplicate signature is deterministic.
- The compatibility API contract stays unchanged; duration continues to flow through `Duration (min)` and start time through `Date`.

## Known Issues

- The working tree already contains unrelated uncommitted changes outside this slice.
- Direct Hevy import still depends on the current JSON-backed exercise/activity-type catalogs for canonical resolution.
- Fuzzy cross-source deduping is still intentionally out of scope.
- Several imported Hevy exercise names remain unresolved and are preserved for later config follow-up rather than silently remapped.

## Git State

- working tree: dirty before Slice 7; now also includes additive Hevy importer code, canonical read-selection changes, focused tests, and slice docs
- commit: not committed
- push: not pushed
- deploy: not deployed

## Next Slice

Slice 8: Fatigue From Canonical Data

## Next Slice Prompt

See `docs/slices/slice-8-fatigue-from-canonical-data-prompt.md`.
