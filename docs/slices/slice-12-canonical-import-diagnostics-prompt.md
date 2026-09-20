# Slice 12: Canonical Import Diagnostics

Read first:
- AGENTS.md
- docs/architecture-next.md
- docs/codex-framework.md
- docs/implementation-progress.md
- docs/compatibility-api.md
- docs/docker-postgres-foundation.md

Goal:
Add local-only canonical import diagnostics so Hevy, Garmin, and legacy bootstrap coverage can be inspected quickly without exposing secrets, raw payloads, or private filesystem paths.

Context:
- Slice 10 turned the foundation stack into a runnable local canonical app runtime on `localhost:8081`.
- Slice 11 added the first direct Garmin import path alongside the existing legacy bootstrap and direct Hevy import flows.
- Canonical reads now select among legacy snapshot, direct Hevy, direct Garmin, and reserved direct Concept2 provenance.
- The working tree is already dirty with unrelated user changes and must be preserved.
- The current production model remains static/PHP and must continue to work unchanged when canonical mode is disabled.

In scope:
- Add a local-only diagnostics surface for canonical import coverage and recent batch health.
- Report app-safe source metadata such as source kind, latest batch status, imported activity counts, latest successful import times, and unresolved activity-type counts.
- Surface enough Garmin/Hevy/legacy import state to debug why canonical reads are or are not winning over legacy rows.
- Add focused tests for the new diagnostics behavior and document the local debugging workflow.

Out of scope:
- No production deploy cutover.
- No browser-facing replacement for `public/api/data.php` or `public/api/exercises.php`.
- No auth/session migration into Postgres.
- No raw payload inspection, file download, or secret exposure.
- No full Concept2 importer unless diagnostics work uncovers a direct blocker that cannot be solved otherwise.

Security constraints:
- Do not read, print, commit, or expose secrets.
- Do not touch `.env.local`, `private/config.php`, API keys, tokens, tunnel credentials, raw health exports, or DB dumps.
- Expose only app-safe status/count/timestamp metadata, never credential values, raw payload contents, or absolute private paths.

Implementation requirements:
- Preserve the current deploy model documented in `docs/architecture.md`.
- Keep diagnostics local-only and additive.
- Reuse the foundation runtime and canonical import metadata already stored in Postgres instead of inventing a second tracking system.
- Preserve automatic legacy fallback when canonical reads are unavailable.

Validation:
- Review changed diagnostics/API/docs code for coherence.
- Run focused PHP validation for touched runtime and diagnostics paths.
- Run `node public/tests/exercise-config-tests.js`.
- Run `php tests/foundation-runtime-status-tests.php`.
- Run `php tests/foundation-import-tests.php`.
- Run `php tests/foundation-compat-api-tests.php`.
- Run `php tests/foundation-config-write-tests.php`.
- Run any new focused diagnostics tests you add.
- Run `git status --short`.
- Do not run deploy.

Completion requirements:
- Update docs/implementation-progress.md.
- Add a slice report using docs/templates/slice-report.md.
- Add the next slice prompt using docs/templates/next-slice-prompt.md.
