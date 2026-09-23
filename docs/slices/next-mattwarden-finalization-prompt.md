# Slice: Finalize the Mattwarden migration branch

Read first:
- `AGENTS.md`
- `docs/README.md`
- `docs/implementation-progress.md`
- `docs/mattwarden-migration.md`
- `docs/hetzner-private-deploy.md`
- `docs/slices/mattwarden-webroot-normalization-report.md`

Goal: separate and commit the completed Mattwarden documentation changes, then merge/push only with explicit operator approval.

Context: production uses `public_html/mattrics` with only Mattwarden's two gate files. Staging, the nested-shim rollback, `/mattrics-private`, and `/mattrics-lib` are absent. The protected non-auth private rollback remains. Non-AI production checks passed. Workout AI still has a rejected Anthropic credential; the operator explicitly skipped key replacement and successful-workout verification for migration completion.

The checkout also contains separately owned Pádel and detail-workout changes, including overlap in `docs/implementation-progress.md`. Do not combine, discard, deploy, or commit those changes as part of the Mattwarden documentation commit.

In scope:
- Reconcile the Mattwarden documentation patch with any concurrently completed product commits.
- Run `git diff --check`, Markdown relative-link validation, JavaScript syntax checks for in-app docs, and `./scripts/prod-gate.sh` on the intended final source state.
- Commit only the Mattwarden documentation/finalization changes in a small commit.
- Review the exact branch diff and working tree.
- Merge `mattwarden` to `main` and push only after explicit operator approval.

Out of scope:
- Re-enabling or testing workout AI unless separately requested.
- Recreating staging/legacy directories or changing the normalized webroot.
- Deploying unrelated Pádel/detail-workout work without its own approval.

Completion requirements:
- Record exact test output and remaining skips.
- Report working-tree, commit, push, and deploy state.
- Update `docs/implementation-progress.md` if the branch state changes.
