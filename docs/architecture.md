# Architecture

Mattrics Training Log is a static frontend with a small PHP API layer and an external data pipeline.

## Components

- [`public/index.html`](/Users/mwieland/dev/MattricsTrainingLog/public/index.html) and assets under [`public/assets/`](/Users/mwieland/dev/MattricsTrainingLog/public/assets) provide the dashboard UI.
- [`public/api/data.php`](/Users/mwieland/dev/MattricsTrainingLog/public/api/data.php) serves training data from a private cached snapshot and refreshes it from Google Apps Script on demand.
- [`public/api/ai.php`](/Users/mwieland/dev/MattricsTrainingLog/public/api/ai.php) proxies AI workout suggestions server-side so the API key is not exposed in deployed frontend assets.
- [`public/api/bootstrap.php`](/Users/mwieland/dev/MattricsTrainingLog/public/api/bootstrap.php) loads config, validates methods, and handles upstream requests.
- [`private/config.php`](/Users/mwieland/dev/MattricsTrainingLog/private/config.example.php) stores runtime-only secrets and upstream endpoints. It is never deployed as part of the public web root.
- `private/cache/training-data.json` stores the last successful sanitized snapshot so the dashboard can open without hitting Google on every visit.
- [`apps-script/Code.gs`](/Users/mwieland/dev/MattricsTrainingLog/apps-script/Code.gs) runs in Google Apps Script and exposes the Google Sheet as JSON behind a shared secret.

## Data flow

`Strava -> Make.com -> Google Sheets -> Apps Script -> private snapshot -> public/api/data.php -> frontend`

Optional AI flow:

`frontend -> public/api/ai.php -> Anthropic API`

## Deploy model

- only [`public/`](/Users/mwieland/dev/MattricsTrainingLog/public) is mirrored to the web root
- `private/config.php` is uploaded separately to `SFTP_REMOTE_PRIVATE_DIR`
- `public/config.js` is treated as local-only and excluded from deploy
- validation and smoke testing are handled by scripts under [`scripts/`](/Users/mwieland/dev/MattricsTrainingLog/scripts)
