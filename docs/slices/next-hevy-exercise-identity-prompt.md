# Slice · Hevy exercise identity and review

## Read first

- `AGENTS.md`
- `docs/architecture-next.md`
- `docs/codex-framework.md`
- `docs/implementation-progress.md`
- `docs/planning/stories/st-hevy-identity.md`
- `docs/planning/tasks/task-hevy-template-path.md`
- `docs/planning/tasks/task-hevy-review.md`
- `docs/slices/first-direct-workout-report.md`

## Goal

Verify stable Hevy exercise mapping across title changes and show that resolving an unmapped template updates historical analysis.

## Context

`ST-FIRST-IMPORT` round-tripped one Hevy REST workout through a dedicated local database and the dashboard API. The ordered template IDs and all structured sets survived exact and edited replays. The fresh database had eight unresolved exercise links.

## Scope

- Inspect the existing template mapping and exercise review paths. Use a fresh synthetic database and replay two templates with the same title, then replay one template under a different title.
- Verify each distinct template retains its own identity, the renamed template retains its mapping, and set values remain attached to the correct exercise.
- Queue an unmapped template, resolve it through the supported review path, and check that historical analysis reflects the resolved mapping.

## Boundaries

Use synthetic records and local private configuration. Keep credentials and personal data outside Git and public paths. Preserve Mattwarden's authentication boundary. Hosted access, production reads, real account mutations, scheduled sync, and deployment require separate scope.

## Validation and completion

Run focused regressions, the synthetic Postgres suite, the app gate, and planning validation and builds. Update the story and tasks only for checks that pass. Update implementation progress, write a slice report, and create the next prompt. Record the working tree, commit, push, and deploy state.
