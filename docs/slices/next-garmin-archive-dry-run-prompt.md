# Slice · Garmin archive diagnostics

## Read first

- `AGENTS.md`
- `docs/architecture-next.md`
- `docs/codex-framework.md`
- `docs/implementation-progress.md`
- `docs/planning/stories/st-garmin-archive.md`
- `docs/planning/tasks/task-garmin-dry-run.md`
- `docs/slices/garmin-archive-inventory-report.md`
- `docs/slices/hevy-exercise-identity-report.md`

## Goal

Verify a read-only Garmin archive dry run that accounts for FIT sessions and reports corrupt or unsupported records before any import.

## Context

The Garmin inventory identified nested ZIPs containing activity FITs. The local importer accepts decoded FIT sessions with source timestamps and a file digest. Linked workouts and Hevy review are already verified locally.

## Scope

- Inspect the existing archive and FIT tooling. Exercise nested ZIP traversal and FIT session accounting with synthetic fixtures.
- Verify safe path handling, depth limits, decompressed-size limits, and corrupt or unsupported record reporting. Preserve file digests and decoded session boundaries for later imports.
- Use the private inventory to target the confirmed layout. Run a read-only diagnostic on the real archive only if the new slice explicitly authorizes it. Keep archive contents, paths, and personal data private.
- Keep archive writes, checkpointed imports, hosted access, scheduled sync, and deploy outside this slice.

## Validation and completion

Run focused archive regressions, the synthetic Postgres suite, the app gate, and planning validation and builds. Update the task only for verified acceptance criteria and record remaining archive work. Update implementation progress, write a slice report and the next prompt, and record working tree, commit, push, and deploy state.
