# Slice 9.5: Foundation Hardening Before Concept2

Read first:
- AGENTS.md
- docs/architecture-next.md
- docs/codex-framework.md
- docs/implementation-progress.md
- docs/compatibility-api.md
- docs/postgres-canonical-schema.md
- docs/docker-postgres-foundation.md

Goal:
Harden the canonical foundation for the next ingestion slice by adding a forward-safe schema evolution path, Concept2-ready provenance support, and explicit canonical read behavior for non-Hevy duplicate handling.

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
- The current foundation schema bootstrap lives in `docker/postgres/initdb/001_canonical_schema.sql`, but fresh-volume init alone is not a sufficient upgrade path for already-seeded local foundation databases.
- The current duplicate-selection rule in canonical reads is intentionally Hevy-specific and does not yet define how future direct Concept2 rows should coexist with legacy snapshot rows.
- The working tree is already dirty with unrelated user changes and must be preserved.
- The current production model remains static/PHP and must continue to work unchanged when canonical mode is disabled.

In scope:
- Define and implement the repo's first explicit forward-only schema evolution path for the Postgres foundation so later slices do not depend on wiping local volumes.
- Extend canonical provenance constraints and documentation so Concept2 can be represented cleanly in `mattrics_data_sources` and `mattrics_import_batches`.
- Define the intended duplicate-selection and read-precedence behavior for Concept2-style endurance rows versus legacy snapshot rows, without implementing the full Concept2 importer yet.
- Tighten the canonical child-row read path if needed so future split-heavy imports do not depend on loading every child row for the user before filtering.
- Add focused tests for any new schema/read-precedence/helper behavior introduced by this hardening slice.
- Update migration docs so Slice 10 can assume the foundation is ready for additive Concept2 ingestion work.

Out of scope:
- No actual Concept2 importer yet.
- No Garmin ingestion yet.
- No production deploy cutover.
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
- Keep the hardening work additive, low-risk, and reversible.
- Do not require deleting the existing local Postgres volume as the normal way to apply the slice.
- Be explicit about whether schema changes live in new forward-only SQL files, bootstrap compatibility helpers, or both.
- Reuse the existing compatibility API surface rather than adding a second browser-facing activity API.
- Preserve automatic legacy fallback when canonical reads are unavailable.
- Document any duplicate-selection rule precisely enough that Slice 10 can implement against it without re-deciding the contract.

Validation:
- Review changed schema/API/docs code for coherence.
- Run focused PHP validation for touched foundation read/import paths.
- Run `php tests/foundation-compat-api-tests.php`.
- Run `php tests/foundation-import-tests.php`.
- Run `php tests/foundation-config-write-tests.php`.
- Run `node public/tests/exercise-config-tests.js`.
- Run any new focused schema or read-precedence tests you add.
- Run `git status --short`.
- Do not run deploy.

Completion requirements:
- Update docs/implementation-progress.md.
- Add a slice report using docs/templates/slice-report.md.
- Add the next slice prompt using docs/templates/next-slice-prompt.md.
