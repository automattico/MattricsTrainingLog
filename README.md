# Training Mattrics

Personal training dashboard — pulls activity data from Google Sheets (via Strava export), displays it with time-windowed views, and generates AI workout suggestions.

## Stack

- Vanilla HTML/CSS/JS — single file, no build step
- Google Apps Script — serves sheet data as JSON
- Anthropic Claude API — AI workout generator

## Setup

### 1. Google Apps Script

1. Open your Google Sheet
2. **Extensions → Apps Script**
3. Paste the contents of `apps-script/Code.gs`
4. **Deploy → New Deployment**
   - Type: Web App
   - Execute as: Me
   - Who has access: Anyone
5. Copy the Web App URL

### 2. Config

```bash
cp config.example.js config.js
```

Edit `config.js` and fill in:
- `SHEET_URL` — your Apps Script Web App URL from step 1
- `API_KEY` — your Anthropic API key from [console.anthropic.com](https://console.anthropic.com)

`config.js` is gitignored and stays local only.

### 3. Run

Open `dashboard.html` in a browser. No server needed.

## Data sources

Currently supported:
- **Strava** (via Google Sheets export)

Planned:
- Hevy (strength training)
- Garmin / fitness watch

## Project

Built by [Matt Wieland](https://mwieland.com)
