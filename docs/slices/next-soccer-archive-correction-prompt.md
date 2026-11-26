# Verify soccer labels in the cutover rehearsal

Read `AGENTS.md`, `docs/architecture-next.md`, `docs/codex-framework.md`, and `docs/implementation-progress.md`.

During the next approved local cutover rehearsal, confirm that the restored history returns the three corrected workouts as `Soccer` through `/api/data` and includes them in the Sessions filter for the relevant dates. Verify that a synthetic Strava archive with `Football (Soccer)` yields `Soccer` and retains the original source label. Report only aggregate results. Keep the check local and record it in the cutover slice report and implementation progress.
