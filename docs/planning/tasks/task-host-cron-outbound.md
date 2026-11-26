---
id: "TASK-HOST-CRON-OUTBOUND"
title: "Check provider egress and bounded request timing"
story: "ST-HOST-RUNTIME"
status: "verified"
depends_on: ["ST-HOST-PATH"]
---

Use harmless checks on the proposed host.

## Acceptance criteria

- Verify Garmin and Hevy egress and bounded probe request timing from hosted PHP. The parent runtime story tracks full app-open refresh timing.

## Evidence

- 2026-11-23 — A hosted Hevy connection test passed. — [Hevy REST preflight](../../slices/hevy-rest-preflight-report.md#verification)
- 2026-11-23 — The runtime rehearsal recorded the scheduling constraint and the remaining hosted provider checks. — [Runtime rehearsal](../../slices/v1-runtime-recovery-report.md#runtime)
- 2026-11-25 — Hosted Hevy and both Garmin controls passed, including a Garmin FIT download. The deployed decoder file was absent. A scheduled Strava-to-Sheets execution did not call Postgres cleanup. — [Hosted probe and Make results](../../slices/v1-hosted-verification-report.md)
- 2026-11-25 — The owner deferred cache cleanup for the future Strava-to-Postgres path. — [Make cleanup decision](../../slices/v1-hosted-verification-report.md#make-cleanup)
