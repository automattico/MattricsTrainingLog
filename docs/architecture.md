# Architecture

## Request boundary

Mattwarden is the only public web application. It authenticates every request, emits security headers, serves allowlisted static files from `sites/mattrics/public`, and dispatches flat `/api/<name>[.php][/<path>]` requests into `sites/mattrics/api/<name>.php`.

Unauthenticated static requests receive Mattwarden's login HTML with status 401. Unauthenticated API requests receive a JSON 401. Mattrics scripts do not run in either case.

Mattrics API scripts defensively call `require_authenticated()` on entry. Mutations also require the fixed site Origin and the Mattwarden session CSRF token. Application code does not own sessions, cookies, credentials, passkeys, enrolment, or recovery.

## Repository and remote layout

| Repository | Remote | Purpose |
|---|---|---|
| `public/` | `/usr/home/mwiela/sites/mattrics/public` | Static HTML, CSS, JS, fonts, and images |
| `api/` | `/usr/home/mwiela/sites/mattrics/api` | Flat URL-addressable PHP endpoints |
| `lib/` | `/usr/home/mwiela/sites/mattrics/lib` | Shared, non-addressable PHP modules |
| selected `private/` seeds | `/usr/home/mwiela/sites/mattrics/private` | Configuration and persistent state |

`MATTWARDEN_SITE_DIR` resolves to `/usr/home/mwiela/sites/mattrics` in production and is the only application base path. Shared modules load through that constant; private storage is exactly `MATTWARDEN_SITE_DIR . '/private'`.

## API

| Endpoint | Methods | Mutation protection |
|---|---|---|
| `/api/session` | GET | authenticated; returns CSRF token and app version |
| `/api/data` | GET | authenticated |
| `/api/settings` | GET, POST | POST: same-origin and CSRF |
| `/api/connectors` | GET, POST | POST: same-origin and CSRF |
| `/api/exercises[/<path>]` | GET, POST, PATCH, DELETE | every mutation: same-origin and CSRF |
| `/api/ai` | POST | same-origin and CSRF |

Exercise subroutes use `PATH_INFO`. Unsupported methods return 405. Shared implementation files are outside `api/` and cannot be dispatched by name.

## Browser flow

The static shell first fetches `/api/session`, validates the 64-hex CSRF token, and retains it only in JavaScript memory. A central fetch wrapper adds `X-CSRF-Token` to mutations. The app then loads training data and other views. AI calls are server-side through `/api/ai`; no provider key or direct provider request exists in browser code.

Logout links to `/__mattwarden/logout`.

## CSP

The shell works with Mattwarden's default policy:

```text
default-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'self'; frame-ancestors 'none'
```

Scripts and styles are external and same-origin. Fonts are self-hosted. Markup/templates contain no inline script blocks, event attributes, JavaScript URLs, style blocks, inline style attributes, or `setAttribute('style', ...)`. Dynamic visual values continue to use permitted CSSOM property mutation.
