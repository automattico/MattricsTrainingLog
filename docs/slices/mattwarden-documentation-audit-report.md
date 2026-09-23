# Slice Report: Mattwarden documentation audit and webroot preflight

Date: 2026-09-23
Branch: `mattwarden`

## Summary

Reconciled current architecture, deployment, local-development, Strava sync, CSS/module ownership, and in-app AI/data/deployment documentation with the deployed Mattwarden runtime. Added a documentation authority index and a secret-safe Anthropic key-replacement guide. Prepared the gate-only staging target for the Hetzner webroot normalization; the live subdomain target was not changed.

## Findings and changes

- The Strava guide still pointed at deleted browser `public/config.js`; it now describes authenticated `/api/data`, private server config, and the private cache.
- `AGENTS.md` had an obsolete state shape and incomplete script/CSS inventory; those now match `public/index.html`, `core/state.js`, and `main.css`.
- The CSS guide named a removed `fatigue-doc.css` and omitted settings, connectors, and exercise-admin styles.
- The in-app AI guide claimed SSE streaming, temperature 0.7, 1024 output tokens, profile/time/goal inputs, and an abort control. None exist in `api/ai.php` or `ai.js`; the guide now describes the actual one-shot JSON request, 900 output tokens, and current prompt inputs.
- The in-app data/deployment guides now describe the server-side sheet proxy and actual nested vhost webroot.
- Current operating docs link to `docs/anthropic-api-key.md`; historical `docs/slices/` records remain dated evidence, not current runbooks.
- `docs/README.md` now separates current operations and code references from the local/future Foundation runtime and historical slice records.
- Read-only SFTP inventory found only `.htaccess`, `index.php`, and `public/` in `public_html/mattrics`; `public/` contains only Mattwarden `.htaccess` and `index.php`. konsoleH showed the `mattrics` subdomain target as `mattrics/public`.
- A temporary `public_html/mattrics-staging` directory was created with byte-identical copies of the two Mattwarden gate files. It is not active. No private data or live target changed.

## Security check

- Secrets read or modified: no. The Anthropic guide contains no key value. The control-panel observation showed only the directory target.
- Sensitive files touched: no. Only public gate shim files were copied to the inactive staging target.
- Raw/private health data handled: no.
- Public responses expose credentials: no.
- Generated artifacts contain private data: no.

## Validation

- Relative Markdown link audit: `broken_relative_links=0`.
- `node --check` on the three edited in-app documentation sections: passed.
- In-app documentation construction with stubbed template helpers: `docs-sections-render: 3 passed, 0 failed`.
- `git diff --check`: passed.
- `./scripts/prod-gate.sh`: `Production gate passed.` Three optional Foundation/Postgres integration blocks skipped because `127.0.0.1:5433` refused connections.
- Production webroot normalization: staging is prepared; the konsoleH target edits and post-change checks remain pending because Firefox automation did not expose a trustworthy page target for the live form.

## Decisions and known issues

- Do not deploy the in-app documentation until this documentation slice is committed; `deploy.sh` mirrors the full working tree.
- Strict Mattwarden two-file-docroot compliance uses the verified gate-only staging target so both hosting-target changes can retain a working authentication boundary. The existing protected private rollback remains untouched.
- The Anthropic workout key still returns HTTP 401; a replacement must be created in the Claude Console and installed privately.

## Git state

- Working tree: this documentation slice plus separately owned Pádel/detail-workout changes; they must not be swept into this commit or a Mattrics deploy.
- Commit: documentation changes not committed when this report was written.
- Push: none.
- Deploy: production remains on the previously deployed Mattwarden migration; no deployment in this slice.

## Next slice

See `docs/slices/next-mattwarden-webroot-and-key-completion-prompt.md`.
