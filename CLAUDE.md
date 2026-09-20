# Mattrics Agent Rules

Authentication is owned exclusively by Mattwarden. Do not add login, registration, passkey, credential-recovery, session, cookie, host-bypass, or origin-discovery code to this repository.

- Every `api/*.php` file starts with `declare(strict_types=1); require_authenticated();`.
- POST, PUT, PATCH, and DELETE handlers call `mattwarden_require_same_origin()` and `mattwarden_require_csrf()` before changing state.
- Use `MATTWARDEN_SITE_DIR` as the only base path; private state is under `MATTWARDEN_SITE_DIR . '/private'`.
- Shared PHP belongs in `lib/`; the API directory is flat and contains only URL-addressable endpoints.
- Application code never accesses `$_SESSION`, calls session functions, sets cookies, or sends `Set-Cookie`.
- Logout links to `/__mattwarden/logout`.
- Deploy only by SFTP with SSH-key authentication and the pinned `deploy/known_hosts` file.
- Remote content lives under `sites/mattrics/*`. This repository never writes into `public_html`.
- Never print, upload, or commit `.env.local`, `private/config.php`, runtime data, logs, raw imports, credentials, or keys.
- Do not deploy, push, or change the server without explicit user approval.

Read `docs/mattwarden-migration.md`, `docs/architecture.md`, and `DEPLOY.md` before changing runtime or deployment behavior.
