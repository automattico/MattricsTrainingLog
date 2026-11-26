---
id: "TASK-HOST-ACCESS"
title: "Find the supported private probe route"
story: "ST-HOST-PATH"
status: "verified"
---

Check supported access paths following the rejected shell-key attempt.

## Acceptance criteria

- Record the working private route or an actionable hosting blocker.

## Evidence

- 2026-11-23 — A pinned key-only shell test was denied. The account is legacy Level 1, which excludes interactive SSH and bundled cron jobs. — [Hosting preflight results](../stories/st-host-path.md#preflight-results)
- 2026-11-25 — Pinned SFTP and authenticated Mattwarden web dispatch worked on Level 1. Removed all three temporary files and confirmed the route was absent. — [Hosted access and removal](../../slices/v1-hosted-verification-report.md#host-access)
