# Slice Report: Slice 8 - Fatigue From Canonical Data

Date: 2026-06-07
Agent/thread: Codex
Branch: current working branch

## Summary

Switched the current fatigue/readiness path to intentionally consume canonical typed child-row set details whenever canonical activity children are available. Personal reference-load history now follows the same typed path, direct Hevy-selected activities stay aligned with their child rows, and the legacy description parser remains the strict fallback when canonical child rows do not exist.

## Files Changed

- `public/assets/js/core/fatigue-engine.js`
- `public/tests/exercise-config-tests.js`
- `tests/foundation-compat-api-tests.php`
- `docs/compatibility-api.md`
- `docs/fatigue-model.md`
- `docs/implementation-progress.md`
- `docs/slices/slice-8-fatigue-from-canonical-data-report.md`
- `docs/slices/slice-9-exercise-config-db-migration-prompt.md`

## Validation

- ✅ `node public/tests/exercise-config-tests.js`
- ✅ `php tests/foundation-compat-api-tests.php`
- ✅ `git status --short` reviewed

## Security Check

- secrets touched: no
- sensitive files modified: no
- notes: This slice changed fatigue/readiness calculations, compatibility documentation, and focused tests only. It did not read or modify `.env.local`, `private/config.php`, tokens, tunnel credentials, raw health exports, or database dumps. Browser-facing responses continue to expose app-safe source/status metadata only.

## Decisions Made

- Canonical child-row `setDetails` are now authoritative for fatigue math when an activity has canonical children.
- Canonical parsed and time-based sets use typed numeric fields directly instead of reparsing display text.
- Canonical unknown or incomplete sets fall back to the existing duration-plus-set-count heuristic rather than dropping the matched exercise block.
- The Slice 5/7 compatibility API remains the only canonical read surface, and direct Hevy-selected activities stay aligned with the child rows returned for `includeChildren=1`.

## Known Issues

- The working tree already contains unrelated uncommitted changes outside this slice.
- Canonical config writes still remain on the legacy JSON path, so canonical exercise/activity-type reads can lag behind new edits until the next bootstrap/import run.
- Canonical fatigue still depends on the current compatibility adapter and frontend in-memory child-row cache rather than a dedicated DB-native browser contract.

## Git State

- working tree: dirty before Slice 8; now also includes additive fatigue-path changes, focused regression tests, and slice docs
- commit: not committed
- push: not pushed
- deploy: not deployed

## Next Slice

Slice 9: Exercise Config DB Migration

## Next Slice Prompt

See `docs/slices/slice-9-exercise-config-db-migration-prompt.md`.
