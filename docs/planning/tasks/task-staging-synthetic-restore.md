---
id: "TASK-STAGING-SYNTHETIC-RESTORE"
title: "Rehearse synthetic recovery on the local staging cluster"
story: "ST-BACKUP"
status: "verified"
---

Run the staging recovery rehearsal with the bundled synthetic workouts. This checks the procedure ahead of the full history recovery gate.

## Acceptance criteria

- Apply and replay the baseline on isolated native staging, then pass the synthetic importer integration suite.
- Restore a staging archive into a new local database and match counts, source links, exercise templates, and set values.

## Evidence

- 2026-11-23 — Added the staging and restore commands. The session could not create the private cluster directory. — [Database environment validation](../../slices/database-environments-report.md#validation)
- 2026-11-23 — Provisioned isolated staging, applied and replayed the baseline, and passed all 22 synthetic importer and API assertions. Restored into a separate database and matched counts, link targets, templates, set values, and canonical row fingerprints. — [Database environment validation](../../slices/database-environments-report.md#validation)
