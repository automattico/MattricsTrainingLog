# Slice · First authorized direct workout

## Read first

- `AGENTS.md`
- `docs/architecture-next.md`
- `docs/codex-framework.md`
- `docs/implementation-progress.md`
- `docs/planning/stories/st-first-import.md`
- `docs/slices/local-postgres-synthetic-import-report.md`
- `docs/slices/hevy-rest-preflight-report.md`

## Goal

Verify one authorized direct workout through local canonical persistence and the current dashboard response.

## Context

`ST-POSTGRES` verified native local migrations, synthetic Garmin and Hevy imports, edited replay, exact FIT source linking, rollback, and `GET /api/data?includeChildren=1`. The Garmin adapter accepts decoded session records. Hevy REST preflight separately confirmed access and the expected workout and exercise fields.

## Scope

Use a separate database and private runtime for the authorized sample. Prefer Hevy REST and document any use of the Garmin Personal API fallback. Retain the provider ID, timestamps, and strength details throughout. Verify one representative workout, one exact replay, and a supported edit using an authorized sample copy. Record unresolved mapping evidence if observed.

Keep production reads, scheduled sync, historical account archives, broad matching, and manual review outside the slice.

## Security constraints

Use only the provider access authorized for that slice. Keep credentials, raw provider data, database files, and identifying output outside Git and public paths. Document counts and redacted evidence. Preserve the Mattwarden boundary and local-only configuration.

## Validation and completion

Run the local synthetic suite, verify the private sample through the existing API response, and run the app gate and planning validator and builds. Update the applicable backlog records and implementation progress. Add a slice report and the following slice prompt. Record the working tree, commit, push, and deploy state.
