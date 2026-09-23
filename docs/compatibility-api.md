# API Compatibility

The browser-facing API remains intentionally small and extensionless. Mattwarden accepts both `/api/name` and `/api/name.php`, but application JavaScript uses extensionless URLs.

The shared compatibility modules in `lib/` retain established browser payloads and JSON-backed fallback behavior. The repository no longer ships or supports a bundled Foundation/Postgres runtime; Hetzner production uses private JSON/cache state.

Removed diagnostic URLs are not part of the product API. Operational diagnosis uses logs and local CLI/tests, not host-gated public endpoints.

Exercise routes append their subroute to `/api/exercises`; Mattwarden exposes it as `PATH_INFO` to `api/exercises.php`. Examples include `/api/exercises/activity-types` and identifier-specific paths.

All endpoints return JSON with an explicit content type. They do not emit cache headers or other security headers; those belong to Mattwarden. Unexpected internal details are logged with `error_log()` and are not returned to clients.
