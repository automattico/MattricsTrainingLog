---
id: "TASK-CUTOVER-CATALOG"
title: "Promote reconciled exercise and activity catalogs"
story: "ST-CUTOVER"
status: "planned"
blocker: "Approve and rehearse an exact production catalog maintenance path. The production host rejected SSH command execution, and the code deploy preserves live catalogs."
---

Follow the [v1.0 catalog promotion procedure](../milestones/ms-v1-0.md#required-production-catalog-promotion). The Forearms code is already deployed. Production catalog promotion is still pending. [Staging reconciliation evidence](../../slices/forearms-fatigue-report.md#staging-catalog-reconciliation)

## Acceptance criteria

- Approve and rehearse the production apply and restore on disposable catalogs before cutover.
- Back up both active production catalogs, reconcile current production and staging, and validate the resulting records, aliases, weights, and template links. Recheck source checksums immediately before the approved apply.
- Keep routine catalog edits in production. Record migration decisions once, including proposals saved in the parity preview, and rehearse a guarded apply that preserves unrelated live edits and stops on an overlapping edit while catalog writes are serialized.
- Compare both live catalogs with the approved candidate and record counts, checksum matches, and authenticated Exercises and Muscle Fatigue Map results. Leave the task planned or in progress until production evidence is recorded.
