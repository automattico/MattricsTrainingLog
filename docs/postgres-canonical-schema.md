# Postgres Canonical Schema

This document defines the Slice 3 canonical Postgres contract for Mattrics.
It is additive, local-only, and designed to support later import and compatibility work without changing the current static/PHP production behavior.

## Purpose

- define the first canonical Postgres data model for future migration work
- keep the model multi-user-ready while remaining compatible with the current single-user app
- support future legacy imports and read-only compatibility APIs
- preserve the current deploy model and keep all private runtime state outside `public/`

## Non-Goals

- no production cutover
- no live data migration from Google Sheets
- no Hevy or Garmin ingestion implementation
- no runtime API switch to Postgres
- no storage of secrets, credentials, tunnel state, or raw payload dumps inside canonical tables

## Runtime Contract

- Foundation schema bootstrap SQL lives in `docker/postgres/initdb/`.
- `docker-compose.postgres.yml` mounts that directory into `/docker-entrypoint-initdb.d` for fresh Postgres volumes only.
- Existing local foundation volumes upgrade through forward-only SQL files in `docker/postgres/migrations/`.
- Foundation migration state is tracked in `mattrics.mattrics_schema_migrations`.
- Fresh-volume bootstrap SQL and the forward-only migration path must converge on the same end-state schema.
- Slice 4 adds `php scripts/bootstrap-foundation.php` as the local-only canonical seed/import runner.
- Current endpoints such as [`public/api/data.php`](../public/api/data.php) and [`public/api/exercises.php`](../public/api/exercises.php) remain unchanged in this slice and will map to canonical storage in later slices.

## Schema Shape

All canonical tables live in the `mattrics` Postgres schema and use:

- UUID primary keys via `gen_random_uuid()`
- `created_at` and `updated_at` timestamps
- text columns plus `CHECK` constraints instead of Postgres enums
- explicit user scoping on domain tables so the model is ready for multi-user use later

### Identity and provenance tables

`mattrics_users`

- canonical user boundary for all future domain records
- intentionally small in Slice 3: external key, display name, timezone, active flag

`mattrics_data_sources`

- per-user source registry for Google Sheet, Hevy, Concept2, Garmin, Strava, manual, and later migration flows
- stores source identity and status metadata only
- does not store credentials or tokens
- live connector source keys now reserve `hevy-live-api` and `garmin-connect-live`
- reserved direct Concept2 provenance uses `source_key = concept2-logbook-export` and `source_kind = concept2`

`mattrics_import_batches`

- tracks one import/bootstrap run from a specific source
- stores source keys, status, counts, timing, and an optional private filesystem reference
- the private payload path is metadata only; raw files remain outside the database and outside `public/`
- live connector batches now reserve `hevy_live_api_sync` and `garmin_live_sync`
- reserved direct Concept2 import batches use `batch_kind = concept2_logbook_export`

### Activity tables

`mattrics_activities`

- canonical session/activity row for compatibility with current dashboard data
- stores normalized facts currently exposed by the app: activity date/time, type/name, distance, duration, elevation, HR, pace, speed, cadence, description, device name, and legacy/source activity IDs
- always references `user_id` and `data_source_id`
- optionally references `import_batch_id` and `activity_type_id`
- keeps source row order and source IDs so later imports can be idempotent

`mattrics_activity_exercises`

- stores one exercise block within a strength-style activity
- links back to the parent activity and optionally to a resolved canonical exercise
- preserves the original source exercise name and normalized form for later diagnostics and remapping

`mattrics_activity_sets`

- stores parsed set rows for an activity exercise block
- supports `parsed`, `time`, and `unknown` parse outcomes
- uses nullable typed columns for reps, weight, duration, distance, RPE, effort factor, derived load, and notes because not every source provides every metric

## Slice 4 Bootstrap Coverage

Slice 4 now seeds this schema from the current private runtime inputs:

- `private/data/exercise-configs.json` → `mattrics_exercises` and `mattrics_exercise_aliases`
- `private/data/activity-type-configs.json` → `mattrics_activity_types` and `mattrics_activity_type_aliases`
- `private/cache/training-data.json` → `mattrics_activities`
- Hevy-formatted descriptions inside imported activities → `mattrics_activity_exercises` and `mattrics_activity_sets`

Import behavior:

- source provenance is stored in `mattrics_data_sources` and `mattrics_import_batches`
- `private_payload_path` stores relative private file references only
- activity upserts prefer `source_activity_id_raw` and fall back to batch row numbers only when needed
- unresolved Hevy exercise blocks are preserved with a null `exercise_id`
- unparseable Hevy set lines are preserved with `parsed_kind = 'unknown'`
- reruns rebuild child exercise/set rows for touched activities so imports stay non-duplicating

### Config/catalog tables

`mattrics_exercises`

- destination table for the current exercise config catalog
- stores canonical naming, fatigue behavior, bodyweight/set handling, source provenance, family/archetype metadata, and one numeric column per allowed muscle weight
- does not migrate JSON config records in Slice 3

`mattrics_exercise_aliases`

- stores both user-facing aliases and legacy match terms for exercise resolution
- uses `alias_kind` to distinguish `alias` from `match_term`

`mattrics_activity_types`

- destination table for the current activity type config catalog
- stores canonical naming, review/status fields, fatigue behavior, provenance metadata, and one numeric column per allowed muscle weight
- does not migrate JSON config records in Slice 3

`mattrics_activity_type_aliases`

- stores activity type aliases using the same relational resolution pattern as exercises

## Constraints and defaults

- `normalized_name` columns are unique per user for canonical exercise and activity type records.
- Alias tables enforce unique normalized lookup terms per user and alias kind so later compatibility APIs can resolve names deterministically.
- Activity imports can enforce idempotency through either source activity IDs or batch-local row numbers.
- Numeric measurement fields use non-negative `CHECK` constraints and remain nullable when a source does not provide the metric.
- Muscle weights are stored as explicit numeric columns rather than JSON so the initial schema remains queryable, constrained, and close to the current fixed muscle list.
- Canonical read precedence remains source-aware and conservative: direct Hevy, direct Concept2, and direct Garmin rows may suppress matching legacy snapshot rows only when the source-family duplicate signature is deterministic.
- When direct Hevy or direct Garmin rows suppress a matching legacy snapshot row, the direct row may still display the legacy snapshot `Name` and `Type` while keeping direct-source metrics, IDs, and child-row provenance.

## Intentionally Deferred

- storing unresolved exercise/activity-type review queues in Postgres
- compatibility read APIs over canonical tables
- auth/session/credential migration into Postgres
- derived analytics or fatigue rollups inside the database

## Expected next slice

Slice 5 should add a read-only compatibility API over the canonical tables without switching the frontend to Postgres yet.
