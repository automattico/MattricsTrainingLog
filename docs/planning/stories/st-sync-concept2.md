---
id: "ST-SYNC-CONCEPT2"
title: "Verify Logbook access and schedule Concept2 sync"
feature: "FEAT-SYNC"
milestone: "MS-V1-0"
activity: "ACT-CONNECT"
order: 30
status: "planned"
scope: "optional"
requirements: ["REQ-SYNC", "REQ-MATCHING"]
depends_on: ["ST-SYNC-GARMIN", "ST-SYNC-HEVY"]
client:
  include: true
  title: "Schedule Concept2 workout updates"
  summary: "Import eligible Logbook workouts and preserve erg-specific results."
  acceptance:
    - "Confirm repeated updates retain one workout when recordings overlap."
---

Direct Concept2 ingestion is deferred from v1.0. When it resumes, confirm authorized Logbook access and expected results.

## Acceptance criteria

- Verify eligible workout coverage, retain erg-specific results, and prove a retried job leaves one canonical workout across overlaps.

## Local rehearsal · 2026-11-23

Deferred by the owner on 2026-11-23. No Logbook token has been configured. [Rehearsal report](../../slices/v1-runtime-recovery-report.md)
