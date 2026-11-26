---
id: "ST-SYNC-HEALTH"
title: "Expose per-source sync health and recovery"
feature: "FEAT-SYNC"
milestone: "MS-V1-0"
activity: "ACT-OPERATE"
order: 10
status: "in_progress"
scope: "required"
requirements: ["REQ-SYNC"]
depends_on: ["ST-SYNC-GARMIN", "ST-SYNC-HEVY"]
---

Keep Garmin and Hevy app-open refreshes independent, serialize overlapping runs per source, and update cursors only after durable writes. Verify equivalent checkpoint and retry behavior for the Make branch.

## Acceptance criteria

- Verify last success and current status in Mattrics. Exercise a timeout, token expiry and replay without exposing credentials or advancing a failed cursor.

## Local rehearsal · 2026-11-23

Local on-demand prototype tested cursor preservation and concurrency. Production sources remain disabled. [Rehearsal report](../../slices/v1-runtime-recovery-report.md)
