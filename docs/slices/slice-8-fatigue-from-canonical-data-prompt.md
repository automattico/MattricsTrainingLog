# Slice 8: Fatigue From Canonical Data

Read first:
- AGENTS.md
- docs/architecture-next.md
- docs/codex-framework.md
- docs/implementation-progress.md
- docs/compatibility-api.md
- docs/docker-postgres-foundation.md
- docs/postgres-canonical-schema.md

Goal:
Make the current fatigue/readiness calculations intentionally consume canonical activity child rows and direct Hevy imports as the primary source when canonical mode is enabled, while preserving the existing legacy fallback behavior.

Context:
- Slice 0 established the Codex slice workflow and templates.
- Slice 1 locked the architecture direction around `MattricsTrainingLog`, Docker/Postgres, and private hosting.
- Slice 2 added the parallel local Docker/Postgres foundation runtime.
- Slice 3 added the first canonical Postgres schema and bootstrap contract.
- Slice 4 added local bootstrap/import tooling that seeds canonical tables from the current private JSON catalogs and cached legacy activity snapshot, including Hevy child rows.
- Slice 4a fixed PDO/Postgres boolean binding and verified local bootstrap against the foundation database.
- Slice 5 added read-only canonical GET support to `public/api/data.php` and `public/api/exercises.php`, plus automatic legacy fallback and `includeChildren=1` support for activity child rows.
- Slice 6 switched the current UI over to intentionally consume canonical-backed read metadata and child rows while preserving the existing legacy fallback model.
- Slice 7 added a direct native Hevy CSV importer, UTF-8-safe workout ingestion, and canonical read preference for direct Hevy rows over matching legacy snapshot rows.
- The working tree is already dirty with unrelated user changes and must be preserved.
- The current production model remains static/PHP and must continue to work unchanged when canonical mode is disabled.

In scope:
- Make fatigue/readiness calculations intentionally rely on canonical child rows first when canonical mode is enabled.
- Remove any remaining accidental dependence on description reparsing for canonical-backed Hevy workouts where canonical child rows already exist.
- Validate that direct Hevy imports and legacy bootstrap Hevy rows produce coherent fatigue outputs through the current UI/API path.
- Add focused tests or validation for any fatigue/readiness behavior changed by the canonical data path.
- Document the canonical-fatigue source precedence and any remaining fallback rules.

Out of scope:
- No production deploy cutover.
- No canonical write path from the browser UI.
- No Garmin ingestion yet.
- No replacement of the existing PHP/vanilla JS UI stack.
- No canonical config mutation migration yet.
- No auth/session migration into Postgres.

Security constraints:
- Do not read, print, commit, or expose secrets.
- Do not touch `.env.local`, `private/config.php`, API keys, tokens, tunnel credentials, raw health exports, or DB dumps.
- Keep private runtime state and raw imports outside `public/`.
- Expose only app-safe source/status metadata, never credential values or filesystem paths.

Implementation requirements:
- Preserve the current deploy model documented in `docs/architecture.md`.
- Keep the canonical-fatigue path additive, low-risk, and reversible.
- Reuse the Slice 5 compatibility API and Slice 7 activity-selection rules rather than introducing a second canonical read surface.
- Preserve legacy fallback behavior when canonical reads are unavailable.

Validation:
- Review changed fatigue/API/docs code for coherence.
- Run focused frontend and PHP validation for touched fatigue-related modules and compatibility reads.
- Run `node public/tests/exercise-config-tests.js`.
- Run `php tests/foundation-compat-api-tests.php`.
- Run `git status --short`.
- Do not run deploy.

Completion requirements:
- Update docs/implementation-progress.md.
- Add a slice report using docs/templates/slice-report.md.
- Add the next slice prompt using docs/templates/next-slice-prompt.md.
