# Local Foundation/Postgres Runtime

Foundation/Postgres is local-only and is not used by Hetzner production. This migration deliberately makes only the minimum compatibility change: obsolete application-auth state and environment settings are gone, and readiness checks the static server.

```sh
docker compose -f docker-compose.postgres.yml --profile foundation config
docker compose -f docker-compose.postgres.yml --profile foundation up --build
./scripts/check-foundation-runtime.sh
```

The stack still provides Postgres, the canonical schema/import tooling, and a PHP static server at port 8081. It is not ported to the Mattwarden development router and its HTTP API must not be treated as a migrated end-to-end runtime. That is a future runtime slice.

Foundation configuration remains optional in `private/config.php`:

```php
'foundation_database_url' => '',
'foundation_user_key' => 'legacy-local-user',
```

CLI bootstrap/import scripts define a local `MATTWARDEN_SITE_DIR` for shared library resolution. Raw Hevy and Garmin exports stay ignored under `private/import-hevy/` and `private/import-garmin/`.

Database-backed tests report an exact skip when Postgres is unavailable. A healthy local database enables their integration blocks.
