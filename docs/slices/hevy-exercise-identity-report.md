# Slice report · Hevy exercise identity and review

**23 November 2026 · MattricsTrainingLog · `main`**

## Summary

`ST-HEVY-IDENTITY`, `TASK-HEVY-TEMPLATE-PATH`, and `TASK-HEVY-REVIEW` are verified with synthetic workouts in disposable local Postgres.

## Import and replay

- ✅ Two entries with the same source title round-tripped through `GET /api/data?includeChildren=1` as distinct templates with seven structured sets. The test checked order, set type, weight, reps, and RPE.
- ✅ Exact replay kept the same child records and set IDs. Renaming the first template preserved its catalog assignment and sets on the same canonical workout. The second template and its sets matched the original API response.
- ✅ A second source account replayed the same provider IDs and had two independent, unresolved templates.

## Historical review

- ✅ The dashboard scanner and authenticated review endpoint queued two separate entries even though the shared title matched an existing catalog name. The existing review merge action mapped each template independently.
- ✅ Applying the second review response to the loaded historical workout changed quadriceps stimulus from zero to positive. The historical exercise reference became 120 kg for the mapped quadriceps exercise. The first exercise retained its 65 kg reference and chest stimulus. Both templates left the review queue, including after a stale scan was submitted.
- ✅ Creating an exercise from review in the second account mapped only the selected template.

## Files changed

- Updated the data and exercise API path in `lib/foundation-read.php`, `api/exercises.php`, and `lib/exercise-config-repository.php`. Updated workout blocks, exercise resolution, fatigue and reference-load calculations, and Exercise Admin under `public/assets/js/`.
- Extended `tests/postgres/integration.php` and added the focused Hevy PHP and JavaScript test drivers. Updated the local database prerequisites, implementation progress, planning story and tasks, and slice handoff.

## Validation

- ✅ Focused suites passed across PHP exercise config, Foundation compatibility, Foundation import, and JavaScript exercise config (470 assertions).
- ✅ `scripts/local-postgres.sh test` passed 29 assertions after creating and migrating a disposable database.
- ✅ `scripts/prod-gate.sh` passed source guard, lint, local router integration, PHP suites, and JavaScript suites.
- ✅ Planning validation, report fixture tests, and development and staging builds passed.

## Security check

- The synthetic suite used the existing local Postgres login from its private configuration and copied the disposable connection into the isolated router's private configuration for the test. The test removed that copy. Repository secrets and real provider credentials were left untouched.
- Workout, catalog and review records were synthetic. Authenticated API responses exposed exercise data and internal identity keys. The review requests passed through the existing Mattwarden same-origin and CSRF guards.
- The report and planning artifacts contain synthetic results. This slice performed no hosted or provider operations.

## Decisions and remaining verification

- Linked workouts use the existing private exercise catalog. A mapping pairs the source account's database ID with the provider template ID. The dashboard resolves this pair against the latest catalog at analysis time, so review updates already-loaded workouts. Legacy exercises with no template key keep title and alias matching.
- Recovery must retain the private catalog alongside the Postgres source IDs. Provider-side template ID behavior across real title and language changes remains open under `ST-HEVY-REST`.

## Git state

- Working tree: dirty at entry and exit on `main`, with pre-existing edits and new slice changes.
- Commit and push: none for this slice.
- Deploy: none.

## Next slice

[Garmin archive diagnostics](next-garmin-archive-dry-run-prompt.md)
