# Mattwarden migration and cut-over runbook

## Security outcome

Mattwarden exclusively authenticates Mattrics. The application-owned WebAuthn/passkey implementation, login/register/recovery pages, credential and challenge stores, recovery codes, auth sessions, cookie handling, host-based development bypass, request-derived origin logic, and host-gated diagnostics are removed.

Every endpoint still calls `require_authenticated()` so direct execution fails closed under the supplied contract. Mutations require the Mattwarden same-origin and CSRF guards. Application state is rooted only at `MATTWARDEN_SITE_DIR . '/private'`.

The earlier security-audit documents remain preserved in the two pre-migration commits on `main`. They are removed from this branch because their findings are resolved by deleting the subsystem rather than incrementally hardening it.

## Current production layout (2026-09-22)

The hosting control panel currently serves `public_html/mattrics/public` as the webroot, contrary to the planned parent path. Mattwarden's shim is installed in that nested webroot and its unauthenticated smoke test passes. An extra shim was also installed in the parent before the actual webroot was identified; it is not web-accessible through this virtual host. Do not remove the nested shim until a separately coordinated Hetzner webroot change to the parent has been verified.

```text
/usr/home/mwiela/
├── public_html/mattrics/
│   ├── .htaccess                         # extra parent shim, inert for this vhost
│   ├── index.php
│   └── public/                           # actual configured webroot
│       ├── .htaccess                     # Mattwarden deploy
│       └── index.php                     # Mattwarden deploy
└── sites/mattrics/
    ├── public/                           # this repository: static files
    ├── api/                              # this repository: six endpoints
    ├── lib/                              # this repository: shared code
    ├── private/
        ├── config.php                    # operator-managed, never overwritten
        ├── config.example.php            # only-missing seed
        ├── data/*.json                   # production data / only-missing seeds
        ├── user-settings.json            # runtime state
        ├── cache/                         # runtime state
        └── logs                           # runtime state where present
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
5. In the Mattwarden repository, run `./deploy.sh` with `MW_SITES` targeting the **actual** configured webroot `mattrics:public_html/mattrics/public`. Verify unauthenticated static paths return the login page with status 401 and `/api/data` returns the JSON authentication error with status 401. The first parent-targeted deploy failed its mattrics smoke test with 404; the nested-target deploy and subsequent full smoke suite passed.
6. Log in with the Mattwarden passkey. Verify the dashboard loads; data, settings, connectors, and an AI call work; and the logout link opens `/__mattwarden/logout`. Then delete `/usr/home/mwiela/mattrics-private` and `/usr/home/mwiela/mattrics-lib`.
7. Merge branch `mattwarden` into `main` and push only with explicit approval.

## Current runbook status (2026-09-22)

- Implementation and local validation: complete on branch `mattwarden`.
- Mattwarden PHP-app mode/site entry: merged and present in production; local `MW_SITES` and smoke URL include mattrics.
- Production data: current non-auth files copied to `sites/mattrics/private` and byte-verified; protected remote rollback tree exists; obsolete auth state was not copied.
- Mattrics deploy: completed with managed remote-tree verification.
- Document-root cleanup: old app removed; actual nested webroot contains only Mattwarden `.htaccess` and `index.php`.
- Mattwarden deploy: completed; brand, matlas, and mattrics unauthenticated smoke suites pass.
- Authenticated verification: passkey sign-in, dashboard, live-sheet refresh, settings save, connectors and exercise views, and the logout confirmation page passed. The AI request failed: Mattwarden's gate log recorded Anthropic HTTP 401. A no-generation check of the ignored local key also returned 401. The authorized temporary gate-log copy was deleted after inspection. A tested, deployed UI fix now handles non-JSON upstream errors without displaying a JSON parser exception.
- Operator action: replace `anthropic_api_key` in `/usr/home/mwiela/sites/mattrics/private/config.php` with a valid Anthropic API key. Do not commit or paste the key into a task. After replacement, validate it with a no-generation models request and perform one authenticated AI workout check. No new paid AI request was sent during diagnosis.
- Old `/mattrics-private` and `/mattrics-lib` deletion, optional webroot correction, merge/push: not done while AI verification remains unresolved.
