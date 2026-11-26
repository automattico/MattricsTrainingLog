---
id: "ST-FIRST-IMPORT"
title: "Round-trip the first direct workout"
feature: "FEAT-PERSISTENCE"
milestone: "MS-V0-1"
activity: "ACT-HISTORY"
order: 20
status: "verified"
scope: "required"
requirements: ["REQ-PERSISTENCE", "REQ-MATCHING"]
depends_on: ["ST-POSTGRES", "ST-GARMIN-PREFLIGHT"]
client:
  include: true
  title: "Verify a direct workout round trip"
  summary: "Check that a workout can move from a direct source through storage to the dashboard."
  acceptance:
    - "Repeat the same input and confirm a later edit updates one workout."
---

Prefer one authorized Hevy REST workout. If REST access is delayed, use the verified Garmin read to exercise the same persistence and dashboard path. Record which provider supplied the verified round trip.

## Acceptance criteria

- Persist one direct workout and check its fields through the existing dashboard response in an isolated runtime.
- Repeat the sync and verify one source record and one canonical workout. Change a supported input and verify an in-place update.
- Record the run outcome, identifiers in redacted form, and any provider limitation separately.

## Evidence

- 2026-11-23 — One authorized Hevy REST workout reached the isolated dashboard API with eight exercise template IDs and 22 structured sets. Postgres retained the linked external identity and the original and normalized timestamps. — [Import and readback](../../slices/first-direct-workout-report.md#import-and-readback)
- 2026-11-23 — Exact replay and a private title and rep edit kept one source record and one canonical workout. Both IDs and all child IDs stayed stable. — [Replay and edit](../../slices/first-direct-workout-report.md#replay-and-edit)
