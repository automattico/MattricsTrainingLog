---
id: "ST-CUTOVER"
title: "Switch dashboard reads with rollback available"
feature: "FEAT-CUTOVER"
milestone: "MS-V1-0"
activity: "ACT-OPERATE"
order: 30
status: "planned"
scope: "required"
requirements: ["REQ-CUTOVER", "REQ-RECOVERY"]
depends_on: ["ST-BACKUP", "ST-PARITY", "TASK-MAKE-HANDOFF", "TASK-CUTOVER-CATALOG"]
client:
  include: true
  title: "Switch dashboard reads after review"
  summary: "Move dashboard reads to the new workout store once recovery and parity have been reviewed."
  acceptance:
    - "Demonstrate that the previous read path remains available during observation."
    - "Verify that the live exercise and activity catalogs preserve existing mappings and include the reviewed staging additions."
---

Switch only after parity and restore review. Keep the old path available during observation.

## Acceptance criteria

- Approve parity and restore evidence, switch reads to Postgres, and demonstrate rollback to the retained read path.
- Approve the reviewed code revision and its deploy separately from the runtime configuration, data preparation, and read switch. Verify the managed deploy inventory and authenticated app before switching.
- Complete [TASK-CUTOVER-CATALOG](../tasks/task-cutover-catalog.md) and verify both live catalogs before switching dashboard reads.

## Local rehearsal · 2026-11-23

Owner raised keeping Make and replacing the Sheet first. Confirm scope and permitted storage for Make-derived rows and the sync cursor before any production action. [Rehearsal report](../../slices/v1-runtime-recovery-report.md)

A local Make destination now supports per-owner seven-day expiry and a retention switch. Verify the production writer, cleanup cadence, source permissions and backup scope before switching reads. [Retention evidence](../../slices/make-retention-report.md)

## Evidence

- 2026-11-24 — Inspected Make’s five-hour flow and tested rollback, durable commit, late delivery and replay in disposable Postgres. The live writer, coverage, runtime and approvals remain open. — [Make handoff](../../slices/v1-make-handoff-rehearsal-report.md)
