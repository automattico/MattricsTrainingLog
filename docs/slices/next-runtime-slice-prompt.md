# Next slice: post-cut-over runtime verification

Start only after the Mattwarden cut-over in `docs/mattwarden-migration.md` has completed successfully.

## Goal

Verify production behavior and decide whether the local-only Foundation/Postgres HTTP runtime should be retired, kept as import tooling, or deliberately ported to a Mattwarden-compatible development/runtime boundary.

## Constraints

- Do not reintroduce application authentication, sessions, cookies, passkeys, recovery, host bypasses, or request-derived origins.
- Keep `MATTWARDEN_SITE_DIR` as the only application base path.
- Preserve the six-endpoint public API unless a separately approved product change requires more.
- Keep production private data and credentials out of Git and logs.
- Do not deploy or push without explicit approval.

## Inputs

- `docs/mattwarden-migration.md`
- `docs/architecture.md`
- `docs/docker-postgres-foundation.md`
- production verification results from cut-over step 6

## Deliverables

- Record authenticated production verification without credential values or private payloads.
- Audit gate logs for generic application errors.
- Make an explicit Foundation-runtime decision and implement it only if separately approved.
- Update `docs/implementation-progress.md`, add a slice report, and prepare the subsequent slice prompt.
