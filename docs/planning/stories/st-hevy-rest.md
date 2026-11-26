---
id: "ST-HEVY-REST"
title: "Verify backend Hevy REST access"
feature: "FEAT-PREFLIGHT"
milestone: "MS-M0"
activity: "ACT-CONNECT"
order: 30
status: "verified"
scope: "required"
requirements: ["REQ-PREFLIGHT", "REQ-EXERCISE"]
---

Use an authorized private key and a read-only sample. The earlier Hevy app connector test supplied a sample of template IDs and structured sets through that interface. The separate hosting preflight still covers CLI access, database connectivity, and scheduling.

## Acceptance criteria

- Confirm that the backend REST API can page workouts and return stable workout and exercise-template identities with structured sets.
- Check ID behavior across a rename and display language where practical. Record observed behavior and unresolved cases without account details.

## Evidence

- 2026-11-23 — The owner confirmed a successful hosted connector test with a one-workout REST request while sync was paused. The local read-only REST sample verified pagination and structured sets, with workout and exercise template IDs matching between list and detail responses. — [Hevy REST preflight](../../slices/hevy-rest-preflight-report.md#verification)
- 2026-11-23 — A synthetic rename preserved the local catalog mapping. Provider-side template ID behavior across a real rename or language change remains open. — [Hevy exercise identity review](../../slices/hevy-exercise-identity-report.md#decisions-and-remaining-verification)
- 2026-11-25 — Three existing German workout labels and their English catalog equivalents had identical template IDs in the Hevy REST detail responses. An in-app rename and an app language setting change were outside this read-only comparison. — [Hevy REST display-language verification](../../slices/hevy-rest-display-language-report.md#verification)
