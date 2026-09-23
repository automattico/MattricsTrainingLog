# Implementation progress

## Current architecture

The Mattwarden application and repository cleanup are deployed from `main` at cleanup commit `b97cab4`. The deploy-time production gate passed and the managed remote trees matched the explicit allowlist on 2026-09-23. The active Hetzner webroot is normalized to `public_html/mattrics`, which contains only Mattwarden's `.htaccess` and `index.php`; the temporary staging target, nested-shim rollback, `/mattrics-private`, and `/mattrics-lib` are absent. The workout-AI credential still returns Anthropic HTTP 401, but repair is explicitly deferred; the browser handles non-JSON upstream errors without a syntax error. Mattwarden owns authentication, sessions, cookies, CSRF generation, headers, static serving, PHP dispatch, and logout. The application is a static shell plus six flat authenticated endpoints.

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
| Hevy first-line recognition | deployed; authenticated verification pending | JavaScript and PHP detect standalone Hevy brand terms in the first non-empty description line, with canonical-read header skipping and regression tests |
| Detail workout visibility | deployed; authenticated verification pending | The detail modal removes its `is-hidden` class when parsed or canonical exercises exist, so the workout breakdown is visible |
| Pádel activity support | deployed; authenticated verification pending | Padel and its accented/tennis aliases share canonical display/filter metadata, card metrics, and an approved duration-scaled fatigue config |
| Repository cleanup | deployed | Obsolete Docker/Foundation/Postgres scaffolding, historical prompts, dead code/CSS, and orphaned assets were removed; required compatibility modules and `CLAUDE.md` remain |

## Current validation

The source contract, endpoint guards, static CSP, local router dispatch, deploy refusal cases, PHP suites, JavaScript suites, lints, and local browser behavior are validated by `scripts/prod-gate.sh` plus the commands recorded in `docs/slices/mattwarden-migration-report.md`. The full gate passed again during the 2026-09-23 deployment with no skipped database blocks, followed by successful managed-remote-tree verification. An isolated local run against the merged Mattwarden gate is recorded in `docs/slices/mattwarden-real-gate-validation-report.md`. Hevy header recognition has JavaScript, PHP import, and canonical-read regression coverage. Detail-modal visibility has focused DOM regression coverage for both workout breakdowns and ordinary notes. Pádel aliases, canonical filtering/display metadata, card metrics, approved activity configuration, and non-zero duration-based fatigue stimulus have focused JavaScript coverage.

## Next slice

Perform authenticated production verification of the newly deployed Hevy header recognition, workout-detail visibility, and Pádel behavior using the corresponding prompts below. Workout AI remains broken because Anthropic rejects the configured credential; repair is explicitly deferred and is not a deployment blocker.

Use `docs/slices/next-hevy-header-runtime-verification-prompt.md` to verify real imported descriptions and any needed re-import without exposing private workout data.

Use `docs/slices/next-detail-workout-visibility-production-prompt.md` to verify the workout breakdown in an authenticated production session; its commit/deploy steps are already complete.

Use `docs/slices/next-padel-production-verification-prompt.md` to verify canonical display, metrics, filtering, and fatigue behavior without changing the source pipeline; delivery is already complete.
