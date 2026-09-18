# Deploy

This repository follows the static-site operating model:

- only [`public/`](/Users/mwieland/dev/MattricsTrainingLog/public) is deployed as the web root
- runtime secrets stay outside the public docroot
- deployment runs through [`deploy.sh`](/Users/mwieland/dev/MattricsTrainingLog/deploy.sh)
- validation runs through [`scripts/prod-gate.sh`](/Users/mwieland/dev/MattricsTrainingLog/scripts/prod-gate.sh) and [`scripts/predeploy-guard.sh`](/Users/mwieland/dev/MattricsTrainingLog/scripts/predeploy-guard.sh)

## Local setup

1. Copy `.env.example` to `.env.local`.
2. Fill in deploy placeholders in `.env.local`.
3. Keep `.env.local`, `private/config.php`, and `public/config.js` out of Git.

Deploy auth is **SSH key only**. `deploy.sh` refuses to run if `SFTP_PASSWORD` is set.

- `SFTP_KEY_PATH` — the deploy key. Point it at the **public** key (`~/.ssh/id_ed25519_hetzner.pub`);
  with `IdentitiesOnly` the private half is taken from the SSH agent, i.e. the Bitwarden desktop
  app's SSH agent (must be running and unlocked; it asks for approval per connection).
- `SFTP_IDENTITY_AGENT` — optional path of the agent socket (`~/.bitwarden-ssh-agent.sock`) for
  shells that do not export `SSH_AUTH_SOCK`.
- `deploy/known_hosts` — pinned Hetzner host keys; connections to any other host key fail.
  Public host keys are not secrets and are committed on purpose.

## Runtime config

Create a real `private/config.php` from [`private/config.example.php`](/Users/mwieland/dev/MattricsTrainingLog/private/config.example.php).

Deploy keeps the current project behavior:

- syncs [`public/`](/Users/mwieland/dev/MattricsTrainingLog/public) to `SFTP_REMOTE_DIR`
- uploads `private/config.php` to `SFTP_REMOTE_PRIVATE_DIR`
- excludes `public/config.js` from deployment

`public/config.js` remains a local-only compatibility file. Do not deploy or commit it.

## Validation

Run these checks before deploy:

```sh
bash -n deploy.sh scripts/*.sh
./scripts/predeploy-guard.sh --check
./scripts/prod-gate.sh
```

Optional health checks:

```sh
./scripts/check-remote-health.sh
./scripts/smoke-test.sh
```

## Deploy

```sh
./deploy.sh
```

The deploy flow is:

1. `scripts/prod-gate.sh`
2. `scripts/predeploy-guard.sh`
3. upload `public/`
4. upload `private/config.php`
5. `scripts/smoke-test.sh`

## Notes

- CI validates only and does not deploy.
- Prefer storing the real runtime config outside the docroot on the server.
- If your host exposes `MATTRICS_CONFIG`, it may point to the uploaded private config path.
