# Implementation progress

## Current architecture

The Mattwarden migration slice is implemented on branch `mattwarden` and awaits coordinated cut-over. Mattwarden owns authentication, sessions, cookies, CSRF generation, headers, static serving, PHP dispatch, and logout. The application is a static shell plus six flat authenticated endpoints.

## Slice history

Earlier Foundation/Postgres slices established canonical schema, importers, compatibility reads/writes, exercise configuration, and connectors. Their detailed reports remain in `docs/slices/` as historical records; paths and runtime claims in those reports describe the repository at the time and are not current operating instructions.

| Slice | State | Current note |
|---|---|---|
| Foundation schema/import/read/write | retained | Optional local/future backend modules live in `lib/` |
| Runtime diagnostics | removed | Diagnostics are not public product endpoints |
| Live connectors | retained | Authenticated connector administration remains a product feature |
| Mattwarden migration | implemented, not deployed | See migration report and fixed runbook |

## Current validation

The source contract, endpoint guards, static CSP, local router dispatch, deploy refusal cases, PHP suites, JavaScript suites, lints, Compose configuration, Foundation static-server startup, and local browser behavior are validated by `scripts/prod-gate.sh` plus the commands recorded in `docs/slices/mattwarden-migration-report.md`. The optional Foundation import integration block has one documented pre-existing schema mismatch; it does not affect Hetzner production or the Mattwarden migration.

## Next slice

Use `docs/slices/next-runtime-slice-prompt.md` only after the Mattwarden cut-over is complete and verified.
