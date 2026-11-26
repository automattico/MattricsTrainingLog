---
id: "REQ-PERSISTENCE"
title: "Persist canonical workouts in Postgres"
---

Keep provider source identity and provenance behind the existing authenticated application boundary.

## Acceptance criteria

- Versioned migrations bootstrap an empty private database. A direct workout reaches the current dashboard shape. Repeat and edited inputs converge without duplicate workouts.
