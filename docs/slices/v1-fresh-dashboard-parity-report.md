# v1.0 dashboard parity

Updated 25 November 2026 · Europe/Berlin

## Fresh comparison · October 28–November 24

**All 18 sessions match on weekly count, duration and distance. Fatigue remains open.** The fresh full `StravaActivities` tab contained 417 rows. Hevy REST returned 166 workouts over 17 requests and supplied one new strength session at the cutoff. It accounts for the same session as a Sheet row by title, duration, exercise order and the Sheet's established one-hour timestamp offset. Three of 31 comparable weighted sets differ. The isolate uses the direct Hevy recording for this session. The corresponding Strava source has yet to be refreshed directly. A fresh Garmin Personal API read returned zero workouts in the comparison window.

Fatigue used the freshly read production profile, the effective catalog on each side and all available history through each week-end. Systemic load is **full Sheet / Postgres**.

| Berlin dates | Sessions | Minutes | km | Systemic load | Largest regional gap |
| --- | ---: | ---: | ---: | ---: | ---: |
| Oct 28–Nov 3 | 2 | 122.2 | 0 | 1.20 / 0.67 | 39 |
| Nov 4–10 | 5 | 432.4 | 0 | 2.69 / 1.49 | 47 |
| Nov 11–17 | 5 | 349.9 | 2.66 | 1.87 / 1.67 | 22 |
| Nov 18–24 | 6 | 438.7 | 2.02 | 2.72 / 2.38 | 31 |

- **Exercise reconciliation.** Used the existing production–preview catalog reconciliation as the local exercise baseline. Verified 30 of the original 35 account-scoped keys and one new key. The isolated preview API returned all 31 assignments. Five keys covering 31 of the original sets need owner review. The decisions concern decline versus flat press, loaded versus bodyweight crunches, straight-arm cable pulldown versus machine lat pulldown, and cable abduction versus kickback. The exercise-level review is private.
- **Pádel.** The Strava archive index supplied `Workout`. The fresh Sheet uses `Padel` for the same activity identity, title and duration, and the archived title identifies the sport. Corrected the local Strava projection and canonical type while retaining the original archive label as evidence.
- **Remaining fatigue gap.** The structured path omits unresolved template keys. Applying the legacy name matches to those keys in a private diagnostic reduced the largest weekly systemic gap from 1.20 to 0.20 and the largest regional gap from 47 to 14 points. These assignments need review because the legacy text resolver can conflate movement and loading variants. With identical catalogs and Sheet descriptions for the compared strength sessions, three week-ends came within 0.03 systemic load and three regional points. The oldest residual of 0.16 and 14 points persisted with the same recent lead-in and disappeared when both sides used the same older reference-load history.
- **Preservation and validation.** Backed up the isolated database and original catalogs. Verified all 1,588 existing workout groups, source preferences, Keep separate decisions, exact FIT links and the original 322 linked sets. The fresh Hevy session added 35 structured sets. Repository and Postgres gates and the four-week May regression passed.

Provider credentials were used for read-only refreshes. A pinned SFTP read obtained the production catalogs and profile. Workout-level evidence, settings and backups are private local artifacts. Both release checks remain open. [Hosted verification and cutover](next-v1-hosted-verification-prompt.md)

## Prior comparison · October 27–November 23

The isolated copy counted all 17 Sheet workouts once. At that cutoff, the fatigue comparison still had 35 unmapped Hevy keys and the Pádel/Workout difference.

### Source coverage

- **Sheets and Strava.** The full `StravaActivities` tab has 417 rows and 17 in the window. The authenticated dashboard served nine of those from the `last10days` view. A fresh Apps Script read and the workbook confirmed the view. The saved Strava export agrees with the full tab through the cutoff. Live late uploads and edits still need verification.
- **Hevy and Garmin.** Hevy REST supplied 165 workouts through the cutoff, including 14 in the window. The November 23 Garmin export, signed-in activity list, and Personal API agree on zero Garmin workouts in the window. The API also decoded four earlier sessions in a positive control. Direct Concept2 is deferred.

### Weekly comparison

Sessions, minutes and distance match in every week. Systemic load is **full Sheet / Postgres**. Fatigue used the production profile, each input’s catalog, and all available history through each week-end.

| Berlin dates | Sessions | Minutes | km | Systemic load | Largest regional gap |
| --- | ---: | ---: | ---: | ---: | ---: |
| Oct 27–Nov 2 | 1 | 82.2 | 0 | 0.44 / 0.08 | 94 |
| Nov 3–9 | 5 | 377.3 | 0 | 2.05 / 0.40 | 83 |
| Nov 10–16 | 6 | 444.9 | 2.66 | 3.30 / 0.82 | 80 |
| Nov 17–23 | 5 | 341.1 | 2.02 | 1.82 / 0.32 | 84 |

- **Matches.** Linked all 14 exact pairs. The existing Strava workout IDs and display fields remain in use, with 322 structured Hevy sets preserved. Both confirmed cardio warm-ups still count separately from strength. The 138 remaining Hevy–Strava suggestions are outside this window.
- **Fatigue.** All 35 Hevy exercise template keys used by these links are unmapped in the restored catalog. The same catalog on both sides still showed large gaps. A diagnostic run using the legacy description parser for the linked workouts reduced three weeks to zero difference and the sport-label week to 0.02 systemic load and three regional points. Use account-scoped template assignments to reconcile the structured sets.
- **Duration.** Switching 15 comparable stationary rows to moving time produced no change at report precision. A fixture verified positive timer use when moving time is zero and that a warm-up marked Keep separate still counts. The May regression passed all four weeks.

### Recovery

Two isolated restores matched 17 table fingerprints and the paired catalog. All 596 exact links, existing workout IDs, decisions and Hevy sets survived. Repository, Postgres and planning gates passed. Workout-level evidence stays private.

The wider history remains a separate review. Hosted verification and the read switch require approval. [Make rehearsal](v1-make-handoff-rehearsal-report.md)
