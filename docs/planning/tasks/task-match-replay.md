---
id: "TASK-MATCH-REPLAY"
title: "Check replay convergence and totals"
story: "ST-MATCH"
status: "verified"
depends_on: ["TASK-MATCH-FIXTURES"]
---

Repeat archives and simulate an interrupted batch.

## Acceptance criteria

- Verify stable links and counts and record the diagnostics.

## Evidence

- 2026-11-23 — Both real archives resumed from checkpoints and replayed with zero batches. Synthetic repacking exercised stable source upserts and canonical IDs. — [Historical preview](../../slices/historical-import-preview-report.md#restart-matching-and-review)
