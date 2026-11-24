# Release and verify Forearms in production

Read `AGENTS.md`, `DEPLOY.md`, `docs/architecture-next.md`, `docs/codex-framework.md`, `docs/implementation-progress.md`, and `docs/slices/forearms-fatigue-report.md`.

- Review the working tree and isolate the intended release from unrelated in-progress changes. Resolve the local deploy environment syntax error without printing credentials. Obtain explicit production deploy approval.
- Capture fresh owner-only copies of both production catalogs over pinned, key-only SFTP. Compare their checksums with the production baselines in the private staging release bundle under `catalog-releases/`. Compare staging with the bundle as well. If either side changed, reconcile the newer data and validate again before publishing.
- Use the approved deploy script for code. Its `--only-missing` seed rule preserves the existing runtime catalogs. Separately authorize and apply the reconciled exercise and activity-type catalogs through the production catalog maintenance path. Compare against the fresh production baselines again immediately before applying the update, and retain both pre-promotion backups for recovery. Keep the private bundle out of Git and the static upload tree.
- Recheck the catalog against the reconciled candidate and verify authenticated Exercise Manager and the fatigue map. Check production merge targets, staged aliases, Spinning, Padel, and saved overrides. Verify saves on disposable catalogs unless a production save is explicitly requested.
- If rolling back the UI after a Forearms weight has been saved, keep a backend that accepts the new weight. Update the slice verification status and report commit, push, and deploy state.
