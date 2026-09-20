# Slice 11: Garmin Import

Read first:
- AGENTS.md
- docs/architecture-next.md
- docs/codex-framework.md
- docs/implementation-progress.md
- docs/compatibility-api.md
- docs/postgres-canonical-schema.md
- docs/docker-postgres-foundation.md

Goal:
Add the first direct Garmin import path into the canonical Postgres foundation so endurance activity history, HR, cadence, pace, and device-native workout metadata can reduce dependence on the legacy Strava/Google Sheet snapshot path.

Context:
- Slice 0 established the Codex slice workflow and templates.
- Slice 1 locked the architecture direction around `MattricsTrainingLog`, Docker/Postgres, and private hosting.
- Slice 2 added the parallel local Docker/Postgres foundation runtime.
- Slice 3 added the first canonical Postgres schema and bootstrap contract.
- Slice 4 added local bootstrap/import tooling that seeds canonical tables from the current private JSON catalogs and cached legacy activity snapshot.
- Slice 4a fixed PDO/Postgres boolean binding and verified bootstrap against the local foundation database.
- Slice 5 added read-only canonical GET support to `public/api/data.php` and `public/api/exercises.php`, plus automatic legacy fallback and `includeChildren=1` support for activity child rows.
- Slice 6 switched the current UI over to intentionally consume canonical-backed read metadata and child rows while preserving legacy fallback.
- Slice 7 added direct native Hevy CSV ingestion and canonical read precedence for direct Hevy rows over matching legacy snapshot rows.
- Slice 8 switched fatigue/readiness and personal reference-load history to consume canonical typed child-row set details first, with legacy description fallback.
- Slice 9 switched exercise/activity-type config mutations onto canonical Postgres records when available, with JSON shadow-sync and legacy fallback preserved.
- Slice 9.5 added the first forward-only foundation migration path and hardened source-aware canonical duplicate selection.
- Slice 10 turned the separate foundation stack into a real local canonical app runtime on `localhost:8081`, added local-only runtime diagnostics, and documented the intentional bootstrap/import path before canonical reads are expected to be ready.
- Concept2 is no longer the next required migration step and should be treated as a future optional connector unless this slice discovers a direct shared need.
- The working tree is already dirty with unrelated user changes and must be preserved.
- The current production model remains static/PHP and must continue to work unchanged when canonical mode is disabled.

In scope:
- Add a local-only direct Garmin import path that writes canonical activities into Postgres without going through the legacy Google Sheet snapshot.
- Support the Garmin export format(s) that are actually present in private storage or already documented in the repo, and reject unsupported shapes clearly.
- Map imported Garmin workouts into canonical activity rows with enough typed fields for the current dashboard/history API to consume them safely.
- Preserve Garmin source provenance so canonical reads can identify Garmin-origin rows distinctly from legacy snapshot rows and Hevy imports.
- Define and implement conservative Garmin-vs-legacy duplicate handling only as far as needed for deterministic canonical read selection.
- Add focused tests for Garmin parsing/import behavior and compatibility-read output.
- Document supported Garmin input, canonical field mapping, duplicate/read-precedence decisions, and any intentionally deferred Garmin details.

Out of scope:
- No production deploy cutover.
- No Concept2 importer in this slice.
- No replacement of the current PHP/vanilla JS UI stack.
- No auth/session migration into Postgres.
- No full removal of the legacy Google Sheet snapshot path.

Security constraints:
- Do not read, print, commit, or expose secrets.
- Do not touch `.env.local`, `private/config.php`, API keys, tokens, tunnel credentials, raw health exports, or DB dumps.
- Keep private runtime state and raw imports outside `public/`.
- Expose only app-safe source/status metadata, never credential values or filesystem paths.

Implementation requirements:
- Preserve the current deploy model documented in `docs/architecture.md`.
- Keep the Garmin import path additive, low-risk, and reversible.
- Reuse the existing compatibility API surface rather than adding a second browser-facing activity API.
- Preserve automatic legacy fallback when canonical reads are unavailable.
- Reuse the local foundation runtime and prep flow from Slice 10 rather than inventing a second canonical runtime path.
- Apply pending forward-only foundation migrations before writing canonical Garmin rows.

Validation:
- Review changed import/API/docs code for coherence.
- Run focused PHP validation for touched importer and compatibility paths.
- Run `node public/tests/exercise-config-tests.js`.
- Run `php tests/foundation-runtime-status-tests.php`.
- Run `php tests/foundation-import-tests.php`.
- Run `php tests/foundation-compat-api-tests.php`.
- Run `php tests/foundation-config-write-tests.php`.
- Run any new focused Garmin import tests you add.
- Run `git status --short`.
- Do not run deploy.

Completion requirements:
- Update docs/implementation-progress.md.
- Add a slice report using docs/templates/slice-report.md.
- Add the next slice prompt using docs/templates/next-slice-prompt.md.
