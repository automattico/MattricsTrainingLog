---
id: "TASK-FIRST-DIRECT-REPLAY"
title: "Verify repeat sync and edits"
story: "ST-FIRST-IMPORT"
status: "verified"
depends_on: ["TASK-FIRST-DIRECT-ADAPTER"]
---

Replay an unchanged and then an edited workout.

## Acceptance criteria

- Confirm the same source and canonical record are updated without duplication.

## Evidence

- 2026-11-23 — Replay kept the original source and canonical IDs. An edited copy with the same Hevy ID updated the title and a set's rep count in Postgres and through the dashboard API. Counts stayed at one source and one canonical workout. — [Replay and edit](../../slices/first-direct-workout-report.md#replay-and-edit)
