---
id: "TASK-REVIEW-DECISIONS"
title: "Persist and test manual review decisions"
story: "ST-REVIEW"
status: "verified"
depends_on: ["ST-MATCH"]
---

Exercise link, split, keep-separate and undo with synthetic activity pairs.

## Acceptance criteria

- Verify reruns honor stored decisions and retain original provenance.

## Evidence

- 2026-11-23 — Synthetic link, split, keep-separate and undo passed. Decisions persisted through an actual source upsert replay and undo restored the canonical IDs. — [Historical preview](../../slices/historical-import-preview-report.md#restart-matching-and-review)
