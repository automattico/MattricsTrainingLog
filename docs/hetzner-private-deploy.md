# Hetzner private layout

The verified production layout as of 2026-09-23 is:

```text
/usr/home/mwiela/
├── public_html/mattrics/
│   ├── .htaccess                 # active Mattwarden gate
│   └── index.php                 # active Mattwarden shim
└── sites/mattrics/
    ├── public/                   # Mattrics static shell/assets
    ├── api/                      # six endpoint scripts
    ├── lib/                      # shared PHP modules
    ├── private/                  # config.php and runtime state
    └── rollback-private-pre-cutover/  # protected non-auth migration copy
```

Mattrics never deploys to `public_html`. Mattwarden's `MW_SITES` targets `mattrics:public_html/mattrics`. The active parent contains exactly `.htaccess` and `index.php`; application content remains outside every document root under `sites/mattrics`.

## Completed webroot normalization

The intended final webroot is `public_html/mattrics` with only Mattwarden's `.htaccess` and `index.php`. Normalization completed on 2026-09-23 using a gate-only intermediate target so every active target retained the authentication boundary.

1. Prepared `public_html/mattrics-staging` with only byte-verified copies of Mattwarden's `.htaccess` and `index.php`.
2. Changed the existing konsoleH `mattrics` target from `mattrics/public` to `mattrics-staging`. Logged-out `/` returned 401 HTML, `/api/data` returned 401 JSON, and the signed-in dashboard loaded.
3. Moved the now-inert nested shim to `sites/mattrics/rollback-docroot-nested-20260923/public`. Confirmed the future parent contained only `.htaccess` and `index.php`.
4. Changed the same target from `mattrics-staging` to `mattrics`. Repeated the logged-out and signed-in checks successfully.
5. Changed Mattwarden's ignored `MW_SITES` mapping to `mattrics:public_html/mattrics` and ran its deploy. The complete brand, matlas, and mattrics smoke suites passed; a signed-in Mattrics reload also passed.

6. Cleanup was completed after a reversible proof of the final target. The first deletion attempt exposed that konsoleH had not actually saved the parent target; the staging gate was restored immediately and 401 behavior recovered. After the operator corrected the row, `mattrics-staging` was renamed temporarily and the site continued returning the expected 401 responses, proving the parent was active. The staging directory and nested-shim rollback were then removed. Legacy `/mattrics-private` and `/mattrics-lib` were already absent.

At no point did this move application data, change `MATTRICS_REMOTE_DIR`, or put private content in a webroot. The protected non-auth private rollback remains under `sites/mattrics/rollback-private-pre-cutover`. This repository's `deploy.sh` still requires the relative SFTP target `sites/mattrics`, SSH-key authentication, and committed pinned host keys.

Private runtime files are readable/writable only by the account. Directories use mode 700 and files use mode 600. The gate is the only request path into application code.

See `docs/mattwarden-migration.md` for the one-time move from the legacy paths and the mandatory cut-over order.
