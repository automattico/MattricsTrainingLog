const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const vm = require("node:vm");

const repo = path.join(__dirname, "../..");
const rows = JSON.parse(fs.readFileSync(0, "utf8"));
const context = vm.createContext({
  window: { Mattrics: {} },
  URL,
  Date: class extends Date {
    constructor(...args) {
      super(...(args.length ? args : ["2020-01-03T12:00:00Z"]));
    }
  },
});
for (const file of ["constants", "state", "date-utils", "formatters", "filters", "exercise-config", "hevy-parser", "fatigue-engine", "fatigue-tiers"]) {
  vm.runInContext(fs.readFileSync(path.join(repo, "public/assets/js/core", file + ".js"), "utf8"), context, { filename: file });
}

const M = context.window.Mattrics;
const activityTypes = JSON.parse(fs.readFileSync(path.join(repo, "private/data/activity-type-configs.json"), "utf8"));
M.indexExerciseConfigs({ activityTypes });
const load = (activities) => M.getMuscleFatigueAnalysis(activities).systemic.rawLoad;
const separate = rows.map((row) => load([row]));
const canonical = load(rows);
assert.equal(separate.length, 2);
assert(separate.every((value) => value > 0));
// The dashboard rounds each reported load to two decimals.
assert(Math.abs(canonical - separate.reduce((a, b) => a + b, 0)) <= 0.015);
assert(load([...rows, ...rows]) > canonical);
process.stdout.write("true");
