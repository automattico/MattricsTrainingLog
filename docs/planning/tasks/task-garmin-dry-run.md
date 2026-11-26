---
id: "TASK-GARMIN-DRY-RUN"
title: "Build and run Garmin archive diagnostics"
story: "ST-GARMIN-ARCHIVE"
status: "verified"
depends_on: ["ST-GARMIN-INVENTORY", "ST-POSTGRES"]
---

Use a synthetic nested ZIP fixture and a private archive inventory.

## Acceptance criteria

- Verify size limits, corrupt records and FIT session accounting before the first write.

## Evidence

- 2026-11-23 — The real archive and nested ZIP fixtures accounted for decodable sessions, non-activity files, unusable session bounds and summary fallback. Path, depth, size and corrupt input checks passed. — [Historical preview](../../slices/historical-import-preview-report.md#archive-diagnostics)
