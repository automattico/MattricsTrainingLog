# Slice report · Database environments

23 November 2026 · main

## Summary

Verified the native dev and staging profiles, rehearsed synthetic recovery, and completed the read-only Hetzner TLS probe from the Mac. The [database environments runbook](../database-environments.md) records the targets and exact rerun commands.

## Validation

| Check | Observed result |
|---|---|
| Dev on port 55432 | Both migration invocations reported no pending migrations. All 22 synthetic importer and API assertions passed in a fresh scratch database. |
| Staging on port 55434 | Created the private Application Support cluster. Applied `001_workouts.sql`, replayed with no pending migrations, ran the dry run and import, and passed all 22 integration assertions. Stopped and restarted the cluster and confirmed no pending migrations. |
| Staging recovery | Restored a custom-format dump into a separate database. Confirmed two workouts, two source records, two source links, two exercises, and three sets. Compared counts and row fingerprints for every canonical-schema table. The restored database and private dump were retained. |
| Hosted PostgreSQL on port 5432 | After the owner configured the connection settings, read-only `SELECT 1` returned `1`. libpq verified the server certificate and hostname with Hetzner's published CA and negotiated TLS 1.3 with `TLS_AES_256_GCM_SHA384`. This verifies the Mac connection. |
| Source and application gate | `scripts/prod-gate.sh` passed the source guard, lints, local HTTP router, and PHP and JavaScript suites. |
| Planning report | The validator and report fixture passed. Development and staging previews were rebuilt. See the [planning workflow](../planning/README.md) for rerun commands. |

## Files changed and decisions

- Extended the existing launcher with `--staging`, preserving its default dev target. Staging uses the available port 55434 and a private Application Support directory.
- Added the synthetic recovery check and the production TLS probe. A successful restore is retained for inspection in a new database on the staging cluster.
- Moved the restore-check SQL to `tests/postgres/` after the migration runner discovered it in the versioned migration directory and PostgreSQL rejected its `psql` commands. Both environments were rerun after the fix.
- The TLS probe now loads the five connection values as literal text. The first invocation stopped before connecting because the shell interpreted characters in the unquoted password. The same settings worked on rerun and the local environment file was left as entered.
- Updated the runbook, architecture brief, implementation progress, and backlog.

## Security and Git state

The probe loaded the hosted settings locally and reports status without exposing credentials. Hetzner's public CA was used from a temporary directory outside Git. No private exports, database dumps, or certificates were added to Git. Existing changes remain in the working tree. No commit, push, or deployment took place.

## Next slice

[Continue hosted runtime checks](next-database-environments-verification-prompt.md)
