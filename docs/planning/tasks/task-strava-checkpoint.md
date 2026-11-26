---
id: "TASK-STRAVA-CHECKPOINT"
title: "Run checkpointed Strava archive import"
story: "ST-STRAVA-ARCHIVE"
status: "verified"
depends_on: ["TASK-STRAVA-DRY-RUN"]
---

Preserve labels and source evidence in the isolated store.

## Acceptance criteria

- Resume safely and report coverage and unmatched items.

## Evidence

- 2026-11-23 — Stopped at 100, resumed to 1,389 and repeated the same archive with zero new batches. — [Historical preview](../../slices/historical-import-preview-report.md#restart-matching-and-review)
