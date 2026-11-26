---
id: "ST-MATCH"
title: "Link cross-source duplicates and make reimports safe"
feature: "FEAT-REVIEW"
milestone: "MS-V0-1"
activity: "ACT-REVIEW"
order: 10
status: "verified"
scope: "required"
requirements: ["REQ-ARCHIVES", "REQ-MATCHING"]
depends_on: ["ST-GARMIN-ARCHIVE", "ST-STRAVA-ARCHIVE"]
client:
  include: true
  title: "Link duplicate activity recordings"
  summary: "Bring overlapping sources together while preserving independently recorded workouts."
  acceptance:
    - "Verify a safe rerun and confirm that a linked workout contributes once to totals."
---

Upsert exact source identities, match identical FIT content by hash and session boundary, and generate time and sport candidates for changed metadata. Use field-level provenance and preserve source records.

## Acceptance criteria

- Verify identical overlaps, a unique probable changed-metadata match, and ambiguous or distinct recordings that stay separate.
- Interrupt and restart a batch, reimport archives, and confirm stable source identities, links and canonical totals.
- Reconcile counts after deduplication and confirm one contribution per linked workout to volume and fatigue.

## Evidence

- 2026-11-23 — The real preview linked 596 exact pairs and queued 24 probable pairs. Synthetic multisport, ambiguous candidates, transactional resume and replay passed, with one volume and fatigue contribution per linked workout. — [Historical preview](../../slices/historical-import-preview-report.md#restart-matching-and-review)
- 2026-11-23 — Verified locally with the reviewed dashboard and its source decisions. — [Reversible recording decisions](../../slices/workout-review-decisions-report.md)
- 2026-11-23 — Verified stationary Garmin–Strava matching and the Keep separate override in disposable Postgres. — [Stationary matching report](../../slices/workout-review-stationary-report.md)
- 2026-11-23 — Verified that a weaker overlap does not block a unique strong match and that stationary duration uses the timer. — [Confidence and duration report](../../slices/workout-review-confidence-report.md)
