# Slice Report: Slice 15 - Local Connector Admin View and Incremental Hevy Live Sync

Date: 2026-06-10
Agent/thread: Codex
Branch: current working branch

## Summary

Added a dedicated authenticated `Connectors` app view plus local-only `GET/POST /api/connectors.php` so operators can set, clear, and test the Hevy API key without editing private files directly. Upgraded `private/storage/live-connectors.json` to schema version `2`, split private sync vs test state, and changed Hevy live sync to keep the first run full-history while later reruns use a 30-day incremental overlap cursor with app-safe runtime and diagnostics visibility.

## Files Changed

- `scripts/lib/foundation-connectors.php`
- `scripts/lib/foundation-import.php`
- `scripts/sync-live-connectors.php`
- `public/api/connectors.php`
- `public/api/foundation-diagnostics.php`
- `public/assets/js/core/constants.js`
- `public/assets/js/core/state.js`
- `public/assets/js/renderers/orchestrator.js`
- `public/assets/js/connectors.js`
- `public/assets/css/connectors.css`
- `public/assets/css/main.css`
- `public/assets/css/responsive.css`
- `public/views/nav.php`
- `public/views/main-views.php`
- `public/views/scripts.php`
- `tests/connectors-api-tests.php`
- `tests/fixtures/run-connectors-endpoint.php`
- `tests/foundation-import-tests.php`
- `tests/foundation-runtime-status-tests.php`
- `tests/foundation-import-diagnostics-tests.php`
- `docs/compatibility-api.md`
- `docs/docker-postgres-foundation.md`
- `docs/implementation-progress.md`
- `docs/slices/slice-15-connector-admin-and-incremental-hevy-sync-report.md`
- `docs/slices/slice-16-manual-live-sync-trigger-prompt.md`

## Validation

- ✅ `php -l public/api/connectors.php`
- ✅ `php -l scripts/lib/foundation-connectors.php`
- ✅ `php -l scripts/lib/foundation-import.php`
- ✅ `php -l public/api/foundation-diagnostics.php`
- ✅ `php -l tests/connectors-api-tests.php`
- ✅ `php -l tests/fixtures/run-connectors-endpoint.php`
- ✅ `node public/tests/exercise-config-tests.js`
- ✅ `php tests/connectors-api-tests.php`
- ✅ `php tests/foundation-runtime-status-tests.php` (`1` integration block skipped because local Postgres was unreachable)
- ✅ `php tests/foundation-import-tests.php` (`1` integration block skipped because local Postgres was unreachable)
- ✅ `php tests/foundation-compat-api-tests.php` (`1` integration block skipped because local Postgres was unreachable)
- ✅ `php tests/foundation-config-write-tests.php` (`1` integration block skipped because local Postgres was unreachable)
- ✅ `php tests/foundation-import-diagnostics-tests.php` (`1` integration block skipped because local Postgres was unreachable)
- ✅ `git status --short` reviewed

## Security Check

- secrets touched: no
- sensitive files modified: no
- notes: This slice stored connector secrets only in private runtime storage, never returned raw API keys to browser responses, and exposed only app-safe connector booleans, timestamps, strategy labels, cursor timestamps, and fetch counts.

## Decisions Made

- Connector admin lives in a dedicated `Connectors` view instead of being folded into the existing settings editor.
- Hevy save with an empty browser key field preserves the existing private key so operators can change `enabled` without retyping secrets.
- Hevy test actions update private test state only and do not create canonical import batches or mutate activity data.
- The first Hevy live sync remains full-history; later reruns use a 30-day `incremental_overlap` refetch window.
- Corrupt Hevy cursor state with prior sync history still falls back to bounded overlap instead of rereading full history.

## Known Issues

- Validation requiring a live local Postgres foundation runtime was skipped in this environment because `127.0.0.1:5433` was unavailable.
- Garmin live fetching remains intentionally deferred; Slice 15 keeps Garmin visible in status and diagnostics only.
- The new Connectors view does not yet trigger a live sync run directly; operators still use the existing sync script/runtime prep path.

## Git State

- working tree: dirty before Slice 15; now also includes additive connector-admin, incremental-sync, test, and documentation changes
- commit: not committed
- push: not pushed
- deploy: not deployed

## Next Slice

Slice 16: Manual Live Sync Trigger and Connector Run Feedback

## Next Slice Prompt

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
