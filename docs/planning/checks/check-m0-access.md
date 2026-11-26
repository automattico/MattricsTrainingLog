---
id: "CHECK-M0-ACCESS"
title: "Review backend and hosting readiness"
milestone: "MS-M0"
status: "in_progress"
---

The private hosting path, web PHP, verified Postgres TLS, and bounded Hevy and Garmin requests are evidenced. Close this check after the application connection and full hosted refresh are verified. The chosen v1.0 runtime uses authenticated app-open refresh and the existing Make schedule.

## Acceptance criteria

- Approve evidence for web PHP, the applicable CLI and scheduling constraints, verified application Postgres TLS, provider access, hosted Garmin decoding, app-open refresh, and the Hevy backend REST decision.

## Evidence

- 2026-11-23 — The hosted Hevy connector passed a connection test. The Mac REST sample verified pagination, template IDs and structured sets. Provider-side identity across a real rename or language change remains open. — [Hevy REST preflight](../../slices/hevy-rest-preflight-report.md#verification)
- 2026-11-23 — The Mac passed a read-only Postgres TLS connection with certificate and hostname verification. — [Database validation](../../slices/database-environments-report.md#validation)
- 2026-11-23 — A private host probe still needs to establish PHP and PDO Postgres access, host-side TLS, a scheduled run and the remaining provider reachability. — [Runtime rehearsal](../../slices/v1-runtime-recovery-report.md#runtime)
- 2026-11-25 — The Hevy REST display-language evidence passed. German workout and English catalog titles shared the same template IDs in three detail comparisons. — [Hevy REST display-language verification](../../slices/hevy-rest-display-language-report.md#verification)
- 2026-11-25 — Hosted PHP passed read-only Postgres TLS and the wrong-hostname control. Bounded provider calls and FIT download passed. Full application refresh is still pending. — [Hosted verification](../../slices/v1-hosted-verification-report.md)
