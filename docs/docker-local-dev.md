# Docker Local Development

This repo can now run locally in Docker without installing the app runtime on the host.

## What runs in the container

- PHP built-in web server serving `public/`
- PHP CLI for auth tests and linting
- Node.js for `public/tests/settings-tests.js`
- `lftp` for deploy script parity when needed

The repo is bind-mounted into the container, so code edits on the host are reflected immediately.

## Data and secrets

- `private/` stays on the host and is mounted into the container through the repo bind mount.
- Existing local state such as passkeys, settings, auth audit logs, and cached training data are preserved.
- `private/config.php`, `.env.local`, and `public/config.js` remain local-only and out of the image build context.

## Local auth behavior

- Docker dev uses `http://localhost:8080`.
- `MATTRICS_SITE_ORIGIN=http://localhost:8080` is injected through `docker-compose.yml`.
- `MATTRICS_AUTH_REQUIRE_HTTPS=0` disables the production HTTPS requirement for local container use.
- Passkeys stay enabled. `localhost` remains a valid WebAuthn development origin in supported browsers.

## Commands

Start the app:

```sh
docker compose up --build
```

Open:

```sh
http://localhost:8080/login.php
```

Run checks inside the container:

```sh
docker compose exec app php tests/auth-security-tests.php
docker compose exec app node public/tests/settings-tests.js
docker compose exec app ./scripts/predeploy-guard.sh --check
```

Stop:

```sh
docker compose down
```

Reset container-only state:

```sh
docker compose down -v
```

This removes the PHP session volume, but does not delete host-mounted `private/` data.

## Notes

- Local Docker uses PHP's built-in server, so Apache `.htaccess` rules are not mirrored in dev.
- Production deploy flow remains unchanged in this pass.
