---
id: "ST-HEVY-IDENTITY"
title: "Preserve Hevy template identity and resolve unknown exercises"
feature: "FEAT-REVIEW"
milestone: "MS-V0-1"
activity: "ACT-REVIEW"
order: 30
status: "verified"
scope: "required"
requirements: ["REQ-EXERCISE", "REQ-PERSISTENCE"]
depends_on: ["ST-POSTGRES", "ST-HEVY-APP-PREFLIGHT"]
client:
  include: true
  title: "Preserve exercise identity across imports"
  summary: "Keep lifting sets linked to the correct exercise when names change."
  acceptance:
    - "Verify that a renamed exercise keeps its mapping and unknown exercises can be resolved."
---

The direct workout path carries Hevy template IDs and source account identity into exercise review and analysis. Legacy descriptions continue to use name and alias matching.

## Acceptance criteria

- Round-trip a representative Hevy payload with template IDs and sets through persistence and the current dashboard response.
- Verify that distinct templates with identical titles remain distinct and a renamed template retains its mapping.
- Send an unmapped template to review, map it, and verify historical analysis reflects the resolved mapping.

## Evidence

- 2026-11-23 — Same-title templates and structured sets survived exact and renamed replays in disposable Postgres and through the dashboard API. The renamed template kept its mapping and a second account kept independent mappings. — [Import and replay](../../slices/hevy-exercise-identity-report.md#import-and-replay)
- 2026-11-23 — Exercise review mapped a previously unresolved template and recalculated muscle stimulus and reference load for the loaded historical workout. — [Historical review](../../slices/hevy-exercise-identity-report.md#historical-review)
