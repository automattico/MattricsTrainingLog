# Architecture

Mattrics Training Log is a static frontend with a small PHP API layer and an external data pipeline.

## Components

- [`public/index.html`](/Users/mwieland/dev/MattricsTrainingLog/public/index.html) and assets under [`public/assets/`](/Users/mwieland/dev/MattricsTrainingLog/public/assets) provide the dashboard UI.
- [`public/api/data.php`](/Users/mwieland/dev/MattricsTrainingLog/public/api/data.php) serves training data from a private cached snapshot and refreshes it from Google Apps Script on demand.
- [`public/api/ai.php`](/Users/mwieland/dev/MattricsTrainingLog/public/api/ai.php) proxies AI workout suggestions server-side so the API key is not exposed in deployed frontend assets.
- [`public/api/bootstrap.php`](/Users/mwieland/dev/MattricsTrainingLog/public/api/bootstrap.php) loads config, validates methods, and handles upstream requests.
- [`public/api/bootstrap-auth.php`](/Users/mwieland/dev/MattricsTrainingLog/public/api/bootstrap-auth.php) owns passkey session policy, WebAuthn origin/RP configuration, challenge storage, rate limiting, CSRF tokens, recovery codes, credential storage, and auth audit logging.
- [`private/config.php`](/Users/mwieland/dev/MattricsTrainingLog/private/config.example.php) stores runtime-only secrets and upstream endpoints. It is never deployed as part of the public web root.
- `private/cache/training-data.json` stores the last successful sanitized snapshot so the dashboard can open without hitting Google on every visit. The `private/cache/` directory is generated runtime state and stays gitignored.
- [`apps-script/Code.gs`](/Users/mwieland/dev/MattricsTrainingLog/apps-script/Code.gs) runs in Google Apps Script and exposes the Google Sheet as JSON behind a shared secret.

## Data flow

`Strava -> Make.com -> Google Sheets -> Apps Script -> private snapshot -> public/api/data.php -> frontend`

Optional AI flow:

`frontend -> public/api/ai.php -> Anthropic API`

Auth flow:

`login/register/settings -> public/api/auth/challenge.php -> browser WebAuthn prompt -> verify/register/passkeys endpoint -> private credential store -> same-site PHP session`

Recovery flow:

`recovery.php -> one-time hashed recovery code -> register.php?recovery=1 -> replacement passkey registration`

## Auth storage

- `private/passkey-credential.json` stores the single-user WebAuthn user handle, credential public keys, signature counters, credential metadata, and hashed recovery-code records.
- `private/auth-challenges.json` stores short-lived, single-use challenge hashes bound to purpose, session, expected origin, and RP ID.
- `private/auth-rate-limits.json` stores temporary IP/session throttling buckets.
- `private/auth-audit.log` stores JSONL security events without raw challenges, recovery codes, secrets, or full IP addresses.

## Session and origin policy

- Production auth requires HTTPS and uses `HttpOnly`, `SameSite=Strict`, secure cookies.
- `site_origin` in `private/config.php` must be the exact public origin, for example `https://mattrics.example.com`.
- `webauthn_rp_id` defaults to the `site_origin` host. A parent-domain RP ID is allowed only when the app origin host is that domain or a real subdomain.
- Sessions have idle and absolute lifetimes. Expired sessions must re-authenticate.
- Authenticated state-changing POST endpoints require `X-CSRF-Token`.

## Deploy model

- only [`public/`](/Users/mwieland/dev/MattricsTrainingLog/public) is mirrored to the web root
- `private/config.php` is uploaded separately to `SFTP_REMOTE_PRIVATE_DIR`
- `public/config.js` is treated as local-only and excluded from deploy
- validation and smoke testing are handled by scripts under [`scripts/`](/Users/mwieland/dev/MattricsTrainingLog/scripts)
- production deployments must set `site_origin`, confirm HTTPS, and keep all private auth storage outside the web root
