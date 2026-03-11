# Mattrics Training Log

Mattrics Training Log is a personal training dashboard built with plain HTML, CSS, JavaScript, and a small PHP proxy layer for private hosting. It loads activity data from a Google Sheet through a token-protected Google Apps Script endpoint, shows recent training in time-windowed views, and can generate an AI workout suggestion without exposing the Anthropic key to the browser.

## What the app does

- Loads training rows from a server-side JSON proxy
- Filters the dashboard by rolling windows such as 7 days, 14 days, 1 month, or all time
- Shows feed and insights views for your recent activity
- Generates an AI workout suggestion through a server-side Anthropic proxy

## Tech stack

- Vanilla HTML/CSS/JS frontend
- PHP endpoints for private hosting
- Google Apps Script for exposing sheet data as JSON behind a shared secret
- Anthropic API for the AI workout feature

## Project structure

- [`public/index.html`](/Users/mwieland/dev/MattricsTrainingLog/public/index.html) - deployable app entry point
- [`public/api`](/Users/mwieland/dev/MattricsTrainingLog/public/api) - PHP data and AI proxy endpoints
- [`private/config.example.php`](/Users/mwieland/dev/MattricsTrainingLog/private/config.example.php) - server-only config template
- [`apps-script/Code.gs`](/Users/mwieland/dev/MattricsTrainingLog/apps-script/Code.gs) - Google Apps Script endpoint
- [`public/assets/js`](/Users/mwieland/dev/MattricsTrainingLog/public/assets/js) - app logic
- [`public/assets/css/main.css`](/Users/mwieland/dev/MattricsTrainingLog/public/assets/css/main.css) - app styling
- [`docs/hetzner-private-deploy.md`](/Users/mwieland/dev/MattricsTrainingLog/docs/hetzner-private-deploy.md) - secure Hetzner deployment guide
- [`docs/strava-sync-architecture.md`](/Users/mwieland/dev/MattricsTrainingLog/docs/strava-sync-architecture.md) - sync and architecture notes

## Setup

### 1. Create `private/config.php`

Copy the example server config:

```bash
cp private/config.example.php private/config.php
```

`private/config.php` is local-only and should not be committed.

Fill in:

- `sheet_url` with your deployed Apps Script URL
- `sheet_token` with the shared secret expected by Apps Script
- `anthropic_api_key` only if you want AI workout generation

### 2. Deploy the Google Apps Script

The dashboard expects a JSON endpoint that returns your sheet rows. This repo includes one in [`apps-script/Code.gs`](/Users/mwieland/dev/MattricsTrainingLog/apps-script/Code.gs).

Deployment steps:

1. Open the Google Sheet that contains your exported training data.
2. Go to `Extensions -> Apps Script`.
3. Replace the script contents with [`apps-script/Code.gs`](/Users/mwieland/dev/MattricsTrainingLog/apps-script/Code.gs).
4. Deploy it as a Web App.
5. Set execution to your account and access to `Anyone`.
6. In `Project Settings -> Script properties`, add `MATTRICS_SHARED_SECRET` with a long random token.
7. Copy the deployment URL into `private/config.php` as `sheet_url`.
8. Copy the same shared secret into `private/config.php` as `sheet_token`.

### 3. Run locally through PHP

Serve the deployable `public/` directory instead of opening a local file:

```bash
php -S localhost:8000 -t public
```

Then open `http://localhost:8000`.

## Data expectations

The dashboard currently assumes a Google Sheet that contains activity rows with headers such as:

- `Date`
- `Type`
- `Name`
- `Distance (km)`
- `Duration (min)`
- `Elevation Gain (m)`
- `Avg HR`
- `Max HR`
- `Avg Pace (min/km)`
- `Avg Speed (km/h)`
- `Avg Cadence`
- `Description`

Rows without `Date` or `Type` are ignored by the app.

## Notes

- The app has no build step.
- Only the contents of [`public`](/Users/mwieland/dev/MattricsTrainingLog/public) should be web-served.
- Secrets belong in [`private/config.php`](/Users/mwieland/dev/MattricsTrainingLog/private/config.example.php) outside the docroot.
- The AI workout feature is optional.

## Documentation

- Secure Hetzner deployment: [`docs/hetzner-private-deploy.md`](/Users/mwieland/dev/MattricsTrainingLog/docs/hetzner-private-deploy.md)
- Architecture and sync notes: [`docs/strava-sync-architecture.md`](/Users/mwieland/dev/MattricsTrainingLog/docs/strava-sync-architecture.md)

## Author

Built by [Matt Wieland](https://mwieland.com)
