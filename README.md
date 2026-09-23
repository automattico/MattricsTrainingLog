# Mattrics Training Log

Mattrics is a private PHP 8 and vanilla-JavaScript training dashboard. Mattwarden authenticates every production request, serves the static shell, dispatches the flat PHP API, owns the session and CSRF token, and provides logout.

The application itself contains no authentication, passkey, session, cookie, or recovery implementation.

## Layout

```text
public/   static index.html, assets, icons, manifest, robots.txt
api/      six flat authenticated endpoint scripts
lib/      shared non-addressable PHP modules
private/  config example and initial JSON seeds
scripts/  local Mattwarden stub/router, imports, validation, deploy
tests/    PHP, JavaScript, contract, router, and deploy-safety tests
```

Production content is stored outside every document root under `/usr/home/mwiela/sites/mattrics`. The web document root contains only Mattwarden's shim and `.htaccess`.

## Local development

```sh
./scripts/dev-server.sh
```

Open `http://127.0.0.1:8080/`. The temporary development site uses the Mattwarden contract stub, the same flat API dispatch rules, and the default HTML CSP.

Run all deploy-blocking checks with:

```sh
./scripts/prod-gate.sh
```

## Operations

- Documentation index and authority: `docs/README.md`
- Deployment: `DEPLOY.md`
- Architecture: `docs/architecture.md`
- Mattwarden migration and fixed cut-over order: `docs/mattwarden-migration.md`
- Local development: `docs/local-development.md`
- Workout AI key replacement: `docs/anthropic-api-key.md`
- Hetzner private layout and webroot normalization: `docs/hetzner-private-deploy.md`

`docs/slices/` contains dated implementation reports and past prompts. Those are historical records, not current deployment instructions.

Live site: [mattrics.mwieland.com](https://mattrics.mwieland.com/)
