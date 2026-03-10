# Mattrics Training Log

Mattrics Training Log is a personal training dashboard built with plain HTML, CSS, and JavaScript. It loads activity data from a Google Sheet through a Google Apps Script web endpoint, shows recent training in time-windowed views, and can generate an AI workout suggestion from your recent history.

## What the app does

- Loads training rows from a Google Sheet JSON endpoint
- Filters the dashboard by rolling windows such as 7 days, 14 days, 1 month, or all time
- Shows feed and insights views for your recent activity
- Generates an AI workout suggestion when an Anthropic API key is configured

## Tech stack

- Vanilla HTML/CSS/JS
- Google Apps Script for exposing sheet data as JSON
- Anthropic API for the AI workout feature

## Project structure

- [`dashboard.html`](/Users/mwieland/dev/MattricsTrainingLog/dashboard.html) - main app entry point
- [`config.example.js`](/Users/mwieland/dev/MattricsTrainingLog/config.example.js) - local config template
- [`apps-script/Code.gs`](/Users/mwieland/dev/MattricsTrainingLog/apps-script/Code.gs) - Google Apps Script endpoint
- [`assets/js`](/Users/mwieland/dev/MattricsTrainingLog/assets/js) - app logic
- [`assets/css/main.css`](/Users/mwieland/dev/MattricsTrainingLog/assets/css/main.css) - app styling
- [`docs/strava-sync-architecture.md`](/Users/mwieland/dev/MattricsTrainingLog/docs/strava-sync-architecture.md) - sync and architecture notes

## Setup

### 1. Create `config.js`

Copy the example config:

```bash
cp config.example.js config.js
```

`config.js` is local-only and should not be committed.

### 2. Configure the data source

Edit `config.js` and set:

- `SHEET_URL` to your deployed Google Apps Script web app URL
- `API_KEY` to your Anthropic API key if you want to use AI workout generation

If you do not want AI workout generation yet, you can leave `API_KEY` empty.

### 3. Deploy the Google Apps Script

The dashboard expects a JSON endpoint that returns your sheet rows. This repo includes one in [`apps-script/Code.gs`](/Users/mwieland/dev/MattricsTrainingLog/apps-script/Code.gs).

Deployment steps:

1. Open the Google Sheet that contains your exported training data.
2. Go to `Extensions -> Apps Script`.
3. Replace the script contents with [`apps-script/Code.gs`](/Users/mwieland/dev/MattricsTrainingLog/apps-script/Code.gs).
4. Deploy it as a Web App.
5. Set execution to your account and access to `Anyone`.
6. Copy the deployment URL into `MATTRICS_CONFIG.SHEET_URL` in `config.js`.

### 4. Open the app

Open [`dashboard.html`](/Users/mwieland/dev/MattricsTrainingLog/dashboard.html) directly in a browser.

If your browser blocks requests from `file://` to the Apps Script endpoint, serve the folder locally instead of opening the file directly.

Example:

```bash
python3 -m http.server
```

Then open `http://localhost:8000/dashboard.html`.

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
- `config.js` is read at runtime by [`dashboard.html`](/Users/mwieland/dev/MattricsTrainingLog/dashboard.html).
- The AI workout feature is optional, but the dashboard itself requires a working `SHEET_URL`.

## Documentation

- Architecture and sync notes: [`docs/strava-sync-architecture.md`](/Users/mwieland/dev/MattricsTrainingLog/docs/strava-sync-architecture.md)

## Author

Built by [Matt Wieland](https://mwieland.com)
