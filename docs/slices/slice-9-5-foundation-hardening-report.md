# Slice Report: Slice 9.5 - Foundation Hardening Before Concept2

Date: 2026-06-08
Agent/thread: Codex
Branch: current working branch

## Summary

Added a forward-only migration path for existing local foundation databases, reserved canonical Concept2 provenance values, generalized canonical read dedupe to cover deterministic direct Concept2 rows versus legacy snapshot rows, and scoped canonical child-row reads to the selected activity IDs only.

## Files Changed

- `docker/postgres/initdb/001_canonical_schema.sql`
- `docker/postgres/migrations/20260608_001_foundation_hardening.sql`
- `scripts/lib/foundation-migrations.php`
- `scripts/lib/foundation-import.php`
- `public/api/foundation-read.php`
- `tests/foundation-import-tests.php`
- `tests/foundation-compat-api-tests.php`
- `docs/compatibility-api.md`
- `docs/postgres-canonical-schema.md`
- `docs/docker-postgres-foundation.md`
- `docs/implementation-progress.md`
- `docs/slices/slice-10-concept2-erg-import-prompt.md`

## Validation

- ✅ `php tests/foundation-import-tests.php`
- ✅ `php tests/foundation-compat-api-tests.php`
- ✅ `php tests/foundation-config-write-tests.php`
- ✅ `node public/tests/exercise-config-tests.js`
- ✅ `php -l public/api/foundation-read.php`
- ✅ `php -l scripts/lib/foundation-import.php`
- ✅ `php -l scripts/lib/foundation-migrations.php`
- ✅ `git status --short` reviewed

## Security Check

- secrets touched: no
- sensitive files modified: no
- notes: This slice changed local schema/migration tooling, canonical read logic, focused tests, and migration docs only. It did not read or modify `.env.local`, `private/config.php`, tokens, tunnel credentials, raw health exports, or database dumps. Browser-facing responses still expose app-safe source/status metadata only.

## Decisions Made

- Existing local foundation volumes now upgrade through forward-only SQL files tracked in `mattrics.mattrics_schema_migrations`.
- Direct Concept2 provenance is reserved as `source_key = concept2-logbook-export`, `source_kind = concept2`, and `batch_kind = concept2_logbook_export`.
- Canonical duplicate suppression remains conservative and source-aware: deterministic direct Hevy or direct Concept2 rows can suppress matching legacy snapshot rows, while ambiguous rows remain visible.
- Canonical child-row fetches are now scoped to selected activity IDs instead of all activities for the user.

## Known Issues

- The working tree already contains unrelated uncommitted changes outside this slice.
- Public HTTP requests still do not auto-run foundation schema migrations; local import/admin paths remain responsible for advancing the schema.
- Concept2 importer implementation is still pending for Slice 10.

## Git State

- working tree: dirty before Slice 9.5; now also includes additive foundation migration tooling, canonical read hardening, focused regression tests, and slice docs
- commit: not committed
- push: not pushed
- deploy: not deployed

## Next Slice

Slice 10: Concept2 Erg Import

## Next Slice Prompt

See `docs/slices/slice-10-concept2-erg-import-prompt.md`.
