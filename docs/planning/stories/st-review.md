---
id: "ST-REVIEW"
title: "Review and override uncertain workout matches"
feature: "FEAT-REVIEW"
milestone: "MS-V0-1"
activity: "ACT-REVIEW"
order: 20
status: "verified"
scope: "required"
requirements: ["REQ-MATCHING"]
depends_on: ["ST-MATCH"]
client:
  include: true
  title: "Review uncertain workout matches"
  summary: "Review ambiguous overlaps and preserve decisions across future imports."
  acceptance:
    - "Verify that a manual decision persists through a rerun and can be undone."
---

Show candidate evidence and provenance. Persist link, split and keep-separate decisions and support undo. Offer differing Strava archive labels as candidates for Mattrics display overrides.

## Acceptance criteria

- Review an ambiguous match and verify that a manual split or keep-separate decision survives a rerun.
- Undo a manual decision and verify source records and selected field provenance remain available.
- Verify that separate warmups, cooldowns and multisport legs stay independently reviewable.

## Evidence

- 2026-11-23 — Local CLI link, split, keep-separate and undo preserved source records and provenance. Decisions survived source upserts on synthetic replay, and exact multisport legs remained separate. — [Historical preview](../../slices/historical-import-preview-report.md#restart-matching-and-review)
- 2026-11-23 — Verified locally with the reviewed dashboard and its source decisions. — [Duplicate review in Sessions](../../slices/workout-review-sessions-report.md)
- 2026-11-23 — Verified title-first comparisons, difference and missing-value labels, original-link availability and the source-selection step in the local browser. — [Comparison report](../../slices/workout-review-comparison-report.md)
- 2026-11-23 — Added recording-level merge buttons and durable saved comparisons to Sessions. — [One-click review](../../slices/workout-review-one-click-report.md)
- 2026-11-23 — Verified the default discrepancy filter and searchable comparison list against the isolated preview. — [Priority filter report](../../slices/workout-review-priority-report.md)
- 2026-11-23 — Verified confidence-ranked comparison groups and warnings at meaningful gap thresholds on the local preview. — [Confidence and duration report](../../slices/workout-review-confidence-report.md)
- 2026-11-23 — Reworked the comparison list, filter behavior, and manual selection in the local preview. — [Comparison layout report](../../slices/workout-review-layout-report.md)
