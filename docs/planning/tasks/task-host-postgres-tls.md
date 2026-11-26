---
id: "TASK-HOST-POSTGRES-TLS"
title: "Verify Hetzner Postgres TLS from the Mac"
story: "ST-HOST-RUNTIME"
status: "verified"
---

Verify the hosted database from the Mac after the owner enables Require SSL and confirms readiness. Hosted PHP and scheduler checks remain separate parts of the host runtime story.

## Acceptance criteria

- Run read-only SELECT 1 with the documented hostname, Hetzner CA, and full server verification.
- Record success or the specific failure without exposing credentials.

## Evidence

- 2026-11-23 — Added the read-only probe. Hosted verification awaits owner confirmation. — [Database environment validation](../../slices/database-environments-report.md#validation)
- 2026-11-23 — Read-only `SELECT 1` passed from the Mac on port 5432. libpq verified the certificate and hostname with Hetzner's public CA and negotiated TLS 1.3 using `TLS_AES_256_GCM_SHA384`. — [Database environment validation](../../slices/database-environments-report.md#validation)
