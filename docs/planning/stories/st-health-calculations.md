---
id: "ST-HEALTH-CALCULATIONS"
title: "Calculate and chart BMI and the hip/waist ratio"
feature: "FEAT-HEALTH-TRENDS"
milestone: "MS-V1-2"
activity: "ACT-HEALTH"
order: 50
scope: "required"
status: "planned"
requirements: ["REQ-HEALTH-DERIVED", "REQ-HEALTH-TRENDS"]
depends_on: ["ST-HEALTH-TRENDS"]
---

Use the designed measurement pairing and formula labels. Display calculated readings alongside the underlying health history.

## Acceptance criteria

- Verify BMI from recorded weight and the configured height, and verify the confirmed hip/waist formula from paired eligible measurements.
- Verify that missing inputs produce an unavailable state and that calculated series respect the chart date filters.
