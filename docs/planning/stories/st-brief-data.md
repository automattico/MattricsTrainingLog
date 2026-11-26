---
id: "ST-BRIEF-DATA"
title: "Define the allowed 10-day brief data set"
feature: "FEAT-BRIEF"
milestone: "MS-V1-1"
activity: "ACT-PLAN"
order: 10
status: "planned"
scope: "required"
requirements: ["REQ-BRIEF"]
depends_on: ["ST-PARITY"]
---

Check provider terms and privacy settings for the intended use. Exclude Strava API-derived rows from AI inputs. Keep authorized source provenance in the selection rule.

## Acceptance criteria

- Demonstrate an allowlist of eligible data fields and a fixture that omits excluded sources while flagging gaps.
