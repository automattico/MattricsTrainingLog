# Slice 5: Compatibility API

Read first:
- AGENTS.md
- docs/architecture-next.md
- docs/codex-framework.md
- docs/implementation-progress.md
- docs/docker-postgres-foundation.md
- docs/postgres-canonical-schema.md

Goal:
Add a read-only compatibility API over the canonical Postgres tables so the current app can fetch canonical activity/config data through internal PHP endpoints without switching the frontend behavior yet.

Context:
- Slice 0 established the Codex slice workflow and templates.
- Slice 1 locked the architecture direction around `MattricsTrainingLog`, Docker/Postgres, and private hosting.
- Slice 2 added the parallel local Docker/Postgres foundation runtime.
- Slice 3 added the first canonical Postgres schema and bootstrap contract.
- Slice 4 added local bootstrap/import tooling that seeds canonical tables from the current private JSON catalogs and cached legacy activity snapshot, including Hevy child rows.
- Slice 4a fixed PDO/Postgres boolean binding in the importer and verified that local bootstrap now completes successfully against the foundation database.
- Verified local canonical counts after bootstrap:
  - `mattrics.mattrics_activities`: 288
  - `mattrics.mattrics_activity_sets`: 2324
- The working tree is already dirty with unrelated user changes and must be preserved.
- The current production model remains static/PHP and must continue to work unchanged.

In scope:
- Add local/server-side Postgres connection helpers for read-only canonical access.
- Add read-only compatibility query helpers that can fetch canonical exercises, activity types, activities, and Hevy child rows.
- Add one or more PHP endpoints or endpoint modes that expose canonical data in shapes compatible with the current app’s expectations.
- Keep canonical reads additive so the current private JSON/snapshot flow still exists as a fallback path during migration.
- Document the read path, fallback behavior, and validation approach clearly.

Out of scope:
- No production deploy cutover.
- No writes from the live app into Postgres yet.
- No frontend framework rewrite.
- No auth/session migration into Postgres.
- No Hevy or Garmin direct ingestion implementation beyond what Slice 4 already imported.
- No canonical analytics or fatigue rollups in SQL yet.

Security constraints:
- Do not read, print, commit, or expose secrets.
- Do not touch `.env.local`, `private/config.php`, API keys, tokens, tunnel credentials, raw health exports, or DB dumps.
- Keep private runtime state and credentials outside `public/`.
- Expose only app-safe data shapes; do not leak filesystem paths or credential values in API responses.

Implementation requirements:
- Preserve the current deploy model documented in `docs/architecture.md`.
- Keep the compatibility API additive and low-risk.
- Prefer read-only adapters and fallback behavior over hard cutovers.
- Reuse Slice 4 canonical tables and provenance model rather than introducing a second schema path.

Validation:
- Review changed API/query code and docs for coherence.
- Run focused PHP validation for new read helpers/endpoints.
- Run `git status --short`.
- Do not run deploy.
- Keep the current app usable even if canonical reads are unavailable.

Completion requirements:
- Update `docs/implementation-progress.md`.
- Add a slice report using `docs/templates/slice-report.md`.
- Add the next slice prompt using `docs/templates/next-slice-prompt.md`.
