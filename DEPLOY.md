# Deploy

Mattrics deploys application content by SFTP to `/usr/home/mwiela/sites/mattrics`. Mattwarden separately installs the `.htaccess` and `index.php` shim in the active Hetzner webroot, normalized on 2026-09-23 to `public_html/mattrics`. Changing `MATTRICS_REMOTE_DIR` is never part of a webroot operation; see `docs/hetzner-private-deploy.md` for the verified layout and retained rollback paths.

Do not run an application deploy against a fresh target until production data is already in `/usr/home/mwiela/sites/mattrics/private`. Deploying first can upload empty seed files with `--only-missing`, making the real production paths no longer missing. The 2026-09-22 first deploy followed a verified data copy from the live private tree.

## Configuration

Copy `.env.example` to `.env.local` and configure:

- `SFTP_HOST`, `SFTP_PORT`, and `SFTP_USER`
- `SFTP_KEY_PATH` and optional `SFTP_IDENTITY_AGENT`
- `SFTP_KNOWN_HOSTS=deploy/known_hosts`
- `MATTRICS_REMOTE_DIR=sites/mattrics` exactly

The deploy refuses password variables, missing key material, an empty host-pin file, absolute or traversing targets, every `public_html` target, and every remote directory except `sites/mattrics`.

Never commit or upload `.env.local` or `private/config.php`.
For the rejected workout-AI credential, follow `docs/anthropic-api-key.md`; `./deploy.sh` cannot rotate it.

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

Stage the approved commit and the verified prior release into new directories. Staging reads Git blobs and checks file hashes and inventory. This step needs no deployment credentials or network access.

```sh
./deploy.sh --stage-only "$release_revision" "$release_bundle"
./deploy.sh --stage-only "$previous_revision" "$rollback_bundle"
```

Run the application gate and disposable Postgres suite from the reviewed release checkout:

```sh
./scripts/prod-gate.sh
./scripts/local-postgres.sh test
```

After explicit operator approval, deploy the exact revision with `./deploy.sh --revision "$release_revision"`. The script reruns the production gate from a snapshot of that commit, then verifies the remote inventory. For the code-only deployment, keep the production canonical database URL empty and direct refresh and the Make cache disabled. Verify authenticated dashboard and Settings loads and the Sheets-backed data source using the fixed runbook in `docs/mattwarden-migration.md`. After separate approval, the same revision option can redeploy the verified rollback revision.

Production database preparation, catalog promotion and changing dashboard reads are separate approved operations. See the [v1.0 cutover procedure](docs/planning/milestones/ms-v1-0.md).

Do not deploy or push without explicit operator approval.
