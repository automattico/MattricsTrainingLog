---
id: "TASK-HOST-APP-TLS"
title: "Enforce verified TLS in the application Postgres connection"
story: "ST-HOST-RUNTIME"
status: "planned"
depends_on: ["TASK-HOST-POSTGRES-TLS"]
---

The hosted probe proved that the server accepts a connection with the pinned CA and rejects a mismatched hostname. The application connection builders currently construct PDO DSNs from host, port, and database without enforcing either check.

## Acceptance criteria

- Require certificate and hostname verification for hosted application reads and writers, including the connection used by app-open refresh. Keep the trust material in private configuration and preserve the loopback development and staging workflows.
- Exercise the real application connection with the correct hostname and a controlled wrong-hostname test. Verify safe error handling and keep connection details private.
