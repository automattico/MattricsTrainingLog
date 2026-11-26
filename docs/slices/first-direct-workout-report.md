# Slice report · First direct Hevy workout

**23 November 2026 · MattricsTrainingLog · `main`**

## Summary

`ST-FIRST-IMPORT`, `TASK-FIRST-DIRECT-ADAPTER`, and `TASK-FIRST-DIRECT-REPLAY` are verified. The official Hevy REST API returned HTTP 200 for a one-workout list request. The sample contained eight exercises with template IDs and 22 structured sets.

## Import and readback

- ✅ Applied `001_workouts.sql` in a new `mattrics_first_hevy` database on a dedicated Postgres cluster at `127.0.0.1:55436`. Running the migration again applied zero versions and preserved the ledger version, checksum, and timestamp. The sample site used its own private configuration and a loopback Mattwarden test router.
- ✅ The existing Hevy adapter and importer created one source record and linked it as the primary source for one canonical workout. SQL matched the Hevy external ID and the verbatim start and end strings, as well as both UTC instants on the source and canonical rows. The source JSON matched the input workout and retained the provider units.
- ✅ `GET /api/data?includeChildren=1` returned HTTP 200 with one canonical workout, no fallback warning, and the canonical UUID shared by the row and its child group. The API start timestamp matched the normalized UTC start at the endpoint's existing precision. The original timestamps, normalized end, and Hevy external ID were verified in Postgres through the source link.
- ✅ All eight ordered template IDs and all 22 sets matched in SQL and HTTP. The comparison covered type, weight, reps, RPE, duration converted from seconds to minutes, and distance converted from meters to kilometers, including nulls.

## Replay and edit

The identifiers below are aliases for the privately compared IDs.

| Run | Source record | Canonical workout | Exercises and sets |
| --- | --- | --- | --- |
| Initial import | 1 · source A | 1 · workout A | 8 exercises · 22 sets |
| Exact replay | 1 · source A | 1 · workout A | Same exercise and set IDs |
| Private title and rep edit | 1 · source A | 1 · workout A | Same exercise and set IDs |

The edited copy kept the Hevy external ID. The new title and the changed rep count appeared on the same workout in SQL and HTTP. All three runs recorded successful import batches. The saved REST response matched its pre-import byte digest, and Hevy received only the original GET.

## Validation

- ✅ `scripts/local-postgres.sh test` passed all 22 assertions in a disposable database on the dev cluster.
- ✅ `scripts/prod-gate.sh` passed the source guard, PHP and shell lint, PHP suites, local router integration, and JavaScript suites.
- ✅ Planning validation and the report fixture suite passed. The development and staging builds both show the story as verified in the internal and client reports. The internal reports show both tasks as verified and keep exercise identity open. A private check confirmed the generated HTML and JSON omit the sampled IDs, timestamps, and local database password.

## Files changed

- Updated the first direct workout story, both tasks, and implementation progress. Added this report and the Hevy exercise identity handoff.
- The slice reused the existing migration, adapter, importer, endpoint, and test router.

## Security check

- The REST request read the existing key from the ignored local environment file. The key and repository secret files were left intact. A separate generated database login was written only to the sample site's mode-600 private configuration.
- The mode-700 sample directory is outside the checkout. It holds the mode-600 raw response, edited copy, and HTTP snapshots, along with the database and test logs. The sample router and cluster were shut down after verification. The recorded evidence uses aliases and aggregate counts.
- The dashboard test response carried workout data and no credentials. The hosted database, production reads, port 5432, real archives, and scheduled sync stayed outside the run.

## Open verification

All eight exercise rows in the fresh sample database had provider template IDs and unresolved catalog links. Matching through a rename, distinguishing same-title templates, and reviewing an unmapped template remain open under `ST-HEVY-IDENTITY`.

## Git state

The working tree was dirty at entry. Existing edits were preserved. The slice is uncommitted on `main`. No push or deployment took place.

## Next slice

[Hevy exercise identity and review](next-hevy-exercise-identity-prompt.md)
