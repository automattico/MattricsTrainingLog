---
id: "TASK-POSTGRES-MIGRATIONS"
title: "Add migration baseline and local setup"
story: "ST-POSTGRES"
status: "verified"
---

Review historical schema for reusable identity and provenance structures.

## Acceptance criteria

- Bootstrap an empty private database and demonstrate idempotent migration replay.

## Evidence

- 2026-11-23 — A fresh database applied one migration. The second run applied zero migrations and preserved the ledger. — [Database and migrations](../../slices/local-postgres-synthetic-import-report.md#database-and-migrations)
