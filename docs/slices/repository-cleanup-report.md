# Slice Report: Repository cleanup

Date: 2026-09-23
Delivery branch: `main` (prepared on `mattwarden`)

## Summary

Removed approved obsolete assets, dead code and CSS, the retired Docker/Foundation/Postgres runtime scaffolding, completed/superseded prompts, and database-only integration tests. Preserved `CLAUDE.md`, updated Claude's launch configuration to use the supported Mattwarden-compatible development server, and retained compatibility modules that current API endpoints still import.

## Removed

- Docker and Compose configuration, images, PHP Docker configuration, Postgres schema/migrations, and local Foundation runtime scripts.
- Retired Foundation/Postgres runbooks and the obsolete runtime handoff prompt.
- Completed and superseded prompts under `docs/slices/` and `docs/features/exercise-config-admin/prompts/`; active release/finalization prompts remain.
- Orphaned README screenshots, the unused 48px favicon, redundant template and `.gitkeep`, and ignored `.DS_Store` files.
- Unreferenced JavaScript globals/helpers, PHP helpers, and CSS blocks identified by the repository audit.
- Database-only config-write tests and database integration branches from retained compatibility/import test suites.

## Compatibility preservation

- `api/connectors.php` no longer loads the large import module merely to test the Hevy connection.
- Hevy request and connector-attempt helpers now live in `lib/foundation-connectors.php`, which is already the endpoint's connector dependency.
- `lib/foundation-read.php`, `lib/foundation-write.php`, `lib/foundation-connectors.php`, and related compatibility code remain because current endpoints still import them.
- Workout AI remains present but broken because Anthropic rejects the configured credential; repair remains deferred.

## Validation

- `./scripts/prod-gate.sh`: passed with no skipped database blocks.
- PHP lint for edited libraries and retained test suites: passed.
- JavaScript syntax checks for edited modules and the new local-development docs section: passed.
- Markdown relative-link audit: passed.
- `public/index.html` local asset-reference audit: 45 references passed.
- `git diff --check`: passed.

## Security check

- Secrets read or modified: no.
- Sensitive files modified: no.
- Raw/private health data handled: no.
- Public credential exposure changed: no.
- Ignored private/runtime files were left untouched except removal of `private/.DS_Store`.

## Git state

- Working tree: clean after the final delivery-status commit.
- Cleanup commit: `b97cab4` (`Finalize Mattwarden docs and repository cleanup`).
- Merge: `main` was fast-forwarded to `b97cab4`.
- Push: `mattwarden` and `main` were pushed to origin.
- Deploy: `b97cab4` deployed successfully on 2026-09-23; the deploy-time production gate passed and the managed remote trees matched the explicit allowlist.

## Next slice

No additional repository-cleanup slice is pending. Authenticated production verification remains separately documented for the Hevy header, workout-detail visibility, and Pádel changes. Workout-AI repair remains explicitly deferred.
