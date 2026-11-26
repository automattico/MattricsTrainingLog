---
id: "REQ-RECOVERY"
title: "Verify backup, restore, and rollback"
---

Keep imported history and review decisions recoverable before changing production reads.

## Acceptance criteria

- A backup restores into a separate database. Samples, counts, source links, and dashboard reads reconcile. The old read path can be restored during observation.
