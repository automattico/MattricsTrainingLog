---
id: "TASK-HEVY-RENAME"
title: "Compare exercise identifiers across display changes"
story: "ST-HEVY-REST"
status: "verified"
depends_on: ["TASK-HEVY-BACKEND-SAMPLE"]
---

Compare stable IDs and names through an authorized test when practical.

## Acceptance criteria

- Record whether a rename or display language affects the returned identifier.

## Evidence

- 2026-11-23 — Provider template IDs matched across REST list and detail for the sampled workout. The sample included neither a real rename nor a language change. A read-only comparison still needs a suitable example. — [Hevy REST preflight](../../slices/hevy-rest-preflight-report.md#verification)
- 2026-11-25 — Three built-in exercises retained the same template ID across German workout titles and English catalog titles. Both detail endpoints confirmed the comparison. This verifies the display-language example. Rename behavior remains untested. — [Hevy REST display-language verification](../../slices/hevy-rest-display-language-report.md#verification)
