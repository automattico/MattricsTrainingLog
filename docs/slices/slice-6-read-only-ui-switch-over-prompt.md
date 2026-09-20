# Slice 6: Read-Only UI Switch-Over

Read first:
- AGENTS.md
- docs/architecture-next.md
- docs/codex-framework.md
- docs/implementation-progress.md
- docs/compatibility-api.md
- docs/docker-postgres-foundation.md
- docs/postgres-canonical-schema.md

Goal:
Make the current UI operate intentionally and verifiably against the new read-only compatibility API path when canonical mode is enabled, while keeping behavior stable and preserving the existing legacy fallback model.

Context:
- Slice 0 established the Codex slice workflow and templates.
- Slice 1 locked the architecture direction around `MattricsTrainingLog`, Docker/Postgres, and private hosting.
- Slice 2 added the parallel local Docker/Postgres foundation runtime.
- Slice 3 added the first canonical Postgres schema and bootstrap contract.
- Slice 4 added local bootstrap/import tooling that seeds canonical tables from the current private JSON catalogs and cached legacy activity snapshot, including Hevy child rows.
- Slice 4a fixed PDO/Postgres boolean binding in the importer and verified local bootstrap against the foundation database.
- Slice 5 added read-only canonical GET support to `public/api/data.php` and `public/api/exercises.php`, plus automatic legacy fallback and `includeChildren=1` support for activity child rows.
- The working tree is already dirty with unrelated user changes and must be preserved.
- The current production model remains static/PHP and must continue to work unchanged when canonical mode is disabled.

In scope:
- Verify the existing frontend works correctly when the server endpoints are serving canonical-backed GET responses.
- Add narrowly scoped frontend or PHP-view changes only where needed to support canonical-read status visibility or canonical child-row consumption.
- Surface read-source status clearly enough for debugging migration behavior without changing the overall UX flow.
- Add focused tests or validation coverage for any UI-facing compatibility changes.
- Document the read-only UI behavior and any remaining canonical/legacy gaps.

Out of scope:
- No production deploy cutover.
- No writes from the live app into Postgres.
- No replacement of the existing PHP/vanilla JS UI stack.
- No exercise-config mutation migration into canonical storage yet.
- No canonical fatigue SQL rollups or analytics migrations.
- No auth/session migration into Postgres.

Security constraints:
- Do not read, print, commit, or expose secrets.
- Do not touch `.env.local`, `private/config.php`, API keys, tokens, tunnel credentials, raw health exports, or DB dumps.
- Keep private runtime state and credentials outside `public/`.
- Expose only app-safe read-source status or timing metadata, never credential values or filesystem paths.

Implementation requirements:
- Preserve the current deploy model documented in `docs/architecture.md`.
- Keep the UI switch-over read-only and reversible.
- Prefer additive status indicators and compatibility wiring over refactors.
- Preserve legacy fallback behavior when canonical reads are unavailable.

Validation:
- Review changed UI/API/docs code for coherence.
- Run focused validation for any touched frontend modules and PHP endpoints.
- Run `php tests/foundation-compat-api-tests.php`.
- Run `git status --short`.
- Do not run deploy.

Completion requirements:
- Update `docs/implementation-progress.md`.
- Add a slice report using `docs/templates/slice-report.md`.
- Add the next slice prompt using `docs/templates/next-slice-prompt.md`.
