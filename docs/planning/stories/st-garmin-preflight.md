---
id: "ST-GARMIN-PREFLIGHT"
title: "Confirm local Garmin Personal API read"
feature: "FEAT-PREFLIGHT"
milestone: "MS-M0"
activity: "ACT-CONNECT"
order: 10
status: "verified"
scope: "required"
requirements: ["REQ-PREFLIGHT"]
client:
  include: true
  title: "Confirm Garmin sample access"
  summary: "A local read confirmed that activity summaries and a sample recording are available."
  acceptance:
    - "Read recent activity summaries and identify the recording in a sample download."
---

This check covers the Personal API from the Mac and one downloaded activity file. The full archive inventory is verified. Hosted runtime access remains open. [Garmin archive inventory](st-garmin-inventory.md) · [Hosted runtime preflight](st-host-runtime.md)

## Acceptance criteria

- Read representative recent activity summaries locally.
- Download an activity and identify its FIT recording.

## Evidence

- 2026-11-19 — Three activity summaries returned start time, sport, duration, distance, and average and maximum heart rate. One downloaded ZIP contained a FIT file. — [Recorded Garmin preflight](../../mattrics-implementation-plan.md#what-we-know)
