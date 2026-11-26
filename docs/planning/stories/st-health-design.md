---
id: "ST-HEALTH-DESIGN"
title: "Design the Health Data section and metric rules"
feature: "FEAT-HEALTH-DESIGN"
milestone: "MS-V1-2"
activity: "ACT-HEALTH"
order: 10
scope: "required"
status: "planned"
requirements: ["REQ-HEALTH-GARMIN", "REQ-HEALTH-MANUAL", "REQ-HEALTH-TRENDS", "REQ-HEALTH-DERIVED"]
blocker: "Deferred until the later Health Data milestone is scheduled."
---

Review the scope and workflow before choosing a storage model or building the feature. Use synthetic measurements in design examples and fixtures.

## Acceptance criteria

- Review a Health Data design covering manual weight entry, circumference entry, an edit, source labels, chart date filters, and missing-data states.
- Record Garmin coverage per metric, the units and timestamps to preserve, and how the view handles overlapping Garmin and manual weight readings.
- Confirm the intended ratio direction, paired measurements, and BMI height behavior. Record whether any optional circumference sites should move forward.
