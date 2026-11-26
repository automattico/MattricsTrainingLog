# Slice M0 · Archive inventory and private runtime preflight

## Read first

- `AGENTS.md`
- `docs/architecture-next.md`
- `docs/codex-framework.md`
- `docs/implementation-progress.md`
- `docs/mattrics-implementation-plan.md`
- `docs/planning/README.md`

## Goal

Advance M0 archive and runtime readiness using the acceptance criteria and dependencies in `docs/planning/stories/`. The [Garmin inventory and overlap comparison](garmin-archive-inventory-report.md), [Strava inventory](strava-archive-inventory-report.md), and [Hevy REST sample](hevy-rest-preflight-report.md) are recorded. Prepare the isolated archive dry run and continue `ST-HOST-PATH` when a private runner is selected.

## Scope

- Use the private inventories to choose archive dry-run fixtures, including an older workout, an indoor or metadata-only case, and an exact-match example. Keep the later Health Data import separate.
- Confirm a permitted private probe route for the target host, followed by CLI and web PHP, PDO Postgres, TLS connectivity, scheduling, and outbound provider checks when authorized.
- If a suitable existing title or language variant is available, compare the template ID through the backend Hevy REST API. Record the redacted result or the remaining limitation.
- Record the chosen private archive formats and the recommended first direct import fixture. Use sanitized aggregate evidence only.

Leave production reads, scheduled jobs, and application deployment as they are. Keep account archives, private data, credential values, and detailed test output outside Git and shared reports.

## Completion

Update the applicable stories, tasks, and release checks with evidence, blockers, and actual status. Run the planning validator, fixture test, and staging build. Update the implementation progress record, add a slice report, and prepare the next slice prompt.
