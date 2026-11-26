# Verify the hosted PHP runtime

Read `AGENTS.md`, `docs/architecture-next.md`, `docs/codex-framework.md`, `docs/implementation-progress.md`, and the [database environments report](database-environments-report.md).

Dev migration replay and the full synthetic staging recovery rehearsal passed. The hosted database also returned `1` from the Mac over TLS with certificate and hostname verification. Hosted CLI and web PHP, scheduled execution, and outbound access still need evidence.

Read `docs/planning/stories/st-host-path.md` and `docs/planning/stories/st-host-runtime.md`. Confirm the approved private probe path before running on the host. Check CLI and web PHP, `pdo_pgsql`, and read-only database access. Verify a harmless scheduled run and relevant outbound access when authorized. Keep credentials, configuration, and probe output private. Production migrations, imports, scheduled sync, and cutover need separate approval.

Update the slice report and backlog with observed evidence, then validate and rebuild both planning previews.
