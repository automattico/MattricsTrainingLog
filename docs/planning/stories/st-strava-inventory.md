---
id: "ST-STRAVA-INVENTORY"
title: "Inventory the full Strava archive"
feature: "FEAT-ARCHIVES"
milestone: "MS-M0"
activity: "ACT-HISTORY"
order: 30
status: "verified"
scope: "required"
requirements: ["REQ-PREFLIGHT", "REQ-ARCHIVES"]
depends_on: ["ST-REQUEST-ARCHIVES"]
client:
  include: true
  title: "Inspect Strava export formats"
  summary: "Identify the activity index, original files, and entries recorded without a file."
  acceptance:
    - "Identify available activity formats and a representative overlap for matching tests."
---

Inspect the official account export activity index and referenced originals. Treat the Make and Sheets copy separately.

## Acceptance criteria

- Check original file types, timestamp and unit conventions, older and manual entries, and missing-file cases.
- Select a representative overlap with Garmin for later dry-run verification. Capture only redacted counts and format notes.

## Evidence

- 2026-11-23 — Inventoried 1,389 index rows and 876 original file references across FIT, GPX, and TCX. Recorded metadata-only handling and date and measurement conventions. — [Strava archive inventory](../../slices/strava-archive-inventory-report.md#inventory)
- 2026-11-23 — The Garmin comparison found 596 byte-identical FIT Activity files, including representative single-session candidates for the exact-match dry run. — [Cross-source archive comparison](../../slices/garmin-archive-inventory-report.md#workout-inventory)
