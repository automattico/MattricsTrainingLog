---
id: "ST-GARMIN-ARCHIVE"
title: "Import the full Garmin workout archive"
feature: "FEAT-ARCHIVES"
milestone: "MS-V0-1"
activity: "ACT-HISTORY"
order: 30
status: "verified"
scope: "required"
requirements: ["REQ-ARCHIVES", "REQ-MATCHING"]
depends_on: ["ST-POSTGRES", "ST-GARMIN-INVENTORY"]
client:
  include: true
  title: "Backfill Garmin activity history"
  summary: "Load recoverable workouts from the full Garmin activity archive."
  acceptance:
    - "Check historical coverage and report unsupported records by reason."
---

Use formats confirmed in inventory. Decode individual FIT sessions, preserve provenance and distinguish device-native activities from imports into Garmin where supported.

## Acceptance criteria

- Dry-run the full official archive in isolation. Handle nested ZIPs with safe path, depth, and decompressed-size limits, and report corrupt or unsupported records.
- Preserve original timestamps, time zone evidence, units, fingerprints, and session boundaries. Preserve lap and multisport relationships where modeled.
- Run a checkpointed import and report year and sport counts, oldest and newest dates, and rejects without raw activity data.

## Evidence

- 2026-11-23 — Full local scan recovered 681 workouts and retained eight recording rejects with summary reconciliation. Resume and replay completed. — [Historical preview](../../slices/historical-import-preview-report.md#archive-diagnostics)
