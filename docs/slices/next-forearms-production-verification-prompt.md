# Release and verify Forearms in production

Read `AGENTS.md`, `DEPLOY.md`, `docs/architecture-next.md`, `docs/codex-framework.md`, `docs/implementation-progress.md`, and `docs/slices/forearms-fatigue-report.md`.

The Forearms application release is deployed. The remaining catalog promotion is a required [v1.0 cutover step](../planning/milestones/ms-v1-0.md#required-production-catalog-promotion), tracked in [TASK-CUTOVER-CATALOG](../planning/tasks/task-cutover-catalog.md).

- Capture fresh owner-only copies of both production catalogs over pinned, key-only SFTP. Compare their checksums with the production baselines in the private staging release bundle under `catalog-releases/`. Compare staging with the bundle as well. If either side changed, reconcile the newer data and validate again before publishing.
- Rehearse and run the approved production catalog promotion using the v1.0 procedure. Retain both backups for recovery. Keep the private bundle out of Git and the static upload tree.
- Recheck the catalog against the reconciled candidate and verify authenticated Exercise Manager and the fatigue map. Check production merge targets, staged aliases, Spinning, Padel, and saved overrides. Verify saves on disposable catalogs unless a production save is explicitly requested.
- If rolling back the UI after a Forearms weight has been saved, keep a backend that accepts the new weight. Update the slice verification status and report commit, push, and deploy state.
