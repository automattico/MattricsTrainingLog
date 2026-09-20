# Slice 13: Activity Type Normalization Hardening

Read first:
- AGENTS.md
- docs/architecture-next.md
- docs/codex-framework.md
- docs/implementation-progress.md
- docs/compatibility-api.md
- docs/docker-postgres-foundation.md

Goal:
Reduce unresolved imported activity types for canonical direct-source and legacy snapshot rows by tightening canonical activity-type normalization and alias coverage, while preserving the current conservative dedupe/read-precedence model and keeping browser-facing APIs unchanged.

Context:
- Slice 10 added a real local foundation runtime on `localhost:8081`.
- Slice 11 added direct Garmin import with deterministic Garmin-vs-legacy suppression only when the signature is stable.
- Slice 12 added `GET /api/import-diagnostics.php` so local debugging can now see unresolved activity-type counts, samples, and selected-vs-suppressed outcomes per source.
- The working tree is already dirty with unrelated user changes and must be preserved.
- The current production model remains static/PHP and must continue to work unchanged when canonical mode is disabled.

In scope:
- Use the new diagnostics surface and existing canonical activity-type config path to reduce unresolved direct Garmin and legacy snapshot activity-type rows.
- Add targeted normalization or alias improvements for high-confidence imported activity types that are currently left unresolved.
- Preserve app-safe diagnostics so before/after coverage can be inspected locally.
- Add focused tests for the changed normalization behavior.
- Document the normalization/debugging workflow.

Out of scope:
- No production deploy cutover.
- No browser-facing replacement for `public/api/data.php` or `public/api/exercises.php`.
- No broad fuzzy cross-source dedupe expansion.
- No raw payload inspection, file download, or secret exposure.
- No full Concept2 importer unless the normalization work uncovers a direct blocker that cannot be solved otherwise.

Security constraints:
- Do not read, print, commit, or expose secrets.
- Do not touch `.env.local`, `private/config.php`, API keys, tokens, tunnel credentials, raw health exports, or DB dumps.
- Expose only app-safe status/count/timestamp metadata, never credential values, raw payload contents, or absolute private paths.

Implementation requirements:
- Preserve the current deploy model documented in `docs/architecture.md`.
- Keep the work local-only and additive.
- Reuse the existing canonical config tables, canonical write path, and diagnostics endpoint instead of inventing a second activity-type review store.
- Preserve automatic legacy fallback when canonical reads are unavailable.
- Keep direct-source precedence conservative: only normalization coverage should improve, not the dedupe safety bar.

Validation:
- Review changed normalization/API/docs code for coherence.
- Run focused PHP validation for touched normalization and diagnostics paths.
- Run `node public/tests/exercise-config-tests.js`.
- Run `php tests/foundation-runtime-status-tests.php`.
- Run `php tests/foundation-import-tests.php`.
- Run `php tests/foundation-compat-api-tests.php`.
- Run `php tests/foundation-config-write-tests.php`.
- Run any new focused normalization tests you add.
- Run `git status --short`.
- Do not run deploy.

Completion requirements:
- Update docs/implementation-progress.md.
- Add a slice report using docs/templates/slice-report.md.
- Add the next slice prompt using docs/templates/next-slice-prompt.md.
