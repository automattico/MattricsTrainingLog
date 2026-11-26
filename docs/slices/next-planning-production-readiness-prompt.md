# Verify the v1.0 hosted application refresh

Read [AGENTS.md](../../AGENTS.md), [architecture-next.md](../architecture-next.md), [codex-framework.md](../codex-framework.md), [implementation-progress.md](../implementation-progress.md), and the [prior hosted probe report](v1-hosted-verification-report.md).

Implement and verify production certificate and hostname checks in the actual Postgres PDO path for dashboard reads and direct refresh writes. Start with [TASK-HOST-APP-TLS](../planning/tasks/task-host-app-tls.md). Preserve the native loopback dev and staging databases. Cover positive and wrong-hostname controls and run the repository gate and disposable Postgres integration suite.

After separate authorization for temporary hosted work, complete [TASK-HOST-REFRESH](../planning/tasks/task-host-refresh.md) on an isolated, Mattwarden-authenticated path. Verify the release decoder from web PHP, authenticated cold app-open and reload for Garmin and Hevy, paging, timeout and retries, overlapping requests, stable IDs, last success and cursor behavior. Measure the practical web request budget and record redacted results. Verify removal of temporary files and routes after the check.

Keep the live dashboard on Sheets. Production code deployment, migrations or data loading, Make changes, catalog promotion, and the read switch require their own approvals. Keep credentials, raw workout data, and private evidence outside Git and the report.

Update the two runtime tasks, M0 access and v1.0 sync checks, and implementation progress according to the observed outcomes. Add a slice report and the next slice prompt, then validate and rebuild the planning previews.
