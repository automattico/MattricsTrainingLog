# Implementation progress

## Current architecture

The Mattwarden migration is deployed on branch `mattwarden`; unauthenticated production smoke checks and most logged-in product checks pass. The AI request is blocked by an Anthropic HTTP 401 from the configured API key; the browser now handles non-JSON upstream error pages without a syntax error. Legacy cleanup and merge/push are on hold until a replacement key is installed and one successful AI request is verified. Mattwarden owns authentication, sessions, cookies, CSRF generation, headers, static serving, PHP dispatch, and logout. The application is a static shell plus six flat authenticated endpoints.

## Slice history

Earlier Foundation/Postgres slices established canonical schema, importers, compatibility reads/writes, exercise configuration, and connectors. Their detailed reports remain in `docs/slices/` as historical records; paths and runtime claims in those reports describe the repository at the time and are not current operating instructions.

| Slice | State | Current note |
|---|---|---|
| Foundation schema/import/read/write | retained | Optional local/future backend modules live in `lib/` |
| Runtime diagnostics | removed | Diagnostics are not public product endpoints |
| Live connectors | retained | Authenticated connector administration remains a product feature |
| Mattwarden migration | deployed, AI credential replacement pending | Production data and app/gate content deployed 2026-09-22; Anthropic rejected the configured key with 401 |
| Real-gate integration check | validated locally | Passkey, static shell, session, guards, PATH_INFO, settings write/readback; synthetic upstream intentionally returned 502 |

## Current validation

The source contract, endpoint guards, static CSP, local router dispatch, deploy refusal cases, PHP suites, JavaScript suites, lints, Compose configuration, Foundation static-server startup, and local browser behavior are validated by `scripts/prod-gate.sh` plus the commands recorded in `docs/slices/mattwarden-migration-report.md`. An isolated local run against the merged Mattwarden gate is recorded in `docs/slices/mattwarden-real-gate-validation-report.md`. The optional Foundation import integration block has one documented pre-existing schema mismatch; it does not affect Hetzner production or the Mattwarden migration.

## Next slice

Use `docs/slices/next-mattwarden-ai-credential-verification-prompt.md` after replacing the private Anthropic key to finish AI verification and legacy cleanup. Use `docs/slices/next-runtime-slice-prompt.md` only after that is complete.
