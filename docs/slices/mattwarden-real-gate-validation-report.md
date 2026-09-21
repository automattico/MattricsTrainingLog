# Slice Report: Mattwarden real-gate validation

Date: 2026-09-21  
Branch: `mattwarden`

## Summary

Ran Mattrics in an isolated local container against Mattwarden's merged PHP-app gate, using only synthetic private configuration and seed data. Corrected one time-of-day-sensitive fatigue test assertion: the fully-fresh projection, unlike the trainable-again projection, still distinguishes heavy from light sessions late in the day.

## Files Changed

- `tests/js/exercise-config-tests.js`: assert the intended full-recovery comparison.
- `docs/implementation-progress.md`: record validation and next step.
- This report and `docs/slices/next-mattwarden-cutover-prompt.md`.

## Validation

- Real gate: unauthenticated `/api/session` returned 401 JSON; a virtual passkey enrolled; the static shell loaded with zero browser page errors; authenticated session returned a 64-hex CSRF token; settings and connectors GET returned 200; settings POST returned 403 for missing CSRF and foreign Origin, then 200 for a same-origin valid write with successful readback; exercise PATH_INFO dispatch and `.php` alias worked.
- `/api/data` returned 502 because the synthetic config deliberately names a nonfunctional upstream; production data was not tested.
- `./scripts/prod-gate.sh`: passed; JavaScript suites 117/117 and 38/38. Three optional Foundation/Postgres integration blocks skipped because local Postgres was stopped.
- The isolated container and synthetic temporary files were removed after the check.

## Security Check

- Secrets read or modified: local `.env.local` was sourced for boolean prerequisite checks, so values entered the shell; no values were printed or modified.
- Sensitive files modified: no.
- Raw/private health data handled: no; only repository seed JSON and synthetic settings were used.
- Public credential exposure: none observed.
- Generated artifacts contain private data: no.

## Decisions Made

- Keep production untouched. The application deploy must follow the operator's production-data move, as the fixed runbook requires.
- Mattwarden's local `MW_SITES` and smoke URL configuration still need a mattrics entry before its production deploy; the merged site definition itself is present.

## Known Issues

- A production upstream and AI call cannot be validated with synthetic configuration. They remain step-6 cut-over checks.
- The local-only Foundation/Postgres import schema mismatch remains outside this migration slice.

## Git State

- Working tree: this validation slice was uncommitted when this report was written.
- Commit: prior migration commits remain local; this slice should be committed separately.
- Push: none.
- Deploy: none.

## Next Slice

Operator-assisted Mattwarden cut-over, following `docs/mattwarden-migration.md` in exact order.

## Next Slice Prompt

See `docs/slices/next-mattwarden-cutover-prompt.md`.
