# Auth Security

## Threat Model

Mattrics is a single-user private app. Passkeys remain the primary authentication method. The hardening assumes:

- the PHP private directory is not web-served
- HTTPS terminates before requests reach PHP
- the configured `site_origin` is the only browser origin allowed to complete WebAuthn ceremonies
- an attacker may spam auth endpoints, reuse old challenges, submit cross-site POSTs, or attempt recovery-code guessing

## Configuration

Production `private/config.php` must set:

```php
'site_origin' => 'https://mattrics.example.com',
'auth_require_https' => true,
'session_idle_seconds' => 1800,
'session_absolute_seconds' => 43200,
```

`webauthn_rp_id` is optional. If omitted, it defaults to the `site_origin` host. Use a parent domain only when the app runs on that domain or a real subdomain. Unsafe suffixes such as `badexample.com` for `example.com` are rejected.

## Challenge Lifecycle

`public/api/auth/challenge.php` creates WebAuthn challenges for registration, login, and delete re-authentication. Each challenge is stored in `private/auth-challenges.json` as a SHA-256 hash with:

- purpose: `register`, `login`, or `delete`
- session hash
- expected origin
- RP ID
- issue and expiry timestamps
- consumed timestamp

Challenges are cryptographically random, short-lived, single-use, and pruned on normal auth activity. Verification endpoints reject missing, expired, reused, mismatched-purpose, mismatched-session, mismatched-origin, or mismatched-RP-ID challenges.

## Rate Limiting

`private/auth-rate-limits.json` stores temporary throttling buckets. Limits are applied by IP hash and session hash:

- challenge creation: 20 per IP and 10 per session per 5 minutes
- WebAuthn register/login/delete verification: 10 per IP and 6 per session per 10 minutes
- recovery verification: 5 per IP and 5 per session per hour

Rate-limited requests return safe generic errors and are audit logged.

## Sessions and CSRF

Auth cookies use the `mattrics_sess` session name, `HttpOnly`, `SameSite=Strict`, path `/`, and `Secure` in production. Successful passkey login keeps `session_regenerate_id(true)`.

Authenticated sessions expire after the configured idle timeout or absolute lifetime. API requests return 401 when expired; page requests redirect through login.

Authenticated state-changing POST endpoints require `X-CSRF-Token`:

- `POST /api/settings.php`
- `POST /api/auth/passkeys.php`
- `POST /api/auth/logout.php`
- authenticated recovery-code regeneration

## Recovery Codes

Recovery codes are generated once during first passkey setup and can be regenerated from Settings. They are shown once, stored hashed in `private/passkey-credential.json`, and invalidated after use. Regenerating codes invalidates all older unused codes.

Recovery flow:

1. Open `/recovery.php`.
2. Enter one saved recovery code.
3. The code is marked used immediately.
4. The session receives recovery-only state, not full app auth.
5. Register a replacement passkey at `/register.php?recovery=1`.
6. Sign in normally with the new passkey.

The last remaining passkey still cannot be deleted from Settings or the backend.

## Audit Log

Security events are appended to `private/auth-audit.log` as JSONL. Logged events include passkey add/rename/delete, login success/failure, challenge issue/reject/consume, recovery-code generation/use/failure, logout, session timeout, and rate limiting.

Audit entries include timestamps plus hashed IP/session identifiers. They must not include raw challenges, recovery codes, secrets, credential public keys, or full IP addresses.
