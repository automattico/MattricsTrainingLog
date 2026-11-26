---
id: "TASK-PRODUCTION-DATABASE"
title: "Prepare and recover the production-bound Postgres data"
story: "ST-BACKUP"
status: "planned"
depends_on: ["TASK-HOST-APP-TLS", "TASK-STAGING-SYNTHETIC-RESTORE"]
---

Hosted database connectivity and the local paired restores have passed. Preparing the final schema and approved dataset is a separate operation with its own approval.

## Acceptance criteria

- Select the private migration and restore runner and document the required database roles, TLS configuration, approved data scope, and recoverable snapshots.
- On an authorized target, apply the reviewed migrations with ledger and replay checks. Load the approved history and reconcile source identities, manual decisions, checkpoints, template links, catalog pairing, and dashboard totals.
- Rehearse backup and isolated restore using the release candidate, including verification of the retained Sheets rollback configuration. Keep private database and catalog evidence outside Git and the planning artifacts.
