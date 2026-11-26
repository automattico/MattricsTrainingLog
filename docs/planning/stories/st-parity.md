---
id: "ST-PARITY"
title: "Compare the new and existing dashboard reads"
feature: "FEAT-CUTOVER"
milestone: "MS-V1-0"
activity: "ACT-REVIEW"
order: 10
status: "in_progress"
scope: "required"
requirements: ["REQ-CUTOVER", "REQ-MATCHING"]
depends_on: ["ST-RECONCILE", "ST-HEVY-IDENTITY", "ST-SYNC-HEALTH"]
client:
  include: true
  title: "Compare dashboard results side by side"
  summary: "Check recent sessions, history and fatigue before the transition."
  acceptance:
    - "Review several weeks of results and account for meaningful differences."
---

Compare several weeks of results, historic coverage, edits, late arrivals, and duplicate recordings.

## Acceptance criteria

- Review counts, recent sessions, training volume and fatigue against the legacy dashboard. Document expected differences and resolve material gaps.

## Local rehearsal · 2026-11-23

The initial comparison found material session and duration differences in three of four cached weekly windows. [Rehearsal report](../../slices/v1-runtime-recovery-report.md)

The owner confirmed Strava title and sport corrections should appear in Mattrics. Local synthetic redelivery updated both fields on the same workout. Discovery of edits by the live Make scenario remains open. [Correction verification](../../slices/strava-corrections-report.md)

After source review, all four windows agreed on sessions, duration, distance and fatigue at report precision in the local rehearsal. [Workout review recovery and parity](../../slices/workout-review-recovery-report.md)

The fresh isolated rerun matches weekly volume. Present the measured fatigue difference at cutover review and record its resolution or explicit acceptance. The five owner exercise decisions can wait for production Exercises. [Catalog ownership](../../slices/v1-catalog-ownership-report.md)

## Evidence

- 2026-11-24 — All 17 full-tab Strava rows matched archive-backed workout identities and volume. The dashboard displays a ten-day view. The Garmin archive, live list and Personal API confirmed zero Garmin workouts in the window. The decision audit found fourteen pending Hevy/Strava pairs in the isolated restore. All matched on start, duration, title, exercise order and comparable weighted sets. Confirm links and rerun fatigue parity. May regression passed. — [Dashboard parity and recovery](../../slices/v1-fresh-dashboard-parity-report.md)
- 2026-11-25 — All fourteen exact pairs linked in the isolated copy. The four weeks match on sessions, duration and distance. The retained structured sets use 35 unmapped exercise templates, and one sport label still differs. — [Linked parity rerun](../../slices/v1-fresh-dashboard-parity-report.md)
- 2026-11-25 — October 28–November 24 matches on all four weekly counts, durations and distances. Verified 30 original template mappings and one newly observed key, corrected the isolated Pádel label and preserved the existing groups, decisions and structured sets. Five owner decisions and fatigue differences remain open. — [Fresh parity and controls](../../slices/v1-fresh-dashboard-parity-report.md)
