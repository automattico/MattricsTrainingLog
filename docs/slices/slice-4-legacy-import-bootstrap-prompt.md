# Slice 4: Legacy Import Bootstrap

Read first:
- AGENTS.md
- docs/architecture-next.md
- docs/codex-framework.md
- docs/implementation-progress.md
- docs/docker-postgres-foundation.md
- docs/postgres-canonical-schema.md

Goal:
Bootstrap the canonical Postgres schema with legacy Google Sheet activity data and the current JSON-backed exercise/activity-type config catalogs, while keeping the current static/PHP app behavior unchanged.

Context:
- Slice 0 established the Codex slice workflow and templates.
- Slice 1 locked the architecture direction around `MattricsTrainingLog`, Docker/Postgres, and private hosting.
- Slice 2 added the parallel local Docker/Postgres foundation runtime.
- Slice 3 added the first canonical Postgres schema and its bootstrap/init contract.
- The working tree is already dirty with unrelated user changes and must be preserved.
- The current production model remains static/PHP and must continue to work unchanged.

In scope:
- Add a private/local bootstrap path that loads current exercise configs into canonical exercise tables.
- Add a private/local bootstrap path that loads current activity type configs into canonical activity type tables.
- Add a private/local bootstrap path that imports legacy Google Sheet snapshot rows into canonical activity tables.
- Preserve source provenance, legacy identifiers, and batch metadata during import.
- Document the import flow, assumptions, and rollback/reset expectations clearly.

Out of scope:
- No production deploy cutover.
- No live Google Sheet sync replacement yet.
- No Hevy or Garmin direct ingestion yet.
- No UI rewrite.
- No app code changes that switch reads or writes to Postgres.
- No compatibility API implementation yet.

Security constraints:
- Do not read, print, commit, or expose secrets.
- Do not touch `.env.local`, `private/config.php`, API keys, tokens, tunnel credentials, raw health exports, or DB dumps.
- Keep raw imports, private state, and credentials outside `public/`.
- Any example config must contain placeholders only.

Implementation requirements:
- Preserve the current deploy model documented in `docs/architecture.md`.
- Keep bootstrap logic additive, local-only, and rerunnable against disposable local foundation data.
- Prefer private runtime paths and source metadata references over storing raw payload blobs in Postgres.
- Keep the slice small and low-risk.

Validation:
- Review changed import/bootstrap code and docs for coherence.
- Run non-destructive local validation against a disposable Postgres foundation project when practical.
- Run `git status --short`.
- Do not run deploy.
- Do not cut over runtime behavior.

Completion requirements:
- Update `docs/implementation-progress.md`.
- Add a slice report using `docs/templates/slice-report.md`.
- Add the next slice prompt using `docs/templates/next-slice-prompt.md`.
