---
id: "REQ-HEALTH-GARMIN"
title: "Import Garmin health readings"
---

Track Garmin body weight, VO₂ max, resting heart rate, and additional heart-rate readings selected during design. Distinguish resting heart rate, other summaries, and time-series readings according to what each value represents.

## Acceptance criteria

- Confirm account-specific archive and API coverage for the target metrics and document any gaps before committing to an ingestion path.
- Import supported readings with measurement date or time, units, and source. Repeated imports preserve manual entries and avoid duplicate Garmin readings.
