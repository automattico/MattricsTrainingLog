# Slice Report: Mattwarden production AI diagnosis

Date: 2026-09-22
Branch: `mattwarden`

## Summary

The production AI failure is an Anthropic credential rejection (HTTP 401), not a Mattwarden routing or CSRF failure. The browser's raw JSON parse error was corrected so a non-JSON upstream error page produces a clear service-unavailable message. The tested static change was deployed; a replacement private API key and one successful authenticated AI request remain necessary.

## Files changed

- `public/assets/js/ai.js`: guarded response parsing and safe fallback errors.
- `tests/js/ai-response-tests.js`: six success/error response cases.
- `docs/implementation-progress.md`, `docs/mattwarden-migration.md`, and cut-over report: diagnosis and remaining dependency.
- This report and the next-slice prompt.

## Validation

- Production gate log: one AI-related line recorded upstream HTTP 401; no raw log content or credential was printed. The authorized protected temporary copy was deleted.
- Ignored local Anthropic key: no-generation `GET /v1/models?limit=1` returned HTTP 401; response body and key were not printed.
- `node tests/js/ai-response-tests.js`: `ai-response-tests: 6 passed, 0 failed`.
- `./scripts/prod-gate.sh`: `Production gate passed.` Three optional local Foundation/Postgres blocks skipped because port 5433 refused connections.
- `./deploy.sh`: `Deploy complete. Managed remote trees match the explicit allowlist.` Private config and runtime data were not overwritten.

## Security check

- Secrets read: the local ignored key was loaded in memory for a no-generation status check; its value was never printed or committed.
- Sensitive files modified: no. The gate-log copy was downloaded into 0700 temporary storage with 0600 file mode, then deleted.
- Raw/private health data handled: no.
- Public responses expose credentials: no; the UI shows only a generic error or HTTP status.
- Generated artifacts contain private data: no.

## Decisions and known issues

- Do not retry paid AI generation until the production key has been replaced and independently validated.
- Keep `/mattrics-private`, `/mattrics-lib`, and the protected rollback tree until AI verification passes.
- The nested Hetzner webroot remains a separate hosting-setting issue; current Mattwarden shim and smoke checks pass.

## Git state

- Working tree: this slice's code and documentation pending commit when written.
- Commit: preceding migration work is committed locally on `mattwarden`.
- Push: none.
- Deploy: the AI error-handling change is deployed; the AI feature remains blocked by the rejected private key.

## Next slice

See `docs/slices/next-mattwarden-ai-credential-verification-prompt.md`.
