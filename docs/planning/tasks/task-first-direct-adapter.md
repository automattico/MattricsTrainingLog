---
id: "TASK-FIRST-DIRECT-ADAPTER"
title: "Choose and connect the first direct import fixture"
story: "ST-FIRST-IMPORT"
status: "verified"
depends_on: ["ST-POSTGRES"]
---

Use Hevy REST if verified and available. Record the use of the Garmin fallback if needed.

## Acceptance criteria

- Read one representative workout through the compatibility response.

## Evidence

- 2026-11-23 — The existing dashboard response returned a single canonical Hevy workout with eight template-bearing exercises and 22 structured sets. — [Import and readback](../../slices/first-direct-workout-report.md#import-and-readback)
