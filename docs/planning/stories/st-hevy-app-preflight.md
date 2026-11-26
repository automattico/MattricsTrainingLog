---
id: "ST-HEVY-APP-PREFLIGHT"
title: "Confirm template IDs through the Hevy app connector"
feature: "FEAT-PREFLIGHT"
milestone: "MS-M0"
activity: "ACT-CONNECT"
order: 20
status: "verified"
scope: "required"
requirements: ["REQ-PREFLIGHT", "REQ-EXERCISE"]
---

This verifies the app connector sample. Backend REST access and ID behavior across title changes or languages have separate acceptance criteria.

## Acceptance criteria

- Inspect a sample of workouts through the Hevy app connector and confirm that it returns template IDs and structured sets.

## Evidence

- 2026-11-19 — Three workouts returned 23 exercise entries with template IDs and 77 structured sets through the app connector. — [Recorded Hevy app connector preflight](../../mattrics-implementation-plan.md#what-we-know)
