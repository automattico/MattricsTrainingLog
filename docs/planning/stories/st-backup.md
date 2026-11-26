---
id: "ST-BACKUP"
title: "Restore Postgres to a separate database"
feature: "FEAT-CUTOVER"
milestone: "MS-V1-0"
activity: "ACT-OPERATE"
order: 20
status: "in_progress"
scope: "required"
requirements: ["REQ-RECOVERY", "REQ-PERSISTENCE"]
depends_on: ["ST-RECONCILE", "ST-HEVY-IDENTITY"]
client:
  include: true
  title: "Rehearse database recovery"
  summary: "Verify that training history can be restored before switching dashboard reads."
  acceptance:
    - "Restore into a separate database and check representative history and dashboard outputs."
---

Keep the existing read path available during observation. Document the chosen backup and restore path.

## Acceptance criteria

- Recover an isolated backup into a separate database and reconcile records, links, manual decisions, checkpoints and dashboard reads.
- Prepare the production-bound schema and approved data, and verify the coordinated database, catalog, and read-configuration recovery in [TASK-PRODUCTION-DATABASE](../tasks/task-production-database.md).

## Local rehearsal · 2026-11-23

Restored the full local history and four catalog files into an isolated recovery target. Real catalog assignments were empty. Paired recovery with populated template mappings remains open. [Rehearsal report](../../slices/v1-runtime-recovery-report.md)

## Evidence

- 2026-11-24 — The reviewed and refreshed restores matched database fingerprints and the paired catalog. All 596 existing exact links and prior decisions survived. The refreshed restore contains structured Hevy sets. Hosted storage and rollback verification remain open. — [Dashboard parity and recovery](../../slices/v1-fresh-dashboard-parity-report.md)
