---
id: "TASK-POSTGRES-FIXTURE"
title: "Round-trip synthetic canonical data"
story: "ST-POSTGRES"
status: "verified"
depends_on: ["TASK-POSTGRES-MIGRATIONS"]
---

Exercise activity, source record, strength and run tracking using synthetic inputs.

## Acceptance criteria

- Assert field fidelity and source uniqueness after repeated writes.

## Evidence

- 2026-11-23 — Synthetic field values and Hevy template IDs survived the Postgres and API round trip. Same-account replay preserved IDs and a separate account could reuse the external ID. — [Integration assertions](../../../tests/postgres/integration.php)
