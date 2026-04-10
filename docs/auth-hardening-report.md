# Auth Hardening Implementation Report

## What Changed

- Added file-backed, short-lived, single-use WebAuthn challenge storage.
- Added IP/session rate limiting for challenge creation, WebAuthn verification, and recovery-code verification.
- Added exact expected-origin validation before WebAuthn library processing.
- Added RP ID policy checks for configured origins and parent-domain RP IDs.
- Hardened session cookies, HTTPS enforcement, idle timeout, and absolute session lifetime.
- Added CSRF protection to authenticated state-changing POST endpoints.
- Added one-time recovery codes with a deliberate recovery-to-re-enrollment flow.
- Added auth audit logging for security-relevant events.
- Added credential metadata: `created_at`, `last_used_at`, and `device_label`.
- Updated Settings UI to show passkey metadata, recovery status, and recovery-code rotation.
- Added no-framework PHP auth security tests and wired them into the predeploy guard.

## Storage Changes

`private/passkey-credential.json` migrates to version 2:

- `version`
- `userId`
- `credentials[]`
  - existing credential ID, public key, counter, internal ID, name
  - `created_at`
  - `last_used_at`
  - `device_label`
- `recovery`
  - `generated_at`
  - `last_rotated_at`
  - `codes[]` with hashed one-time recovery codes

New runtime files:

- `private/auth-challenges.json`
- `private/auth-rate-limits.json`
- `private/auth-audit.log`

These files must remain private runtime state and must not be served from `public/`.

## Security Decisions

- Challenges are stored as hashes, bound to purpose/session/origin/RP ID, and consumed only after successful verification.
- Server errors are generic so credential existence, WebAuthn internals, and recovery state are not leaked.
- `site_origin` is treated as the exact expected WebAuthn origin.
- `webauthn_rp_id` defaults to the exact origin host. Parent-domain RP IDs are allowed only for real subdomains.
- Recovery sessions are scoped to passkey re-enrollment and do not grant full app access.
- Recovery codes are shown once, hashed at rest, invalidated after use, and rotated as a full set.
- Audit logs use hashed IP/session identifiers and omit secrets, raw challenges, and recovery codes.

## Backward Compatibility

- Existing single-credential stores migrate automatically.
- Existing multi-passkey stores migrate automatically.
- Existing passkey credential IDs, public keys, signature counters, user handles, and display names are preserved.
- Existing `registeredAt` values continue to work and are mirrored into `created_at` when needed.
- Existing login/register/passkey endpoint URLs remain available.

## Manual Deployment Steps

1. Set `site_origin` in production `private/config.php` to the exact public origin.
2. Confirm production traffic reaches PHP over HTTPS or sets the expected forwarded HTTPS signal.
3. Optionally set `webauthn_rp_id`; leave it blank unless a parent-domain RP ID is intentionally needed.
4. Confirm `private/` is outside the public web root or otherwise denied by the web server.
5. Deploy from `public/` only, then run the smoke tests.
