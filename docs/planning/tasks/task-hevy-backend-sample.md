---
id: "TASK-HEVY-BACKEND-SAMPLE"
title: "Capture read-only REST verification"
story: "ST-HEVY-REST"
status: "verified"
---

Use approved private credential handling and record only redacted outcome details.

## Acceptance criteria

- Verify a paged workout response and template identifiers with structured sets.

## Evidence

- 2026-11-23 — Two REST pages returned four distinct workouts. All 30 exercise entries had template IDs and all 96 sets included an index and a type. The first workout and its exercise template IDs matched the detail response. — [Hevy REST preflight report](../../slices/hevy-rest-preflight-report.md#verification)
