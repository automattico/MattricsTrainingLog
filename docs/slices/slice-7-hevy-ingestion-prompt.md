# Slice 7: Hevy Ingestion

Read first:
- AGENTS.md
- docs/architecture-next.md
- docs/codex-framework.md
- docs/implementation-progress.md
- docs/compatibility-api.md
- docs/docker-postgres-foundation.md
- docs/postgres-canonical-schema.md

Goal:
Add the first direct Hevy ingestion path into the canonical Postgres foundation so new Hevy exports can be imported without depending on the legacy Google Sheet snapshot, while keeping the current deployed app and compatibility fallback paths usable.

Context:
- Slice 0 established the Codex slice workflow and templates.
- Slice 1 locked the architecture direction around `MattricsTrainingLog`, Docker/Postgres, and private hosting.
- Slice 2 added the parallel local Docker/Postgres foundation runtime.
- Slice 3 added the first canonical Postgres schema and bootstrap contract.
- Slice 4 added local bootstrap/import tooling that seeds canonical tables from the current private JSON catalogs and cached legacy activity snapshot, including Hevy child rows.
- Slice 4a fixed PDO/Postgres boolean binding and verified local bootstrap against the foundation database.
- Slice 5 added read-only canonical GET support to `public/api/data.php` and `public/api/exercises.php`, plus automatic legacy fallback and `includeChildren=1` support for activity child rows.
- Slice 6 switched the current UI over to intentionally consume canonical-backed read metadata and child rows while preserving the existing legacy fallback model.
- The working tree is already dirty with unrelated user changes and must be preserved.
- The current production model remains static/PHP and must continue to work unchanged when canonical mode is disabled.

In scope:
- Add a local/server-side Hevy import entrypoint that reads Hevy export input from private/runtime-safe storage.
- Normalize imported Hevy workouts into the existing canonical activity, activity-exercise, and activity-set tables.
- Reuse the existing canonical exercise resolver and provenance model where possible.
- Keep the live app read-only against canonical storage; no frontend write path is required.
- Add focused tests or validation for the Hevy import flow and any canonical read effects it changes.
- Document the Hevy ingestion contract, input expectations, and any remaining gaps versus the legacy snapshot bootstrap.

Out of scope:
- No production deploy cutover.
- No Garmin ingestion yet.
- No replacement of the existing PHP/vanilla JS UI stack.
- No canonical config mutation migration yet.
- No auth/session migration into Postgres.
- No full retirement of the legacy snapshot/bootstrap path.

Security constraints:
- Do not read, print, commit, or expose secrets.
- Do not touch `.env.local`, `private/config.php`, API keys, tokens, tunnel credentials, raw health exports, or DB dumps.
- Keep raw Hevy exports, private runtime state, and credentials outside `public/`.
- Expose only app-safe import status metadata, never credential values or filesystem paths.

Implementation requirements:
- Preserve the current deploy model documented in `docs/architecture.md`.
- Keep the Hevy ingestion path additive, local/private, and reversible.
- Reuse the Slice 3 canonical schema and Slice 4 import helpers where that avoids a second ingestion path.
- Preserve legacy fallback behavior when no direct Hevy import has been run.

Validation:
- Review changed ingestion/API/docs code for coherence.
- Run focused validation for any touched PHP import helpers and compatibility read paths.
- Run `php tests/foundation-compat-api-tests.php`.
- Run `git status --short`.
- Do not run deploy.

Completion requirements:
- Update docs/implementation-progress.md.
- Add a slice report using docs/templates/slice-report.md.
- Add the next slice prompt using docs/templates/next-slice-prompt.md.
