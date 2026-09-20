# Slice 10: Concept2 Erg Import

Read first:
- AGENTS.md
- docs/architecture-next.md
- docs/codex-framework.md
- docs/implementation-progress.md
- docs/compatibility-api.md
- docs/postgres-canonical-schema.md
- docs/docker-postgres-foundation.md

Goal:
Add the first direct Concept2 erg import path into the canonical Postgres foundation so rowing-machine workouts, distance, pace, time, and split-oriented source facts no longer depend on the legacy Strava/Google Sheet snapshot path.

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
- Slice 9.5 added the first forward-only foundation migration path, reserved Concept2 provenance values, and locked the conservative Concept2-vs-legacy canonical duplicate-selection contract.
- The working tree is already dirty with unrelated user changes and must be preserved.
- The current production model remains static/PHP and must continue to work unchanged when canonical mode is disabled.

In scope:
- Add a local-only direct Concept2 import path that writes canonical activities into Postgres without going through the legacy Google Sheet snapshot.
- Support the currently available Concept2 export format(s) that are present in private storage or documented in the repo, and reject unsupported shapes clearly.
- Map imported Concept2 workouts into canonical activity rows with enough typed fields for the current dashboard/history API to consume them safely.
- Preserve source provenance so canonical reads can identify Concept2-origin rows distinctly from legacy snapshot rows and Hevy imports.
- Reuse the reserved direct Concept2 provenance contract from Slice 9.5: `source_key = concept2-logbook-export`, `source_kind = concept2`, `batch_kind = concept2_logbook_export`.
- Add focused tests for parsing/import behavior and compatibility-read output.
- Document supported Concept2 input, canonical field mapping, read precedence decisions, and any intentionally deferred split-detail storage.

Out of scope:
- No production deploy cutover.
- No Garmin ingestion yet.
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
- Keep the Concept2 import path additive, low-risk, and reversible.
- Reuse the existing compatibility API surface rather than adding a second browser-facing activity API.
- Preserve automatic legacy fallback when canonical reads are unavailable.
- Use the locked Slice 9.5 duplicate-selection contract rather than redefining it:
  - direct Concept2 rows may suppress matching legacy snapshot rows only when the canonical Concept2 duplicate signature is deterministic
  - the deterministic Concept2 signature requires local activity date, distance, duration, and a stable anchor from local start minute or normalized activity name
  - legacy rows participate only when they are explicit Concept2-like rows
  - ambiguous rows must remain visible
- Apply pending forward-only foundation migrations before writing canonical Concept2 rows.

Validation:
- Review changed import/API/docs code for coherence.
- Run focused PHP validation for touched importer and compatibility paths.
- Run `node public/tests/exercise-config-tests.js`.
- Run `php tests/foundation-compat-api-tests.php`.
- Run any new focused Concept2 import tests you add.
- Run `git status --short`.
- Do not run deploy.

Completion requirements:
- Update docs/implementation-progress.md.
- Add a slice report using docs/templates/slice-report.md.
- Add the next slice prompt using docs/templates/next-slice-prompt.md.
