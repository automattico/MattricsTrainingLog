# Deploy

Mattrics deploys application content by SFTP to `/usr/home/mwiela/sites/mattrics`. Mattwarden separately installs the only two files in `public_html/mattrics`: its `.htaccess` and `index.php` shim.

Do not run the first post-migration application deploy until the operator has moved production data into `/usr/home/mwiela/sites/mattrics/private`. Deploying first can upload empty seed files with `--only-missing`, making the real production paths no longer missing.

## Configuration

Copy `.env.example` to `.env.local` and configure:

- `SFTP_HOST`, `SFTP_PORT`, and `SFTP_USER`
- `SFTP_KEY_PATH` and optional `SFTP_IDENTITY_AGENT`
- `SFTP_KNOWN_HOSTS=deploy/known_hosts`
- `MATTRICS_REMOTE_DIR=sites/mattrics` exactly

The deploy refuses password variables, missing key material, an empty host-pin file, absolute or traversing targets, every `public_html` target, and every remote directory except `sites/mattrics`.

Never commit or upload `.env.local` or `private/config.php`.

## Managed upload set

```text
public/  -> sites/mattrics/public   mirror --delete
api/     -> sites/mattrics/api      mirror --delete
lib/     -> sites/mattrics/lib      mirror --delete
private/config.example.php          upload --only-missing
private/data/{activity-type-configs,exercise-configs,exercise-dataset,exercise-unknowns}.json
                                         upload --only-missing
```

Runtime `config.php`, user settings, data/cache/log state, and raw imports are never uploaded or overwritten. Directories are set to mode 700 and managed files to 600. The deploy verifies exact file inventories for the managed public/API/library trees and only verifies presence of required private seeds, leaving unrelated runtime state intact.

## Validation and deployment

```sh
./scripts/prod-gate.sh
./deploy.sh
```

`deploy.sh` runs the production gate again before connecting. It does not make unauthenticated HTTP content requests; authenticated verification belongs to the fixed runbook in `docs/mattwarden-migration.md`.

Do not deploy or push without explicit operator approval.
