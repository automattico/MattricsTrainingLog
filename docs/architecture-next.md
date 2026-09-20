# Mattrics Next Architecture Brief

This document is the permanent architecture brief for future Codex work on Mattrics.
Every implementation slice must read this before making changes.

## Current Decision

- `MattricsTrainingLog` is the main app and practical source of truth.
- `MattricsNext` is reference/archive only.
- Useful MattricsNext concepts may be ported into this repo, especially canonical tables, source adapters, import diagnostics, and migration patterns.
- MattricsNext is not the future UI shell, and feature development should not continue there unless a later architecture decision explicitly reverses this.

## Product Direction

- Mattrics remains the real app.
- The current purpose is training analysis, workout history, exercise mapping, and muscle fatigue/readiness calculation.
- Open Health Hub is a future-compatible direction, not current scope.
- Current architecture work must serve Mattrics first and avoid building a generic health platform before it is needed.
- The system should keep future health-hub concepts possible by using clear boundaries for users, sources, raw imports, normalized records, metrics, recommendations, and exports.

## Runtime Direction

- Production is a Mattwarden-protected static shell plus flat PHP API on Hetzner Webhosting.
- A possible own-server Docker/Postgres runtime remains future work, not the current production target.
- The local Foundation stack must not be treated as a deployed HTTP application until a future slice explicitly migrates it.
- Production must keep private runtime state outside the public web root.
- Backups and restore checks are required before disabling the current Google Sheet pipeline.

## Frontend Direction

- Keep the existing vanilla JavaScript UI for now.
- Do not rewrite the UI into React, Vue, Svelte, or another framework until canonical APIs and storage are stable.
- The current UI contains the latest practical product work and should be preserved while the backend/data architecture changes.
- A framework may make sense later if UI state, multi-user workflows, or Open Health Hub shell needs outgrow the current module pattern.

## Data Direction

- Build provider-independent ingestion.
- Store normalized training data in canonical Postgres tables.
- Preserve raw provider payloads and imported files outside the public web root for debugging, provenance, and future re-normalization.
- Hevy is the first direct source because it drives strength sessions, sets, and fatigue, and live Hevy API connectivity is now a near-term migration priority.
- Garmin is a major direct source for device-native endurance, HR, recovery, and future health-hub signals, and live Garmin Connect connectivity is now a near-term migration priority.
- Concept2 erg data remains valuable future ingestion, but it is explicitly deferred behind live Hevy/Garmin connector work.
- Strava is optional/fallback and must not remain the central data dependency for canonical storage.
- During migration, when a Garmin/Hevy direct row and a Strava/Google Sheet row represent the same underlying activity, the Strava-derived title and activity type should be treated as the preferred display metadata until explicit field-level provenance rules replace that policy.
- Legacy Google Sheet data is migration/fallback input, not the target architecture.

## Domain Boundaries

Keep these boundaries explicit as the app evolves:

- ingestion/connectors
- raw import storage
- normalized workout/activity data
- exercise and activity config resolution
- training load and muscle fatigue calculation
- recommendation logic
- persistence/storage
- user settings and connector status
- API/export boundary
- UI rendering

The UI should consume internal APIs rather than reading private files directly once canonical storage exists.

## Security

- Mattwarden exclusively owns authentication, sessions, cookies, CSRF generation, security headers, and logout.
- Application code must not add authentication or credential-recovery features.
- Every endpoint calls `require_authenticated()`; mutations also require same-origin and CSRF guards.
- Resolve all application paths from `MATTWARDEN_SITE_DIR`.
- Never expose secrets, API keys, tokens, `.env.local`, `private/config.php`, raw health exports, database dumps, or Cloudflare Tunnel credentials.
- Do not commit credentials, private health data, generated raw imports, or local database files.
- Connector responses may expose credential status only, such as `hasCredential: true`, never credential values.
- Raw imports and health data must stay outside `public/`.
- Logs and docs must not include secret values or private payload contents.
