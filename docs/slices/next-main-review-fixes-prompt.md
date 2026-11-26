# Verify the hosted refresh with resumable Hevy paging

Read [AGENTS.md](../../AGENTS.md), [architecture-next.md](../architecture-next.md), [codex-framework.md](../codex-framework.md), [implementation-progress.md](../implementation-progress.md), and the [hosted application refresh handoff](next-planning-production-readiness-prompt.md).

Follow the hosted TLS and refresh handoff after separate approval for temporary hosted work. Include migration `007_hevy_refresh_paging.sql` on the authorized isolated database. Check that Hevy fetches the newest page, advances its history continuation after a durable commit, revisits edited workouts, and retries a failed batch without losing progress. Measure the hosted request budget. Keep live dashboard reads on Sheets through the rehearsal.

Record observed evidence in the runtime and sync tickets and rebuild the private planning workspace.
