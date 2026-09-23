# Mattwarden migration and cut-over runbook

## Security outcome

Mattwarden exclusively authenticates Mattrics. The application-owned WebAuthn/passkey implementation, login/register/recovery pages, credential and challenge stores, recovery codes, auth sessions, cookie handling, host-based development bypass, request-derived origin logic, and host-gated diagnostics are removed.

Every endpoint still calls `require_authenticated()` so direct execution fails closed under the supplied contract. Mutations require the Mattwarden same-origin and CSRF guards. Application state is rooted only at `MATTWARDEN_SITE_DIR . '/private'`.

The earlier security-audit documents remain preserved in the two pre-migration commits on `main`. They are removed from this branch because their findings are resolved by deleting the subsystem rather than incrementally hardening it.

## Current production layout (2026-09-23)

The hosting control panel serves the normalized parent `public_html/mattrics` as the webroot. It contains only Mattwarden's `.htaccess` and `index.php`. Application content and private state remain outside every document root. The temporary gate-only staging target, former nested shim, and legacy `/mattrics-private` and `/mattrics-lib` paths are absent; the protected non-auth private migration copy remains outside the webroot.

```text
/usr/home/mwiela/
├── public_html/mattrics/
│   ├── .htaccess                         # active Mattwarden gate
│   └── index.php                         # active Mattwarden shim
└── sites/mattrics/
    ├── public/                           # this repository: static files
    ├── api/                              # this repository: six endpoints
    ├── lib/                              # this repository: shared code
    ├── private/
    │   ├── config.php                    # operator-managed, never overwritten
    │   ├── config.example.php            # only-missing seed
    │   ├── data/*.json                   # production data / only-missing seeds
    │   ├── user-settings.json            # runtime state
    │   ├── cache/                         # runtime state
    │   └── logs                           # runtime state where present
    └── rollback-private-pre-cutover/      # protected non-auth data snapshots
```

## API inventory

| Endpoint | Methods | Same-origin + CSRF |
|---|---|---|
| `/api/session` | GET | No mutation |
| `/api/data` | GET | No mutation |
| `/api/settings` | GET, POST | POST |
| `/api/connectors` | GET, POST | POST |
| `/api/exercises[/<path>]` | GET, POST, PATCH, DELETE | POST, PATCH, DELETE |
| `/api/ai` | POST | POST |

## CSP result

The static shell requires no site-specific CSP exception. All JavaScript and CSS are external and same-origin, fonts are self-hosted, and the generated/static markup has no inline script, event-handler, style block, style attribute, or JavaScript URL. JavaScript CSSOM property updates and SVG presentation attributes remain because the CSP permits them.

## Fixed cut-over order

❗ **Do not run step 3 before step 2.** The application deploy uses `--only-missing` for private seeds. Deploying before production data is moved can create empty/default files and obstruct the intended move because the real targets are then no longer missing.

1. In the Mattwarden repository, confirm PHP-app mode is merged, the `mattrics` entry exists in `sites.php`, and `MW_SITES` includes it.
2. Before any Mattrics deploy, create `/usr/home/mwiela/sites/mattrics/private` (mode 700) and populate it with verified production state. The 2026-09-22 inspection found `/mattrics-private` held older exercise JSON while the live app's `public_html/mattrics/private` held the current settings, cache, log, exercise configs, and unknowns. The live non-auth files were copied to the new private tree (0600) and byte-verified; both live and older external versions were retained in protected `sites/mattrics/rollback-private-pre-cutover`. No auth/passkey state was copied. The legacy source was left in place to keep the old site working until the webroot switch.
3. In this repository, run `./deploy.sh`. It mirrors public/API/library content and uploads only missing private examples/seeds. The real production data must already exist.
4. Empty the old application tree only after the new private tree is verified. On 2026-09-22 the legacy `lib/`, `private/`, and `public/` trees and Basic Auth files were removed from `public_html/mattrics`. This deleted the obsolete passkey credential, challenge, rate-limit, and auth-audit files from the old docroot. The configured webroot was then found to be the nested `public_html/mattrics/public`, so only a new Mattwarden shim and `.htaccess` were installed there.
5. In the Mattwarden repository, run `./deploy.sh` against the configured webroot. The initial cut-over used the then-active nested target; the separately verified 2026-09-23 normalization changed `MW_SITES` to `mattrics:public_html/mattrics`. Verify unauthenticated static paths return the login page with status 401 and `/api/data` returns the JSON authentication error with status 401.
6. Log in with the Mattwarden passkey. Verify the dashboard loads; data, settings, connectors, and logout work. The original runbook also required an AI call; the operator explicitly waived that check on 2026-09-23 after the configured Anthropic key returned 401. Then delete `/usr/home/mwiela/mattrics-private` and `/usr/home/mwiela/mattrics-lib`.
7. Merge branch `mattwarden` into `main` and push only with explicit approval.

## Current runbook status (2026-09-23)

- Implementation and local validation: complete on branch `mattwarden`.
- Mattwarden PHP-app mode/site entry: merged and present in production; local `MW_SITES` and smoke URL include mattrics.
- Production data: current non-auth files copied to `sites/mattrics/private` and byte-verified; protected remote rollback tree exists; obsolete auth state was not copied.
- Mattrics deploy: completed with managed remote-tree verification.
- Document-root cleanup: complete. The active parent webroot contains only Mattwarden `.htaccess` and `index.php`; staging, the former nested shim, `/mattrics-private`, and `/mattrics-lib` are absent.
- Mattwarden deploy: completed against `public_html/mattrics`; brand, matlas, and mattrics unauthenticated smoke suites pass.
- Authenticated verification: passkey sign-in, dashboard, live-sheet refresh, settings save, connectors and exercise views, and the logout confirmation page passed. The operator subsequently reported the other product checks complete and working. The AI workout request remains a known exception: Mattwarden's gate log recorded Anthropic HTTP 401, and a no-generation check of the ignored local key also returned 401. The operator explicitly skipped replacement and successful-workout verification for migration completion. The deployed UI handles non-JSON upstream errors without displaying a JSON parser exception.
- Optional operator action: use `anthropic-api-key.md` if workout AI is intentionally re-enabled later. No new paid AI request was sent during migration completion.
- Webroot normalization: complete. Both konsoleH target changes were verified, the signed-in dashboard reloaded, the final remote tree was inspected, and the app's `sites/mattrics` layout remained unchanged.
- Old `/mattrics-private`, `/mattrics-lib`, staging, and the nested-shim rollback are absent. The protected private rollback remains. Branch commit/merge/push are still pending and must not sweep in separately owned dirty product work.
