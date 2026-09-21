# Next slice: operator-assisted Mattwarden cut-over

Read first: `AGENTS.md`, `docs/architecture-next.md`, `docs/codex-framework.md`, `docs/implementation-progress.md`, and `docs/mattwarden-migration.md`.

Goal: perform and verify the production cut-over in the runbook's exact order, with the operator present for the production data move, docroot replacement, and passkey verification.

In scope: recheck the merged Mattwarden PHP-app mode and mattrics site entry; configure `MW_SITES` and the smoke URL; verify deploy prerequisites; then, after explicit approval and the operator's data move, run the Mattrics deploy, coordinate docroot emptying and Mattwarden deploy, and verify authenticated product behavior. Record results without private payloads or credentials.

Out of scope: changing Foundation/Postgres runtime, reintroducing application auth, deploying before the production data move, deleting old production data before logged-in verification, merging or pushing without explicit approval.

Security constraints: do not print or commit secrets, credentials, raw health data, or `.env.local`; use pinned SSH-key SFTP only. Treat the step-2 data move as a hard prerequisite: deploying first would seed defaults under `--only-missing` and obstruct the intended move.

Validation: run `./scripts/prod-gate.sh` before deploy; verify unauthenticated 401 responses and authenticated dashboard, data, settings, connectors, AI, and logout after the gate deploy. Do not claim production readiness from synthetic local data.

Completion: update `docs/implementation-progress.md`, add a cut-over report and subsequent prompt, report git/commit/push/deploy state, and request explicit approval before merge/push.
