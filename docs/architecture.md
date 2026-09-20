# Architecture

Mattrics Training Log is the active application and the practical source of truth for product work. Today it runs as a static frontend with a small PHP API layer and an external data pipeline. The near-term migration direction is private server hosting with Docker Compose and Postgres, but the current production model remains the live system until that replacement is implemented and validated.

## Architecture decisions

- `MattricsTrainingLog` is the main app and the only active implementation target.
- `MattricsNext` is reference/archive only. It may inform table design, import patterns, and migration ideas, but it is not the future UI shell and should not receive ongoing feature work unless a later architecture decision explicitly changes that.
- The long-term direction is own-server deployment with Docker Compose, Postgres, and private access patterns such as Cloudflare Tunnel.
- The current static/PHP hosting model remains the production architecture until the Docker/Postgres path explicitly replaces it.

See [`docs/architecture-next.md`](./architecture-next.md) for the permanent forward-looking architecture brief that guides future implementation slices.

## Runtime status

- Current production runtime: static frontend under `public/` plus PHP endpoints under `public/api/`
- Current primary data path: Google Sheets and Apps Script with a private cached snapshot
- Target runtime direction: private server hosting with canonical Postgres storage and runtime/private state kept outside the public web root
- Migration rule: preserve the deployed app and current deploy scripts until the replacement runtime is ready

## Components

- [`public/index.html`](/Users/mwieland/dev/MattricsTrainingLog/public/index.html) and assets under [`public/assets/`](/Users/mwieland/dev/MattricsTrainingLog/public/assets) provide the dashboard UI.
- [`public/api/data.php`](/Users/mwieland/dev/MattricsTrainingLog/public/api/data.php) serves training data from a private cached snapshot and refreshes it from Google Apps Script on demand.
- [`public/api/ai.php`](/Users/mwieland/dev/MattricsTrainingLog/public/api/ai.php) proxies AI workout suggestions server-side so the API key is not exposed in deployed frontend assets.
- [`public/api/bootstrap.php`](/Users/mwieland/dev/MattricsTrainingLog/public/api/bootstrap.php) loads config, validates methods, and handles upstream requests.
- [`public/api/bootstrap-auth.php`](/Users/mwieland/dev/MattricsTrainingLog/public/api/bootstrap-auth.php) owns passkey session policy, WebAuthn origin/RP configuration, challenge storage, rate limiting, CSRF tokens, recovery codes, credential storage, and auth audit logging.
- [`public/api/config-defaults.php`](/Users/mwieland/dev/MattricsTrainingLog/public/api/config-defaults.php) stores checked-in, non-sensitive runtime defaults such as auth policy.
- [`private/config.php`](/Users/mwieland/dev/MattricsTrainingLog/private/config.example.php) stores secrets, upstream endpoints, and any environment-specific overrides. It is never deployed as part of the public web root.
- `private/cache/training-data.json` stores the last successful sanitized snapshot so the dashboard can open without hitting Google on every visit. The `private/cache/` directory is generated runtime state and stays gitignored.
- [`apps-script/Code.gs`](/Users/mwieland/dev/MattricsTrainingLog/apps-script/Code.gs) runs in Google Apps Script and exposes the Google Sheet as JSON behind a shared secret.
- [`docker-compose.yml`](/Users/mwieland/dev/MattricsTrainingLog/docker-compose.yml) runs the current PHP app locally in Docker without changing the deployed architecture.

## Reference repository status

`MattricsNext` is not part of the active production path for this repo. Keep it as a reference/archive source for migration ideas, canonical schema inspiration, adapter patterns, and import diagnostics. Do not treat it as the destination runtime, destination UI, or primary feature branch for Mattrics.

## Data flow

`Strava -> Make.com -> Google Sheets -> Apps Script -> private snapshot -> public/api/data.php -> frontend`

Optional AI flow:

`frontend -> public/api/ai.php -> Anthropic API`

Auth flow:

`login/register/settings -> public/api/auth/challenge.php -> browser WebAuthn prompt -> verify/register/passkeys endpoint -> private credential store -> same-site PHP session`

Recovery flow:

`recovery.php -> one-time hashed recovery code -> register.php?recovery=1 -> replacement passkey registration`

## Migration direction

- Replace the Google Sheet-centered runtime over time with canonical Postgres-backed storage.
- Introduce Docker Compose services for the private server deployment path without breaking the current static/PHP deploy path during migration.
- Keep raw imports, credentials, logs, and private runtime state outside `public/`.
- Prefer compatibility layers so the current UI can keep working while storage and ingestion move behind internal APIs.
- Treat Hevy as the first direct ingestion priority and prioritize live Hevy API connectivity, bring in Garmin as another major device-native source and prioritize live Garmin Connect connectivity, and defer Concept2 direct import until after those live connector and duplicate-resolution needs are handled. Strava remains optional/fallback rather than the central source, but when duplicate activities are merged during migration its title and activity type should remain the preferred metadata.

## Auth storage

- `private/passkey-credential.json` stores the single-user WebAuthn user handle, credential public keys, signature counters, credential metadata, and hashed recovery-code records.
- `private/auth-challenges.json` stores short-lived, single-use challenge hashes bound to purpose, session, expected origin, and RP ID.
- `private/auth-rate-limits.json` stores temporary IP/session throttling buckets.
- `private/auth-audit.log` stores JSONL security events without raw challenges, recovery codes, secrets, or full IP addresses.

## Session and origin policy

- Production auth requires HTTPS and uses `HttpOnly`, `SameSite=Strict`, secure cookies.
- `site_origin` can be supplied from `private/config.php` when the deployment needs an explicit origin override, for example `https://mattrics.example.com`.
- `webauthn_rp_id` defaults to the `site_origin` host. A parent-domain RP ID is allowed only when the app origin host is that domain or a real subdomain.
- Sessions have idle and absolute lifetimes. Expired sessions must re-authenticate.
- Authenticated state-changing POST endpoints require `X-CSRF-Token`.

## Deploy model

- only [`public/`](/Users/mwieland/dev/MattricsTrainingLog/public) is mirrored to the web root
- `private/config.php` is uploaded separately to `SFTP_REMOTE_PRIVATE_DIR`
- `public/config.js` is treated as local-only and excluded from deploy
- validation and smoke testing are handled by scripts under [`scripts/`](/Users/mwieland/dev/MattricsTrainingLog/scripts)
- production deployments must set `site_origin`, confirm HTTPS, and keep all private auth storage outside the web root
- this remains the current production deploy model until the Docker/Postgres/private-server architecture explicitly replaces it

## Local container model

- Docker local dev keeps the same code layout and serves `public/` with the PHP built-in server.
- `private/` remains host-mounted runtime state; Docker does not move secrets or auth data into the image.
- Local container auth uses env overrides for `site_origin` and `auth_require_https` so passkeys can work on `http://localhost:8080` without editing production config.
