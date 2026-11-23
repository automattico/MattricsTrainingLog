# Exercise Manager merge layout

Date 2026-11-23 · Branch `main`

## Summary

Configured and unconfigured exercises show two equal-width cards in Names. The names editor comes first when the cards stack at 720 px and below. Activity types keep the full-width names field. Advanced contains the classification fields and the existing delete action for configured items.

The merge picker uses the full configured exercise catalog in alphabetical order. Configured sources are excluded from their own picker. For unconfigured sources, every configured exercise is eligible. The picker opens on demand and explains the effect on the source and target.

## Files changed

- `public/assets/js/exercise-admin.js` moves merge rendering and describes its effect.
- `public/assets/css/exercise-admin.css` lays out the cards and wraps long names.
- `tests/js/exercise-config-tests.js` covers placement, eligible targets, and empty catalogs.

## Validation

- ✅ `./scripts/prod-gate.sh` passed, including 165 JavaScript and 157 PHP exercise configuration checks.
- ✅ Headless Chromium used the application renderer and styles with a synthetic catalog at widths of 1440, 1100, 721, 720, 390, and 320 px. The cards were equal width in collapsed and expanded states, switched to a vertical stack at 720 px, and stayed within the viewport with long aliases and a long target name. Opening and canceling the merge picker preserved the open names editor and its draft.
- ✅ `git diff --check` passed.

## Security and delivery

The slice used synthetic data for browser checks and reused the existing authenticated merge flow. The working tree contains these changes and pre-existing edits. Commit, push, and deployment are pending.

## Next slice

Use the [exercise layout delivery verification prompt](next-exercise-manager-merge-layout-prompt.md) after release authorization.
