# Slice Report: Detail Workout Visibility

Date: 2026-09-23
Agent/thread: Codex, current task
Branch: `mattwarden`

## Summary

Fixed the detail modal so parsed or canonical workout exercises are visible. The modal now removes and adds the existing `is-hidden` class instead of trying to override its `display: none !important` rule with inline styles. The same correction keeps ordinary notes visibility consistent.

## Files Changed

- `public/assets/js/detail.js`: toggle `is-hidden` for workout and notes sections.
- `tests/js/detail-tests.js`: focused DOM regression coverage.
- Progress documentation, this report, and the next production-verification prompt.

## Validation

- ✅ `node tests/js/detail-tests.js`: 6 passed, 0 failed.
- ✅ `./scripts/prod-gate.sh`: source guard, lints, PHP suites, and JavaScript suites passed. Optional database-backed Foundation checks skipped because local Postgres was unavailable.

## Security Check

- secrets touched: no
- sensitive files modified: no
- raw/private health data handled: no; the regression test uses synthetic workout data
- public responses exposing credential values: no change
- generated artifacts containing private data: no

## Decisions Made

- Keep `is-hidden` as the single visibility mechanism because its `!important` declaration intentionally wins over inline display values.
- Do not change workout parsing, canonical child lookup, or stored training data; the screenshot's exercise/set counts prove that data was already present.

## Known Issues

- The fix is local only until separately committed and deployed.
- Unrelated documentation and Pádel changes remain in the working tree and must not be included accidentally.

## Git State

- working tree: this slice is committed in the containing changeset; unrelated pre-existing changes remain
- commit: this report is part of the detail visibility commit
- push: requested after commit
- deploy: none for this slice

## Next Slice

Commit, deploy, and verify the detail workout breakdown in production.

## Next Slice Prompt

Use `docs/slices/next-detail-workout-visibility-production-prompt.md`.
