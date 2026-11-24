# Forearms fatigue

Date 2026-11-24 · Branch `main`

## Changes

- Added TeamBuildr `3.0.7` forearm shapes to both views, with one Forearms score in the fatigue map and one involvement control in Exercise Manager.
- Exercise indexing supplies a weight when the local catalog lacks one. Explicit weights, including zero, remain authoritative. A Curl entry can use its merged Hammer Curl alias. The default 48-hour base half-life and 2.5 normalization load are model estimates.
- The exercise repository and AI schema accept the new weight. The existing set-load threshold still governs other muscles and systemic fatigue in multi-muscle exercises.

## Verification

- Downloaded the 37-record production exercise catalog over read-only SFTP with a pinned host key. Rehearsed on a disposable copy alongside the dev and linked-history preview catalogs. All records, aliases, template links, and activity mappings survived prefill, and source file bytes matched. Verified positive and zero saves on the disposable copies.
- Passed `./scripts/prod-gate.sh` and `./scripts/local-postgres.sh --staging test`. The staging cluster used its scratch database and linked-workout JSON catalog route. The staging site had no standalone catalog file.
- Headless Chrome parsed all 14 forearm paths across the four highlights and confirmed their geometry fell within the expected arm bounds. JavaScript tests cover the shared tooltip, editor preview, Soccer activity mapping, forearm decay, and stimulus parity.

## Security and release

Used the configured SSH identity and read only the SFTP settings needed from `.env.local`. Kept the private catalog outside Git with owner-only permissions. No credentials were printed or modified. No workout export was copied and no production writes were made.

The checkout already contained unrelated changes. Forearms work is uncommitted, unpushed, and undeployed. The local deploy environment file has an unmatched quote that prevents the deploy script from sourcing it. The read-only snapshot used the SFTP settings directly.

## Staging catalog reconciliation

Merged the latest production snapshots and the linked history preview catalog into 38 exercises and 11 activity types. Kept all 37 production exercise IDs and existing muscle weights. Five exercises already covered by production merges retained their production targets. Spinning, Padel, and missing exact aliases carried forward from staging. Both local staging catalog locations have the verified copies and use the Forearms prefill. Original snapshots and the validated promotion candidates are retained in an owner-only release bundle outside Git. The production catalog promotion is pending an approved release.

[Next slice prompt](next-forearms-production-verification-prompt.md)
