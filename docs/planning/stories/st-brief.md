---
id: "ST-BRIEF"
title: "Generate a copyable Last 10 days brief"
feature: "FEAT-BRIEF"
milestone: "MS-V1-1"
activity: "ACT-PLAN"
order: 20
status: "planned"
scope: "required"
requirements: ["REQ-BRIEF"]
depends_on: ["ST-BRIEF-DATA", "ST-CUTOVER"]
client:
  include: true
  title: "Generate a 10-day workout brief"
  summary: "Prepare workouts with a copyable summary of recent sessions, relevant lifts, fatigue estimates, and data gaps."
  acceptance:
    - "Check that the brief flags missing and stale data and is easy to copy."
---

Use current fatigue estimates and relevant lift history. Keep routine saving in the official Hevy connector initially.

## Acceptance criteria

- Show the last 10 days of session facts, relevant lift history, fatigue and data gaps including stale sources, missing sets and unmapped exercises.
- Verify copy output with synthetic recent and boundary-date workouts and test a data set with gaps.
