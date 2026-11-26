---
id: "ST-SYNC-HEVY"
title: "Refresh Hevy REST workouts on app open"
feature: "FEAT-SYNC"
milestone: "MS-V1-0"
activity: "ACT-CONNECT"
order: 20
status: "in_progress"
scope: "required"
requirements: ["REQ-SYNC", "REQ-EXERCISE"]
depends_on: ["ST-HOST-RUNTIME", "ST-HEVY-REST", "ST-FIRST-IMPORT", "ST-HEVY-IDENTITY"]
client:
  include: true
  title: "Refresh Hevy workouts on app open"
  summary: "Keep strength sessions, sets, and edits current from Hevy."
  acceptance:
    - "Confirm that a workout edit updates the existing session and preserves exercise mappings."
---

Verify real REST inputs against the same template-identity and set path validated for the historical preview.

## Acceptance criteria

- Verify authenticated app-open refresh and reload, late edits, exercise identity, failure recovery and no duplicate workouts.

## Evidence

- 2026-11-24 — Read all seventeen authorized REST pages and imported through the cutoff with structured sets. Hosted refresh timing and matching review remain open. — [Dashboard parity and recovery](../../slices/v1-fresh-dashboard-parity-report.md)
