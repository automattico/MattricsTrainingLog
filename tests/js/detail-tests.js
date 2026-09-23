/**
 * detail-tests.js
 * Run in Node: node tests/js/detail-tests.js
 */

const fs = require("fs");
const path = require("path");
const vm = require("vm");

const repoRoot = path.resolve(__dirname, "..", "..");
let passed = 0;
let failed = 0;

function assert(condition, message) {
  if (condition) {
    passed += 1;
    return;
  }
  failed += 1;
  console.error("FAIL:", message);
}

function createElement(initialClasses = []) {
  const classes = new Set(initialClasses);
  return {
    classList: {
      add: (...names) => names.forEach((name) => classes.add(name)),
      remove: (...names) => names.forEach((name) => classes.delete(name)),
      contains: (name) => classes.has(name),
    },
    innerHTML: "",
    style: {},
    textContent: "",
  };
}

const elements = {
  detailKicker: createElement(),
  detailTitle: createElement(),
  detailDate: createElement(),
  detailMetrics: createElement(),
  detailMeta: createElement(),
  detailWorkoutSection: createElement(["is-hidden"]),
  detailWorkoutList: createElement(),
  detailNotesSection: createElement(["is-hidden"]),
  detailNotes: createElement(),
  detailOverlay: createElement(),
};

const activity = {
  "Activity ID": "workout-1",
  Date: "2026-09-21",
  Description: "Logged with Hevy",
  Name: "Strength workout",
  Type: "WeightTraining",
  "Device Name": "Hevy",
};

const window = {
  Mattrics: {
    state: { allData: [activity] },
    detailFacts: () => [{ val: "1h 24m", lab: "Duration" }],
    esc: (value) => String(value),
    escAttr: (value) => String(value),
    fmtDate: (value) => value,
    getActivityId: (row) => row["Activity ID"],
    getActivityWorkoutBlocks: () => [{ name: "Bench Press", sets: ["80 kg x 5", "80 kg x 5"] }],
    tc: () => ({ color: "purple", icon: "🏋️", label: "Weights" }),
  },
};

const document = {
  body: { style: {} },
  getElementById: (id) => elements[id],
};

vm.runInNewContext(
  fs.readFileSync(path.join(repoRoot, "public/assets/js/detail.js"), "utf8"),
  { document, window },
  { filename: "public/assets/js/detail.js" }
);

window.Mattrics.openDetail("workout-1");
assert(!elements.detailWorkoutSection.classList.contains("is-hidden"), "exercise breakdown is shown when workout blocks exist");
assert(elements.detailWorkoutList.innerHTML.includes("Bench Press"), "exercise breakdown renders exercise names");
assert(elements.detailNotesSection.classList.contains("is-hidden"), "raw Hevy description is hidden when structured blocks exist");

window.Mattrics.getActivityWorkoutBlocks = () => null;
activity.Description = "Felt strong today";
window.Mattrics.openDetail("workout-1");
assert(elements.detailWorkoutSection.classList.contains("is-hidden"), "exercise breakdown is hidden when workout blocks are absent");
assert(!elements.detailNotesSection.classList.contains("is-hidden"), "notes are shown when no workout blocks exist");
assert(elements.detailNotes.textContent === "Felt strong today", "notes retain their content");

console.log(`detail-tests: ${passed} passed, ${failed} failed`);
if (failed) process.exit(1);
