# Hosted runtime verification

25 November 2026 · Europe/Berlin

## Host access

The owner approved the temporary upload, authenticated read-only invocation and removal. Pinned key-only SFTP verified that the three target paths were absent before upload. The in-app browser passkey attempt failed. The existing signed-in Firefox session successfully invoked the probe through Mattwarden. The Level 1 plan’s previously recorded CLI and bundled cron constraints still apply.

After the checks, pinned SFTP confirmed that the endpoint and both private inputs were absent. A fresh authenticated request on the same route returned `Not found.` The probe emitted only component results and timing.

## Hosted runtime

| Check | Result |
| --- | --- |
| Web PHP | PHP 8.4.28, FPM/FastCGI. `pdo_pgsql`, cURL and `proc_open` available. PHP’s execution limit was 60 seconds. The upstream web timeout was not measured. |
| Postgres | PDO used the configured hostname, the pinned [Hetzner CA](https://docs.hetzner.com/managed/databases/postgresql/#encrypted-access) and `sslmode=verify-full`. The database rejected authentication after 21 ms. The Mac rerun with the same stored credentials reproduced the failure. Hosted `SELECT 1`, same-backend TLS status and the wrong-host control remain unverified. The successful November 23 Mac result remains historical. |
| Hevy | HTTP 200 and a valid first workout page. The call took 319 ms, with 320 ms total. |
| Garmin empty window | HTTP 200 for token and list. The October 27–November 23 window was empty as expected. Token 1,366 ms, list 411 ms, total 1,779 ms. |
| Garmin positive control | HTTP 200 for token, nonempty list and FIT download. Token 1,404 ms, list 267 ms, download 342 ms, total 2,016 ms. The deployed decoder file was absent, so decoding remains unverified. |

The timings cover bounded single-page checks. Full app-open cold start, paging, retries and concurrency remain open before hosted refresh can be enabled.

## Make cleanup

The connected team listed four scenarios. Its sole active scenario was the scheduled Strava-to-Sheets flow. The automatic run at 08:00:32 CET succeeded in 3,771 ms. Its four module operations read and updated the Sheet checkpoint, listed Strava activities and filtered Sheet rows. No activity write or Postgres cleanup module executed. [Inspected Make scenario](https://eu2.make.com/1710719/scenarios/8986838)

The owner confirmed that the current flow ends at Google Sheets and deferred scheduled cleanup for the future temporary Strava Postgres cache. Without an independent job, expired records in that cache could remain physically stored during idle periods. The owner accepted that risk for the deferral. Revisit it with the decision to enable the cache. [Retention design](make-retention-report.md)

## Mac retry

On November 25, the read-only Mac retry with the current private configuration returned `1`. libpq verified the certificate and hostname and negotiated TLS 1.3 with `TLS_AES_256_GCM_SHA384`.

## Hosted Postgres retry

After a new owner approval, hosted PHP 8.4.28 FPM/FastCGI with `pdo_pgsql` and libpq 15.20 connected with `sslmode=verify-full` and the pinned Hetzner CA. `SELECT 1` returned `1` in a read-only transaction. The same backend reported TLS 1.3 with `TLS_AES_256_GCM_SHA384` in `pg_stat_ssl`. The positive check took 29 ms. A deliberately incorrect hostname sent to the same server address was rejected for a certificate hostname mismatch. Both checks took 38 ms total.

Pinned SFTP confirmed that the temporary endpoint and both private inputs were removed. A fresh authenticated request in Firefox returned `Not found.`

## Follow-up

Closed [ST-HOST-PATH](../planning/stories/st-host-path.md) and verified the hosted database and provider egress tasks. [ST-HOST-RUNTIME](../planning/stories/st-host-runtime.md) remains open for hosted FIT decoding and full app-open refresh timing. The production database connection builder must enforce the same TLS verification before switching dashboard reads. No application release or cutover took place. [Continuation prompt](next-v1-hosted-runtime-followup-prompt.md)
