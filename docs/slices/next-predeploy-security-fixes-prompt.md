# Verify the secured release on the Sheets-backed deployment

Read [AGENTS.md](../../AGENTS.md), [implementation progress](../implementation-progress.md), and the [deployment runbook](../../DEPLOY.md).

After explicit approval for the code-only deployment, deploy the reviewed commit with the canonical database URL unset and the direct providers and Make cache disabled. Verify the managed remote inventory. Through an authenticated Mattwarden session, verify the dashboard loads its Sheet-backed data and the Refresh button uses the guarded POST to refresh it.

Before separately enabling direct Garmin refresh, rehearse the final request deadline on hosted PHP and verify stable IDs and checkpoint recovery. Keep Sheets available until the approved read switch.
