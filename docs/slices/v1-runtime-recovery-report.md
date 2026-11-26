# v1.0 runtime, recovery and parity rehearsal

23 November 2026 · local development · main

## Cutover decision

**Hold production cutover.** The owner raised a Make-first sequence: keep Make reading Strava and replace the Sheet as the dashboard store first. The checked-in implementation plan instead moves to direct providers first and allows a temporary Make branch for comparison. Decide the destination, retention and provenance of Make-derived rows before changing the scenario. The Sheet also holds Make's cursor in SyncState!B2. Replacing only the activity table leaves that dependency.

Concept2 is deferred at the owner's request. The local private configuration has no identified Logbook token. Direct-provider checks on app open and reload can run inside authenticated web requests. Cron and a separate scheduled runner are unnecessary for that design.

## Runtime

The existing Mac-to-database verify-full TLS result stands. No approved private web probe route was identified. The recorded legacy hosting plan has no included shell or cron. Hosted web PHP, pdo_pgsql, hostname-verified database access, request limits, FIT decoding and provider egress are unverified. The current PHP PDO connection builder does not apply URL TLS query parameters. Require verified host-side TLS configuration and evidence before any hosted database use.

## Recovery

Ran `scripts/local-postgres.sh history-api-audit` and `scripts/local-postgres.sh history-restore-check`. The local source returned all 1,474 dashboard rows. A new database restored from a custom-format dump matched every canonical-schema table by count and row fingerprint, including source links, checkpoints, candidates and the decisions table. There are 596 exact archive links and 24 probable pairs still awaiting review. Real manual decisions were not created for the rehearsal.

Four JSON files accompanied the dump, including the exercise catalog and activity-type catalog. Restored dashboard rows, children and both authenticated catalog lists equalled the originals. The history preview's catalog contains zero Hevy template assignments. Synthetic tests supply the mapping and review evidence. A paired restore with populated account-scoped mappings still needs a separate check. The backup is a private temporary local rehearsal artifact. Production requires durable private backup storage and a coordinated database/catalog snapshot.

## Dashboard parity

Ran `TZ=Europe/Berlin node tests/postgres/history-parity.js "$backup"` with the restored dashboard and catalog and the existing private Sheet cache. Each seven-day period ends at or before the newest cached day. The dashboard fatigue engine received all available history through each comparison date.

| Weeks before latest cached day | Sessions difference | Minutes difference | Distance difference (km) | Systemic load difference | Largest regional score difference |
|---:|---:|---:|---:|---:|---:|
| 0 | +3 | +982.3 | +28.08 | 0.00 | 0 |
| 1 | +1 | +1092.3 | +20.96 | +0.03 | 14 |
| 2 | 0 | 0 | 0 | 0.00 | 0 |
| 3 | +2 | +944.8 | +35.34 | +0.01 | 2 |

The four windows cover 1–28 May 2026. Matching the two dashboards by original Strava activity ID accounts for all six extra rows and all 3,019.42 extra minutes. Six Garmin rows appear separately from six Strava rows with the same recording start, adding 1,954.15 minutes. Five of the pairs also have identical end times. One Garmin recording ends about 135 minutes earlier. The files in all six pairs have different SHA-256 digests, so the exact-FIT rule leaves them separate. Only one pair is in the probable review queue. Source identity is verified within each provider; cross-provider identity remains a review decision.

The remaining net 1,065.27 minutes come from workouts already present in both dashboards. For the differing matched rows, the Sheet value agrees with the Strava export's moving seconds converted to minutes, while the canonical value uses the decoded FIT elapsed duration. Four small differences are below one second each. One week rounds to zero despite a 0.01-minute difference. No session moving across a weekly boundary was needed to explain these totals. Private row-level evidence is retained beside the local recovery snapshot. No matches or durations were changed.

## Local direct refresh development

The opt-in connector POST and linked-workout import use Mattwarden's existing guards. The default provider list is empty. Enabled sources get independent refresh requests after the saved dashboard renders and per-source status and last-success timestamps. Garmin uses the archive FIT decoder and a fourteen-day overlap. Edits outside that window need an explicit reconciliation strategy. Hevy rechecks the workout list for edits. Changed Garmin recordings requiring a new exact-match decision stop the run. Concept2 is deferred.

The disposable Postgres suite exercised Hevy replay, simulated timeout and credential failures, interrupted fetch and provider lock contention. The existing synthetic suite covers late Garmin overlap, review replay and Hevy set edits. Live web-triggered provider requests, real Hevy template identity across renames/language changes and hosted request timing remain open.

## Rollback and observation proposal

Before an approved switch, retain the complete private read configuration, Sheet feed and cursor, and take a coordinated database/catalog backup. Keep Make writing the Sheet throughout observation. If canonical reads fail or parity diverges, restore the previous private read configuration (including its empty canonical database URL), reload through the authenticated app, confirm the expected Sheet source and sample totals, and reconcile the failed interval privately. Keep new-source data available for diagnosis.

Propose four consecutive weekly comparisons with each expected sport represented, one edited workout, one late arrival, overlap replay, stable source IDs and successful recovery. Require explanations for session, duration, distance and regional fatigue differences, plus explicit approval for switching reads. Separately sign off before removing either legacy component. No production operations were performed.

## Validation and handling

The repository gate, disposable Postgres suite and local history restore passed. The implementation used the existing private local database configuration and processed private workout and catalog data in the isolated cluster. Raw responses, credentials and dumps stay outside Git. Hosted settings and provider credentials were not changed. Existing changes remain uncommitted on main. No push or deployment.

[Next slice](next-v1-make-first-prompt.md)

A subsequent read-only audit checked all 596 stored multi-source workouts for an identical FIT digest and matching start and end across one Garmin and one Strava record. All passed. The review queue has 24 unresolved pairs. Searching across sports found a further 23 Garmin–Strava pairs with identical starts and six with starts up to 90 seconds apart. Five within-provider pairs also started within 90 seconds. These are candidates for review, not an instruction to merge.

A simulation retained one Strava row for each of the 19 Sheet activities in the four windows, used archive moving time and applied the trimmed hike's summary distance. All four windows then agreed on sessions, minutes and distance at report precision. A two-point regional fatigue difference in the earliest window disappeared when the comparison used the same pre-window history. No source records, links or review decisions were modified.

The subsequent [reviewed history rehearsal](workout-review-recovery-report.md) verified the four weekly windows after resolving the duplicate pairs and using the selected recording.
