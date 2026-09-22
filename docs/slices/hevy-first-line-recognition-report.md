# Slice Report: Hevy First-Line Recognition

Date: 2026-09-22
Agent/thread: Codex, current task
Branch: `mattwarden`

## Summary

Hevy-formatted descriptions now use a case-insensitive, word-bounded brand check on the first non-empty line. JavaScript and PHP remove the entire identifying line before parsing, and canonical-read deduplication skips the same header when selecting an exercise anchor. English and German Hevy attribution lines are covered without requiring fixed wording.

## Files Changed

- `public/assets/js/core/hevy-parser.js`, `lib/hevy-import-parser.php`, `lib/foundation-read.php`: recognition, stripping, and deduplication.
- `tests/js/exercise-config-tests.js`, `tests/foundation-import-tests.php`, `tests/foundation-compat-api-tests.php`: positive and negative regression cases.
- `docs/fatigue-model.md`, `public/assets/js/renderers/docs/section-fatigue-model.js`, `docs/implementation-progress.md`: behavior and progress documentation.
- This report and `docs/slices/next-hevy-header-runtime-verification-prompt.md`.

## Validation

- ✅ `node tests/js/exercise-config-tests.js`: 143/143 passed.
- ✅ `php tests/foundation-import-tests.php`: 117 passed, 0 failed; database-backed integration skipped because local Postgres was unavailable.
- ✅ `php tests/foundation-compat-api-tests.php`: 37 passed, 0 failed; database-backed integration skipped because local Postgres was unavailable.
- ✅ `./scripts/prod-gate.sh`: source guard, lints, PHP suites, and JavaScript suites passed. Database-backed Foundation checks skipped because local Postgres was unavailable.
- ✅ `git diff --check`: no whitespace errors.

## Security Check

- secrets touched: no
- sensitive files modified: no
- raw/private health data handled: no; tests use synthetic descriptions only
- public responses exposing credential values: no change
- generated artifacts containing private data: no

## Decisions Made

- Accept any standalone Hevy brand term on the first non-empty description line, including changing English or German attribution wording.
- Do not scan subsequent lines; skip only the first identifying line during canonical-read deduplication.

## Known Issues

- The local Postgres service was unavailable, so optional database integration assertions did not run. The production gate passed with these skips.
- Existing imported canonical child rows may need a later authorized re-import to reflect newly recognized legacy descriptions; this slice does not alter stored data.

## Git State

- working tree: this slice is committed in the containing changeset; unrelated local documentation changes may remain
- commit: this report is part of the Hevy recognition commit
- push: requested after commit
- deploy: requested after push

## Next Slice

Optional Hevy header runtime verification, after separate approval. The existing Mattwarden AI credential verification remains the main pending cut-over task.

## Next Slice Prompt

Use `docs/slices/next-hevy-header-runtime-verification-prompt.md`.
