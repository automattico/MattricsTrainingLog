---
id: "TASK-GARMIN-CHECKPOINT"
title: "Run checkpointed Garmin archive import"
story: "ST-GARMIN-ARCHIVE"
status: "verified"
depends_on: ["TASK-GARMIN-DRY-RUN"]
---

Capture private coverage and a sanitized diagnostic summary.

## Acceptance criteria

- Interrupt and resume the import without duplicate Garmin source records.

## Evidence

- 2026-11-23 — Stopped at 100, resumed to 681 and repeated the same archive with zero new batches. — [Historical preview](../../slices/historical-import-preview-report.md#restart-matching-and-review)
