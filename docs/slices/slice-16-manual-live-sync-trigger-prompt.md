# Slice 16: Manual Live Sync Trigger and Connector Run Feedback

Read first:
- AGENTS.md
- docs/architecture-next.md
- docs/codex-framework.md
- docs/implementation-progress.md
- docs/compatibility-api.md
- docs/docker-postgres-foundation.md

Goal:
Add a local-only operator-safe way to trigger live connector sync runs from the new Connectors view, return app-safe run summaries immediately, and keep the current CLI/runtime prep path compatible.

Context:
- Slice 14 added the private live connector store plus real Hevy live sync and Garmin groundwork.
- Slice 15 added authenticated local-only Hevy save/clear/test management through `GET/POST /api/connectors.php`.
- Slice 15 upgraded the connector store to schema version `2` and changed Hevy live sync to use a 30-day incremental overlap window after the first successful full-history sync.
- Operators can now configure and test Hevy without editing private files, but they still need `scripts/sync-live-connectors.php` or `scripts/foundation-runtime-prepare.sh` to actually run a sync.
- Garmin live fetching remains intentionally out of scope until a stable provider path is chosen.
- The working tree may still contain unrelated user changes and must be preserved.

In scope:
- Add a local-only authenticated sync-trigger action for Hevy through the connector admin surface.
- Reuse `mattrics_foundation_run_live_connector_sync()` instead of creating a second sync path.
- Return app-safe sync-run summaries and refresh connector/runtime/import-diagnostics visibility after a manual run.
- Surface pending/success/error run feedback in the Connectors view.
- Add focused tests and docs for the manual trigger path.

Out of scope:
- No background scheduler or cron system.
- No Garmin live fetch implementation.
- No browser-visible secret values or raw upstream payload exposure.
- No production deploy cutover.

Security constraints:
- Do not read, print, commit, or expose secrets.
- Do not commit `.env.local`, `private/config.php`, API keys, tokens, tunnel credentials, raw health exports, or DB dumps.
- Expose only app-safe connector booleans, statuses, timestamps, strategy labels, cursor timestamps, counts, and sync-run summaries.

Implementation requirements:
- Preserve the current deploy model documented in `docs/architecture.md`.
- Keep the work additive and compatible with the current production path.
- Reuse the Slice 15 connector endpoint/module where practical instead of inventing a parallel operator UI.
- Keep Garmin live groundwork visible and compatible even though Garmin fetching remains out of scope.

Validation:
- Review changed connector/admin/sync/docs code for coherence.
- Run focused validation for touched connector-trigger and sync-summary paths.
- Run `node public/tests/exercise-config-tests.js`.
- Run `php tests/connectors-api-tests.php`.
- Run `php tests/foundation-runtime-status-tests.php`.
- Run `php tests/foundation-import-tests.php`.
- Run `php tests/foundation-compat-api-tests.php`.
- Run `php tests/foundation-config-write-tests.php`.
- Run `php tests/foundation-import-diagnostics-tests.php`.
- Run any new focused sync-trigger tests you add.
- Run `git status --short`.
- Do not run deploy.

Completion requirements:
- Update docs/implementation-progress.md.
- Add a slice report using docs/templates/slice-report.md.
- Add the next slice prompt using docs/templates/next-slice-prompt.md.
