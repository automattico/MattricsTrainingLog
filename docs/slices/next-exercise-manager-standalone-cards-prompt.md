# Standalone Exercise Manager cards release verification

Read `AGENTS.md`, `docs/architecture-next.md`, `docs/codex-framework.md`, `docs/implementation-progress.md`, and the [slice report](exercise-manager-standalone-cards-report.md).

After an authorized release, verify that matching names and merging are standalone, equal-width cards on desktop and stack with names first on a phone. Open both controls on a configured exercise and check that the picker still shows all eligible configured targets. Verify that activity types show one full-width matching names card. Use synthetic data for any merge submission.

Preserve unrelated working-tree changes. Run the production gate on the release checkout and record the verification and delivery state.
