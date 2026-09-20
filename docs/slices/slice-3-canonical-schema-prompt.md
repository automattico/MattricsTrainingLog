# Slice 3: Canonical Schema

Read first:
- AGENTS.md
- docs/architecture-next.md
- docs/codex-framework.md
- docs/implementation-progress.md
- docs/docker-postgres-foundation.md

Goal:
Define the first additive canonical Postgres schema for Mattrics so later slices can import legacy data and expose compatibility APIs without changing the current production/static app behavior.

Context:
- Slice 0 established the Codex slice workflow and templates.
- Slice 1 locked the architecture direction: `MattricsTrainingLog` is the active app, `MattricsNext` is reference only, and Docker/Postgres/private hosting is the future target.
- Slice 2 added a parallel local Docker/Postgres foundation in `docker-compose.postgres.yml` and documented service/storage boundaries.
- The working tree is already dirty with unrelated user changes and must be preserved.
- The current production model remains static/PHP and must continue to work unchanged.

In scope:
- Add a schema design doc for the first canonical Postgres model.
- Add additive schema artifacts for the foundation runtime, such as SQL files or migration scaffolding for initial canonical tables.
- Cover the minimum canonical entities needed to support future imports and read-only compatibility APIs.
- Keep raw imports, private state, and credentials outside `public/`.
- Document table purposes, key relationships, and migration assumptions clearly.

Out of scope:
- No production deploy cutover.
- No live data migration from Google Sheets yet.
- No Hevy or Garmin ingestion implementation yet.
- No UI rewrite.
- No app code changes that switch reads or writes to Postgres.
- No Cloudflare Tunnel implementation yet.

Security constraints:
- Do not read, print, commit, or expose secrets.
- Do not touch `.env.local`, `private/config.php`, API keys, tokens, tunnel credentials, raw health exports, or DB dumps.
- Keep private runtime state outside `public/`.
- Any example config must contain placeholders only.

Implementation requirements:
- Preserve the current deploy model documented in `docs/architecture.md`.
- Keep the schema additive and migration-friendly.
- Prefer clarity over premature optimization.
- Design for multi-user-ready boundaries while staying compatible with the current single-user app.
- Keep the slice small and low-risk.

Validation:
- Review changed SQL and markdown files for coherence.
- If schema files are added under the Docker foundation path, run non-destructive validation that does not require secrets when practical.
- Run `git status --short`.
- Do not run deploy.
- Do not cut over runtime behavior.

Completion requirements:
- Update `docs/implementation-progress.md`.
- Add a slice report using `docs/templates/slice-report.md`.
- Add the next slice prompt using `docs/templates/next-slice-prompt.md`.
