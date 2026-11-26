---
id: "ST-RECONCILE"
title: "Reconcile merged history against the legacy Sheet"
feature: "FEAT-ARCHIVES"
milestone: "MS-V0-1"
activity: "ACT-REVIEW"
order: 40
status: "verified"
scope: "required"
requirements: ["REQ-ARCHIVES", "REQ-MATCHING", "REQ-CUTOVER"]
depends_on: ["ST-MATCH", "ST-REVIEW"]
client:
  include: true
  title: "Check historical coverage"
  summary: "Compare the merged history with the existing dashboard and investigate missing or ambiguous workouts."
  acceptance:
    - "Account for representative older and manual workouts and review remaining data gaps."
---

Use legacy rows to compare recent coverage and identify genuinely missing activities or metadata candidates. Establish allowed provenance before incorporating any rows into permanent history or AI inputs.

## Acceptance criteria

- Produce an isolated reconciliation by year and sport with date bounds, linked pairs, unmatched rows, and rejects.
- Check known old and manual-only workouts and explain representative mismatches before closing the preview.

## Evidence

- 2026-11-23 — Reconciled 2,070 source records and 1,474 canonical workouts across 2019–2026. All 288 local legacy cache IDs matched, including 140 metadata-only activities. The slice records the timestamp differences. — [Historical preview](../../slices/historical-import-preview-report.md#coverage-and-reconciliation)
