# Compact Exercise Manager merge

Date 2026-11-23 · Branch `main`

## Summary

Matching names use one full-width card with Edit names and Merge side by side. The merge picker opens inline, starts with no target selected, and searches the full eligible catalog by name, alias, or matching term. Selecting a target reveals the source and target names and the applicable warning. The existing merge endpoint handles confirmation. Canceling preserves an open alias draft.

Activity types show the matching names editor. For exercises, the picker excludes the current configured exercise and remains independent of the manager list filters.

On 25 November the target list became visible beneath the search field so results update as the user types. The merge panel now shows request errors beside its confirmation controls. The parity preview routes confirmed exercise merges to its isolated catalog for the guarded migration workflow. [Catalog ownership](v1-catalog-ownership-report.md)

## Validation

- ✅ `./scripts/prod-gate.sh` passed, including 167 JavaScript exercise configuration checks.
- ✅ Headless Chromium verified the compact header, search focus, target selection, inline warning, draft preservation, and a mocked confirmation request at 1440, 1100, 721, 720, 390, and 320 px.
- ✅ `git diff --check` passed.

## Git and deployment

The changes are local alongside existing working-tree edits. Commit, push, and deployment are pending. Browser checks used synthetic exercise data.

## Next slice

Use the [compact merge release verification prompt](next-exercise-manager-compact-merge-prompt.md) when releasing the change.
