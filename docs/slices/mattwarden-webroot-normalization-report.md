# Slice Report: Mattwarden parent-webroot normalization

Date: 2026-09-23
Branch: `mattwarden`

## Summary

Normalized the Hetzner `mattrics` subdomain from the accidental nested target `public_html/mattrics/public` to the intended parent `public_html/mattrics`. Every active target remained a two-file Mattwarden gate during the transition. Application content and private state under `sites/mattrics` were not moved.

## Procedure and outcome

1. Prepared `public_html/mattrics-staging` with only Mattwarden's `.htaccess` and `index.php`.
2. Changed the konsoleH target from `mattrics/public` to `mattrics-staging`.
3. Verified logged-out `/` returned 401 HTML, `/api/data` returned `401 {"error":"Authentication required."}`, and the signed-in dashboard loaded.
4. Moved the inactive nested gate to `sites/mattrics/rollback-docroot-nested-20260923/public`.
5. Confirmed `public_html/mattrics` contained exactly `.htaccess` and `index.php`.
6. Changed the konsoleH target from `mattrics-staging` to `mattrics`.
7. Repeated the logged-out checks and confirmed the signed-in dashboard and logout link loaded.
8. Changed Mattwarden's ignored `MW_SITES` entry to `mattrics:public_html/mattrics` and ran Mattwarden's deploy.

## Final remote layout

```text
public_html/mattrics/
├── .htaccess
└── index.php

sites/mattrics/rollback-private-pre-cutover/  # protected non-auth data only
```

The deployed Mattrics content remains in `sites/mattrics/{public,api,lib,private}`. `MATTRICS_REMOTE_DIR` remains `sites/mattrics`.

## Validation

Final logged-out checks:

```text
ROOT_STATUS HTTP/2 401
ROOT_CONTENT_TYPE content-type: text/html; charset=utf-8
ROOT_LOGIN_MARKER present
API_STATUS HTTP/2 401
API_CONTENT_TYPE content-type: application/json; charset=utf-8
API_BODY {"error":"Authentication required."}
```

Mattwarden deploy:

```text
Smoke test passed for https://brand.mwieland.com
Smoke test passed for https://matlas.mwieland.com
Smoke test passed for https://mattrics.mwieland.com
Deploy finished.
```

The authenticated Firefox session reloaded the Mattrics dashboard successfully after the final deploy.

The final Mattrics checkout validation reported `Production gate passed.` Three optional Foundation/Postgres integration blocks skipped because the local service at `127.0.0.1:5433` was not running; those checks are unrelated to Hetzner production. This validation included the separately owned dirty Pádel/detail-workout changes and was not used to authorize an application deploy.

## Cleanup follow-up

The operator explicitly chose to skip AI verification and proceed with legacy/staging cleanup. `/mattrics-private` and `/mattrics-lib` were already absent. The first staging deletion exposed that the konsoleH row had not actually saved the parent target: `/` and `/api/data` returned 404. The two-file staging gate was immediately recreated from the verified parent copies, restoring the expected 401 responses.

After the operator corrected the konsoleH row, staging was renamed to `mattrics-staging-probe-20260923`. Both public checks continued to return the correct Mattwarden 401 responses, proving that `public_html/mattrics` was active. The probe directory and `sites/mattrics/rollback-docroot-nested-20260923` were then deleted. Final inventory confirmed all four authorized cleanup targets absent and the protected private rollback present.

Final checks:

```text
ROOT_STATUS HTTP/2 401
ROOT_CONTENT_TYPE content-type: text/html; charset=utf-8
API_STATUS HTTP/2 401
API_CONTENT_TYPE content-type: application/json; charset=utf-8
API_BODY {"error":"Authentication required."}
```

## Security and rollback

- No private data, API key, session cookie, or training payload was copied or printed.
- Pinned-host, SSH-key-only SFTP was used for remote inventories and the recoverable gate move.
- The temporary staging gate and nested-shim copy were removed only after the reversible rename proof.
- The rejected Anthropic key remains a known, explicitly deferred feature issue. The protected non-auth private rollback remains available.

## Git and deployment state

- Mattrics working tree: documentation slice plus separately owned Pádel/detail-workout changes; no combined commit or application deploy was made.
- Mattwarden tracked working tree: clean; its ignored `.env.local` mapping now targets the parent.
- Push: none.
- Mattwarden deploy: complete.
- Mattrics application deploy: not run; no product code or private state changed during normalization.

## Next slice

Use `next-mattwarden-finalization-prompt.md` to separate the dirty checkout and finalize the migration branch. Workout-AI key replacement is an optional future task.
