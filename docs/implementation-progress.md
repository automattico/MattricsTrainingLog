# Implementation progress

## Current architecture

The Mattwarden migration is deployed on branch `mattwarden`; unauthenticated production smoke checks pass and the operator reports non-AI product checks complete. The active Hetzner webroot is normalized to `public_html/mattrics`, which contains only Mattwarden's `.htaccess` and `index.php`; the temporary staging target, nested-shim rollback, `/mattrics-private`, and `/mattrics-lib` are absent. The workout-AI credential still returns Anthropic HTTP 401, but the operator explicitly skipped AI verification for this migration completion; the browser handles non-JSON upstream errors without a syntax error. Mattwarden owns authentication, sessions, cookies, CSRF generation, headers, static serving, PHP dispatch, and logout. The application is a static shell plus six flat authenticated endpoints.

## Slice history

Earlier Foundation/Postgres slices established canonical schema experiments, importers, compatibility reads/writes, exercise configuration, and connectors. The bundled Docker/Postgres runtime, schema files, and CLI preparation/import scripts were removed during repository cleanup. Compatibility modules that current API endpoints still import remain in `lib/`. Detailed reports remain in `docs/slices/` as historical records; paths and runtime claims in those reports describe the repository at the time and are not current operating instructions.

| Slice | State | Current note |
|---|---|---|
| Foundation Docker/Postgres runtime | removed | Docker, Compose, schema, migration, and CLI runtime scaffolding were retired; API compatibility modules remain |
| Runtime diagnostics | removed | Diagnostics are not public product endpoints |
| Live connectors | retained | Authenticated connector administration remains a product feature |
| Mattwarden migration | deployed, AI verification explicitly deferred | Production data and app/gate content deployed 2026-09-22; Anthropic rejected the configured key with 401 |
| Hetzner parent-webroot normalization | deployed, verified, and cleaned up | Active target is `public_html/mattrics`; staging, nested-shim rollback, and legacy external paths are absent |
| Real-gate integration check | validated locally | Passkey, static shell, session, guards, PATH_INFO, settings write/readback; synthetic upstream intentionally returned 502 |
| Hevy first-line recognition | validated locally, not deployed | JavaScript and PHP now detect standalone Hevy brand terms in the first non-empty description line, with canonical-read header skipping and regression tests |
| Detail workout visibility | validated locally, not deployed | The detail modal now removes its `is-hidden` class when parsed or canonical exercises exist, so the workout breakdown is visible |
| Pádel activity support | validated locally, not deployed | Padel and its accented/tennis aliases share canonical display/filter metadata, card metrics, and an approved duration-scaled fatigue config |

## Current validation

The source contract, endpoint guards, static CSP, local router dispatch, deploy refusal cases, PHP suites, JavaScript suites, lints, and local browser behavior are validated by `scripts/prod-gate.sh` plus the commands recorded in `docs/slices/mattwarden-migration-report.md`. An isolated local run against the merged Mattwarden gate is recorded in `docs/slices/mattwarden-real-gate-validation-report.md`. Hevy header recognition has JavaScript, PHP import, and canonical-read regression coverage. Detail-modal visibility has focused DOM regression coverage for both workout breakdowns and ordinary notes. Pádel aliases, canonical filtering/display metadata, card metrics, approved activity configuration, and non-zero duration-based fatigue stimulus have focused JavaScript coverage. Retained compatibility tests may skip external database branches when Postgres is unavailable; the repository no longer provides that database runtime.

## Next slice

Use `docs/slices/next-repository-cleanup-finalization-prompt.md` to review and commit the cleanup without sweeping unrelated work into the commit. The earlier Mattwarden documentation finalization handoff remains available for the documentation slice. Merge/push only with explicit approval. Workout AI remains broken because Anthropic rejects the configured credential; repair is explicitly deferred and is not a migration blocker.

For a later, separately approved Hevy release, use `docs/slices/next-hevy-header-runtime-verification-prompt.md` to verify real imported descriptions and any needed re-import without exposing private workout data.

For the detail-modal fix, use `docs/slices/next-detail-workout-visibility-production-prompt.md` to commit, deploy, and verify the workout breakdown in an authenticated production session.

For a separately approved Pádel release, use `docs/slices/next-padel-production-verification-prompt.md` to deliver the code and approved activity config, then verify canonical display, metrics, filtering, and fatigue behavior without changing the source pipeline.
