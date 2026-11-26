const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const vm = require("node:vm");

const banner = { hidden: true, textContent: "" };
const stamp = { innerHTML: "" };
const refresh = {
  setAttribute(name, value) { this[name] = value; },
};
const Mattrics = {
  state: {},
  esc: String,
  parseTimestamp: (value) => value ? new Date(value) : null,
  resolveHeaderTimestamp: (meta) => Mattrics.parseTimestamp(meta.lastSuccessfulSyncAt || meta.lastFetchAt),
  fmtRelativeAge: () => "19 mins ago",
  fmtBerlinDate: (timestamp) => timestamp.toISOString().slice(0, 10),
  fmtBerlinTime: () => "17:35:43 CET",
};
vm.runInNewContext(
  fs.readFileSync(path.join(__dirname, "../../public/assets/js/renderers/loader.js"), "utf8"),
  {
    window: { Mattrics, setInterval: () => 1, clearInterval: () => {} },
    document: {
      getElementById: (id) => ({ dataStatusBanner: banner, dataSyncStamp: stamp, refreshDataButton: refresh })[id],
    },
  },
);

function render(activities, catalog, configWarning = null, dataMeta = {}) {
  Mattrics.state.dataMeta = { source: activities, ...dataMeta };
  Mattrics.state.exerciseConfigMeta = { source: catalog, warning: configWarning };
  Mattrics.renderDataStatus();
}

render("canonical", "linked-workout-json");
assert.equal(banner.hidden, true);
assert.match(stamp.innerHTML, /Workouts: Local Postgres/);
assert.match(stamp.innerHTML, /Catalog: Local JSON/);
assert.match(stamp.innerHTML, /Last import unavailable/);
assert.equal(refresh.title, "Reload imported workouts");
assert.equal(refresh["aria-label"], "Reload imported workouts");

render("canonical", "linked-workout-json", null, {
  lastSuccessfulSyncAt: "2026-11-23T16:35:43Z",
  lastFetchAt: "2026-11-24T17:00:00Z",
});
assert.match(stamp.innerHTML, /Last import: 19 mins ago/);
assert.match(stamp.innerHTML, /2026-11-23, 17:35:43 CET/);

render("canonical", "linked-workout-json", null, { lastFetchAt: "2026-11-24T17:00:00Z" });
assert.match(stamp.innerHTML, /Last import unavailable/);

render("canonical", "linked-workout-json", "Catalog read failed.");
assert.equal(banner.hidden, false);
assert.equal(banner.textContent, "Config: Catalog read failed.");

render("canonical", "legacy");
assert.equal(banner.hidden, false);
assert.match(banner.textContent, /Read-source mismatch/);

render("cache", "canonical");
assert.equal(banner.hidden, false);
assert.match(banner.textContent, /Read-source mismatch/);

assert.equal(refresh.title, "Refresh from sheet");
assert.equal(refresh["aria-label"], "Refresh data");

async function checkRefreshFilters() {
  let rows = [{ Date: "2026-11-24", Type: "Trail Run" }];
  let finishRequest;
  let request;
  const app = {
    DATA_URL: "/api/data",
    EXERCISE_CONFIG_URL: "/api/exercises",
    state: { typeFilter: "All" },
    canonicalType: (type) => type === "Trail Run" ? "Run" : type,
    normalizeDateValue: (date) => date,
    apiFetch: (url, options) => new Promise(resolve => {
      request = { url: new URL(url), options };
      finishRequest = () => resolve({ ok: true, text: async () => JSON.stringify({ rows }) });
    }),
    loadExerciseConfigs: async () => {},
    scanAndSyncUnknownExercises: async () => {},
    loadUserSettings: async () => {},
    showApp() {},
    showLoading() {},
    showError(message) { throw new Error(message); },
    renderDataStatus() {},
  };
  vm.runInNewContext(
    fs.readFileSync(path.join(__dirname, "../../public/assets/js/renderers/orchestrator.js"), "utf8"),
    {
      window: { Mattrics: app, location: { href: "http://localhost/" } },
      document: { addEventListener() {} },
      URL,
    },
  );
  app.renderAll = () => {};

  const refresh = app.fetchData({ background: true });
  assert.equal(request.options.method, "GET", "Dashboard reads use GET");
  app.state.typeFilter = "Run";
  finishRequest();
  await refresh;
  assert.equal(app.state.typeFilter, "Run", "Background refresh preserves a filter selected while the request was open");

  rows = [{ Date: "2026-11-24", Type: "Ride" }];
  const removed = app.fetchData({ background: true });
  finishRequest();
  await removed;
  assert.equal(app.state.typeFilter, "All", "A removed filter falls back to All");

  app.state.typeFilter = "Ride";
  const foreground = app.fetchData();
  finishRequest();
  await foreground;
  assert.equal(app.state.typeFilter, "All", "Foreground refresh retains its existing reset behavior");

  const force = app.fetchData({ forceRefresh: true });
  assert.equal(request.options.method, "POST", "Explicit same-origin refresh uses the protected POST path");
  assert.equal(request.options.redirect, "error", "A refresh cannot forward CSRF through redirects");
  assert.equal(request.url.searchParams.get("includeChildren"), "1");
  assert.equal(request.url.searchParams.has("refresh"), false);
  finishRequest();
  await force;

  app.DATA_URL = "https://sheet.example/api/data";
  const external = app.fetchData({ forceRefresh: true });
  assert.equal(request.options.method, "GET", "External source requests keep their existing read method");
  assert.equal(request.url.searchParams.get("refresh"), "1");
  finishRequest();
  await external;
}

checkRefreshFilters().then(() => {
  console.log("data-status-tests: status, filters and refresh routing passed");
}).catch(error => {
  console.error(error);
  process.exitCode = 1;
});
