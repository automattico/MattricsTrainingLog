---
id: "CHECK-V1-0-SYNC"
title: "Review source delivery and refresh reliability"
milestone: "MS-V1-0"
status: "in_progress"
client:
  include: true
  title: "Workout updates reviewed"
  summary: "Verify coverage and retry behavior for Garmin, Hevy, and the Strava delivery path."
---

Review authenticated app-open and reload refresh for Garmin and Hevy and the existing scheduled Strava-to-Sheets delivery. Scheduled cleanup is deferred and belongs to any later decision to enable the temporary Strava Postgres cache. Direct Concept2 access is deferred.

## Acceptance criteria

- Approve provider coverage, retries, durable checkpoints, overlap handling, visible status and hosted timing. Verify that an older Strava rename or trim and late delivery update the intended workout.
- Verify provider-specific refresh under the hosted application connection and request limits. For any approved Make-to-Postgres writer, verify deletion and permitted retention before enabling the destination.

## Evidence

- 2026-11-24 — Local Make failure and retry and direct refresh gates passed. Hosted timing, coverage, live edit delivery and idle cleanup evidence remain open. — [Make handoff](../../slices/v1-make-handoff-rehearsal-report.md)
- 2026-11-25 — The owner deferred idle cleanup for the temporary Strava Postgres cache. Hosted FIT decoding and full app-open refresh timing remain open. — [Hosted verification](../../slices/v1-hosted-verification-report.md)
