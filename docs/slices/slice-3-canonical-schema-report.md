# Slice Report: Slice 3 - Canonical Schema

Date: 2026-06-06
Agent/thread: Codex
Branch: current working branch

## Summary

Added the first canonical Postgres contract for Mattrics. This slice introduced a schema design document, local foundation bootstrap SQL, and Postgres initialization wiring so later slices can import legacy data and expose compatibility APIs without changing the current static/PHP production path.

## Files Changed

- `docker-compose.postgres.yml`
- `docker/postgres/initdb/001_canonical_schema.sql`
- `docs/docker-postgres-foundation.md`
- `docs/implementation-progress.md`
- `docs/postgres-canonical-schema.md`
- `docs/slices/slice-3-canonical-schema-report.md`
- `docs/slices/slice-4-legacy-import-bootstrap-prompt.md`

## Validation

- ✅ `docker compose -f docker-compose.postgres.yml --profile foundation config`
- ✅ Disposable Postgres bootstrap validated with the canonical init SQL
- ✅ SQL and markdown reviewed for coherence and additive scope
- ✅ `git status --short` reviewed

## Security Check

- secrets touched: no
- sensitive files modified: no
- notes: This slice added placeholder-free schema/docs only. It did not read or modify `.env.local`, `private/config.php`, API keys, tokens, tunnel credentials, raw health exports, or database dumps.

## Decisions Made

- Canonical tables live under the `mattrics` Postgres schema and use UUID primary keys.
- Postgres enum types were avoided in favor of text columns with `CHECK` constraints to keep future migrations simpler.
- Activities, config catalogs, and provenance records are all user-scoped now even though the current app is single-user in practice.
- Muscle weights are stored as explicit numeric columns in the first schema pass instead of JSON blobs so the catalog tables remain constrained and queryable.

## Known Issues

- The working tree already contains unrelated uncommitted changes outside this slice.
- The canonical schema exists, but no legacy/config data has been imported yet by design.
- The current app still reads JSON/private snapshot sources and does not use Postgres yet.

## Git State

- working tree: dirty before Slice 3; now also includes canonical schema docs and bootstrap SQL
- commit: not committed
- push: not pushed
- deploy: not deployed

## Next Slice

Slice 4: Legacy Import Bootstrap

## Next Slice Prompt

See `docs/slices/slice-4-legacy-import-bootstrap-prompt.md`.
