# Slice 14: Live Hevy/Garmin Connectors and Duplicate Resolution

Read first:
- AGENTS.md
- docs/architecture-next.md
- docs/codex-framework.md
- docs/implementation-progress.md
- docs/compatibility-api.md
- docs/docker-postgres-foundation.md
- docs/strava-sync-architecture.md

Goal:
Define and implement the first live-connector migration slice for Hevy and Garmin Connect, plus the duplicate-resolution contract that lets direct-source rows coexist safely with the existing Strava -> Make.com -> Google Sheet path while preserving Strava-edited title and activity-type metadata.

Context:
- Slice 10 added a real local canonical runtime on `localhost:8081`.
- Slice 11 added direct Garmin import and deterministic Garmin-vs-legacy suppression.
- Slice 12 added import diagnostics for local coverage and suppression debugging.
- Slice 13 hardened shared activity-type normalization and reduced unresolved imported labels without changing read precedence.
- The current real-world activity path often starts on a Garmin device, lands in Garmin Connect, syncs to Strava, then gets edited in Strava before Make.com writes it into Google Sheets and Mattrics.
- Future direct Hevy/Garmin live connectors will therefore create duplicates against the legacy Strava/Google Sheet path unless provenance and precedence are handled intentionally.
- The working tree is already dirty with unrelated user changes and must be preserved.
- The current production model remains static/PHP and must continue to work unchanged when canonical mode is disabled.

In scope:
- Add the minimum viable canonical connector groundwork for live Hevy and Garmin Connect ingestion.
- Define how connector credentials/status are represented server-side without exposing secrets to the browser.
- Reuse the existing canonical source registry, import-batch tracking, compatibility read layer, and diagnostics endpoint where practical.
- Add duplicate-resolution behavior or metadata rules so that when a direct Hevy/Garmin row and a legacy Strava/Google Sheet row represent the same activity, Strava-derived title and activity type are preferred.
- Add focused tests and documentation for connector-state handling and duplicate metadata precedence.

Out of scope:
- No production deploy cutover.
- No Concept2 import in this slice.
- No broad fuzzy cross-source dedupe expansion beyond the specific Hevy/Garmin-vs-Strava duplicate workflow.
- No raw payload exposure or browser-visible credential values.
- No full multi-provider sync scheduler unless a small local-only version is required for proving the connector path.

Security constraints:
- Do not read, print, commit, or expose secrets.
- Do not touch `.env.local`, `private/config.php`, API keys, tokens, tunnel credentials, raw health exports, or DB dumps unless the slice explicitly adds new private-only credential storage scaffolding.
- Expose only app-safe status/count/timestamp metadata, never credential values, raw payload contents, or absolute private paths.

Implementation requirements:
- Preserve the current deploy model documented in `docs/architecture.md`.
- Keep the work additive and compatible with the current production path.
- Preserve automatic legacy fallback when canonical reads are unavailable.
- Treat the Strava/Google Sheet path as legacy/fallback overall, but preserve its title and activity-type edits as the preferred metadata for matched duplicates.
- Reuse existing migration/import helpers and diagnostics where practical instead of inventing parallel infrastructure.

Validation:
- Review changed connector/auth/duplicate-resolution/docs code for coherence.
- Run focused validation for touched server-side connector and precedence paths.
- Run `node public/tests/exercise-config-tests.js`.
- Run `php tests/foundation-runtime-status-tests.php`.
- Run `php tests/foundation-import-tests.php`.
- Run `php tests/foundation-compat-api-tests.php`.
- Run `php tests/foundation-config-write-tests.php`.
- Run any new focused connector or duplicate-resolution tests you add.
- Run `git status --short`.
- Do not run deploy.

Completion requirements:
- Update docs/implementation-progress.md.
- Add a slice report using docs/templates/slice-report.md.
- Add the next slice prompt using docs/templates/next-slice-prompt.md.
