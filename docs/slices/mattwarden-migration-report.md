# Mattwarden migration slice report

## Outcome

Mattrics is converted from a self-authenticating PHP document-root application into a static shell plus a flat authenticated API behind Mattwarden. The old authentication subsystem and public diagnostics are removed. Shared code and private state now sit outside the static tree.

## Major changes

- Added six root `api/` endpoints and non-addressable root `lib/` modules.
- Added static `public/index.html`, self-hosted fonts, memory-only CSRF bootstrap, strict-CSP-compatible handlers/templates, and Mattwarden logout.
- Added the Mattwarden contract stub, local router, integration tests, structural tests, and deploy refusal tests.
- Replaced deployment with explicit SFTP allowlists targeting `sites/mattrics` and only-missing private seeds.
- Kept Foundation/Postgres local-only with minimal auth-state/environment cleanup; its HTTP application was not migrated.
- Added the fixed ordered cut-over runbook in `docs/mattwarden-migration.md`.

## Security

- No secrets or raw health exports were added to Git.
- `.env.local` and `private/config.php` remain ignored and were not printed.
- Application code has no session, cookie, WebAuthn, credential-recovery, request-host, or proxy-origin logic.
- Provider credentials remain server-side in private configuration/state.
- Mattwarden owns authentication, CSRF token generation, headers, dispatch, and logout.

## Validation

- `scripts/prod-gate.sh` passes with all non-database suites; its three canonical database blocks skip cleanly when Postgres is stopped.
- With local Postgres started, compatibility API tests pass `78/78` and config-write tests pass `41/41`.
- The Foundation import integration block exposes a pre-existing local schema mismatch: connector state `ready` is rejected by the old `mattrics_data_sources_connection_status_check`. It is recorded as a future Foundation issue rather than expanding this auth migration into a schema slice.
- The Foundation static server starts successfully in a one-off container. Host port 8081 remains occupied by the separately running Mattwarden stack, so startup validation was performed without publishing that port.
- Browser smoke testing loaded the dashboard and documentation under the default CSP with no console warnings or errors after the final reload.
- Deployment refusal tests cover password authentication, missing host pins, and a `public_html` target.

The deploy itself is intentionally not run.

## Dependencies

Cut-over waits for the parallel Mattwarden PHP-app mode and `mattrics` site configuration. The operator must move production private data before the first Mattrics deploy.
