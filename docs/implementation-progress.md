# Implementation progress

## Current architecture

The Mattwarden migration is deployed on branch `mattwarden`; unauthenticated production smoke checks and most logged-in product checks pass. The AI request returned non-JSON HTML and remains unresolved, so legacy cleanup and merge/push are on hold. Mattwarden owns authentication, sessions, cookies, CSRF generation, headers, static serving, PHP dispatch, and logout. The application is a static shell plus six flat authenticated endpoints.

## Slice history

Earlier Foundation/Postgres slices established canonical schema, importers, compatibility reads/writes, exercise configuration, and connectors. Their detailed reports remain in `docs/slices/` as historical records; paths and runtime claims in those reports describe the repository at the time and are not current operating instructions.

| Slice | State | Current note |
|---|---|---|
| Foundation schema/import/read/write | retained | Optional local/future backend modules live in `lib/` |
| Runtime diagnostics | removed | Diagnostics are not public product endpoints |
| Live connectors | retained | Authenticated connector administration remains a product feature |
| Mattwarden migration | deployed, AI verification pending | Production data and app/gate content deployed 2026-09-22; see cut-over report |
| Real-gate integration check | validated locally | Passkey, static shell, session, guards, PATH_INFO, settings write/readback; synthetic upstream intentionally returned 502 |

## Current validation

The source contract, endpoint guards, static CSP, local router dispatch, deploy refusal cases, PHP suites, JavaScript suites, lints, Compose configuration, Foundation static-server startup, and local browser behavior are validated by `scripts/prod-gate.sh` plus the commands recorded in `docs/slices/mattwarden-migration-report.md`. An isolated local run against the merged Mattwarden gate is recorded in `docs/slices/mattwarden-real-gate-validation-report.md`. The optional Foundation import integration block has one documented pre-existing schema mismatch; it does not affect Hetzner production or the Mattwarden migration.

## Next slice

Use `docs/slices/next-mattwarden-production-verification-prompt.md` to diagnose AI, finish verification, and clean up legacy paths. Use `docs/slices/next-runtime-slice-prompt.md` only after that is complete.
