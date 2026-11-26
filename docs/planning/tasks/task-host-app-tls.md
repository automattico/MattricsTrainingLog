---
id: "TASK-HOST-APP-TLS"
title: "Enforce verified TLS in the application Postgres connection"
story: "ST-HOST-RUNTIME"
status: "in_progress"
depends_on: ["TASK-HOST-POSTGRES-TLS"]
---

The hosted probe proved that the server accepts a connection with the pinned CA and rejects a mismatched hostname. The dashboard, writes, and hosted migration runner now share one connection builder. The hosted application path still needs verification.

## Acceptance criteria

- Require certificate and hostname verification for hosted application reads and writers, including the connection used by app-open refresh. Keep the trust material in private configuration and preserve the loopback development and staging workflows.
- Exercise the real application connection with the correct hostname and a controlled wrong-hostname test. Verify safe error handling and keep connection details private.

## Evidence

- 2026-11-26 — The application connection passed with a synthetic certificate and rejected the wrong hostname on a disposable Postgres server. The same builder applied migrations 001–007 through the private runner and reported zero pending versions on replay. Hosted application evidence remains open. — [Release safeguards](../../slices/cutover-prep-implementation-report.md)
