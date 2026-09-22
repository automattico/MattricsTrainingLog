# Hetzner private layout

The observed production layout as of 2026-09-22 is:

```text
/usr/home/mwiela/
├── public_html/mattrics/
│   ├── .htaccess                 # extra parent shim; not the configured webroot
│   ├── index.php
│   └── public/                   # active Hetzner webroot
│       ├── .htaccess             # installed by Mattwarden
│       └── index.php             # installed by Mattwarden
└── sites/mattrics/
    ├── public/                   # Mattrics static shell/assets
    ├── api/                      # six endpoint scripts
    ├── lib/                      # shared PHP modules
    ├── private/                  # config.php and runtime state
    └── rollback-private-pre-cutover/  # protected non-auth migration copy
```

Mattrics never deploys to `public_html`. Mattwarden's `MW_SITES` currently targets `mattrics:public_html/mattrics/public`. The parent shim is inert while the host points at the nested directory. Changing the Hetzner webroot to the planned parent directory requires a separate coordinated change; after that, remove the nested shim directory so the parent again contains only two files. `deploy.sh` requires the relative SFTP target `sites/mattrics`, SSH-key authentication, and the committed pinned host keys.

Private runtime files are readable/writable only by the account. Directories use mode 700 and files use mode 600. The gate is the only request path into application code.

See `docs/mattwarden-migration.md` for the one-time move from the legacy paths and the mandatory cut-over order.
