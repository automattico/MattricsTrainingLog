---
id: "CHECK-V0-1-ROUNDTRIP"
title: "Review canonical write and read parity"
milestone: "MS-V0-1"
status: "verified"
client:
  include: true
  title: "Canonical storage preview reviewed"
  summary: "Check that direct workouts, edits, and exercise mappings appear correctly in the preview."
---

Review migration runs, chosen direct provider, repeat and edit results, and template identity diagnostics.

## Acceptance criteria

- Approve migration replay, a direct workout in the dashboard shape, and verification of unresolved Hevy mappings.

## Evidence

- 2026-11-23 — One direct Hevy workout, its edited replay and structured sets reached the existing isolated dashboard API. — [Direct-workout round trip](../../slices/first-direct-workout-report.md)
- 2026-11-23 — Hevy template identity and historical exercise review passed with an unmapped template, persisted mapping and recalculated fatigue. Migration replay and the Hevy tests passed again during the history preview. — [Hevy identity and review](../../slices/hevy-exercise-identity-report.md)
