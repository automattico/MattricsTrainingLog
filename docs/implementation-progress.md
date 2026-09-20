# Mattrics Implementation Progress

This file is the handoff ledger for Codex implementation slices. Future slices must update it before completion.

## Current Status

- Slice 0: Codex Framework is implemented as a documentation-only slice.
- Slice 1: Architecture Decision Docs is implemented as a documentation-only slice.
- Slice 2: Docker/Postgres Foundation is implemented as an additive local-only scaffolding slice.
- Slice 3: Canonical Schema is implemented as additive local-only schema and documentation scaffolding.
- Slice 4: Legacy Import Bootstrap is implemented as additive local-only bootstrap/import tooling and documentation.
- Slice 4a: Bootstrap Boolean Binding is implemented and verifies the legacy bootstrap successfully against the local Postgres foundation database.
- Slice 5: Compatibility API is implemented as an additive read-only canonical GET layer with automatic legacy fallback for the current PHP endpoints.
- Slice 6: Read-Only UI Switch-Over is implemented as an additive frontend switch-over to canonical-backed read responses, split source-status visibility, and canonical child-row consumption with legacy fallback.
- Slice 7: Hevy Ingestion is implemented as an additive direct native Hevy CSV import path with canonical child-row writes, UTF-8-safe workout text handling, and canonical read precedence over matching legacy Hevy snapshot rows.
- Slice 8: Fatigue From Canonical Data is implemented as an additive frontend fatigue/readiness switch to canonical typed child-row set details with strict legacy description fallback.
- Slice 9: Exercise Config DB Migration is implemented as an additive canonical config-write layer for `public/api/exercises.php` with JSON shadow-sync and legacy fallback preserved.
- Slice 9.5: Foundation Hardening is implemented as an additive migration-path and canonical read-contract hardening slice ahead of expanded live-connector and duplicate-resolution work.
- Slice 10: Local Canonical Runtime Readiness is implemented as an additive local-only foundation runtime slice that serves the current app on a separate Docker/Postgres stack, generates runtime config from env into private runtime storage, and exposes local-only canonical runtime diagnostics.
- Slice 11: Garmin Import is implemented as an additive local-only Garmin Connect `Activities.csv` ingestion path with deterministic Garmin source IDs, support for both observed Garmin CSV variants, additive activity-type aliases for common Garmin labels, runtime prep integration, and conservative Garmin-vs-legacy read precedence.
- Slice 12: Canonical Import Diagnostics is implemented as an additive local-only diagnostics slice that exposes per-source canonical import coverage, selected-vs-suppressed activity counts, unresolved activity-type samples, and recent batch health without changing browser-facing app APIs.
- Slice 13: Activity Type Normalization Hardening is implemented as an additive local-only normalization slice that tightens shared activity-type name normalization, expands high-confidence canonical aliases for imported Garmin/legacy labels, preserves unresolved ambiguous labels like `Other`, and keeps diagnostics/read precedence contracts unchanged.
- Slice 14: Live Hevy Connector and Duplicate Metadata Overlay is implemented as an additive local-only connector slice that adds private connector-state storage, real Hevy live sync, Garmin live groundwork/status, and Strava-metadata overlay on deterministic Hevy/Garmin duplicate winners.
- Slice 15: Local Connector Admin View and Incremental Hevy Live Sync is implemented as an additive local-only connector-admin slice that adds authenticated Hevy save/clear/test management, private connector-store schema v2, and incremental Hevy overlap sync cursoring without enabling Garmin live fetches.
- Main app is `MattricsTrainingLog`.
- `MattricsNext` is reference/archive only.
- Existing dirty tree must be respected; many unrelated feature files were already modified before this framework slice.
- Current production architecture remains static/PHP until the Docker/Postgres migration explicitly replaces it.

## Locked Decisions

- Keep vanilla JavaScript for now.
- Target own-server Docker/Postgres deployment.
- Use Cloudflare Tunnel/private access for the server deployment path.
- Hevy is the first direct ingestion priority.
- Live API connectors for Hevy and Garmin Connect are now a higher priority than additional file-based imports.
- Garmin follows Hevy as a major device-native ingestion path for endurance activity and future health signals.
- Strava is optional/fallback, not the central data source.
- During duplicate resolution, Strava/Google Sheet title and activity type should trump direct Hevy/Garmin metadata for the same underlying activity until a richer per-field merge model replaces that rule.
- Connector secrets remain private-runtime file-backed for now; SQL stores only app-safe source and sync status metadata.
- Hevy live sync keeps a 30-day overlap refetch window after the first successful full-history live sync.
- Garmin live fetching remains deferred until a stable provider path is chosen; this slice only reserves Garmin live connector groundwork.
- Concept2 erg import remains a future connector option, but it is explicitly deferred for now.
- Design as multi-user-ready while staying single-user-first.
- Open Health Hub is future-compatible direction, not current scope.
- `MattricsNext` concepts may be ported, but MattricsNext is not the future UI shell.

## Completed Slices

| Slice | Date | Summary | Validation |
|---|---|---|---|
| Slice 0: Codex Framework | 2026-06-06 | Added architecture brief, Codex slice workflow, progress ledger, slice templates, and AGENTS guidance. | Docs-only diff reviewed; `git status --short` reviewed. |
| Slice 1: Architecture Decision Docs | 2026-06-06 | Updated the main architecture doc to name `MattricsTrainingLog` as the active app, keep `MattricsNext` as reference only, and define the Docker/Postgres/private-server direction without changing the current deploy model. | Docs reviewed for coherence; `git status --short` reviewed. |
| Slice 2: Docker/Postgres Foundation | 2026-06-06 | Added a separate local-only Compose foundation for a future runtime app and Postgres, documented storage boundaries, and kept the current static/PHP path untouched. | `docker compose -f docker-compose.postgres.yml --profile foundation config`; docs/Docker files reviewed; `git status --short` reviewed. |
| Slice 3: Canonical Schema | 2026-06-06 | Added the first canonical Postgres schema contract, foundation init SQL, and schema design documentation for later imports and compatibility APIs without changing current app behavior. | `docker compose -f docker-compose.postgres.yml --profile foundation config`; disposable Postgres bootstrap validation; SQL/docs reviewed; `git status --short` reviewed. |
| Slice 4: Legacy Import Bootstrap | 2026-06-06 | Added a local-only canonical bootstrap CLI, Postgres import helpers, PHP Hevy import parsing, focused import tests, and slice docs for seeding canonical tables from legacy private inputs without changing live app behavior. | `php tests/foundation-import-tests.php`; `docker compose -f docker-compose.postgres.yml --profile foundation config`; PHP lint on new files; disposable DB integration attempted but blocked by unavailable Docker daemon and local Postgres shared-memory limits; `git status --short` reviewed. |
| Slice 4a: Bootstrap Boolean Binding | 2026-06-06 | Fixed PDO/Postgres boolean parameter handling in the foundation importer so legacy bootstrap writes canonical config booleans safely and completes successfully against the local Postgres foundation database. | `php tests/foundation-import-tests.php`; `php -l scripts/bootstrap-foundation.php`; `php -l scripts/lib/foundation-import.php`; `php scripts/bootstrap-foundation.php --database-url=postgres://mattrics:mattrics-local-only-change-me@127.0.0.1:5433/mattrics`; `psql postgres://mattrics:mattrics-local-only-change-me@127.0.0.1:5433/mattrics -Atc "select count(*) from mattrics.mattrics_activities; select count(*) from mattrics.mattrics_activity_sets;"`; `git status --short` reviewed. |
| Slice 5: Compatibility API | 2026-06-06 | Added a read-only canonical compatibility layer for `GET /api/data.php` and `GET /api/exercises.php`, plus automatic legacy fallback, canonical child-row reads, config toggles, and focused compatibility docs/tests. | `php tests/foundation-compat-api-tests.php`; `php tests/exercise-config-tests.php`; `php tests/foundation-import-tests.php`; `php -l public/api/foundation-read.php`; `php -l public/api/data.php`; `php -l public/api/exercises.php`; `php -l public/api/bootstrap-auth.php`; `php -l public/api/config-defaults.php`; `git status --short` reviewed. |
| Slice 6: Read-Only UI Switch-Over | 2026-06-07 | Switched the current UI to intentionally consume canonical-backed read metadata and child rows through the existing PHP endpoints, surfaced split activity/config source status in the header, and kept legacy fallback behavior intact. | `node public/tests/exercise-config-tests.js`; `php tests/foundation-compat-api-tests.php`; `php -l public/api/exercises.php`; `git status --short` reviewed. |
| Slice 7: Hevy Ingestion | 2026-06-07 | Added a CLI-only native Hevy CSV importer, canonical direct Hevy child-row writes, UTF-8-safe workout text preservation, and read-time preference for direct Hevy rows over matching legacy snapshot rows. | `php -l scripts/import-hevy.php`; `php -l scripts/lib/foundation-import.php`; `php -l public/api/foundation-read.php`; `php tests/foundation-import-tests.php`; `php tests/foundation-compat-api-tests.php`; `php scripts/import-hevy.php`; `git status --short` reviewed. |
| Slice 8: Fatigue From Canonical Data | 2026-06-07 | Switched fatigue/readiness and personal reference-load history to consume canonical typed child-row set details first, while keeping description parsing as strict fallback and preserving canonical read-selection precedence. | `node public/tests/exercise-config-tests.js`; `php tests/foundation-compat-api-tests.php`; `git status --short` reviewed. |
| Slice 9: Exercise Config DB Migration | 2026-06-07 | Switched `public/api/exercises.php` mutations onto canonical Postgres config records when available, kept browser-facing payloads stable, shadow-synced the legacy JSON catalogs after canonical writes, and preserved legacy fallback plus JSON-backed unknown review data. | `node public/tests/exercise-config-tests.js`; `php tests/exercise-config-tests.php`; `php tests/foundation-compat-api-tests.php`; `php tests/foundation-config-write-tests.php`; `php -l public/api/foundation-write.php`; `php -l public/api/exercises.php`; `git status --short` reviewed. |
| Slice 9.5: Foundation Hardening | 2026-06-08 | Added a forward-only foundation migration path, reserved Concept2 provenance support, conservative Concept2-vs-legacy canonical dedupe rules, and selected-activity-scoped child-row reads ahead of Slice 10. | `php tests/foundation-import-tests.php`; `php tests/foundation-compat-api-tests.php`; `php tests/foundation-config-write-tests.php`; `node public/tests/exercise-config-tests.js`; `php -l public/api/foundation-read.php`; `php -l scripts/lib/foundation-import.php`; `php -l scripts/lib/foundation-migrations.php`; `git status --short` reviewed. |
| Slice 10: Local Canonical Runtime Readiness | 2026-06-08 | Turned the separate foundation stack into a real local app runtime on `localhost:8081`, added local-only runtime diagnostics, generated runtime config into private runtime storage, and documented the intentional bootstrap/import path before canonical reads are expected to be ready. | `docker compose -f docker-compose.postgres.yml --profile foundation config`; `php tests/foundation-runtime-status-tests.php`; `php tests/foundation-import-tests.php`; `php tests/foundation-compat-api-tests.php`; `php tests/foundation-config-write-tests.php`; `node public/tests/exercise-config-tests.js`; `php -l public/api/runtime-status.php`; `php -l tests/foundation-runtime-status-tests.php`; `php -l tests/fixtures/run-runtime-status-endpoint.php`; `php -l scripts/lib/foundation-migrations.php`; `git status --short` reviewed. |
| Slice 11: Garmin Import | 2026-06-09 | Added a CLI-only Garmin Connect `Activities.csv` importer, then broadened it to accept both observed Garmin CSV variants, added additive aliases for the most common Garmin activity-type labels, and preserved deterministic Garmin provenance plus conservative Garmin-vs-legacy canonical dedupe while keeping the browser on the existing compatibility APIs. | `php -l scripts/import-garmin.php`; `php -l scripts/lib/garmin-import-parser.php`; `php -l scripts/lib/foundation-import.php`; `php -l public/api/foundation-read.php`; `php -l tests/foundation-import-tests.php`; `php -l tests/foundation-compat-api-tests.php`; `php tests/foundation-import-tests.php`; `php tests/foundation-compat-api-tests.php`; `php tests/foundation-runtime-status-tests.php`; `php tests/foundation-config-write-tests.php`; `node public/tests/exercise-config-tests.js`; `git status --short` reviewed. |
| Slice 12: Canonical Import Diagnostics | 2026-06-09 | Added a local-only `GET /api/import-diagnostics.php` endpoint, shared diagnostics helpers, per-source import coverage and batch summaries, deterministic-vs-non-deterministic dedupe counters, unresolved activity-type samples, focused diagnostics tests, and operator docs for inspecting why direct Hevy/Garmin rows are or are not suppressing legacy snapshot rows. | `php -l public/api/foundation-diagnostics.php`; `php -l public/api/import-diagnostics.php`; `php -l public/api/runtime-status.php`; `php -l public/api/foundation-read.php`; `php -l tests/foundation-import-diagnostics-tests.php`; `php -l tests/fixtures/run-import-diagnostics-endpoint.php`; `node public/tests/exercise-config-tests.js`; `php tests/foundation-runtime-status-tests.php`; `php tests/foundation-import-tests.php`; `php tests/foundation-compat-api-tests.php`; `php tests/foundation-config-write-tests.php`; `php tests/foundation-import-diagnostics-tests.php`; `git status --short` reviewed. |
| Slice 13: Activity Type Normalization Hardening | 2026-06-09 | Tightened the shared config-name normalizer for acronym-prefixed camel case, expanded high-confidence canonical activity-type aliases for Garmin and legacy import labels, reduced unresolved diagnostics for resolvable rows, and intentionally left ambiguous labels like `Other` unresolved. | `php -l public/api/exercise-config-repository.php`; `php -l tests/foundation-import-tests.php`; `php -l tests/foundation-import-diagnostics-tests.php`; `php -l tests/exercise-config-tests.php`; `node public/tests/exercise-config-tests.js`; `php tests/foundation-runtime-status-tests.php`; `php tests/foundation-import-tests.php`; `php tests/foundation-compat-api-tests.php`; `php tests/foundation-config-write-tests.php`; `php tests/foundation-import-diagnostics-tests.php`; `php tests/exercise-config-tests.php`; `git status --short` reviewed. |
| Slice 14: Live Hevy Connector and Duplicate Metadata Overlay | 2026-06-09 | Added a private live-connector store, real Hevy live sync through shared canonical import code, Garmin live groundwork/status, new live batch kinds, and duplicate-resolution metadata overlay so deterministic direct Hevy/Garmin winners can display legacy Strava-edited `Name` and `Type` without losing direct-source metrics or child rows. | `php -l scripts/lib/foundation-connectors.php`; `php -l scripts/lib/foundation-import.php`; `php -l scripts/sync-live-connectors.php`; `php -l public/api/foundation-read.php`; `php -l public/api/foundation-diagnostics.php`; `php -l public/api/runtime-status.php`; `php -l tests/foundation-import-tests.php`; `php -l tests/foundation-compat-api-tests.php`; `php -l tests/foundation-import-diagnostics-tests.php`; `php -l tests/foundation-runtime-status-tests.php`; `node public/tests/exercise-config-tests.js`; `php tests/foundation-runtime-status-tests.php`; `php tests/foundation-import-tests.php`; `php tests/foundation-compat-api-tests.php`; `php tests/foundation-config-write-tests.php`; `php tests/foundation-import-diagnostics-tests.php`; `git status --short` reviewed. |
| Slice 15: Local Connector Admin View and Incremental Hevy Live Sync | 2026-06-10 | Added a dedicated authenticated Connectors view, local-only `GET/POST /api/connectors.php` Hevy save/clear/test actions, connector-store schema v2 with nested sync/test state, and incremental Hevy overlap cursoring with app-safe diagnostics/runtime visibility. | `php -l public/api/connectors.php`; `php -l scripts/lib/foundation-connectors.php`; `php -l scripts/lib/foundation-import.php`; `php -l public/api/foundation-diagnostics.php`; `php -l tests/connectors-api-tests.php`; `php -l tests/fixtures/run-connectors-endpoint.php`; `node public/tests/exercise-config-tests.js`; `php tests/connectors-api-tests.php`; `php tests/foundation-runtime-status-tests.php`; `php tests/foundation-import-tests.php`; `php tests/foundation-compat-api-tests.php`; `php tests/foundation-config-write-tests.php`; `php tests/foundation-import-diagnostics-tests.php`; `git status --short` reviewed. |

## Active Slice

None. Slice 15 is complete.

## Slice Report: Slice 15 - Local Connector Admin View and Incremental Hevy Live Sync

Date: 2026-06-10
Agent/thread: Codex
Branch: current working branch

### Summary

Added a dedicated authenticated `Connectors` app view plus local-only `GET/POST /api/connectors.php` so operators can set, clear, and test the Hevy API key without editing private files directly. Upgraded `private/storage/live-connectors.json` to schema version `2`, split private sync vs test state, and changed Hevy live sync to keep the first run full-history while later reruns use a 30-day incremental overlap cursor with app-safe runtime and diagnostics visibility.

### Files Changed

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

### Validation

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

### Security Check

- secrets touched: no
- sensitive files modified: no
- notes: This slice stored connector secrets only in private runtime storage, never returned raw API keys to browser responses, and exposed only app-safe connector booleans, timestamps, strategy labels, cursor timestamps, and fetch counts.

### Decisions Made

- Connector admin lives in a dedicated `Connectors` view instead of being folded into the existing settings editor.
- Hevy save with an empty browser key field preserves the existing private key so operators can change `enabled` without retyping secrets.
- Hevy test actions update private test state only and do not create canonical import batches or mutate activity data.
- The first Hevy live sync remains full-history; later reruns use a 30-day `incremental_overlap` refetch window.
- Corrupt Hevy cursor state with prior sync history still falls back to bounded overlap instead of rereading full history.

### Known Issues

- Validation requiring a live local Postgres foundation runtime was skipped in this environment because `127.0.0.1:5433` was unavailable.
- Garmin live fetching remains intentionally deferred; Slice 15 keeps Garmin visible in status and diagnostics only.
- The new Connectors view does not yet trigger a live sync run directly; operators still use the existing sync script/runtime prep path.

### Git State

- working tree: dirty before Slice 15; now also includes additive connector-admin, incremental-sync, test, and documentation changes
- commit: not committed
- push: not pushed
- deploy: not deployed

## Slice Report: Slice 14 - Live Hevy Connector and Duplicate Metadata Overlay

Date: 2026-06-09
Agent/thread: Codex
Branch: current working branch

### Summary

Added the first private live-connector contract under `private/storage/live-connectors.json`, implemented a real Hevy live sync path through `scripts/sync-live-connectors.php`, reserved Garmin live connector/source scaffolding without adding a brittle Garmin fetcher, and updated canonical duplicate resolution so direct Hevy/Garmin winners can display legacy Strava/Google Sheet `Name` and `Type` while keeping direct-source metrics, IDs, and child-row provenance.

### Files Changed

- `scripts/lib/foundation-connectors.php`
- `scripts/lib/foundation-import.php`
- `scripts/sync-live-connectors.php`
- `scripts/foundation-runtime-entrypoint.sh`
- `scripts/foundation-runtime-prepare.sh`
- `public/api/foundation-read.php`
- `public/api/foundation-diagnostics.php`
- `public/api/runtime-status.php`
- `docker/postgres/initdb/001_canonical_schema.sql`
- `docker/postgres/migrations/20260609_001_live_connectors.sql`
- `tests/foundation-import-tests.php`
- `tests/foundation-runtime-status-tests.php`
- `tests/foundation-compat-api-tests.php`
- `tests/foundation-import-diagnostics-tests.php`
- `docs/compatibility-api.md`
- `docs/docker-postgres-foundation.md`
- `docs/postgres-canonical-schema.md`
- `docs/implementation-progress.md`
- `docs/slices/slice-14-live-connectors-duplicate-resolution-report.md`
- `docs/slices/slice-15-connector-admin-and-incremental-hevy-sync-prompt.md`

### Validation

- ✅ `php -l scripts/lib/foundation-connectors.php`
- ✅ `php -l scripts/lib/foundation-import.php`
- ✅ `php -l scripts/sync-live-connectors.php`
- ✅ `php -l public/api/foundation-read.php`
- ✅ `php -l public/api/foundation-diagnostics.php`
- ✅ `php -l public/api/runtime-status.php`
- ✅ `php -l tests/foundation-import-tests.php`
- ✅ `php -l tests/foundation-compat-api-tests.php`
- ✅ `php -l tests/foundation-import-diagnostics-tests.php`
- ✅ `php -l tests/foundation-runtime-status-tests.php`
- ✅ `node public/tests/exercise-config-tests.js`
- ✅ `php tests/foundation-runtime-status-tests.php` (`1` integration block skipped because local Postgres was unreachable)
- ✅ `php tests/foundation-import-tests.php` (`1` integration block skipped because local Postgres was unreachable)
- ✅ `php tests/foundation-compat-api-tests.php` (`1` integration block skipped because local Postgres was unreachable)
- ✅ `php tests/foundation-config-write-tests.php` (`1` integration block skipped because local Postgres was unreachable)
- ✅ `php tests/foundation-import-diagnostics-tests.php` (`1` integration block skipped because local Postgres was unreachable)
- ✅ `git status --short` reviewed

### Security Check

- secrets touched: no
- sensitive files modified: no
- notes: This slice added private connector-store scaffolding and app-safe connector diagnostics only. It did not read or modify `.env.local`, `private/config.php`, raw health exports, database dumps, or real credential values. Browser-facing and diagnostic responses expose only booleans, statuses, counts, source keys, and timestamps.

### Decisions Made

- Connector secrets stay file-backed in private runtime storage instead of being moved into canonical SQL tables.
- Hevy live sync reuses the existing canonical workout importer instead of creating a second direct-write path.
- Garmin remains groundwork-only in this slice; the repo now reserves source, batch, and status contracts for Garmin live without implementing an unstable fetcher.
- Deterministic Hevy/Garmin dedupe signatures were shifted away from editable Strava-facing title/type fields so legacy metadata overlay can work even after Strava edits.

### Known Issues

- Validation requiring a live local Postgres foundation runtime was skipped in this environment because `127.0.0.1:5433` was unavailable.
- The live Hevy sync is still full-history rather than incremental.
- Connector secrets still require operator-managed private file edits; no local-only mutation endpoint or admin UI exists yet.
- Garmin live fetching remains intentionally deferred.

### Git State

- working tree: dirty before Slice 14; now also includes additive live-connector, duplicate-resolution, test, and documentation changes
- commit: not committed
- push: not pushed
- deploy: not deployed

## Slice Report: Slice 13 - Activity Type Normalization Hardening

Date: 2026-06-09
Agent/thread: Codex
Branch: current working branch

### Summary

Hardened the shared activity-type normalization path used by the legacy config repository, canonical config writes, and foundation import resolvers so acronym-prefixed camel-case labels normalize consistently. Expanded the checked-in activity-type catalog with a small set of high-confidence Garmin/legacy aliases, reduced unresolved direct-import diagnostics for resolvable labels, and intentionally kept ambiguous labels like `Other` unresolved.

### Files Changed

- `public/api/exercise-config-repository.php`
- `public/assets/js/core/exercise-config.js`
- `private/data/activity-type-configs.json`
- `tests/foundation-import-tests.php`
- `tests/foundation-import-diagnostics-tests.php`
- `tests/exercise-config-tests.php`
- `public/tests/exercise-config-tests.js`
- `docs/compatibility-api.md`
- `docs/implementation-progress.md`
- `docs/slices/slice-13-activity-type-normalization-report.md`
- `docs/slices/slice-14-live-connectors-duplicate-resolution-prompt.md`

### Validation

- ✅ `php -l public/api/exercise-config-repository.php`
- ✅ `php -l tests/foundation-import-tests.php`
- ✅ `php -l tests/foundation-import-diagnostics-tests.php`
- ✅ `php -l tests/exercise-config-tests.php`
- ✅ `node public/tests/exercise-config-tests.js`
- ✅ `php tests/foundation-runtime-status-tests.php`
- ✅ `php tests/foundation-import-tests.php`
- ✅ `php tests/foundation-compat-api-tests.php`
- ✅ `php tests/foundation-config-write-tests.php`
- ✅ `php tests/foundation-import-diagnostics-tests.php`
- ✅ `php tests/exercise-config-tests.php`
- ✅ `git status --short` reviewed

### Security Check

- secrets touched: no
- sensitive files modified: no
- notes: This slice only changed normalization logic, checked-in config aliases, tests, and docs. It did not read or modify `.env.local`, `private/config.php`, tunnel credentials, database URLs, raw health exports, or dumps. Diagnostics remain app-safe counts and samples only.

### Decisions Made

- Shared normalization was hardened in one place and mirrored in the browser helper so activity-type matching stays consistent across legacy and canonical paths.
- Alias expansion stayed limited to high-confidence labels such as `Trail Running`, `Indoor Rowing`, and `Strength Workout`.
- Ambiguous labels such as Garmin `Other` remain unresolved and visible in diagnostics by design.

### Known Issues

- The working tree already contains unrelated uncommitted changes outside this slice.
- Canonical integration coverage still skips when no local Postgres foundation is reachable from the current environment.
- This slice reduces unresolved counts for resolvable labels only; it does not add a review UI or automatic policy for ambiguous imported activity types.

### Git State

- working tree: dirty before Slice 13; now also includes additive normalization, alias, test, and docs changes
- commit: not committed
- push: not pushed
- deploy: not deployed

## Slice Report: Slice 12 - Canonical Import Diagnostics

Date: 2026-06-09
Agent/thread: Codex
Branch: current working branch

### Summary

Added a new local-only `GET /api/import-diagnostics.php` endpoint beside `runtime-status.php` so the canonical foundation runtime can explain source coverage, recent batch health, unresolved activity-type mapping, and the selected-vs-suppressed outcomes of the existing Hevy/Garmin/legacy read-precedence rules. The slice reuses current Postgres source and batch metadata plus the existing canonical read-selection helpers instead of inventing a second tracking system.

### Files Changed

- `public/api/foundation-read.php`
- `public/api/foundation-diagnostics.php`
- `public/api/import-diagnostics.php`
- `public/api/runtime-status.php`
- `tests/fixtures/run-import-diagnostics-endpoint.php`
- `tests/foundation-import-diagnostics-tests.php`
- `docs/compatibility-api.md`
- `docs/docker-postgres-foundation.md`
- `docs/implementation-progress.md`
- `docs/slices/slice-12-canonical-import-diagnostics-report.md`
- `docs/slices/slice-13-activity-type-normalization-prompt.md`

### Validation

- ✅ `php -l public/api/foundation-diagnostics.php`
- ✅ `php -l public/api/import-diagnostics.php`
- ✅ `php -l public/api/runtime-status.php`
- ✅ `php -l public/api/foundation-read.php`
- ✅ `php -l tests/foundation-import-diagnostics-tests.php`
- ✅ `php -l tests/fixtures/run-import-diagnostics-endpoint.php`
- ✅ `node public/tests/exercise-config-tests.js`
- ✅ `php tests/foundation-import-tests.php`
- ✅ `php tests/foundation-import-diagnostics-tests.php`
- ✅ `php tests/foundation-runtime-status-tests.php`
- ✅ `php tests/foundation-compat-api-tests.php`
- ✅ `php tests/foundation-config-write-tests.php`
- ✅ `git status --short` reviewed

### Security Check

- secrets touched: no
- sensitive files modified: no
- notes: This slice added local-only diagnostics code, tests, and docs only. It did not read or modify `.env.local`, `private/config.php`, tokens, tunnel credentials, raw health exports, or database dumps. The new endpoint returns app-safe counts, timestamps, status strings, and unresolved activity-type samples only.

### Decisions Made

- Import diagnostics ship as a second local-only JSON endpoint rather than changing the current browser-facing app APIs or adding a frontend diagnostics panel.
- Diagnostics reuse the existing canonical read-selection and source-role helpers, so `selectedActivityCount` and `suppressedActivityCount` explain current behavior rather than introducing a competing dedupe model.
- Per-source summaries intentionally exclude `private_payload_path`, raw `error_summary`, database URLs, and any absolute private filesystem paths.
- Manual/config-only sources remain visible in `sources[]` with zero activity counts so the source registry stays complete and deterministic for local debugging.
- `nonDeterministicActivityCount` is limited to Hevy/Garmin/Concept2/legacy snapshot families where cross-source suppression is relevant.

### Known Issues

- The working tree already contains unrelated uncommitted changes outside this slice.
- Canonical integration coverage still skips when no local Postgres foundation is reachable from the current environment.
- The diagnostics endpoint reports unresolved activity-type rows, but it does not resolve them; targeted normalization work remains for a later slice.
- Manual config seed sources can appear in the diagnostics output with batch metadata but zero activities by design.

### Git State

- working tree: dirty before Slice 12; now also includes additive diagnostics code, focused tests, and slice docs
- commit: not committed
- push: not pushed
- deploy: not deployed

## Next Slice

Slice 15: Connector Admin Surface and Incremental Hevy Sync

## Next Slice Prompt

See `docs/slices/slice-15-connector-admin-and-incremental-hevy-sync-prompt.md`.

## Slice Report: Slice 11 - Garmin Import

Date: 2026-06-09
Agent/thread: Codex
Branch: current working branch

### Summary

Added the first direct Garmin import path into the canonical foundation. The local-only `scripts/import-garmin.php` importer now accepts both observed Garmin Connect `Activities.csv` variants, parses supported endurance summary fields into canonical activity rows, derives deterministic `garmin-csv-...` source IDs because the export lacks a stable activity ID, adds narrow aliases for common Garmin activity-type labels, hooks Garmin import into the local foundation runtime prep flow, and extends canonical read selection so deterministic direct Garmin rows can suppress matching legacy Garmin snapshot rows while ambiguous rows remain visible.

### Files Changed

- `scripts/import-garmin.php`
- `scripts/lib/garmin-import-parser.php`
- `scripts/lib/foundation-import.php`
- `scripts/foundation-runtime-prepare.sh`
- `public/api/foundation-read.php`
- `tests/fixtures/garmin-activities.csv`
- `tests/fixtures/garmin-activities-invalid.csv`
- `tests/foundation-import-tests.php`
- `tests/foundation-compat-api-tests.php`
- `docs/compatibility-api.md`
- `docs/docker-postgres-foundation.md`
- `docs/postgres-canonical-schema.md`
- `docs/implementation-progress.md`
- `docs/slices/slice-11-garmin-import-report.md`
- `docs/slices/slice-12-canonical-import-diagnostics-prompt.md`

### Validation

- ✅ `php -l scripts/import-garmin.php`
- ✅ `php -l scripts/lib/garmin-import-parser.php`
- ✅ `php -l scripts/lib/foundation-import.php`
- ✅ `php -l public/api/foundation-read.php`
- ✅ `php -l tests/foundation-import-tests.php`
- ✅ `php -l tests/foundation-compat-api-tests.php`
- ✅ `php tests/foundation-import-tests.php`
- ✅ `php tests/foundation-compat-api-tests.php`
- ✅ `php tests/foundation-runtime-status-tests.php`
- ✅ `php tests/foundation-config-write-tests.php`
- ✅ `node public/tests/exercise-config-tests.js`
- ✅ `git status --short` reviewed

### Security Check

- secrets touched: no
- sensitive files modified: no
- notes: This slice added importer code, tests, and docs only. It did not read or modify `.env.local`, `private/config.php`, tokens, tunnel credentials, raw health exports beyond the user-provided local Garmin CSV path, or database dumps. Browser-facing responses still expose app-safe source/status metadata only.

### Decisions Made

- The importer now accepts both observed Garmin Connect `Activities.csv` header variants by validating shared required columns and ignoring unmapped Garmin-only extras.
- Garmin activity-type expansion stays intentionally narrow: only high-confidence aliases were added for existing canonical types, while lower-confidence Garmin labels still import literally and unresolved.
- Garmin imports currently write canonical top-level activity rows only and do not create child exercise/set records.
- Because the Garmin activity-list CSV lacks a stable exported activity ID, canonical idempotency uses deterministic hashed `garmin-csv-...` source IDs.
- Direct Garmin rows suppress legacy Garmin snapshot rows only when the Garmin duplicate signature is deterministic; otherwise both remain visible.

### Known Issues

- The working tree already contains unrelated uncommitted changes outside this slice.
- Canonical integration tests still skip when no local Postgres foundation is reachable from the current environment.
- Some Garmin activity types such as `Other` still import literally and may remain unresolved against canonical activity-type config until a later normalization slice.
- Garmin-only fields that do not fit the current canonical activity schema remain intentionally deferred.

### Git State

- working tree: dirty before Slice 11; now also includes additive Garmin importer code, runtime-prep wiring, focused tests, and slice docs
- commit: not committed
- push: not pushed
- deploy: not deployed

## Slice Report: Slice 10 - Local Canonical Runtime Readiness

Date: 2026-06-08
Agent/thread: Codex
Branch: current working branch

### Summary

Turned the dormant Docker/Postgres foundation into a real local canonical app runtime. The `foundation-app` service now serves the existing PHP + vanilla JS app on `http://localhost:8081`, generates local-only runtime config into the private runtime volume, keeps repo `private/` mounted read-only as a legacy source, exposes a local-only `runtime-status.php` diagnostic endpoint, and documents an intentional prep/import path through existing bootstrap and Hevy import scripts before canonical reads are expected to be ready.

### Files Changed

- `docker-compose.postgres.yml`
- `public/api/runtime-status.php`
- `scripts/foundation-runtime-entrypoint.sh`
- `scripts/foundation-runtime-prepare.sh`
- `scripts/check-foundation-runtime.sh`
- `scripts/lib/foundation-migrations.php`
- `tests/fixtures/run-runtime-status-endpoint.php`
- `tests/foundation-runtime-status-tests.php`
- `docs/docker-postgres-foundation.md`
- `docs/compatibility-api.md`
- `docs/implementation-progress.md`
- `docs/slices/slice-10-local-canonical-runtime-readiness-report.md`
- `docs/slices/slice-11-garmin-import-prompt.md`

### Validation

- ✅ `docker compose -f docker-compose.postgres.yml --profile foundation config`
- ✅ `php tests/foundation-runtime-status-tests.php`
- ✅ `php tests/foundation-import-tests.php`
- ✅ `php tests/foundation-compat-api-tests.php`
- ✅ `php tests/foundation-config-write-tests.php`
- ✅ `node public/tests/exercise-config-tests.js`
- ✅ `php -l public/api/runtime-status.php`
- ✅ `php -l tests/foundation-runtime-status-tests.php`
- ✅ `php -l tests/fixtures/run-runtime-status-endpoint.php`
- ✅ `php -l scripts/lib/foundation-migrations.php`
- ✅ `git status --short` reviewed

### Security Check

- secrets touched: no
- sensitive files modified: no
- notes: This slice added local runtime scaffolding, diagnostics, tests, and docs only. It did not read or modify `.env.local`, `private/config.php`, tokens, tunnel credentials, raw health exports, or database dumps. The new diagnostic endpoint returns app-safe status metadata only.

### Decisions Made

- The canonical runtime stays on a separate local port (`8081`) so it does not replace the current `docker-compose.yml` workflow.
- Runtime config is generated from env into the writable private runtime volume instead of relying on checked-in config.
- Legacy `private/` remains mounted read-only as a compatibility seed source; the foundation runtime writes only to its dedicated private runtime volume.
- Runtime readiness uses one local-only diagnostic endpoint instead of changing existing browser-facing activity/config APIs.
- Schema migrations are still intentional and importer-driven; the runtime diagnostic endpoint does not advance schema state.
- Concept2 is no longer the next mandatory migration slice and remains a future connector option.

### Known Issues

- The working tree already contains unrelated uncommitted changes outside this slice.
- Full Docker end-to-end runtime startup was not verified in this run because local Postgres/Docker integration access was unavailable from the sandboxed test environment.
- Canonical integration tests continue to skip when no local Postgres foundation is reachable.
- Unknown review records still remain JSON-backed on the legacy/private compatibility path.

### Git State

- working tree: dirty before Slice 10; now also includes additive local canonical runtime scaffolding, diagnostics, tests, and docs
- commit: not committed
- push: not pushed
- deploy: not deployed

## Slice Report: Slice 9.5 - Foundation Hardening Before Concept2

Date: 2026-06-08
Agent/thread: Codex
Branch: current working branch

### Summary

Added the first forward-only local Postgres migration path for the canonical foundation, extended provenance constraints for future direct Concept2 imports, generalized canonical duplicate selection so direct Concept2 rows can conservatively suppress matching legacy snapshot rows, and tightened `includeChildren=1` to fetch child rows only for already-selected activities.

### Files Changed

- `docker/postgres/initdb/001_canonical_schema.sql`
- `docker/postgres/migrations/20260608_001_foundation_hardening.sql`
- `scripts/lib/foundation-migrations.php`
- `scripts/lib/foundation-import.php`
- `public/api/foundation-read.php`
- `tests/foundation-import-tests.php`
- `tests/foundation-compat-api-tests.php`
- `docs/compatibility-api.md`
- `docs/postgres-canonical-schema.md`
- `docs/docker-postgres-foundation.md`
- `docs/implementation-progress.md`
- `docs/slices/slice-9-5-foundation-hardening-report.md`
- `docs/slices/slice-10-concept2-erg-import-prompt.md`

### Validation

- ✅ `php tests/foundation-import-tests.php`
- ✅ `php tests/foundation-compat-api-tests.php`
- ✅ `php tests/foundation-config-write-tests.php`
- ✅ `node public/tests/exercise-config-tests.js`
- ✅ `php -l public/api/foundation-read.php`
- ✅ `php -l scripts/lib/foundation-import.php`
- ✅ `php -l scripts/lib/foundation-migrations.php`
- ✅ `git status --short` reviewed

### Security Check

- secrets touched: no
- sensitive files modified: no
- notes: This slice changed local schema/migration tooling, canonical read logic, focused tests, and migration docs only. It did not read or modify `.env.local`, `private/config.php`, tokens, tunnel credentials, raw health exports, or database dumps. Browser-facing responses still expose app-safe source/status metadata only.

### Decisions Made

- Existing local foundation volumes now upgrade through forward-only SQL files tracked in `mattrics.mattrics_schema_migrations`.
- Direct Concept2 provenance is reserved as `source_key = concept2-logbook-export`, `source_kind = concept2`, and `batch_kind = concept2_logbook_export`.
- Canonical duplicate suppression remains conservative and source-aware: deterministic direct Hevy or direct Concept2 rows can suppress matching legacy snapshot rows, while ambiguous rows remain visible.
- Canonical child-row fetches are now scoped to selected activity IDs instead of all activities for the user.

### Known Issues

- The working tree already contains unrelated uncommitted changes outside this slice.
- Public HTTP requests still do not auto-run foundation schema migrations; local import/admin paths remain responsible for advancing the schema.
- Concept2 importer implementation is still pending for Slice 10.

### Git State

- working tree: dirty before Slice 9.5; now also includes additive foundation migration tooling, canonical read hardening, focused regression tests, and slice docs
- commit: not committed
- push: not pushed
- deploy: not deployed

## Slice Report: Slice 9 - Exercise Config DB Migration

Date: 2026-06-07
Agent/thread: Codex
Branch: current working branch

### Summary

Switched browser-facing exercise/activity-type config mutations onto canonical Postgres records whenever foundation mode is available. `public/api/exercises.php` now resolves config lookups against canonical data for mutation routes, rewrites the legacy JSON catalogs as compatibility shadows after canonical writes, keeps the unknown review queue on the legacy JSON path, and preserves automatic legacy fallback when canonical write initialization is unavailable.

### Files Changed

- `public/api/exercises.php`
- `public/api/foundation-write.php`
- `tests/foundation-config-write-tests.php`
- `docs/compatibility-api.md`
- `docs/implementation-progress.md`
- `docs/slices/slice-9-exercise-config-db-migration-report.md`
- `docs/slices/slice-10-concept2-erg-import-prompt.md`

### Validation

- ✅ `node public/tests/exercise-config-tests.js`
- ✅ `php tests/exercise-config-tests.php`
- ✅ `php tests/foundation-compat-api-tests.php`
- ✅ `php tests/foundation-config-write-tests.php`
- ✅ `php -l public/api/foundation-write.php`
- ✅ `php -l public/api/exercises.php`
- ✅ `git status --short` reviewed

### Security Check

- secrets touched: no
- sensitive files modified: no
- notes: This slice changed canonical config mutation code, compatibility docs, and focused tests only. It did not read or modify `.env.local`, `private/config.php`, tokens, tunnel credentials, raw health exports, or database dumps. Browser-facing responses continue to expose app-safe source/status metadata only.

### Decisions Made

- Canonical Postgres config rows are now the intended source of truth for browser-facing exercise/activity-type writes when canonical mode is available.
- Canonical write routes preserve the current `public/api/exercises.php` request and response shapes instead of adding a second browser-facing config API.
- Legacy JSON config catalogs are now compatibility shadows after canonical writes rather than the primary source in canonical mode.
- Unknown review data intentionally remains on the legacy JSON path in this slice.

### Known Issues

- The working tree already contains unrelated uncommitted changes outside this slice.
- Canonical config shadow-sync can still fail independently after a successful canonical DB write, leaving the legacy JSON copies temporarily stale until the next successful shadow sync or bootstrap run.
- The unknown review queue is still JSON-backed and has not moved into canonical storage yet.
- The browser still depends on the compatibility PHP endpoints rather than a dedicated DB-native config API.

### Git State

- working tree: dirty before Slice 9; now also includes additive canonical config-write code, focused regression tests, and slice docs
- commit: not committed
- push: not pushed
- deploy: not deployed

## Next Slice

Slice 12: Canonical Import Diagnostics

## Next Slice Prompt

See `docs/slices/slice-12-canonical-import-diagnostics-prompt.md`.

## Slice Report: Slice 0 - Codex Framework

Date: 2026-06-06
Agent/thread: Codex
Branch: current working branch

### Summary

Added a documentation framework so future Codex work proceeds in coherent slices, preserves the current app, avoids secret exposure, and keeps the Docker/Postgres/Open Health Hub-compatible direction visible.

### Files Changed

- `AGENTS.md`
- `docs/architecture-next.md`
- `docs/codex-framework.md`
- `docs/implementation-progress.md`
- `docs/templates/slice-prompt.md`
- `docs/templates/slice-report.md`
- `docs/templates/next-slice-prompt.md`

### Validation

- ✅ `git status --short` inspected before and after changes.
- ✅ Docs-only target files changed.
- ✅ No `public/`, `private/data/`, runtime config, source code, deploy scripts, or feature docs were intentionally modified by this slice.
- ✅ No secret values were added.

### Security Check

- secrets touched: no
- sensitive files modified: no
- notes: This slice added documentation only. It did not read or modify `.env.local`, `private/config.php`, API keys, tokens, tunnel credentials, raw health exports, or database dumps.

### Decisions Made

- Future Codex implementation must be slice-based.
- Every slice must update this progress file.
- Every slice must include a slice report and next slice prompt.
- Current static deploy rules remain valid until explicitly replaced.

### Known Issues

- The working tree already contains unrelated uncommitted feature changes.
- Future slices must avoid overwriting or reverting those unrelated changes.

### Git State

- working tree: dirty before Slice 0; now also includes new framework docs and `AGENTS.md` update
- commit: not committed
- push: not pushed
- deploy: not deployed

## Slice Report: Slice 1 - Architecture Decision Docs

Date: 2026-06-06
Agent/thread: Codex
Branch: current working branch

### Summary

Updated the main architecture doc so it now clearly states that `MattricsTrainingLog` is the active app, `MattricsNext` is reference/archive only, the migration direction is Docker/Postgres/private server hosting, and the current static/PHP deploy model stays in force until explicitly replaced.

### Files Changed

- `docs/architecture.md`
- `docs/implementation-progress.md`

### Validation

- ✅ `git status --short` inspected before edits.
- ✅ Markdown docs reviewed after edits for coherent structure and consistent decisions.
- ✅ Docs-only target files changed by this slice.
- ✅ Deploy was not run.

### Security Check

- secrets touched: no
- sensitive files modified: no
- notes: This slice changed documentation only. It did not read or modify `.env.local`, `private/config.php`, tokens, tunnel credentials, raw health exports, or database dumps.

### Decisions Made

- `MattricsTrainingLog` remains the main app and active implementation target.
- `MattricsNext` remains reference/archive only.
- Docker Compose, Postgres, and private server hosting remain the migration direction.
- Current static/PHP hosting remains the production model until a replacement runtime is implemented and validated.

### Known Issues

- The working tree already contains unrelated uncommitted feature and data changes outside this slice.
- The Docker/Postgres target is now documented, but foundation scaffolding is still pending.

### Git State

- working tree: dirty before Slice 1; now also includes architecture/progress doc updates
- commit: not committed
- push: not pushed
- deploy: not deployed

## Slice Report: Slice 2 - Docker/Postgres Foundation

Date: 2026-06-06
Agent/thread: Codex
Branch: current working branch

### Summary

Added a parallel local Docker/Postgres foundation for future migration work. The new scaffolding defines a future runtime container boundary, a Postgres service, and separate persistent storage for database data and private runtime state without changing the current static/PHP deploy model or the existing `docker-compose.yml` workflow.

### Files Changed

- `docker-compose.postgres.yml`
- `docker/runtime/Dockerfile`
- `docs/docker-postgres-foundation.md`
- `docs/implementation-progress.md`
- `docs/slices/slice-2-docker-postgres-foundation-report.md`
- `docs/slices/slice-3-canonical-schema-prompt.md`

### Validation

- ✅ `docker compose -f docker-compose.postgres.yml --profile foundation config`
- ✅ Markdown and Docker files reviewed for coherent boundaries and additive scope.
- ✅ `git status --short` inspected after changes.
- ✅ Deploy was not run.

### Security Check

- secrets touched: no
- sensitive files modified: no
- notes: This slice added placeholder-only local scaffolding and documentation. It did not read or modify `.env.local`, `private/config.php`, API keys, tokens, tunnel credentials, raw health exports, or database dumps.

### Decisions Made

- The future Docker/Postgres path lives in `docker-compose.postgres.yml` so the existing `docker-compose.yml` remains the current local static/PHP workflow.
- Postgres data uses a dedicated named volume.
- Future private runtime/config state uses a separate named volume instead of reusing current private runtime files.
- The foundation app container is intentionally dormant so later slices can add schema and compatibility work without implying a runtime cutover.

### Known Issues

- The working tree already contains unrelated uncommitted feature and data changes outside this slice.
- The foundation stack does not yet serve the app or own any schema by design.
- Canonical tables, migrations, and compatibility APIs are still pending.

### Git State

- working tree: dirty before Slice 2; now also includes additive Docker/Postgres foundation docs and scaffolding
- commit: not committed
- push: not pushed
- deploy: not deployed

## Slice Report: Slice 3 - Canonical Schema

Date: 2026-06-06
Agent/thread: Codex
Branch: current working branch

### Summary

Added the first canonical Postgres contract for Mattrics. This slice introduced a schema design doc, local foundation bootstrap SQL, and Postgres init wiring so later slices can import legacy data and expose compatibility APIs without changing the current static/PHP production path.

### Files Changed

- `docker-compose.postgres.yml`
- `docker/postgres/initdb/001_canonical_schema.sql`
- `docs/docker-postgres-foundation.md`
- `docs/implementation-progress.md`
- `docs/postgres-canonical-schema.md`
- `docs/slices/slice-3-canonical-schema-report.md`
- `docs/slices/slice-4-legacy-import-bootstrap-prompt.md`

### Validation

- ✅ `docker compose -f docker-compose.postgres.yml --profile foundation config`
- ✅ Disposable Postgres bootstrap validated with the canonical init SQL.
- ✅ SQL and markdown reviewed for coherence and additive scope.
- ✅ `git status --short` inspected after changes.
- ✅ Deploy was not run.

### Security Check

- secrets touched: no
- sensitive files modified: no
- notes: This slice added schema/docs only. It did not read or modify `.env.local`, `private/config.php`, API keys, tokens, tunnel credentials, raw health exports, or database dumps.

### Decisions Made

- Canonical tables live in the `mattrics` Postgres schema and use UUID primary keys.
- Postgres enum types were avoided in favor of text columns with `CHECK` constraints.
- Activities, config catalogs, and provenance records are user-scoped now even though the current app remains single-user in practice.
- Muscle weights are stored as explicit numeric columns in the initial catalog tables rather than JSON blobs.

### Known Issues

- The working tree already contains unrelated uncommitted changes outside this slice.
- The canonical schema exists, but no legacy/config data has been imported yet by design.
- The current app still reads JSON/private snapshot sources and does not use Postgres yet.

### Git State

- working tree: dirty before Slice 3; now also includes canonical schema docs and bootstrap SQL
- commit: not committed
- push: not pushed
- deploy: not deployed

## Slice Report: Slice 4 - Legacy Import Bootstrap

Date: 2026-06-06
Agent/thread: Codex
Branch: current working branch

### Summary

Added a local-only bootstrap/import path for the canonical Postgres schema. The new tooling reads the existing private config catalogs and cached legacy activity snapshot, upserts canonical config/activity rows, backfills Hevy exercise/set rows, preserves provenance metadata, and leaves the current static/PHP app behavior unchanged.

### Files Changed

- `scripts/bootstrap-foundation.php`
- `scripts/lib/foundation-import.php`
- `scripts/lib/hevy-import-parser.php`
- `tests/foundation-import-tests.php`
- `docs/docker-postgres-foundation.md`
- `docs/postgres-canonical-schema.md`
- `docs/implementation-progress.md`
- `docs/slices/slice-4-legacy-import-bootstrap-report.md`
- `docs/slices/slice-5-compatibility-api-prompt.md`

### Validation

- ✅ `php tests/foundation-import-tests.php`
- ✅ `php -l scripts/bootstrap-foundation.php`
- ✅ `php -l scripts/lib/foundation-import.php`
- ✅ `php -l scripts/lib/hevy-import-parser.php`
- ✅ `php -l tests/foundation-import-tests.php`
- ✅ `docker compose -f docker-compose.postgres.yml --profile foundation config`
- ❌ Disposable Docker/Postgres validation could not run because the local Docker daemon was unavailable.
- ❌ Local fallback Postgres bootstrap via `initdb` could not run in this environment because shared-memory creation was not permitted.
- ✅ `git status --short` reviewed

### Security Check

- secrets touched: no
- sensitive files modified: no
- notes: This slice read the existing private catalog/snapshot files as migration inputs but did not modify them, print raw health payloads, or touch `.env.local`, `private/config.php`, tokens, tunnel credentials, or database dumps.

### Decisions Made

- The canonical bootstrap path is a standalone CLI script rather than a live API endpoint.
- Importer code reuses the existing config validators from `public/api/exercise-config-repository.php` without coupling to `bootstrap.php`.
- Hevy child rows are rebuilt per imported activity on rerun to keep legacy backfills idempotent.
- Source provenance is tracked with relative private file references and stable file-state batch keys instead of storing raw payload blobs in Postgres.

### Known Issues

- The working tree already contains unrelated uncommitted changes outside this slice.
- End-to-end database import validation is still pending on a machine with either a working Docker daemon or a permissive local Postgres runtime.
- The current app still reads JSON/private snapshot sources and does not use Postgres yet.

### Git State

- working tree: dirty before Slice 4; now also includes additive bootstrap/import code, tests, and docs
- commit: not committed
- push: not pushed
- deploy: not deployed

## Next Slice

Slice 6: Read-Only UI Switch-Over

## Next Slice Prompt

See `docs/slices/slice-6-read-only-ui-switch-over-prompt.md`.

## Slice Report: Slice 7 - Hevy Ingestion

Date: 2026-06-07
Agent/thread: Codex
Branch: current working branch

### Summary

Added the first direct native Hevy ingestion path into the canonical Postgres foundation. The new CLI importer reads a private Hevy CSV export, preserves UTF-8 workout text including umlauts and emojis, writes canonical activity/exercise/set rows directly, rebuilds Hevy-compatible descriptions for fallback compatibility, and makes canonical reads prefer direct Hevy rows over matching legacy snapshot rows.

### Files Changed

- `scripts/import-hevy.php`
- `scripts/lib/foundation-import.php`
- `public/api/foundation-read.php`
- `tests/foundation-import-tests.php`
- `tests/foundation-compat-api-tests.php`
- `docs/docker-postgres-foundation.md`
- `docs/compatibility-api.md`
- `docs/implementation-progress.md`
- `docs/slices/slice-7-hevy-ingestion-report.md`
- `docs/slices/slice-8-fatigue-from-canonical-data-prompt.md`

### Validation

- ✅ `php -l scripts/import-hevy.php`
- ✅ `php -l scripts/lib/foundation-import.php`
- ✅ `php -l public/api/foundation-read.php`
- ✅ `php tests/foundation-import-tests.php`
- ✅ `php tests/foundation-compat-api-tests.php`
- ✅ `php scripts/import-hevy.php`
- ✅ `git status --short` reviewed

### Security Check

- secrets touched: no
- sensitive files modified: no
- notes: This slice read the private Hevy export at `private/import-hevy/workouts.csv` and imported it into the local canonical foundation database. It did not read or modify `.env.local`, `private/config.php`, tokens, tunnel credentials, or database dumps. The importer exposes relative private paths only and preserves raw health data outside `public/`.

### Decisions Made

- The direct Hevy path is CLI-only in this slice.
- Native Hevy CSV is the only supported direct-import format.
- Workout titles, exercise names, notes, and descriptions are preserved as UTF-8 text while canonical matching and dedupe use hashed UTF-8-safe signatures.
- Direct Hevy rows win over matching legacy snapshot rows when the duplicate signature is deterministic.
- The compatibility API contract stays unchanged; duration continues to flow through `Duration (min)` and start time through `Date`.

### Known Issues

- The working tree already contains unrelated uncommitted changes outside this slice.
- Direct Hevy import still depends on the current JSON-backed exercise/activity-type catalogs for canonical resolution.
- Fuzzy cross-source deduping is still intentionally out of scope.
- Several imported Hevy exercise names remain unresolved and are preserved for later config follow-up rather than silently remapped.

### Git State

- working tree: dirty before Slice 7; now also includes additive Hevy importer code, canonical read-selection changes, focused tests, and slice docs
- commit: not committed
- push: not pushed
- deploy: not deployed

## Next Slice

Slice 8: Fatigue From Canonical Data

## Next Slice Prompt

See `docs/slices/slice-8-fatigue-from-canonical-data-prompt.md`.

## Slice Report: Slice 6 - Read-Only UI Switch-Over

Date: 2026-06-07
Agent/thread: Codex
Branch: current working branch

### Summary

Switched the current vanilla JS UI over to intentionally consume canonical-backed read responses through the existing PHP endpoints. The app now requests canonical child rows when available, uses them across Hevy-dependent UI paths through one shared adapter, surfaces separate activity/config read-source status in the existing header chrome, and preserves the current legacy fallback model.

### Files Changed

- `public/assets/js/core/state.js`
- `public/assets/js/core/exercise-config.js`
- `public/assets/js/core/hevy-parser.js`
- `public/assets/js/core/fatigue-engine.js`
- `public/assets/js/detail.js`
- `public/assets/js/exercise-admin.js`
- `public/assets/js/renderers/orchestrator.js`
- `public/assets/js/renderers/loader.js`
- `public/api/exercises.php`
- `public/tests/exercise-config-tests.js`
- `tests/foundation-compat-api-tests.php`
- `docs/compatibility-api.md`
- `docs/implementation-progress.md`
- `docs/slices/slice-6-read-only-ui-switch-over-report.md`
- `docs/slices/slice-7-hevy-ingestion-prompt.md`

### Validation

- ✅ `node public/tests/exercise-config-tests.js`
- ✅ `php tests/foundation-compat-api-tests.php`
- ✅ `php -l public/api/exercises.php`
- ✅ `git status --short` reviewed

### Security Check

- secrets touched: no
- sensitive files modified: no
- notes: This slice changed browser-facing read behavior and compatibility metadata only. It did not read or modify `.env.local`, `private/config.php`, API keys, tokens, tunnel credentials, raw health exports, or database dumps. The UI exposes app-safe source/status metadata only.

### Decisions Made

- The current UI now requests `includeChildren=1` only when using the internal `api/data.php` path.
- Canonical activity child rows are consumed through one shared adapter across fatigue, unknown detection, detail modal, and exercise-admin recent workout lookups.
- Header status now shows activity and config read sources separately using the existing chrome rather than adding a new debug view.
- Legacy config write routes remain unchanged, and the browser preserves the last known GET read-source state when write responses omit source metadata.

### Known Issues

- The working tree already contains unrelated uncommitted changes outside this slice.
- Canonical config GET responses can still lag behind legacy config edits until the next bootstrap/import rerun.
- The app still depends on compatibility adapters; direct Hevy ingestion and canonical write paths are still pending.

### Git State

- working tree: dirty before Slice 6; now also includes read-only UI switch-over code, focused tests, and slice docs
- commit: not committed
- push: not pushed
- deploy: not deployed

## Next Slice

Slice 7: Hevy Ingestion

## Next Slice Prompt

See `docs/slices/slice-7-hevy-ingestion-prompt.md`.

## Slice Report: Slice 4a - Bootstrap Boolean Binding

Date: 2026-06-06
Agent/thread: Codex
Branch: current working branch

### Summary

Fixed the foundation importer so booleans are bound to Postgres with explicit PDO types instead of being passed through generic execute-array coercion. This unblocked the legacy bootstrap runner and allowed the canonical local foundation database to seed successfully from the existing private catalogs and cached training snapshot.

### Files Changed

- `scripts/lib/foundation-import.php`
- `docs/implementation-progress.md`
- `docs/slices/slice-4a-bootstrap-boolean-binding-report.md`
- `docs/slices/slice-5-compatibility-api-prompt.md`

### Validation

- ✅ `php tests/foundation-import-tests.php`
- ✅ `php -l scripts/bootstrap-foundation.php`
- ✅ `php -l scripts/lib/foundation-import.php`
- ✅ `php scripts/bootstrap-foundation.php --database-url=postgres://mattrics:mattrics-local-only-change-me@127.0.0.1:5433/mattrics`
- ✅ `select count(*) from mattrics.mattrics_activities;` → `288`
- ✅ `select count(*) from mattrics.mattrics_activity_sets;` → `2324`
- ✅ `git status --short` reviewed

### Security Check

- secrets touched: no
- sensitive files modified: no
- notes: This slice did not modify `.env.local`, `private/config.php`, tokens, tunnel credentials, raw health exports, or database dumps. It used the existing local placeholder database URL supplied in the slice instructions and did not print secret values.

### Decisions Made

- Boolean importer params now use explicit PDO binding types through a shared helper instead of raw `execute([...])` coercion.
- The fix stays importer-focused and does not redesign the schema, importer flow, or compatibility API plan.
- Slice 5 remains the next migration step now that the canonical bootstrap path is verified locally.

### Known Issues

- The working tree still contains many unrelated uncommitted changes outside this slice.
- The current app still reads legacy JSON/private sources and does not consume canonical Postgres data yet.
- Several Hevy exercise names remain unresolved during bootstrap and are preserved for later config/catalog follow-up rather than being remapped in this slice.

### Git State

- working tree: dirty before Slice 4a; now also includes importer boolean-binding fix and slice docs
- commit: not committed
- push: not pushed
- deploy: not deployed

## Next Slice

Slice 6: Read-Only UI Switch-Over

## Next Slice Prompt

See `docs/slices/slice-6-read-only-ui-switch-over-prompt.md`.

## Slice Report: Slice 5 - Compatibility API

Date: 2026-06-06
Agent/thread: Codex
Branch: current working branch

### Summary

Added a read-only canonical compatibility layer to the existing PHP API. `GET /api/data.php` and `GET /api/exercises.php` can now read the canonical Postgres foundation when server-side foundation config is present, while automatically falling back to the current legacy snapshot and JSON config paths if canonical reads are disabled or unavailable. The live mutation routes remain legacy-backed.

### Files Changed

- `public/api/config-defaults.php`
- `public/api/bootstrap-auth.php`
- `public/api/foundation-read.php`
- `public/api/data.php`
- `public/api/exercises.php`
- `tests/fixtures/run-data-endpoint.php`
- `tests/foundation-compat-api-tests.php`
- `docs/compatibility-api.md`
- `docs/implementation-progress.md`
- `docs/slices/slice-5-compatibility-api-report.md`
- `docs/slices/slice-6-read-only-ui-switch-over-prompt.md`

### Validation

- ✅ `php tests/foundation-compat-api-tests.php`
- ✅ `php tests/exercise-config-tests.php`
- ✅ `php tests/foundation-import-tests.php`
- ✅ `php -l public/api/foundation-read.php`
- ✅ `php -l public/api/data.php`
- ✅ `php -l public/api/exercises.php`
- ✅ `php -l public/api/bootstrap-auth.php`
- ✅ `php -l public/api/config-defaults.php`
- ✅ `php -l tests/fixtures/run-data-endpoint.php`
- ✅ `php -l tests/foundation-compat-api-tests.php`
- ✅ `git status --short` reviewed

### Security Check

- secrets touched: no
- sensitive files modified: no
- notes: This slice added server-side config toggles and Postgres read helpers, but it did not read or modify `.env.local`, `private/config.php`, tokens, tunnel credentials, raw health exports, or database dumps. Canonical responses expose app-safe activity/config fields only.

### Decisions Made

- Canonical reads stay behind the existing GET endpoints instead of adding a second public endpoint surface.
- Canonical mode is enabled only by server-side config and environment overrides.
- Canonical failures fall back automatically to the legacy sources instead of failing the app closed.
- Exercise/activity-type mutations, unknown sync, and AI flows remain legacy-backed until a later migration slice.
- `GET /api/data.php?includeChildren=1` exposes canonical Hevy child rows without changing the default frontend response.

### Known Issues

- The working tree already contains unrelated uncommitted changes outside this slice.
- Canonical config reads can lag behind legacy config mutations until the next bootstrap/import rerun.
- The current frontend still behaves as before and does not yet use the additive canonical metadata or child-row response.

### Git State

- working tree: dirty before Slice 5; now also includes additive compatibility API code, docs, and tests
- commit: not committed
- push: not pushed
- deploy: not deployed

## Next Slice

Slice 6: Read-Only UI Switch-Over

## Next Slice Prompt

See `docs/slices/slice-6-read-only-ui-switch-over-prompt.md`.
