# Slice report · v0.1 historical import preview

**23 November 2026 · MattricsTrainingLog · `main`**

## Outcome

The two existing account exports produced **2,070 source records and 1,474 canonical workouts** in the dedicated loopback history database. The **596 exact FIT links** agree with the overlap inventory. An authenticated local request to `/api/data?includeChildren=1` returned precisely the canonical IDs with no duplicate rows. Its distance and duration totals matched Postgres.

The [direct Hevy round trip](first-direct-workout-report.md) and [Hevy exercise identity slice](hevy-exercise-identity-report.md) supply the direct-workout, edit, template identity, review and fatigue evidence for the other v0.1 release check. The synthetic suite reran those checks. The history database holds the two archive sources.

## Archive diagnostics

| Source | Intake | Recovered | Diagnostics |
| --- | ---: | ---: | --- |
| Garmin | 32,687 FIT files and 679 activity summaries | **681** | 32,009 non-activity FITs excluded. 670 activity FIT sessions decoded, 668 matched an index summary, and two remained FIT-only. All 11 remaining summaries were recoverable separately. Eight FIT originals lacked a usable end or elapsed duration and were recorded as rejects. Two other recordings recovered their ends from elapsed time. |
| Strava | 1,389 index rows, 876 referenced originals | **1,389** | 780 FIT, 89 GPX and seven TCX originals decoded, including gzip-wrapped files. All 513 rows without an original were preserved. 875 recording starts corroborated the CSV interpretation as UTC within one minute, and one disagreed. For metadata-only rows UTC is an assumption. 146 FIT ends came from elapsed time. 171 ambiguous pairs of CSV distance columns were retained as evidence without treating their numbers as interchangeable. |

Garmin indexes were associated with a unique FIT start to the second. The summary-only fallback required a unique summary and no decoded FIT within 90 seconds. Positive summary metric units were checked against paired FIT values. Summary timer time remains distinct from elapsed time. FIT session numbers, lap references and available lap bounds remain attached in private source evidence. Recorded values and summary IDs are retained locally. The preview does not infer device-native origin from a matching FIT alone.

Synthetic diagnostics exercised nested ZIP traversal, path and symlink rejection, depth and decompressed-size limits, CRC failures, metadata fallback, missing and unreferenced originals, XML entity rejection, and separate FIT session boundaries. [Garmin FIT protocol and profile](https://developer.garmin.com/fit/protocol/)

## Restart, matching and review

| Source | Stopped after | Resumed to | Byte-identical replay |
| --- | ---: | ---: | --- |
| Garmin | 100 | 681 in six further batches | Zero batches |
| Strava | 100 | 1,389 in thirteen further batches | Zero batches |

The archive digest and decoded manifest identify a run. Checkpoint advancement, source upserts, canonical links and candidate refresh commit together. A synthetic repack with a different archive digest exercised source upserts and preserved IDs, links and totals. Synthetic two-leg overlap linked each Strava leg only to its corresponding Garmin leg. The dashboard used Strava labels and Garmin measured values, and each leg contributed once to distance and fatigue.

Time, sport and duration suggest review candidates. Exact links require identical unwrapped FIT bytes and matching session boundaries with an unambiguous counterpart. The real preview contains **878 unlinked workouts**, including **48 workouts in 24 probable review pairs**. No real candidate was adjudicated. A synthetic two-candidate conflict remained separate, and manual link, exact split, keep-separate and undo preserved source records and survived replay. Review is available through the local CLI.

## Coverage and reconciliation

Both exports and the merged history span **2019–2026**. The local diagnostic and database report include exact first and last calendar days and the joint year-and-sport counts. The table below uses source UTC years for the provider columns and dashboard calendar years for the canonical column.

| Year | Garmin source records | Strava source records | Canonical workouts |
| --- | ---: | ---: | ---: |
| 2019 | 84 | 81 | 100 |
| 2020 | 89 | 87 | 89 |
| 2021 | 57 | 57 | 58 |
| 2022 | 87 | 311 | 317 |
| 2023 | 108 | 256 | 266 |
| 2024 | 115 | 170 | 171 |
| 2025 | 99 | 227 | 231 |
| 2026 | 42 | 200 | 242 |
| **Total** | **681** | **1,389** | **1,474** |

Canonical sport counts are Run 455, Workout 245, WeightTraining 142, Rowing 130, Yoga 113, Ride 91, Walk 60, Canoe 58, Hike 52, Swim 45, Other 38, WaterSport 23, Surfing 13, Breathwork 5, and Mobility, Pádel, Skate and Soccer one each.

All **288** rows in the existing local Sheet cache (2025–2026) matched an archive Strava ID, including **140** metadata-only records. The Strava archive adds **1,101** IDs outside that snapshot. The 2019 archive rows and metadata-only records made it through the canonical API. The calendar day agrees for 283 cached rows in the dashboard timezone. All 288 cached starts differ by more than one minute, and 274 differences match the Europe/Berlin UTC offset to within one minute. That pattern is consistent with a local clock value encoded as UTC in the cache. The other 14 timestamp differences lack enough timezone evidence for correction. Archive starts remain authoritative in the preview. The comparison used the cache already on disk.

## Validation

- Both authorized local archive dry runs, checkpoint restarts, and completed replays.
- Focused archive diagnostics and the disposable Postgres suite, including the prior Hevy tests and synthetic archive/review/fatigue regressions.
- Existing authenticated local dashboard route with one row per canonical workout, matching IDs, distance and duration.
- Repository gate, planning record validation, report fixture tests, and development and staging report builds.

## Protection and local operation

The archive readers made no extraction and left the originals untouched. Private recording locators, source payloads, diagnostics and review identifiers stay in the restricted local database. This report contains aggregate data. One exploratory column check printed private description text to the session tool output. Subsequent checks printed aggregates, and the description text was excluded from the reports and fixtures.

The tools used the existing private local Postgres credential and an ephemeral router configuration. No application or provider credential files changed. Existing Mattwarden endpoints, authentication and guards were reused. No production access, sync, deployment or cutover took place.

## Dashboard source status

The preview header identifies **Local Postgres** workouts and a **Local JSON** catalog. **Last import** shows the last successful database import. The reload action rereads the stored workouts and leaves the import timestamp unchanged. Actual fallback mismatches and explicit read warnings still appear in the banner.

## Git and handoff

The working tree was dirty on entry and remains dirty on `main`. Existing edits were preserved. No commit or push was made. See the [local runbook](../database-environments.md#historical-import-preview) and [review handoff](next-historical-preview-review-prompt.md).
