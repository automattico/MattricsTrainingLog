# Slice: Verify Hevy Header Recognition Against Runtime Imports

Read first:
- `AGENTS.md`
- `docs/architecture-next.md`
- `docs/codex-framework.md`
- `docs/implementation-progress.md`
- `docs/slices/hevy-first-line-recognition-report.md`

Goal: confirm that real English and German Hevy-attributed activities parse correctly after the first-line matcher change, without exposing private workout descriptions.

Context: JavaScript, PHP import, and canonical-read deduplication now recognize standalone Hevy brand terms on the first non-empty description line. Synthetic regression tests and the production gate pass. Existing canonical child rows were not rewritten.

In scope:
- With explicit access and release approval, verify the matcher against a small, privacy-preserving set of runtime examples and confirm the first exercise, set count, and deduplication behavior.
- Determine whether previously imported canonical child rows require a targeted, idempotent re-import; document evidence and a rollback path before changing stored data.
- Run relevant validation and report the outcome without printing raw workout descriptions.

Out of scope:
- Changing authentication, connector credentials, or workout parsing rules without observed evidence.
- Deploying or modifying production data without explicit approval.

Security constraints:
- Do not read, print, commit, or expose secrets or raw health exports.
- Keep private health data and runtime state outside `public/`.
- Keep Mattwarden as the sole authentication and session boundary.

Validation:
- `./scripts/prod-gate.sh` plus privacy-preserving runtime checks for recognized headers, first exercise, and deduplication.
- If a re-import is approved, verify counts and rollback readiness before and after it.

Completion requirements:
- Update `docs/implementation-progress.md`.
- Add a slice report using `docs/templates/slice-report.md`.
- Add the next slice prompt using `docs/templates/next-slice-prompt.md`.
