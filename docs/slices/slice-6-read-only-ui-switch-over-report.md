# Slice Report: Slice 6 - Read-Only UI Switch-Over

Date: 2026-06-07
Agent/thread: Codex
Branch: current working branch

## Summary

Switched the current vanilla JS UI over to intentionally consume canonical-backed read responses through the existing PHP endpoints. The app now requests canonical child rows when available, uses them across Hevy-dependent UI paths through one shared adapter, surfaces separate activity/config read-source status in the existing header chrome, and preserves the current legacy fallback model.

## Files Changed

- `public/assets/js/core/state.js`
- `public/assets/js/core/exercise-config.js`
- `public/assets/js/core/hevy-parser.js`
- `public/assets/js/core/fatigue-engine.js`
- `public/assets/js/detail.js`
- `public/assets/js/exercise-admin.js`
- `public/assets/js/renderers/orchestrator.js`
- `public/assets/js/renderers/loader.js`
- `public/api/exercises.php`
- `public/tests/exercise-config-tests.js`
- `tests/foundation-compat-api-tests.php`
- `docs/compatibility-api.md`
- `docs/implementation-progress.md`
- `docs/slices/slice-6-read-only-ui-switch-over-report.md`
- `docs/slices/slice-7-hevy-ingestion-prompt.md`

## Validation

- ✅ `node public/tests/exercise-config-tests.js`
- ✅ `php tests/foundation-compat-api-tests.php`
- ✅ `php -l public/api/exercises.php`
- ✅ `git status --short` reviewed

## Security Check

- secrets touched: no
- sensitive files modified: no
- notes: This slice changed browser-facing read behavior and compatibility metadata only. It did not read or modify `.env.local`, `private/config.php`, API keys, tokens, tunnel credentials, raw health exports, or database dumps. The UI exposes app-safe source/status metadata only.

## Decisions Made

- The current UI now requests `includeChildren=1` only when using the internal `api/data.php` path.
- Canonical activity child rows are consumed through one shared adapter across fatigue, unknown detection, detail modal, and exercise-admin recent workout lookups.
- Header status now shows activity and config read sources separately using the existing chrome rather than adding a new debug view.
- Legacy config write routes remain unchanged, and the browser preserves the last known GET read-source state when write responses omit source metadata.

## Known Issues

- The working tree already contains unrelated uncommitted changes outside this slice.
- Canonical config GET responses can still lag behind legacy config edits until the next bootstrap/import rerun.
- The app still depends on compatibility adapters; direct Hevy ingestion and canonical write paths are still pending.

## Git State

- working tree: dirty before Slice 6; now also includes read-only UI switch-over code, focused tests, and slice docs
- commit: not committed
- push: not pushed
- deploy: not deployed

## Next Slice

Slice 7: Hevy Ingestion

## Next Slice Prompt

See `docs/slices/slice-7-hevy-ingestion-prompt.md`.
