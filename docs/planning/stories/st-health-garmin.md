---
id: "ST-HEALTH-GARMIN"
title: "Import Garmin health readings"
feature: "FEAT-HEALTH-GARMIN"
milestone: "MS-V1-2"
activity: "ACT-HEALTH"
order: 20
scope: "required"
status: "planned"
requirements: ["REQ-HEALTH-GARMIN"]
depends_on: ["ST-HEALTH-DESIGN"]
---

Use the supported Garmin source identified during design for body weight, VO₂ max, resting heart rate, and the selected additional heart-rate measures. Keep health ingestion separate from workout backfill.

The archived JSON contains dated body weight, resting and daily minimum and maximum heart rate, and VO₂ max records. Verify live API coverage, source units, and overlap among the available series during design.

## Acceptance criteria

- Verify source labeling and units for each supported metric, including any distinctions among VO₂ max or heart-rate series.
- Verify that a repeated import converges and preserves manual weight entries. Report coverage gaps per metric.

## Evidence

- 2026-11-23 — Confirmed historical body weight, daily heart rate, and VO₂ max records in the Garmin export. — [Garmin Health Data coverage](../../slices/garmin-archive-inventory-report.md#health-data-coverage)
