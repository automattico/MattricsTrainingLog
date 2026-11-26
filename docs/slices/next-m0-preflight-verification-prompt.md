# Verify the remaining M0 runtime and Hevy checks

Read `AGENTS.md`, `docs/architecture-next.md`, `docs/codex-framework.md`, `docs/implementation-progress.md`, and the [M0 ticket audit](m0-preflight-ticket-audit-report.md).

- Resolve the private runner decision under [ST-HOST-PATH](../planning/stories/st-host-path.md). If the on-demand design is chosen, record the decision on the scheduling requirement before changing the release criteria.
- Once the probe route is approved, follow the [hosted PHP verification handoff](next-database-environments-verification-prompt.md). Record hosted PHP and PDO Postgres versions and access, certificate-verified database connectivity, scheduling evidence for the agreed runtime, and provider reachability.
- Under [TASK-HEVY-RENAME](../planning/tasks/task-hevy-rename.md), compare provider template IDs across an existing renamed or translated exercise if a suitable read-only sample is available. Record aggregate findings. Obtain authorization for any provider-side edit.

Keep credentials, account data and raw probe output private. Update the M0 tickets, release check and implementation progress with observed evidence. Run planning validation and fixture tests, rebuild both previews, and record the slice outcome and next handoff.
