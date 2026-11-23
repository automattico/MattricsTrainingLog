# Exercise Manager merge layout release verification

Read `AGENTS.md`, `docs/architecture-next.md`, `docs/codex-framework.md`, `docs/implementation-progress.md`, and the [layout slice report](exercise-manager-merge-layout-report.md).

Verify the layout in an authenticated session after an authorized release. Confirm that the two Names cards have equal widths on desktop and stack on a phone. Search for a specific exercise, open the merge picker, and verify that it still offers exercises outside the search results. Check a configured exercise, an unconfigured exercise, and an activity type. Use synthetic data for any merge submission.

Preserve unrelated working-tree changes and keep credentials and training data out of verification notes. Run `./scripts/prod-gate.sh` before an authorized release. Record results in implementation progress and report commit, push, and deployment state.
