---
id: "ST-GARMIN-INVENTORY"
title: "Inventory the full Garmin archive"
feature: "FEAT-ARCHIVES"
milestone: "MS-M0"
activity: "ACT-HISTORY"
order: 20
status: "verified"
scope: "required"
requirements: ["REQ-PREFLIGHT", "REQ-ARCHIVES"]
depends_on: ["ST-REQUEST-ARCHIVES"]
client:
  include: true
  title: "Inspect Garmin export formats"
  summary: "Identify which activity files and metadata the full Garmin export provides."
  acceptance:
    - "Identify representative early and indoor records where available."
---

Inspect the official Garmin export locally. The Personal API sample gives a FIT reference only.

## Acceptance criteria

- Identify actual archive structure, nested ZIPs, FIT sessions and any metadata for provider identity.
- Record representative older and indoor or manual cases where present, plus date coverage and candidate overlap without publishing activity details.

## Evidence

- 2026-11-23 — Inventoried 679 Garmin summaries and 678 FIT Activity files from nested ZIPs. Confirmed 2019–2026 coverage, indoor categories, and byte-identical Strava overlap with single-session samples. — [Garmin archive inventory](../../slices/garmin-archive-inventory-report.md#workout-inventory)
