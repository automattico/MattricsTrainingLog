// Compare four complete Berlin weeks. The one-argument form is the historical cache regression.
process.env.TZ = "Europe/Berlin";
const fs = require("node:fs");
const path = require("node:path");
const vm = require("node:vm");
const repo = path.resolve(__dirname, "../..");
const read = file => JSON.parse(fs.readFileSync(file, "utf8"));
const [backup, sheetPath, cutoff, sheetCatalogPath, profilePath] = process.argv.slice(2);
if (!backup || Boolean(sheetPath) !== Boolean(cutoff)) {
  throw new Error("Usage: history-parity.js restored-directory [sheet.json YYYY-MM-DD [sheet-catalog.json [settings.json]]]");
}
const legacy = read(sheetPath || path.join(repo, "private/cache/training-data.json"));
const canonical = read(path.join(backup, "dashboard.json"));
const catalog = {
  exercises: read(path.join(backup, "restored-catalog/exercise-configs.json")),
  activityTypes: read(path.join(backup, "restored-catalog/activity-type-configs.json")),
};
const legacyCatalog = sheetCatalogPath ? read(sheetCatalogPath) : catalog;
let day = cutoff || legacy.rows.map(row => row.Date.slice(0, 10)).sort().pop();
if (!/^\d{4}-\d{2}-\d{2}$/.test(day) || Number.isNaN(Date.parse(day))
    || new Date(day).toISOString().slice(0, 10) !== day) {
  throw new Error("Expected a valid comparison end date");
}
const context = vm.createContext({window: {Mattrics: {}}, URL, Date: class extends Date {
  constructor(...args) { super(...(args.length ? args : [day + "T23:59:59"])); }
}});
for (const file of ["constants", "state", "date-utils", "formatters", "filters", "exercise-config", "hevy-parser", "fatigue-engine", "fatigue-tiers"]) {
  vm.runInContext(fs.readFileSync(path.join(repo, "public/assets/js/core", file + ".js"), "utf8"), context);
}
const M = context.window.Mattrics;
if (profilePath) {
  const {settings} = read(profilePath);
  if (!settings || typeof settings !== "object" || Array.isArray(settings)) {
    throw new Error("Expected an authenticated settings response");
  }
  M.state.userSettings = settings;
}
const berlinDay = new Intl.DateTimeFormat("en-CA", {timeZone: "Europe/Berlin", dateStyle: "short"});
function localDate(value) {
  const parsed = new Date(value);
  if (cutoff && /T.*(?:Z|[+-]\d{2}:\d{2})$/i.test(value) && Number.isFinite(parsed.getTime())) {
    return berlinDay.format(parsed);
  }
  return M.normalizeDateValue(value);
}
function totals(data, configs, movingOnly = false) {
  M.indexExerciseConfigs(configs);
  const rows = data.rows.filter(row => row.Date && row.Type)
    .map(row => ({
      ...row,
      Date: localDate(row.Date),
      "Duration (min)": movingOnly && isStationaryTimer(row) ? Number(row["Moving seconds"]) / 60 : row["Duration (min)"],
    })).filter(row => row.Date && row.Date <= day)
    .sort((a, b) => b.Date.localeCompare(a.Date));
  M.state.allData = rows;
  M.setActivityChildren(data.activityChildren || []);
  const week = rows.filter(row => row.Date >= M.shiftDate(day, -6));
  const sum = key => week.reduce((total, row) => total + Number(row[key] || 0), 0);
  const fatigue = M.getMuscleFatigueAnalysis(rows);
  return {
    metrics: [week.length, sum("Duration (min)"), sum("Distance (km)"), fatigue.systemic.rawLoad,
      ...fatigue.regions.map(region => region.fatigueScore)],
    priorSessions: rows.length - week.length,
    stationaryTimerSessions: week.filter(isStationaryTimer).length,
  };
}
function isStationaryTimer(row) {
  return row["Duration basis"] === "Timer" && row["Distance (km)"] === 0 &&
    ["Workout", "WeightTraining"].includes(row.Type) && row["Moving seconds"] != null;
}
function summary([sessions, minutes, km, systemicLoad]) {
  return {sessions, minutes: +minutes.toFixed(1), km: +km.toFixed(2), systemicLoad: +systemicLoad.toFixed(2)};
}
for (let week = 1; week <= 4; week++) {
  const a = totals(legacy, legacyCatalog), b = totals(canonical, catalog);
  const moving = totals(canonical, catalog, true);
  const delta = b.metrics.map((value, i) => value - a.metrics[i]);
  if (!delta.every(Number.isFinite)) throw new Error("Non-numeric parity result");
  console.log(JSON.stringify({
    start: M.shiftDate(day, -6), end: day, weeksBack: week - 1,
    sheet: summary(a.metrics), postgres: summary(b.metrics),
    sessionsDelta: delta[0], minutesDelta: +delta[1].toFixed(1), kmDelta: +delta[2].toFixed(2),
    systemicLoadDelta: +delta[3].toFixed(2), maxRegionScoreDelta: Math.max(...delta.slice(4).map(Math.abs)),
    priorSessions: {sheet: a.priorSessions, postgres: b.priorSessions},
    stationaryTimerSessions: b.stationaryTimerSessions,
    stationaryTimerMinutesDelta: +(b.metrics[1] - moving.metrics[1]).toFixed(1),
    stationaryTimerSystemicLoadDelta: +(b.metrics[3] - moving.metrics[3]).toFixed(2),
    stationaryTimerMaxRegionScoreDelta: Math.max(...b.metrics.slice(4).map((score, i) => Math.abs(score - moving.metrics[i + 4]))),
  }));
  day = M.shiftDate(day, -7);
}
