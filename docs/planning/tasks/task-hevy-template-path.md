---
id: "TASK-HEVY-TEMPLATE-PATH"
title: "Preserve template IDs through the mapper"
story: "ST-HEVY-IDENTITY"
status: "verified"
depends_on: ["ST-POSTGRES"]
---

Use same-title and renamed templates with structured sets.

## Acceptance criteria

- Confirm template identity and sets round-trip without collisions.

## Evidence

- 2026-11-23 — Two same-title templates retained distinct account-scoped keys and structured sets through exact and renamed replay. — [Import and replay](../../slices/hevy-exercise-identity-report.md#import-and-replay)
