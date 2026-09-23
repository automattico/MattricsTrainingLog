# Strava Sync Architecture

This document describes how Mattrics Training Log gets Strava data into the dashboard, why the sync works the way it does, and which caveats matter in practice.

## Overview

The data pipeline is:

1. Strava activity data is pulled with a Make.com scenario
2. Make writes activities into a Google Sheet
3. Google Apps Script exposes that sheet as JSON
4. Authenticated `GET /api/data` reads the Apps Script URL and shared token from server-only `sites/mattrics/private/config.php`, refreshes a private cache, and returns filtered rows to the dashboard

In short:

`Strava -> Make.com -> Google Sheets -> Apps Script -> /api/data -> dashboard`

## Components

### 1. Make.com scenario

The Make scenario is responsible for syncing Strava activities into the `StravaActivities` sheet.

Current scenario structure:

1. `Google Sheets - Get Cell`
   Reads `SyncState!B2`
2. `Strava - List Activities`
   Uses `after = SyncState!B2`
3. `Router`
   Splits into:
   - processing route
   - cursor update route
4. Processing route:
   - `Google Sheets - Search Rows`
   - duplicate check
   - `Strava - Get an Activity`
   - `Google Sheets - Add a Row`
5. Cursor route:
   - filter: only run if `Activity ID` exists
   - `Google Sheets - Update Cell`

### 2. Google Sheet

Two sheets matter:

- `StravaActivities`
  The activity database used by the dashboard
- `SyncState`
  Stores the rolling cursor in `B2`

`StravaActivities` uses `Activity ID raw` as the unique key for deduplication.

### 3. Google Apps Script

[`apps-script/Code.gs`](../apps-script/Code.gs) reads the active sheet and returns JSON to the server-side Mattrics data endpoint.

Important detail:

- the script does not contain the sheet ID
- it runs against the spreadsheet where it is deployed

### 4. Dashboard

The dashboard requests `/api/data`; it never sees the Apps Script URL or shared token. The server-side endpoint loads `sheet_url` and `sheet_token` from `MATTWARDEN_SITE_DIR . '/private/config.php'`. That file is excluded from Git and from `deploy.sh`. A successful refresh updates `private/cache/training-data.json`; a failed refresh can serve the previous snapshot with a stale-data warning.

## Why the cursor exists

The sync does not query "last 24 hours".

Instead, it stores a rolling UTC timestamp in:

- `SyncState!B2`

Then the Strava query uses:

- `after = last_after`

This keeps the Make scenario efficient and avoids scanning large parts of history on every run.

## Current cursor behavior

The scenario now updates the cursor using:

```make
{{formatDate(addMinutes(1.start_date; -30); "YYYY-MM-DDTHH:mm:ss[Z]")}}
```

with a filter on the cursor-update route:

- `Strava 1 -> Activity ID`
- `Exists`

### Why this is better than `now - 15 minutes`

An older version updated the cursor to "now minus 15 minutes".

That was worse because it anchored the cursor to wall-clock time instead of real activity timestamps.

The current version anchors the cursor to:

- the activity `Start Date in UTC`

This is better because timezone changes and travel do not matter as long as the cursor stays in UTC.

## Important rule: use UTC only

For sync logic, use:

- `Start Date in UTC`

Do not use:

- local `Start Date`
- `start_date_local`
- timezone-formatted local timestamps

Why:

- the cursor in `SyncState!B2` is stored in UTC
- comparisons are only reliable if both sides are UTC
- travel between time zones should not affect the sync logic

## Known limitation: manual and backdated activities

This is the main operational caveat.

The scenario syncs by activity start time, not by creation time.

That means:

- if a manual Strava activity is created later
- or an activity is backdated
- or Strava computes a start time earlier than the current cursor

then the activity may not be returned by:

```make
after = SyncState!B2
```

### Example

If:

- `SyncState!B2 = 2026-03-10T19:52:36Z`
- a manual activity is created now
- but Strava stores its `Start Date in UTC` as `2026-03-10T18:07:00Z`

then the activity will not sync, because its activity time is before the cursor.

### Recovery

To recover:

1. look up the activity's `Start Date in UTC`
2. manually set `SyncState!B2` to a slightly earlier UTC timestamp
3. rerun the Make scenario

This is safe because duplicate protection uses `Activity ID raw`.

## What is considered normal behavior

The current setup is considered correct when:

- normal live-recorded Strava activities sync automatically
- travel or changing local time zone does not break syncing
- duplicate rows are prevented by activity ID
- manual/backdated activities may require a manual cursor reset

## Why we did not fully over-engineer this

There is a theoretically more robust design where the cursor is aggregated and updated exactly once from the newest returned activity across the whole batch.

In practice, for this project:

- activity volume is very low
- the current scenario is simpler to maintain
- the remaining edge case is manual/backdated activities, which can be handled operationally

So the chosen design is pragmatic rather than perfectly general.

## If something breaks later

Check these first:

1. `SyncState!B2`
   Is the cursor later than the missing activity's UTC start time?
2. Strava activity time
   Is the missing activity backdated or manually entered?
3. Make route filter
   Is the cursor update route still filtered on `Activity ID exists`?
4. Apps Script deployment and server configuration
   Does `sheet_url` in the server-only `sites/mattrics/private/config.php` point at the active deployment, and does `sheet_token` match the Apps Script shared secret? Check configuration without printing either value.

## Operational checklist

When editing or rebuilding the sync later, preserve these rules:

- keep the cursor in UTC
- base cursor updates on `Start Date in UTC`
- keep duplicate protection on `Activity ID raw`
- keep the Apps Script URL and token only in the private server configuration; never restore browser `public/config.js`
- do not assume manual activities will always sync automatically

## Migration note

In the current production workflow, Strava remains the editable source where activity title and activity type may be corrected before data reaches Mattrics through Make.com and Google Sheets.

That matters for the migration roadmap:

- future direct Hevy and Garmin Connect connectors are now higher priority than Concept2 import
- duplicate handling between direct Hevy/Garmin rows and Strava/Google Sheet rows is expected
- until a richer field-level provenance model exists, Strava-derived title and activity type should win when duplicate rows represent the same underlying activity
