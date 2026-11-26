---
id: "TASK-MAKE-HANDOFF"
title: "Rehearse the Make-to-Postgres delivery and checkpoint"
story: "ST-CUTOVER"
status: "in_progress"
---

Keep the Sheet serving the existing dashboard during the shadow write and observation.

The current five-hour Make scenario ends at Sheets and uses a start-time lookback and an insert-only write. Verify a path for older updates and backdated arrivals by stable ID before changing the live scenario. The owner deferred scheduled idle cleanup while the temporary Postgres cache is unused. Revisit physical deletion, permitted lifetime, and backup exclusions when deciding whether to enable it.

## Acceptance criteria

- Verify the Make trigger, retained Sheet branch, pagination, older edits and late arrivals against the approved destination.
- Prove stable activity identity, durable checkpoint advance, replay, provider deletion and idle cache cleanup.
- Record allowed retention, rollback and the approvals for each production change.

## Evidence

- 2026-11-24 — Read-only inspection confirmed the live start-time cursor and insert-only behavior. Synthetic Postgres verified transactional retry and replay, an older trim and the expiry switch. Hosted integration and live edit discovery are pending. — [Make handoff](../../slices/v1-make-handoff-rehearsal-report.md)
