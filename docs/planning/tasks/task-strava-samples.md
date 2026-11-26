---
id: "TASK-STRAVA-SAMPLES"
title: "Identify Strava archive samples"
story: "ST-STRAVA-INVENTORY"
status: "verified"
depends_on: ["ST-REQUEST-ARCHIVES"]
---

Inspect index rows, referenced originals and a metadata-only activity.

The Strava file, date, and missing-reference inventory is complete. Exact FIT overlap was verified against the Garmin archive.

## Acceptance criteria

- Record file types, time conventions, missing-file handling and a candidate overlap.

## Evidence

- 2026-11-23 — Recorded Strava format and time conventions and retained the metadata-only rows in the inventory. — [Strava archive inventory](../../slices/strava-archive-inventory-report.md#inventory)
- 2026-11-23 — Found byte-identical FIT files in both exports and verified a single session boundary in representative pairs. — [Cross-source archive comparison](../../slices/garmin-archive-inventory-report.md#workout-inventory)
