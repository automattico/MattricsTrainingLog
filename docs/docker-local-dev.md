# Local development

## Native server

```sh
./scripts/dev-server.sh
```

The script builds a temporary `sites/mattrics`-style tree, copies local `private/config.php` when present (otherwise the example), sets private permissions, and starts PHP's built-in server at `http://127.0.0.1:8080`.

`scripts/dev-router.php` includes `tests/stubs/mattwarden.php`, applies the default Mattwarden headers/CSP, serves allowlisted static types, rejects dotfiles, and dispatches flat endpoints with `.php` aliases and `PATH_INFO`.

The stub is authenticated by default, uses an explicit non-request-derived origin matching the configured development port, and exposes a fixed 64-hex CSRF token. Set `MATTWARDEN_TEST_AUTHENTICATED=0` only when verifying the gate's 401 behavior.

## Docker

```sh
docker compose up --build
docker compose down
```

The default Compose service runs the same temporary router/stub, without application session volumes or auth-origin environment overrides.

## Tests

```sh
./scripts/prod-gate.sh
php tests/dev-router-tests.php
node tests/js/settings-tests.js
node tests/js/exercise-config-tests.js
```

The router integration test opens a loopback port and may require sandbox permission in managed environments.
