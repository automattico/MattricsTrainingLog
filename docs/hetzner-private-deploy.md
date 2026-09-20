# Hetzner private layout

The authoritative production layout is:

```text
/usr/home/mwiela/
├── public_html/mattrics/
│   ├── .htaccess                 # installed by Mattwarden
│   └── index.php                 # installed by Mattwarden
└── sites/mattrics/
    ├── public/                   # Mattrics static shell/assets
    ├── api/                      # six endpoint scripts
    ├── lib/                      # shared PHP modules
    └── private/                  # config.php and runtime state
```

Mattrics never deploys to `public_html`. `deploy.sh` requires the relative SFTP target `sites/mattrics`, SSH-key authentication, and the committed pinned host keys.

Private runtime files are readable/writable only by the account. Directories use mode 700 and files use mode 600. The gate is the only request path into application code.

See `docs/mattwarden-migration.md` for the one-time move from the legacy paths and the mandatory cut-over order.
