---
id: "ST-REQUEST-ARCHIVES"
title: "Request both official account archives"
feature: "FEAT-ARCHIVES"
milestone: "MS-M0"
activity: "ACT-HISTORY"
order: 10
status: "verified"
scope: "required"
requirements: ["REQ-PREFLIGHT", "REQ-ARCHIVES"]
client:
  include: true
  title: "Request full activity exports"
  summary: "Obtain the original Garmin and Strava account exports for the historical import."
  acceptance:
    - "Confirm availability of the official exports from both providers."
---

Keep the official Garmin and Strava export originals in private storage outside Git and deploy paths. Both archives are inventoried.

## Request status

- Garmin · User confirmed the request and download on 2026-11-23. The original ZIP is preserved in private local storage and its index was read successfully.
- Strava · User confirmed the request on 2026-11-23. The original ZIP is in private local storage and the activity index was read successfully.

## Acceptance criteria

- Record request and availability separately for both providers without account details.
- Preserve each official original and distinguish it from API or Make exports.

## Evidence

- 2026-11-23 — Both official export ZIPs are present in separate private folders and were opened for inventory. The Garmin copy matches the original download byte-for-byte. — [Garmin archive inventory](../../slices/garmin-archive-inventory-report.md#workout-inventory)
- 2026-11-23 — The Strava inventory read the activity index and verified the original file references directly from the preserved official ZIP. — [Strava archive inventory](../../slices/strava-archive-inventory-report.md#inventory)
