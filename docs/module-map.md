# Module map

## Server

```text
Mattwarden gate
  └─ api/<endpoint>.php
       ├─ require_authenticated()
       ├─ mutation guards where applicable
       └─ MATTWARDEN_SITE_DIR/lib/*.php
            └─ MATTWARDEN_SITE_DIR/private/*
```

- `lib/bootstrap.php`: private config/path, JSON request/response, filesystem, and upstream helpers.
- `lib/exercise-config-repository.php`: JSON-backed exercise/activity configuration.
- `lib/exercise-config-ai.php`: server-side AI support for exercise configuration.
- `lib/foundation-read.php` / `foundation-write.php`: optional canonical Postgres compatibility.
- `lib/foundation-connectors.php`: connector credential/status storage and Hevy requests.
- `lib/foundation-import.php`, migrations, and parser modules: local canonical import tooling.

## Browser

`public/index.html` loads external scripts in the order documented in `AGENTS.md`. `core/constants.js` defines fixed extensionless API URLs, session bootstrap, and the mutation-aware fetch wrapper. `app.js` waits for session bootstrap before loading data.

The renderer and domain modules remain plain global-namespace JavaScript. No browser runtime configuration or provider credential is loaded.
