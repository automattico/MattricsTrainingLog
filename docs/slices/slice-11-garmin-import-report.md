# Slice Report: Slice 11 - Garmin Import

Date: 2026-06-09
Agent/thread: Codex
Branch: current working branch

## Summary

Added the first direct Garmin import path into the canonical foundation. The local-only `scripts/import-garmin.php` importer now accepts both observed Garmin Connect `Activities.csv` variants, parses supported endurance summary fields into canonical activity rows, derives deterministic `garmin-csv-...` source IDs because the export lacks a stable activity ID, adds narrow aliases for common Garmin activity-type labels, hooks Garmin import into the local foundation runtime prep flow, and extends canonical read selection so deterministic direct Garmin rows can suppress matching legacy Garmin snapshot rows while ambiguous rows remain visible.

## Files Changed

- `scripts/import-garmin.php`
- `scripts/lib/garmin-import-parser.php`
- `scripts/lib/foundation-import.php`
- `scripts/foundation-runtime-prepare.sh`
- `public/api/foundation-read.php`
- `tests/fixtures/garmin-activities.csv`
- `tests/fixtures/garmin-activities-invalid.csv`
- `tests/foundation-import-tests.php`
- `tests/foundation-compat-api-tests.php`
- `docs/compatibility-api.md`
- `docs/docker-postgres-foundation.md`
- `docs/postgres-canonical-schema.md`
- `docs/implementation-progress.md`
- `docs/slices/slice-11-garmin-import-report.md`
- `docs/slices/slice-12-canonical-import-diagnostics-prompt.md`

## Validation

- ✅ `php -l scripts/import-garmin.php`
- ✅ `php -l scripts/lib/garmin-import-parser.php`
- ✅ `php -l scripts/lib/foundation-import.php`
- ✅ `php -l public/api/foundation-read.php`
- ✅ `php -l tests/foundation-import-tests.php`
- ✅ `php -l tests/foundation-compat-api-tests.php`
- ✅ `php tests/foundation-import-tests.php`
- ✅ `php tests/foundation-compat-api-tests.php`
- ✅ `php tests/foundation-runtime-status-tests.php`
- ✅ `php tests/foundation-config-write-tests.php`
- ✅ `node public/tests/exercise-config-tests.js`
- ✅ `git status --short` reviewed

## Security Check

- secrets touched: no
- sensitive files modified: no
- notes: This slice added importer code, tests, and docs only. It did not read or modify `.env.local`, `private/config.php`, tokens, tunnel credentials, raw health exports beyond the user-provided local Garmin CSV path, or database dumps. Browser-facing responses still expose app-safe source/status metadata only.

## Decisions Made

- The importer accepts both observed Garmin Connect `Activities.csv` header variants by validating shared required columns and ignoring unmapped Garmin-only extras.
- Garmin activity-type expansion stays intentionally narrow: only high-confidence aliases were added for existing canonical types, while lower-confidence Garmin labels still import literally and unresolved.
- Garmin imports currently write canonical top-level activity rows only and do not create child exercise/set records.
- Because the Garmin activity-list CSV lacks a stable exported activity ID, canonical idempotency uses deterministic hashed `garmin-csv-...` source IDs.
- Direct Garmin rows suppress legacy Garmin snapshot rows only when the Garmin duplicate signature is deterministic; otherwise both remain visible.

## Known Issues

- The working tree already contains unrelated uncommitted changes outside this slice.
- Canonical integration tests still skip when no local Postgres foundation is reachable from the current environment.
- Some Garmin activity types such as `Other` still import literally and may remain unresolved against canonical activity-type config until a later normalization slice.
- Garmin-only fields that do not fit the current canonical activity schema remain intentionally deferred.

## Git State

- working tree: dirty before Slice 11; now also includes additive Garmin importer code, runtime-prep wiring, focused tests, and slice docs
- commit: not committed
- push: not pushed
- deploy: not deployed

## Next Slice

Slice 12: Canonical Import Diagnostics

## Next Slice Prompt

See `docs/slices/slice-12-canonical-import-diagnostics-prompt.md`.
