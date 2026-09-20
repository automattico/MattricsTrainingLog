# Slice 9: Exercise Config DB Migration

Read first:
- AGENTS.md
- docs/architecture-next.md
- docs/codex-framework.md
- docs/implementation-progress.md
- docs/compatibility-api.md
- docs/postgres-canonical-schema.md
- docs/docker-postgres-foundation.md

Goal:
Make canonical Postgres exercise and activity-type config records the intentional source of truth for browser-facing config mutations and reads, while preserving safe legacy fallback until the migration is verified.

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
- The working tree is already dirty with unrelated user changes and must be preserved.
- The current production model remains static/PHP and must continue to work unchanged when canonical mode is disabled.

In scope:
- Add canonical-backed mutation support for exercise and activity-type config routes currently handled by `public/api/exercises.php`.
- Preserve the current browser-facing response shapes so the vanilla JS exercise-admin UI does not need a new API surface.
- Define and implement a safe write strategy for canonical exercise/activity-type records, aliases, and match terms.
- Preserve or intentionally bridge unresolved/unknown review data that still lives on the legacy private JSON path.
- Add focused tests for canonical config writes plus fallback behavior.
- Document canonical config write precedence, any lag removed by this slice, and any remaining temporary fallback rules.

Out of scope:
- No production deploy cutover.
- No Garmin ingestion yet.
- No full removal of the legacy private JSON catalogs yet.
- No auth/session migration into Postgres.
- No replacement of the existing PHP/vanilla JS UI stack.

Security constraints:
- Do not read, print, commit, or expose secrets.
- Do not touch `.env.local`, `private/config.php`, API keys, tokens, tunnel credentials, raw health exports, or DB dumps.
- Keep private runtime state and raw imports outside `public/`.
- Expose only app-safe source/status metadata, never credential values or filesystem paths.

Implementation requirements:
- Preserve the current deploy model documented in `docs/architecture.md`.
- Keep the canonical config-write path additive, low-risk, and reversible.
- Reuse the existing `public/api/exercises.php` compatibility surface rather than adding a second public config API.
- Preserve automatic legacy fallback when canonical reads or writes are unavailable.
- Be explicit about which records remain legacy-backed after this slice, if any.

Validation:
- Review changed config/API/docs code for coherence.
- Run focused frontend and PHP validation for touched exercise-admin and compatibility paths.
- Run `node public/tests/exercise-config-tests.js`.
- Run `php tests/foundation-compat-api-tests.php`.
- Run any new focused PHP config-write tests you add.
- Run `git status --short`.
- Do not run deploy.

Completion requirements:
- Update docs/implementation-progress.md.
- Add a slice report using docs/templates/slice-report.md.
- Add the next slice prompt using docs/templates/next-slice-prompt.md.
