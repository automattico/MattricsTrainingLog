---
id: "ST-STRAVA-ARCHIVE"
title: "Import the full Strava workout archive"
feature: "FEAT-ARCHIVES"
milestone: "MS-V0-1"
activity: "ACT-HISTORY"
order: 40
status: "verified"
scope: "required"
requirements: ["REQ-ARCHIVES", "REQ-MATCHING"]
depends_on: ["ST-POSTGRES", "ST-STRAVA-INVENTORY"]
client:
  include: true
  title: "Backfill Strava activity history"
  summary: "Load recoverable workouts from the full Strava archive, including manual entries."
  acceptance:
    - "Check historical coverage and account for rows with missing original files."
---

Use the official archive activity index and referenced originals. Start with FIT and support TCX or GPX where confirmed and necessary. Keep official exports distinct from API-derived rows.

## Acceptance criteria

- Dry-run the full official archive in isolation and preserve metadata-only and manual activities, plus source identity and user-edited titles and sport labels.
- Tie originals to index rows where available and preserve unsupported and unmatched items in diagnostics.
- Run a checkpointed import and report coverage by year and sport, date range and rejects.

## Evidence

- 2026-11-23 — All 1,389 archive rows, including 513 without an original, reached the isolated store. The local report records dates, sports and units exceptions. — [Historical preview](../../slices/historical-import-preview-report.md#archive-diagnostics)
