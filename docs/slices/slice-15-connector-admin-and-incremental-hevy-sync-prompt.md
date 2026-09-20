# Slice 15: Connector Admin Surface and Incremental Hevy Sync

Read first:
- AGENTS.md
- docs/architecture-next.md
- docs/codex-framework.md
- docs/implementation-progress.md
- docs/compatibility-api.md
- docs/docker-postgres-foundation.md

Goal:
Add the next operator-safe live-connector slice: manage Hevy connector state without hand-editing private files, add incremental Hevy sync cursoring so reruns do not refetch the full workout history, and keep the Garmin live groundwork compatible without attempting a brittle Garmin fetch path yet.

Context:
- Slice 14 added the first private connector store in `private/storage/live-connectors.json`.
- Slice 14 added real Hevy live sync through `scripts/sync-live-connectors.php`.
- Slice 14 reserved the Garmin live source and batch contract, but Garmin still has groundwork/status only.
- Slice 14 changed duplicate resolution so direct Hevy/Garmin winners can display legacy Strava/Google Sheet `Name` and `Type`.
- The current operator flow still requires manual editing of the private connector file to add or rotate a Hevy API key.
- The current Hevy live sync is additive but full-history; it does not yet persist an incremental remote cursor or bounded fetch policy.
- The working tree is already dirty with unrelated user changes and must be preserved.
- The current production model remains static/PHP and must continue to work unchanged when canonical mode is disabled.

In scope:
- Add a local-only operator surface for connector management that writes only to private runtime storage.
- Support set/clear/test for the Hevy API key without exposing the raw key to browser responses.
- Persist app-private incremental Hevy sync state such as last remote sync marker or bounded refetch metadata in the connector store.
- Change Hevy live sync to use incremental fetch rules when prior sync state exists, with a conservative fallback to bounded backfill when needed.
- Extend runtime-status and diagnostics with any additional app-safe fields needed to explain connector state transitions and incremental sync behavior.
- Add focused tests and documentation for connector admin writes and incremental Hevy sync behavior.

Out of scope:
- No Garmin live web-session, cookie, or unofficial API fetch implementation.
- No production deploy cutover.
- No browser-visible secret values or raw upstream payload exposure.
- No full multi-user connector UI beyond the current single-user/local operator workflow.

Security constraints:
- Do not read, print, commit, or expose secrets.
- Do not commit `.env.local`, `private/config.php`, API keys, tokens, tunnel credentials, raw health exports, or DB dumps.
- Expose only app-safe connector booleans, status strings, counts, cursor timestamps, and error timestamps.
- Keep connector secrets and incremental sync state outside `public/` and outside canonical SQL tables.

Implementation requirements:
- Preserve the current deploy model documented in `docs/architecture.md`.
- Keep the work additive and compatible with the current production path.
- Reuse the Slice 14 connector store, source registry, import-batch tracking, and diagnostics endpoints instead of inventing a parallel connector system.
- Keep Garmin live groundwork visible and compatible even though Garmin fetching remains out of scope.
- Keep duplicate metadata overlay behavior unchanged unless a bug fix is required.

Validation:
- Review changed connector/admin/sync/docs code for coherence.
- Run focused validation for touched server-side connector and incremental sync paths.
- Run `node public/tests/exercise-config-tests.js`.
- Run `php tests/foundation-runtime-status-tests.php`.
- Run `php tests/foundation-import-tests.php`.
- Run `php tests/foundation-compat-api-tests.php`.
- Run `php tests/foundation-config-write-tests.php`.
- Run `php tests/foundation-import-diagnostics-tests.php`.
- Run any new focused connector-admin or incremental-sync tests you add.
- Run `git status --short`.
- Do not run deploy.

Completion requirements:
- Update docs/implementation-progress.md.
- Add a slice report using docs/templates/slice-report.md.
- Add the next slice prompt using docs/templates/next-slice-prompt.md.
