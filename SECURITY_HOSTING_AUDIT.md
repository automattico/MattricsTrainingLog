# Security & Hosting Audit

Scope: repository inspection only, 2026-09-16. No credentials are reproduced; any such value is `[REDACTED]`.

## 1. Application

- Mattrics Training Log is a single-user training dashboard: activity history, fatigue/recovery analysis, exercise configuration, optional AI coaching, and planned live fitness-source connectors.
- Production is a PHP-served application with vanilla JavaScript/CSS; it is not purely static in its intended private configuration.
- Server components in `public/api/` provide WebAuthn authentication, authenticated data/configuration APIs, server-side proxies to external AI/data services, and file-backed private state. The current runtime uses Google Apps Script/Google Sheets with a server-side cached JSON snapshot.
- A Docker/Postgres “foundation” runtime exists for local/target migration work. It supplies canonical data and connector/import storage, but repository documentation says it has not replaced current production hosting.
- A display-only static build is technically possible, but authenticated operation, data refresh/caching, AI proxying, passkeys, configuration writes, and PostgreSQL mode require PHP (and optionally Postgres).

## 2. Hosting

- Current documented production architecture is PHP on shared web hosting, with `public/` as the document root and private configuration/state outside it. The documented public URL is `https://mattrics.mwieland.com/`.
- Apache-compatible hosting is indicated by `public/.htaccess` and `public/api/.htaccess`; no production container, vhost, or web-server configuration is committed.
- Production HTTP(S) ports are not configured in the repository; standard ports 80/443 are implied. `.htaccess` redirects HTTP to HTTPS and sets HSTS (one year) plus browser hardening headers.
- Deployment is configured for SFTP, default port 22. Local Docker maps app port 8080; the optional foundation stack maps app 8081 and Postgres 5433 on the host.
- Cloudflare is not detected as an active current dependency/configuration. It is mentioned only as a future private-access option (“Cloudflare Tunnel”).

## 3. Deployment

- `deploy.sh` loads untracked `.env.local`, runs production/predeploy checks, then uses `lftp` over SFTP. It reverse-mirrors `public/` to `SFTP_REMOTE_DIR`, separately uploads `private/config.php`, synchronizes initial private data, mirrors `lib/`, and runs an HTTP smoke test against `DEPLOY_URL`.
- SFTP key authentication is preferred (`SFTP_KEY_PATH`); password authentication is supported as a fallback (`SFTP_PASSWORD`). Values are not included here.
- `public/config.js`, `.htpasswd`, and `.well-known` are excluded from the public mirror; `public/config.js` is explicitly removed remotely before synchronization.
- GitHub Actions (`.github/workflows/site-checks.yml`) runs validation only; there is no committed CI deployment job.
- Docker Compose files are local development/foundation tooling, not documented production deployment.

## 4. Authentication & Access Control

- The application-level mechanism is single-user WebAuthn/passkeys, backed by PHP sessions (`mattrics_sess`). Credentials, challenge hashes, rate-limit buckets, recovery-code hashes, and audit logs are stored under the private runtime directory.
- Passkey ceremonies enforce HTTPS in production, configured origin/RP-ID validation, user verification/presence, short-lived single-use challenges, rate limiting, signature-counter updates, and session-ID regeneration after login.
- Cookies are configured `HttpOnly`, `SameSite=Strict`, path `/`, and `Secure` when HTTPS is required/detected. Idle and absolute session lifetimes are configurable. Authenticated mutations require a CSRF token.
- `index.php` and the browser-facing data/AI/settings/connectors/exercises APIs require an authenticated session. Login, first registration, WebAuthn challenge/verification, and recovery-code verification are intentionally reachable pre-authentication; registration after initial setup requires authentication or a recovery-only session.
- Recovery uses one-time, hashed recovery codes to create only a passkey-reenrollment session; it does not create a full authenticated session. The last passkey cannot be deleted.
- Local loopback hosts intentionally receive a development authentication bypass. This is host-name based and must not be exposed through a non-local deployment.

## 5. Security-Relevant Files

- `public/.htaccess` — HTTPS redirect, no directory indexes, HSTS/security headers, noindex, dotfile denial; web-host Basic Auth is documented but not enabled here.
- `public/api/.htaccess` — API route rewrite and direct denial of bootstrap helper files.
- `deploy.sh`, `scripts/common.sh`, `scripts/prod-gate.sh`, `scripts/predeploy-guard.sh`, `scripts/smoke-test.sh` — SFTP deployment, validation, secret scanning, and smoke checks.
- `.env.example`, `private/config.example.php`, `.gitignore` — deployment/runtime configuration templates and exclusion of real configuration/private JSON/cache.
- `public/api/bootstrap-auth.php`, `public/api/auth/*.php`, `public/login.php`, `public/register.php`, `public/recovery.php` — sessions, WebAuthn, CSRF, rate limits, recovery.
- `docker-compose.yml`, `docker-compose.postgres.yml`, `docker/php/Dockerfile`, `docker/runtime/Dockerfile`, `docker/postgres/` — local PHP and optional Postgres runtime.
- `docs/hetzner-private-deploy.md`, `docs/architecture*.md` — documented shared-hosting deployment and future Cloudflare/Postgres direction.

## 6. External Dependencies

- Current data path: Google Apps Script endpoint backed by Google Sheets; the PHP server supplies its shared key and caches results privately.
- Optional server-side AI: Anthropic Messages API and OpenAI Responses API.
- Fitness inputs/roadmap include Strava via Make.com/Google Sheets, Hevy Live API, Garmin imports/connectivity, and future Concept2 support.
- Google Fonts is loaded by the browser. The vendored `lib/WebAuthn` library supports passkey handling.
- Google/AI services do not prevent Hetzner Webhosting migration because PHP can call them outbound. A live Postgres foundation runtime, scheduled imports, or always-on connector sync would require a database/runtime/cron capability beyond ordinary static hosting.

## 7. Migration Assessment

**Classification: static + PHP** for the documented current production system.

Standard Hetzner Webhosting is suitable if it provides PHP, HTTPS, writable private storage, and SFTP: deploy `public/` as the docroot and keep `private/config.php` and runtime state outside the web root. The optional future PostgreSQL/connector runtime is instead **requires a server/container** (or managed PostgreSQL plus scheduled workers); it is not a fit for static files alone.

## 8. Security Observations

- `public/index.html` remains directly addressable and is not application-authenticated; it attempts to load `config.js`. The deployment script removes that file, but direct static access should be reviewed or blocked when making the site private. Host-level directory protection would cover it.
- `public/config.js` exists locally and contains configuration values. It is ignored and excluded from deployment, but any browser-served copy must be treated as public; do not place secrets there.
- Application privacy depends on correct document-root/private-directory mapping. A host-level password gate is recommended by the repository but is not enabled in committed `.htaccess`.
- HTTPS enforcement trusts `X-Forwarded-Proto`; deploy only behind a proxy that strips client-supplied forwarding headers or sets them reliably.
- The future foundation Compose file publishes Postgres on host port 5433 and contains a local-only development database password. Do not reuse that configuration or password in production; avoid publicly exposing database ports.
- The current Google Apps Script deployment may be publicly callable but relies on a shared request key. Treat the endpoint URL and key as sensitive and preserve server-side-only handling.
