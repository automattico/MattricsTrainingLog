# Slice Report: Slice 13 - Activity Type Normalization Hardening

Date: 2026-06-09
Agent/thread: Codex
Branch: current working branch

## Summary

Hardened the shared activity-type normalization path used by the legacy config repository, canonical config writes, and foundation import resolvers so acronym-prefixed camel-case labels normalize consistently. Expanded the checked-in activity-type catalog with a small set of high-confidence Garmin/legacy aliases, reduced unresolved direct-import diagnostics for resolvable labels, and intentionally kept ambiguous labels like `Other` unresolved.

## Files Changed

- `public/api/exercise-config-repository.php`
- `public/assets/js/core/exercise-config.js`
- `private/data/activity-type-configs.json`
- `tests/foundation-import-tests.php`
- `tests/foundation-import-diagnostics-tests.php`
- `tests/exercise-config-tests.php`
- `public/tests/exercise-config-tests.js`
- `docs/compatibility-api.md`
- `docs/implementation-progress.md`
- `docs/slices/slice-13-activity-type-normalization-report.md`
- `docs/slices/slice-14-live-connectors-duplicate-resolution-prompt.md`

## Validation

- ✅ `php -l public/api/exercise-config-repository.php`
- ✅ `php -l tests/foundation-import-tests.php`
- ✅ `php -l tests/foundation-import-diagnostics-tests.php`
- ✅ `php -l tests/exercise-config-tests.php`
- ✅ `node public/tests/exercise-config-tests.js`
- ✅ `php tests/foundation-runtime-status-tests.php`
- ✅ `php tests/foundation-import-tests.php`
- ✅ `php tests/foundation-compat-api-tests.php`
- ✅ `php tests/foundation-config-write-tests.php`
- ✅ `php tests/foundation-import-diagnostics-tests.php`
- ✅ `php tests/exercise-config-tests.php`
- ✅ `git status --short` reviewed

## Security Check

- secrets touched: no
- sensitive files modified: no
- notes: This slice only changed normalization logic, checked-in config aliases, tests, and docs. It did not read or modify `.env.local`, `private/config.php`, tunnel credentials, database URLs, raw health exports, or dumps. Diagnostics remain app-safe counts and samples only.

## Decisions Made

- Shared normalization was hardened in one place and mirrored in the browser helper so activity-type matching stays consistent across legacy and canonical paths.
- Alias expansion stayed limited to high-confidence labels such as `Trail Running`, `Indoor Rowing`, and `Strength Workout`.
- Ambiguous labels such as Garmin `Other` remain unresolved and visible in diagnostics by design.

## Known Issues

- The working tree already contains unrelated uncommitted changes outside this slice.
- Canonical integration coverage still skips when no local Postgres foundation is reachable from the current environment.
- This slice reduces unresolved counts for resolvable labels only; it does not add a review UI or automatic policy for ambiguous imported activity types.

## Git State

- working tree: dirty before Slice 13; now also includes additive normalization, alias, test, and docs changes
- commit: not committed
- push: not pushed
- deploy: not deployed

## Next Slice

Slice 14: Concept2 Erg Import

## Next Slice Prompt

# Slice 14: Concept2 Erg Import

Read first:
- AGENTS.md
- docs/architecture-next.md
- docs/codex-framework.md
- docs/implementation-progress.md
- docs/compatibility-api.md
- docs/docker-postgres-foundation.md
- docs/postgres-canonical-schema.md

Goal:
Add a local-only direct Concept2 import path into the canonical foundation so erg sessions can be imported without relying on the legacy Google Sheet snapshot, while preserving current browser-facing API shapes and conservative direct-vs-legacy dedupe rules.

Context:
- Slice 10 added a real local canonical runtime on `localhost:8081`.
- Slice 11 added direct Garmin import and deterministic Garmin-vs-legacy suppression.
- Slice 12 added import diagnostics for local coverage and suppression debugging.
- Slice 13 hardened shared activity-type normalization and reduced unresolved imported labels without changing read precedence.
- The working tree is already dirty with unrelated user changes and must be preserved.
- The current production model remains static/PHP and must continue to work unchanged when canonical mode is disabled.

In scope:
- Add a CLI-only Concept2 import path for a documented local export format.
- Write imported Concept2 sessions into canonical activities with deterministic direct-source provenance.
- Reuse the existing canonical import batch/source registry, compatibility read layer, and diagnostics endpoint.
- Add focused tests for parser behavior, import writes, and read-precedence behavior against matching legacy snapshot rows.
- Document the supported export format and local import/debugging workflow.

Out of scope:
- No production deploy cutover.
- No new browser-facing write routes.
- No broad fuzzy cross-source dedupe expansion.
- No raw payload exposure, credential exposure, or download tooling.
- No changes to Garmin or Hevy import behavior beyond any small shared helper reuse required for Concept2.

Security constraints:
- Do not read, print, commit, or expose secrets.
- Do not touch `.env.local`, `private/config.php`, API keys, tokens, tunnel credentials, raw health exports beyond the selected local import file, or DB dumps.
- Expose only app-safe status/count/timestamp metadata, never credential values, raw payload contents, or absolute private paths.

Implementation requirements:
- Preserve the current deploy model documented in `docs/architecture.md`.
- Keep the work local-only and additive.
- Preserve automatic legacy fallback when canonical reads are unavailable.
- Keep direct-source precedence conservative: direct Concept2 rows should suppress matching legacy snapshot rows only when the existing deterministic signature bar is met.
- Reuse existing migration/import helpers where practical instead of inventing parallel import infrastructure.

Validation:
- Review changed import/parser/API/docs code for coherence.
- Run focused PHP validation for touched parser/import/read paths.
- Run `node public/tests/exercise-config-tests.js`.
- Run `php tests/foundation-runtime-status-tests.php`.
- Run `php tests/foundation-import-tests.php`.
- Run `php tests/foundation-compat-api-tests.php`.
- Run `php tests/foundation-config-write-tests.php`.
- Run any new focused Concept2 tests you add.
- Run `git status --short`.
- Do not run deploy.

Completion requirements:
- Update docs/implementation-progress.md.
- Add a slice report using docs/templates/slice-report.md.
- Add the next slice prompt using docs/templates/next-slice-prompt.md.
