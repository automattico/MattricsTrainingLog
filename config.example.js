// Mattrics Training Log — Configuration
// ─────────────────────────────────────────────────────────────
// Copy this file to config.js and fill in your real values.
// config.js is gitignored and will never be committed.
// ─────────────────────────────────────────────────────────────

window.MATTRICS_CONFIG = {
  // Your Google Apps Script Web App URL
  // Deploy from: Extensions → Apps Script → Deploy → New Deployment
  SHEET_URL: "YOUR_GOOGLE_APPS_SCRIPT_URL_HERE",

  // Required when your Apps Script endpoint is protected by MATTRICS_SHARED_SECRET
  SHEET_TOKEN: "YOUR_SHARED_SHEET_TOKEN_HERE",

  // Your Anthropic API key (for the AI Workout tab)
  // Get one at: https://console.anthropic.com
  API_KEY: "YOUR_ANTHROPIC_API_KEY_HERE",
};
