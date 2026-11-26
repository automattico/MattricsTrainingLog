---
id: "ST-POSTGRES"
title: "Bootstrap private Postgres canonical storage"
feature: "FEAT-PERSISTENCE"
milestone: "MS-V0-1"
activity: "ACT-HISTORY"
order: 10
status: "verified"
scope: "required"
requirements: ["REQ-PERSISTENCE", "REQ-RECOVERY"]
client:
  include: true
  title: "Set up canonical workout storage"
  summary: "Store workouts and source history in a dedicated database."
  acceptance:
    - "Set up an empty database and round-trip a synthetic workout with its source information."
---

Review Foundation as historical reference. Provide versioned migrations and private local tooling for activities, source records, strength details, and import runs. Preserve the live application read path.

## Acceptance criteria

- Apply versioned migrations to an empty private local database and record the applied version.
- Store one synthetic activity with source identity, original timestamp and units, normalized values, and import provenance.
- Reapply the migration command safely and verify uniqueness for provider, account, and external record ID.

## Evidence

- 2026-11-23 — Applied the baseline to fresh native Postgres and verified its version, checksum, and replay. — [Database and migrations](../../slices/local-postgres-synthetic-import-report.md#database-and-migrations)
- 2026-11-23 — Imported synthetic Garmin and Hevy workouts, checked preserved source information and normalized values, and verified readback through the dashboard API. — [Import and API readback](../../slices/local-postgres-synthetic-import-report.md#import-and-api-readback)
- 2026-11-23 — Verified provider/account uniqueness, stable IDs, edited replay, exact source links, ambiguous separation, dry-run isolation, and failed-batch rollback. — [Replay and matching](../../slices/local-postgres-synthetic-import-report.md#replay-and-matching)
