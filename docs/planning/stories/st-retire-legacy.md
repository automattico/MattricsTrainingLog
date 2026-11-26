---
id: "ST-RETIRE-LEGACY"
title: "Review legacy retirement after observation"
feature: "FEAT-CUTOVER"
milestone: "MS-V1-0"
activity: "ACT-OPERATE"
order: 40
status: "planned"
scope: "required"
requirements: ["REQ-CUTOVER", "REQ-RECOVERY"]
depends_on: ["ST-CUTOVER"]
client:
  include: true
  title: "Complete the dashboard transition"
  summary: "Retire the old data pipeline once the new sources have demonstrated coverage."
  acceptance:
    - "Confirm expected workouts continue to arrive during the observation period."
---

Confirm expected activity coverage and complete observation. Keep Make where it supplies Strava workouts. Decide Sheet retirement and any future Make retirement separately.

## Acceptance criteria

- Record coverage and observation sign-off, obtain separate approval for each component proposed for retirement, and verify continued dashboard availability.
