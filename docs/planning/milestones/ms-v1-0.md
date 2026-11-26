---
id: "MS-V1-0"
title: "v1.0 · Direct sync and cutover"
order: 30
client:
  include: true
  title: "v1.0 · Direct sync and cutover"
  summary: "Verify workout refresh, exercise catalog transfer, and the Make-first dashboard transition."
---

## Production readiness · 25 November 2026

The live dashboard still reads Sheets through Apps Script. The Postgres history, duplicate review, and direct refresh are local or isolated rehearsals. The hosted probe verified PHP, database TLS, provider access, and FIT download. **Production code deployment and the dashboard read switch are separate approval points.**

- **Next, finish hosted refresh verification.** The shared application connection passed certificate and hostname verification on a disposable server. Repeat the controls on an approved isolated Mattwarden path, decode a Garmin FIT file with the release's private decoder, and verify full authenticated Garmin and Hevy refresh. Cover cold open, reload, paging, timeout, retries, simultaneous requests, and the actual web request budget. Track this work in `TASK-HOST-APP-TLS` and `TASK-HOST-REFRESH`.
- **Prepare the production database and recovery.** Choose the authorized private runner, apply and record the reviewed migration ledger, and load and reconcile approved history and review decisions. Rehearse recovery of the production-bound database, both catalogs, and read configuration to an isolated target. The hosted connectivity probe and local restores cover separate parts of this work.
- **Prove Strava delivery.** Keep the Make-to-Sheets branch and cursor live. Verify an older edit and a late arrival by stable Strava ID, paging, failed-batch replay, and durable checkpoint behavior in the chosen handoff. Approve the destination and allowed retention before enabling a Postgres writer. The owner deferred scheduled idle cleanup while the temporary cache is unused.
- **Prepare and approve the release candidate.** Reconcile the dirty local branch with current upstream, review and record the intended revision, and rerun the application and synthetic Postgres gates from it. Verify deployment prerequisites with pinned key-only SFTP. The code mirror includes the review endpoint and FIT decoder. Runtime data and config require their own approved operations.
- **Promote the catalogs and resolve or explicitly accept the fatigue gap.** Four recent weeks match on session counts, duration, and distance. The largest fatigue gaps were 1.20 systemic load and 47 regional points. Five exercise decisions may wait for production Exercises. Present the residual gap and its effects at cutover approval, and complete both catalog checks before switching reads.
- **Switch and observe.** Obtain explicit approval for each production operation. After the code deploy, verify the managed tree and authenticated dashboard. At the approved read switch, confirm Postgres is serving expected data and demonstrate rollback to Sheets. Keep Make and Sheets available during observation. Use the proposed four weekly comparisons for sign-off and obtain separate approval before retiring either legacy component.

Direct Concept2, the deferred workout AI credential repair, the workout brief, and Health Data do not gate v1.0.

## Required production catalog promotion

Complete [TASK-CUTOVER-CATALOG](../tasks/task-cutover-catalog.md) before switching dashboard reads or beginning observation. This covers the active exercise and activity-type catalogs. The code deploy uploads private seed files only when they are missing, so the catalog promotion is a separate production operation. [Application deploy rules](../../DEPLOY.md)

Production Exercises is the sole editor for routine catalog changes. The history preview is read-only for catalog edits. Catalog saves in the parity preview are available for rehearsal. Record each migration decision once with the exercise identity, intended change and production baseline. The five remaining owner decisions can wait for production Exercises. Include the measured fatigue gap in the cutover approval review.

1. Confirm the approved production catalog maintenance path and rehearse it against disposable files before cutover. The Forearms release confirmed pinned SFTP reads and found that the host rejects SSH command execution. Obtain approval for any exception to the existing runtime-file deployment rules before scheduling cutover.
2. Take fresh owner-only backups of both active production catalogs and capture the reviewed staging versions. Record checksums and counts in the private release bundle. Keep the files outside Git, the application upload tree, and the generated planning reports.
3. Reconcile against those fresh snapshots. Preserve production IDs, merge targets, explicit weights including zero, and template links. Carry forward reviewed staging-only additions and aliases without duplicating merged records. Validate both catalogs and rehearse the apply and restore on a disposable copy. The previous candidate had 38 exercises and 11 activity types. Use the newly reconciled counts if either side has changed.
4. After code deployment and before switching dashboard reads, use an approved maintenance path that calls the shared catalog promotion routine under the same lock as the web editor. The routine takes the reviewed pair, checks both live SHA-256 digests under the lock, backs up the live pair, and verifies the published files. A changed baseline stops the apply. Reconcile against the new live copy, carrying unrelated changes forward and reviewing only overlaps. Retain the verified backups through observation. The CLI in `scripts/catalog-promotion.php` supports local rehearsal of the same routine.
5. Check that both live catalogs match the approved candidate and verify the authenticated Exercises and Muscle Fatigue Map views. Confirm production merge targets and saved weights, staging additions such as Spinning and Padel, and Forearms prefill. If a catalog fails verification, pause the cutover and use the rehearsed restore path. Preserve any edits saved after the backup when reconciling a restore.

Record the production verification under [CHECK-V1-0-CUTOVER](../checks/check-v1-0-cutover.md). Keep the check open until both catalogs pass. [Staging reconciliation evidence](../../slices/forearms-fatigue-report.md#staging-catalog-reconciliation)
