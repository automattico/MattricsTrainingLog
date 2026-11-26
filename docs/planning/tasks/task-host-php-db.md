---
id: "TASK-HOST-PHP-DB"
title: "Probe private PHP and database access"
story: "ST-HOST-RUNTIME"
status: "verified"
depends_on: ["ST-HOST-PATH"]
---

Probe both runtime surfaces and keep probe output private.

## Acceptance criteria

- Record PHP versions, pdo_pgsql and a successful TLS SELECT 1 or a specific failure.

## Evidence

- 2026-11-23 — The Mac returned `1` over TLS with full server verification. The hosted CLI and web PHP versions, PDO Postgres and a host-side database connection still need evidence. — [Database validation](../../slices/database-environments-report.md#validation)
- 2026-11-25 — Verified PHP 8.4.28 FPM/FastCGI and `pdo_pgsql`. Both the documented Mac probe and hosted PDO received an authentication failure. Hosted CLI is unavailable on Level 1. — [Hosted runtime](../../slices/v1-hosted-verification-report.md#hosted-runtime)
- 2026-11-25 — The Mac retry passed with the current production login. Hosted PDO still needs a successful TLS connection and query. — [Mac retry](../../slices/v1-hosted-verification-report.md#mac-retry)
- 2026-11-25 — PHP 8.4.28 with libpq 15.20 returned `1` in a read-only transaction over verified TLS 1.3. `pg_stat_ssl` confirmed encryption on the same backend, and the wrong-hostname control was rejected. — [Hosted Postgres retry](../../slices/v1-hosted-verification-report.md#hosted-postgres-retry)
