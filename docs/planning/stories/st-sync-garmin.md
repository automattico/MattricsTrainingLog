---
id: "ST-SYNC-GARMIN"
title: "Refresh Garmin Personal API workouts on app open"
feature: "FEAT-SYNC"
milestone: "MS-V1-0"
activity: "ACT-CONNECT"
order: 10
status: "in_progress"
scope: "required"
requirements: ["REQ-SYNC", "REQ-MATCHING"]
depends_on: ["ST-HOST-RUNTIME", "ST-GARMIN-ARCHIVE", "ST-MATCH"]
client:
  include: true
  title: "Refresh Garmin workouts on app open"
  summary: "Keep device-recorded workouts current through direct Garmin updates."
  acceptance:
    - "Confirm that repeated syncs preserve existing workouts and recover from temporary failures."
---

Reuse the Garmin FIT importer for API downloads and reconcile overlap with the archive.

## Acceptance criteria

- Verify authenticated app-open refresh and reload, activity coverage, repeat-safe cursors, failure recovery and stable archive overlap.

## Evidence

- 2026-11-24 — Verified Garmin token exchange, listing, and FIT decoding locally against four positive-control sessions. The November 23 export and signed-in All Activities list corroborate the empty response for October 27–November 23. Hosted app-open refresh remains open. — [Dashboard parity and recovery](../../slices/v1-fresh-dashboard-parity-report.md)
