---
id: "ST-HOST-RUNTIME"
title: "Probe the proposed private production runtime"
feature: "FEAT-PREFLIGHT"
milestone: "MS-M0"
activity: "ACT-OPERATE"
order: 20
status: "in_progress"
scope: "required"
requirements: ["REQ-PREFLIGHT", "REQ-SYNC"]
depends_on: ["ST-HOST-PATH"]
blocker: "The hosted probe passed. The application still needs verified database TLS, hosted Garmin FIT decoding, and full authenticated app-open refresh."
---

Validate the chosen host for authenticated web requests and Postgres. Verify CLI where available. The owner deferred scheduled cleanup for the future temporary Strava Postgres cache. The current Strava-to-Sheets workflow does not use that cache.

## Acceptance criteria

- Verify web PHP and pdo_pgsql, and run a host-side verify-full TLS connection with SELECT 1. Check CLI PHP where available.
- Verify that the actual application read and write connections enforce the same certificate and hostname checks.
- Confirm Garmin and Hevy outbound calls, hosted FIT decoding, and app-open request timing.
- Record readiness or constraints that require another host. Keep all probe and credential details private.

## Evidence

- 2026-11-23 — The Mac passed a read-only Postgres TLS connection with certificate and hostname verification. Hosted PHP, PDO Postgres and host-side TLS still need a private probe. — [Database validation](../../slices/database-environments-report.md#validation)
- 2026-11-23 — The hosted connector passed a single Hevy connection test. — [Hevy REST preflight](../../slices/hevy-rest-preflight-report.md#verification)
- 2026-11-23 — Scheduled execution and the remaining provider reachability checks await a private runner. — [Runtime rehearsal](../../slices/v1-runtime-recovery-report.md#runtime)

- 2026-11-24 — Defined a temporary Mattwarden-protected read-only probe. Hosting changes await explicit owner approval. — [Probe plan](../../slices/next-v1-hosted-verification-prompt.md#temporary-mattwarden-probe-for-owner-approval)
- 2026-11-25 — Web PHP 8.4.28 FPM/FastCGI, `pdo_pgsql` and cURL were available. Hosted PDO configured with `verify-full` and the Mac both received a database authentication failure. Successful host-side TLS and `SELECT 1` evidence remain pending. — [Hosted runtime](../../slices/v1-hosted-verification-report.md#hosted-runtime)
- 2026-11-25 — Hevy returned a valid page in 320 ms total. Garmin returned the expected empty window in 1,779 ms and completed the positive-control list and FIT download in 2,016 ms. The deployed private FIT decoder file was absent. — [Hosted runtime](../../slices/v1-hosted-verification-report.md#hosted-runtime)
- 2026-11-25 — The scheduled Strava-to-Sheets run took 3,771 ms and executed no activity write or Postgres cleanup. No separate cleanup scenario was visible in the connected team. Idle expiry and full app-open refresh timing remain pending. — [Make cleanup](../../slices/v1-hosted-verification-report.md#make-cleanup)
- 2026-11-25 — The read-only retry from the Mac returned `1` over TLS 1.3 with certificate and hostname verification. The hosted retry remains pending. — [Mac retry](../../slices/v1-hosted-verification-report.md#mac-retry)
- 2026-11-25 — Hosted PHP returned `1` in a read-only transaction over `verify-full` TLS 1.3. The same server rejected a deliberately incorrect hostname. The positive check took 29 ms, and the probe and private inputs were removed. — [Hosted Postgres retry](../../slices/v1-hosted-verification-report.md#hosted-postgres-retry)
- 2026-11-25 — The owner deferred idle cleanup and accepted the risk of delayed physical deletion if a temporary Strava Postgres cache is used without scheduled cleanup. Cleanup is tracked with that future delivery path. — [Make cleanup decision](../../slices/v1-hosted-verification-report.md#make-cleanup)
