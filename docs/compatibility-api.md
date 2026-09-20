# Compatibility API

This document describes the Slice 5, Slice 6, and Slice 9 compatibility layer that lets the existing PHP API read and selectively write canonical Postgres data while keeping the current UI and deploy model intact during migration.

## Purpose

- keep `public/api/data.php` and `public/api/exercises.php` as the browser-facing GET endpoints
- allow those GET endpoints to read canonical Postgres tables when foundation config is present
- preserve the current legacy snapshot and private JSON paths as automatic fallbacks
- keep the existing browser-facing mutation contract while moving config writes onto canonical Postgres when available

## Canonical Mode

Canonical mode is enabled only when server-side config provides a non-blank foundation database URL.

Config keys:

- `foundation_database_url`
- `foundation_user_key`

Environment overrides:

- `MATTRICS_FOUNDATION_DATABASE_URL`
- `MATTRICS_FOUNDATION_USER_KEY`

Defaults:

- `foundation_database_url`: blank
- `foundation_user_key`: `legacy-local-user`

Canonical mode stays server-side only. Slice 6 keeps the browser on the same PHP endpoints and does not introduce a second public API surface.

## Local Runtime Diagnostics

Slice 10 and Slice 12 add local-only diagnostic endpoints:

- `GET /api/runtime-status.php`
- `GET /api/import-diagnostics.php`

Purpose:

- report whether the dedicated local foundation runtime is actually running in canonical mode
- show app-safe readiness and import-coverage signals such as canonical mode enabled, DB reachable, configured `foundation_user_key`, migration status, latest successful import timestamps, per-source activity coverage, unresolved activity-type counts, and dedupe/suppression outcomes
- help debug runtime cutover locally without changing the current browser-facing activity/config contracts

Constraints:

- it is intended for local/foundation runtime debugging only
- it returns status booleans, counts, timestamps, and status strings only
- it does not expose database URLs, credentials, filesystem paths, or raw import payload metadata
- it does not run schema migrations

`runtime-status.php` stays focused on readiness:

- canonical mode enabled/disabled
- DB reachability
- configured foundation user presence
- migration state
- latest successful import timestamp and batch kind
- app-safe live connector state for `hevy` and `garmin`, including `authType`, `enabled`, `hasCredential`, `connectionStatus`, sync/test timestamps, incremental sync strategy, overlap window, cursor timestamps, and last fetch counts only

`import-diagnostics.php` stays focused on coverage and precedence:

- `summary` reports total source count, canonical activity count, selected vs suppressed activity counts, legacy-metadata overlay totals, latest successful import time, and unresolved activity-type totals
- `selectionDiagnostics` groups activity counts by source family (`directHevy`, `directGarmin`, `directConcept2`, `legacySnapshot`) so local debugging can explain why direct rows are or are not beating legacy snapshot rows
- `sources[]` reports app-safe per-source metadata only, including latest batch health, imported activity counts, selected vs suppressed counts, deterministic-vs-non-deterministic dedupe coverage, metadata-donor counts, latest activity time, unresolved activity-type samples, and live-connector credential/status booleans for live source rows
- live Hevy source diagnostics now also include app-safe incremental fields such as `syncStrategy`, `overlapDays`, `cursorStartedAt`, `lastSyncWindowStartedAt`, and recent page/workout fetch counts

Interpretation rules:

- `selectedActivityCount` means rows from that source survive the current canonical read-selection logic and are visible through `GET /api/data.php`
- `suppressedActivityCount` means canonical rows from that source exist but lose to a higher-precedence duplicate under the existing deterministic dedupe rules
- `nonDeterministicActivityCount` means a Hevy/Garmin/Concept2/legacy-family row lacked enough stable signature fields for deterministic cross-source suppression, so diagnostics will show it as visible rather than automatically deduped

## Activity-Type Normalization Workflow

Slice 13 hardens activity-type matching without changing public endpoint contracts or dedupe safety rules.

Matching flow:

- imported activity types are normalized through the shared `mattrics_normalize_config_name()` path used by the legacy JSON repository, canonical config writes, and foundation import resolvers
- normalization now handles common formatting noise such as punctuation, underscores, repeated whitespace, ordinary camelCase, and acronym-prefixed camelCase labels
- canonical activity-type resolution remains conservative: exact normalized canonical name first, then exact normalized alias match
- importer-specific title heuristics and fuzzy cross-source matching remain out of scope

Catalog strategy:

- high-confidence imported labels can be added as aliases on existing canonical activity types in `private/data/activity-type-configs.json`
- ambiguous bucket labels such as Garmin `Other` stay unresolved by design until a human decides whether they deserve a real canonical type or a more specific alias

Local debugging workflow:

- use `GET /api/import-diagnostics.php` to compare unresolved counts and samples before and after alias or normalization changes
- treat decreases in unresolved counts as expected only for high-confidence labels already represented by the canonical catalog
- if a sample is still unresolved and its meaning is ambiguous, leave it unresolved instead of forcing a fallback canonical type

## Foundation Migration Contract

- fresh Postgres volumes still bootstrap from `docker/postgres/initdb/`
- existing local foundation volumes now upgrade through forward-only SQL files in `docker/postgres/migrations/`
- `scripts/bootstrap-foundation.php`, `scripts/import-hevy.php`, and `scripts/import-garmin.php` apply pending foundation migrations automatically before they write canonical rows
- applied migration versions are tracked in `mattrics.mattrics_schema_migrations`
- public HTTP requests do not auto-run schema migrations; browser-facing endpoints keep their existing fallback behavior if canonical foundation access fails

## Endpoint Behavior

### `GET /api/data.php`

Canonical mode:

- reads canonical activities from Postgres
- returns the existing `rows`, `count`, and `meta` response shape
- maps canonical columns back to the legacy activity field names the app already expects
- keeps `Duration (min)` mapped from canonical `duration_minutes`
- keeps `Date` mapped from canonical `started_at` when present, otherwise `activity_date`
- sets `meta.source` to `canonical`
- sets `meta.lastSuccessfulSyncAt` from the latest succeeded canonical import batch

Legacy fallback:

- if canonical reads are disabled, the endpoint keeps the current cached-snapshot behavior
- if canonical reads fail, the endpoint falls back to the legacy snapshot/live-refresh path
- fallback responses add a warning in `meta.warning`

`refresh=1`:

- when canonical reads succeed, `refresh=1` is treated as a normal reread only
- it does not trigger a Google refresh while canonical mode is active

`includeChildren=1`:

- adds `activityChildren` to the response
- each item includes:
  - `activityIdRaw`
  - `activityId`
  - `exercises`
- each exercise item includes:
  - `exerciseId`
  - `canonicalExerciseName`
  - `sourceExerciseName`
  - `normalizedSourceExerciseName`
  - `sets`
- canonical child-row loading is scoped to the already-selected canonical activity IDs instead of loading every child row for the user first

Slice 6 frontend behavior:

- when the app is using the internal `api/data.php` route, the browser now requests `includeChildren=1`
- the UI stores canonical child rows in an in-memory activity cache keyed by `Activity ID raw` first, then `Activity ID`
- Hevy-dependent UI paths now prefer those canonical child rows and fall back to parsing `Description` only when child rows are unavailable

Slice 8 fatigue/readiness behavior:

- fatigue and readiness now consume canonical child-row `setDetails` as the authoritative workout-set source whenever those child rows exist for an activity
- canonical `parsed` sets use typed `weightKg`, `reps`, and `effortFactor` values directly instead of reparsing display text
- canonical `time` sets use typed `durationMinutes` and `distanceKm` directly for exercises configured with `setTypeHandling: "time_duration"`
- canonical `unknown` sets or canonical sets missing required numeric fields fall back to the existing duration plus set-count heuristic for that matched exercise block
- description parsing remains the strict fallback only when canonical child rows are unavailable for the activity

Slice 7 read precedence:

- canonical reads now apply one source-aware activity selection step before the response is built
- direct Hevy import rows win over matching legacy Google Sheet snapshot rows when the duplicate signature is deterministic
- the current deterministic Hevy signature uses local activity date, rounded duration, and one stable anchor from either local start minute or the first parsed Hevy exercise block so Strava-edited titles do not break matching
- when a direct Hevy row wins over a matched legacy snapshot row, the direct row keeps its metrics, IDs, and child exercise/set rows but may display the legacy snapshot `Name` and `Type`
- non-duplicate legacy rows stay visible
- fuzzy cross-source deduping is still intentionally out of scope

Slice 9.5 read precedence:

- canonical reads now use source-family-specific dedupe signatures rather than a single Hevy-only rule
- direct Concept2 rows use the reserved provenance contract `source_key = concept2-logbook-export`, `source_kind = concept2`, `batch_kind = concept2_logbook_export`
- direct Concept2 rows suppress matching legacy Google Sheet snapshot rows only when the duplicate signature is deterministic
- the deterministic Concept2 signature requires local activity date, distance, duration, and one stable anchor from either local start minute or normalized activity name
- legacy snapshot rows participate in Concept2 dedupe only when they are explicit Concept2-like rows, currently identified by `device_name = 'Concept2'`
- when those fields are incomplete or ambiguous, both rows remain visible

Slice 11 read precedence:

- direct Garmin rows now use the provenance contract `source_key = garmin-activities-csv-export`, `source_kind = garmin`, `batch_kind = garmin_export`
- direct Garmin rows suppress matching legacy snapshot rows only when the Garmin duplicate signature is deterministic
- the deterministic Garmin signature requires local activity date, distance, duration, and one stable anchor from either local start minute or normalized activity name so Strava-edited activity types can still match
- legacy snapshot rows participate in Garmin dedupe only when they are Garmin-like rows, currently identified by `device_name` beginning with `Garmin`
- when a direct Garmin row wins over a matched legacy snapshot row, the direct row keeps its metrics and provenance but may display the legacy snapshot `Name` and `Type`
- when those Garmin signature fields are incomplete or ambiguous, both rows remain visible

## Live Connector Groundwork

Slice 14 adds the first additive live-connector contract without changing the browser-facing app routes:

- private connector secrets live in `private/storage/live-connectors.json`
- canonical source registry now reserves:
  - `hevy-live-api`
  - `garmin-connect-live`
- canonical import batches now reserve:
  - `hevy_live_api_sync`
  - `garmin_live_sync`
- `scripts/sync-live-connectors.php` currently performs real Hevy live sync and Garmin status groundwork only
- Garmin live fetching remains deferred; only app-safe Garmin connector status appears in diagnostics and runtime status

Slice 15 extends that contract with a local-only operator/admin surface:

- `GET /api/connectors.php`
- `POST /api/connectors.php`

Local-only connector-admin rules:

- the endpoint is authenticated and CSRF-protected
- it is hidden from non-local non-foundation requests with `404`
- it returns app-safe connector booleans, statuses, timestamps, sync strategy, overlap window, cursor timestamps, and fetch counts only
- it never returns stored API keys, raw Hevy workout IDs, DB URLs, private filesystem paths, or raw upstream payload fragments

Supported Hevy actions:

- `save` updates `enabled` and optionally replaces the stored private API key
- `clear` removes the stored private API key, disables Hevy, and resets app-private sync/test state
- `test` performs a Hevy API connectivity check with the stored key only and updates app-private test timestamps without creating a canonical import batch

Connector status vocabulary is now:

- `paused`: disabled or missing credential
- `ready`: enabled with credential but no successful sync/test yet
- `active`: latest successful sync/test is newer than any error
- `error`: latest error is newer than the latest successful sync/test

Slice 15 also changes Hevy live sync behavior:

- the first successful Hevy live sync still uses full history
- later reruns use `incremental_overlap` with a 30-day refetch window anchored from the saved latest-seen workout start time
- when cursor state is damaged but prior Hevy sync history exists, the sync still stays on bounded overlap instead of rereading full history

### `GET /api/exercises.php`

Canonical mode:

- reads canonical exercises from `mattrics_exercises` plus `mattrics_exercise_aliases`
- reads canonical activity types from `mattrics_activity_types` plus `mattrics_activity_type_aliases`
- keeps unresolved review data in `unknowns` on the legacy private JSON path
- sets `meta.source` to `canonical`
- sets `meta.lastSuccessfulSyncAt` from the latest succeeded canonical import batch
- keeps `meta.loadedAt` and `meta.seedVersion` in the existing response contract

Legacy fallback:

- if canonical reads fail, the endpoint returns the existing legacy JSON payload
- fallback responses add a warning in `meta.warning`
- legacy `GET` responses now also set `meta.source` to `legacy` and `meta.lastSuccessfulSyncAt` to `null` so the frontend can render read-source state consistently

Non-GET behavior:

- `POST`, `PATCH`, `DELETE`, and `.../merge-alias` now write canonical exercise/activity-type config rows when canonical mode is available
- canonical config writes reuse the existing `public/api/exercises.php` request and response shapes
- canonical exercise writes store aliases and `matchTerms` in `mattrics_exercise_aliases` using `alias` and `match_term` row kinds
- canonical activity-type writes store aliases in `mattrics_activity_type_aliases`
- canonical config writes keep unresolved review data in the legacy `exercise-unknowns.json` path
- unknown sync and AI suggestion preview routes still use the legacy unknown-review store
- if canonical write initialization is unavailable, mutation routes automatically fall back to the legacy JSON repository path
- canonical mutation responses now report canonical config read metadata when canonical mode is active

## UI Read-Source Visibility

Slice 6 intentionally exposes migration read state in the existing header chrome:

- `Activities: <source>` shows whether session data came from canonical reads, cached snapshot fallback, or live sheet refresh
- `Config: <source>` shows whether exercise/activity-type config data came from canonical reads or the legacy JSON catalog
- the existing banner now shows canonical fallback warnings and a read-source mismatch warning when one side is canonical and the other is not

This status is app-safe only. It does not expose database URLs, filesystem paths, or credentials.

## Fallback Order

### `data.php`

1. canonical Postgres read when enabled
2. cached legacy snapshot if canonical is disabled or canonical read fails
3. existing legacy live refresh path only when legacy snapshot data is unavailable or legacy refresh is explicitly in use

### `exercises.php`

1. canonical Postgres read for `GET` when enabled
2. legacy JSON repository for `GET` if canonical is disabled or canonical read fails
3. canonical Postgres config write path for `POST`, `PATCH`, `DELETE`, and merge routes when canonical mode is available
4. legacy JSON repository fallback for non-GET routes when canonical write initialization is unavailable

## Config Write Precedence

Slice 9 removes the normal config read-after-write lag in canonical mode.

When canonical mode is available:

- canonical Postgres exercise/activity-type rows are the intended source of truth for browser-facing config writes
- canonical `GET /api/exercises.php` responses reflect those writes immediately
- after a successful canonical config write, the current canonical config catalogs are rewritten back into the legacy JSON files as compatibility shadows

Those legacy JSON files still matter during migration because:

- the unknown review queue remains legacy JSON-backed
- canonical-unavailable write fallback still needs the JSON repository path
- bootstrap/import tooling still reads the private JSON catalogs as migration inputs

The shadow catalogs are no longer intended to lead canonical config state in canonical mode.

Legacy bootstrap remains available for recovery or backfill:

```sh
php scripts/bootstrap-foundation.php
```

Direct Hevy imports are additive and separate:

```sh
php scripts/import-hevy.php
```

That importer reads only from private storage and does not create any browser-facing write route.

Remaining temporary fallback rules:

- unknown review records still live only in `private/data/exercise-unknowns.json`
- canonical config shadow-sync can fail independently after a successful canonical DB write, leaving Postgres authoritative and the legacy JSON copies temporarily stale
- the current browser still depends on the compatibility PHP endpoints rather than a dedicated DB-native config API
- the local foundation runtime still depends on `scripts/foundation-runtime-prepare.sh` or equivalent bootstrap/import commands before canonical reads are expected to be warm and complete
- the local foundation diagnostics endpoint `import-diagnostics.php` explains canonical source coverage and suppression state, but it does not change read precedence or import state by itself

## Validation

Primary validation for this slice:

```sh
node public/tests/exercise-config-tests.js
php tests/foundation-compat-api-tests.php
php tests/foundation-config-write-tests.php
php -l public/api/foundation-write.php
php -l public/api/exercises.php
git status --short
```
