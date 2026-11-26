---
id: "TASK-STRAVA-DRY-RUN"
title: "Build and run Strava index diagnostics"
story: "ST-STRAVA-ARCHIVE"
status: "verified"
depends_on: ["ST-STRAVA-INVENTORY", "ST-POSTGRES"]
---

Use synthetic index rows, missing originals, and confirmed formats.

## Acceptance criteria

- Account for metadata-only rows and parse failures before the first write.

## Evidence

- 2026-11-23 — The diagnostic reconciled all indexed originals and metadata-only rows. Fixtures covered corrupt, missing, unreferenced and unsupported originals. — [Historical preview](../../slices/historical-import-preview-report.md#archive-diagnostics)
