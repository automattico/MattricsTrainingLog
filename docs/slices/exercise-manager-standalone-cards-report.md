# Standalone Exercise Manager cards

Date 2026-11-23 · Branch `main`

## Summary

Removed the surrounding Names card and heading. Matching names and merging are now sibling cards styled like the other sections in the editor. The cards are equal width on desktop and stack with names first at 720 px and below. Activity types show one full-width matching names card.

## Validation

- ✅ `./scripts/prod-gate.sh` passed, including 165 JavaScript exercise configuration checks.
- ✅ Headless Chromium verified the standalone cards, collapsed and expanded layout, long aliases and target names, and merge cancellation at 1440, 1100, 721, 720, 390, and 320 px.
- ✅ `git diff --check` passed.

## Git and deployment

The layout, regression test, and slice documentation are local edits alongside existing working-tree changes. Commit, push, and deployment are pending. Browser verification used a synthetic catalog.

## Next slice

Use the [standalone card release verification prompt](next-exercise-manager-standalone-cards-prompt.md) when the change is released.
