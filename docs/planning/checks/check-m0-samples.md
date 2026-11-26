---
id: "CHECK-M0-SAMPLES"
title: "Review archive sample and overlap inventory"
milestone: "MS-M0"
status: "verified"
client:
  include: true
  title: "Archive samples reviewed"
  summary: "Confirm that both archives have representative samples for import and matching tests."
---

Review both official archive inventories and the proposed format priority before planning import fixtures.

## Acceptance criteria

- Confirm representative old, manual or indoor, and overlap cases were recorded where available.

## Evidence

- 2026-11-23 — The Garmin inventory covers 2019 activities and indoor categories, with FIT as its recording format. It identifies five representative single-session pairs among 596 exact FIT overlaps with Strava. — [Garmin archive inventory](../../slices/garmin-archive-inventory-report.md#workout-inventory)
- 2026-11-23 — The Strava inventory covers 2019 activities and 513 metadata-only entries. The local import decoded FIT, GPX and TCX originals, preserved all metadata-only rows, and produced 596 exact links from the archive pair. — [Historical import preview](../../slices/historical-import-preview-report.md)
