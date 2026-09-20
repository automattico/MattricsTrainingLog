# Mattwarden migration and cut-over runbook

## Security outcome

Mattwarden exclusively authenticates Mattrics. The application-owned WebAuthn/passkey implementation, login/register/recovery pages, credential and challenge stores, recovery codes, auth sessions, cookie handling, host-based development bypass, request-derived origin logic, and host-gated diagnostics are removed.

Every endpoint still calls `require_authenticated()` so direct execution fails closed under the supplied contract. Mutations require the Mattwarden same-origin and CSRF guards. Application state is rooted only at `MATTWARDEN_SITE_DIR . '/private'`.

The earlier security-audit documents remain preserved in the two pre-migration commits on `main`. They are removed from this branch because their findings are resolved by deleting the subsystem rather than incrementally hardening it.

## Final production layout

```text
/usr/home/mwiela/
├── public_html/mattrics/
│   ├── .htaccess                         # Mattwarden deploy
│   └── index.php                         # Mattwarden deploy
└── sites/mattrics/
    ├── public/                           # this repository: static files
    ├── api/                              # this repository: six endpoints
    ├── lib/                              # this repository: shared code
    └── private/
        ├── config.php                    # operator-managed, never overwritten
        ├── config.example.php            # only-missing seed
        ├── data/*.json                   # production data / only-missing seeds
        ├── user-settings.json            # runtime state
        ├── cache/                         # runtime state
        └── logs                           # runtime state where present
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
2. Before any Mattrics deploy, the operator uses FileZilla to create `/usr/home/mwiela/sites/mattrics` and `/usr/home/mwiela/sites/mattrics/private` (mode 700), then moves `/usr/home/mwiela/mattrics-private/*` into the new private directory. Keep `config.php`, data JSON, settings, cache, and log data. Delete `passkey-credential.json`, `auth-challenges.json`, `auth-rate-limits.json`, and `auth-audit.log`; do not move them.
3. In this repository, run `./deploy.sh`. It mirrors public/API/library content and uploads only missing private examples/seeds. The real production data must already exist.
4. The operator empties `/usr/home/mwiela/public_html/mattrics`. Nothing from the old application remains.
5. In the Mattwarden repository, run `./deploy.sh` to install only its shim and `.htaccess`. Verify unauthenticated static paths return the login page with status 401 and `/api/data` returns the JSON authentication error with status 401.
6. Log in with the Mattwarden passkey. Verify the dashboard loads; data, settings, connectors, and an AI call work; and the logout link opens `/__mattwarden/logout`. Then delete `/usr/home/mwiela/mattrics-private` and `/usr/home/mwiela/mattrics-lib`.
7. Merge branch `mattwarden` into `main` and push only with explicit approval.

## Current runbook status

- Implementation and local validation: complete on branch `mattwarden`.
- Mattwarden PHP-app mode/site entry: external dependency; confirmation pending.
- Production data move: not started.
- Mattrics deploy: not run.
- Document-root cleanup: not run.
- Mattwarden deploy and authenticated verification: not run.
- Merge/push: not run.
