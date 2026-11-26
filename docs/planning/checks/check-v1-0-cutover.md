---
id: "CHECK-V1-0-CUTOVER"
title: "Approve cutover and observation results"
milestone: "MS-V1-0"
status: "in_progress"
client:
  include: true
  title: "Dashboard transition reviewed"
  summary: "Review dashboard parity, catalog promotion, restore results, and expected activity coverage before closing the transition."
---

Review side-by-side results, separate database restore, rollback evidence, and the observation period.

## Acceptance criteria

- Confirm dashboard parity, recovery, and provider coverage before approving the read switch. Complete observation with Make and Sheets available and obtain separate retirement sign-off.
- Require production evidence from [TASK-CUTOVER-CATALOG](../tasks/task-cutover-catalog.md). Both live catalogs must match the approved reconciliation, and authenticated verification must confirm that existing mappings, staged additions, and Forearms work before observation starts.

## Evidence

- 2026-11-24 — Reviewed recovery passed. The full workbook comparison identified unresolved matches and fatigue differences. Catalog promotion and production cutover remain open. — [Dashboard parity and recovery](../../slices/v1-fresh-dashboard-parity-report.md)
- 2026-11-25 — The isolated comparison matched weekly counts, durations, and distances. Fatigue and production catalog promotion still require explicit review. — [Latest comparison](../../slices/v1-fresh-dashboard-parity-report.md)
