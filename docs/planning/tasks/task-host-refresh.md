---
id: "TASK-HOST-REFRESH"
title: "Verify the full hosted Garmin and Hevy refresh"
story: "ST-HOST-RUNTIME"
status: "planned"
depends_on: ["TASK-HOST-APP-TLS"]
---

Use the approved private Mattwarden path and an isolated configured database. Bounded hosted outbound calls and FIT download already passed.

## Acceptance criteria

- Exercise the release's private FIT decoder from hosted web PHP using a bounded positive control, and verify the recorded workout through the authenticated app.
- Verify cold app-open and reload timing, paging, overlapping runs, timeouts and retries, stable workout and child IDs, and per-source status and cursor behavior. Record the practical request limit and restore the temporary probe surface after the rehearsal.

## Local rehearsal · 2026-11-25

The disposable Postgres suite verified that a failed Hevy import rolls back the batch and preserves its page checkpoint. Synthetic pagination reached all 1,001 workouts over successive bounded runs. Hosted timing and behavior with real provider data still need verification. [Review fixes report](../../slices/main-review-fixes-report.md)
