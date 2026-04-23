/**
 * exercise-config-tests.js
 * Run in Node: node public/tests/exercise-config-tests.js
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

function loadScriptContext(files) {
  const window = {
    location: {
      protocol: "http:",
      href: "http://localhost/",
    },
    MATTRICS_CONFIG: {},
    Mattrics: {},
  };

  const context = vm.createContext({
    console,
    Date,
    Intl,
    Math,
    Map,
    Set,
    URL,
    URLSearchParams,
    fetch: async () => {
      throw new Error("fetch should not be called in unit tests");
    },
    requestAnimationFrame: (fn) => fn(),
    window,
    document: {},
    globalThis: null,
    self: null,
  });

  context.globalThis = context;
  context.self = window;

  files.forEach((file) => {
    const source = fs.readFileSync(path.join(repoRoot, file), "utf8");
    vm.runInContext(source, context, { filename: file });
  });

  return context.window.Mattrics;
}

const M = loadScriptContext([
  "public/assets/js/core/constants.js",
  "public/assets/js/core/state.js",
  "public/assets/js/core/date-utils.js",
  "public/assets/js/core/formatters.js",
  "public/assets/js/core/filters.js",
  "public/assets/js/core/exercise-config.js",
  "public/assets/js/core/hevy-parser.js",
  "public/assets/js/core/fatigue-engine.js",
  "public/assets/js/core/fatigue-tiers.js",
  "public/assets/js/exercise-admin.js",
]);

const payload = {
  exercises: JSON.parse(fs.readFileSync(path.join(repoRoot, "private/data/exercise-configs.json"), "utf8")),
  activityTypes: JSON.parse(fs.readFileSync(path.join(repoRoot, "private/data/activity-type-configs.json"), "utf8")),
  unknowns: JSON.parse(fs.readFileSync(path.join(repoRoot, "private/data/exercise-unknowns.json"), "utf8")),
  meta: { seedVersion: 1, loadedAt: "2026-04-20T00:00:00Z" },
};

M.indexExerciseConfigs(payload);

assert(
  M.EXERCISE_ADMIN_DELETE_CONFIRMATION_COPY === "Are you sure you want to permanently delete this exercise? Once deleted it cannot be recovered!",
  "exercise admin exposes the exact delete confirmation copy"
);

assert(M.normalizeExerciseConfigName("CableLateralRaise") === "cable lateral raise", "camelCase names are split and lowercased");
assert(M.normalizeExerciseConfigName("  Push-Up!!!  ") === "push up", "punctuation and repeated whitespace collapse");

assert(M.getExerciseMuscleLevelKeyForWeight(0) === "", "zero weight maps to not involved");
assert(M.getExerciseMuscleLevelKeyForWeight(0.14) === "stabilizer", "stabilizer threshold includes 0.14");
assert(M.getExerciseMuscleLevelKeyForWeight(0.20) === "minor", "minor threshold maps correctly");
assert(M.getExerciseMuscleLevelKeyForWeight(0.45) === "secondary", "secondary threshold maps correctly");
assert(M.getExerciseMuscleLevelKeyForWeight(0.65) === "strongSecondary", "strong secondary threshold maps correctly");
assert(M.getExerciseMuscleLevelKeyForWeight(0.83) === "primary", "primary threshold maps values above 0.82");
assert(M.getExerciseMuscleWeightForLevelKey("secondary") === 0.45, "secondary level maps back to canonical weight");
assert(M.getExerciseMuscleUiLevel(M.EXERCISE_MUSCLE_DEFAULT_LEVEL).label === "Secondary", "default checked level is Secondary");

const legacyEditorState = M.getExerciseMuscleEditorState({
  upperBack: 0.82,
  trapezius: 0.58,
  biceps: 0.34,
  deltoids: 0.22,
  lowerBack: 0.12,
});
assert(legacyEditorState.upperBack.levelKey === "primary", "highest legacy weight is promoted to primary when none cross the threshold");
assert(legacyEditorState.upperBack.weight === 1, "promoted primary snaps to canonical primary weight");
assert(legacyEditorState.trapezius.levelKey === "strongSecondary", "supporting legacy weights map to semantic levels");

const tiedPrimaryEditorState = M.getExerciseMuscleEditorState({
  chest: 0.78,
  deltoids: 0.78,
  triceps: 0.2,
});
assert(
  tiedPrimaryEditorState.chest.levelKey === "primary" && tiedPrimaryEditorState.deltoids.levelKey === "primary",
  "tied highest legacy weights are both promoted to primary"
);
assert(M.hasExerciseMusclePrimary({
  chest: 1,
  deltoids: 0.45,
  triceps: 0.2,
}), "primary detection succeeds for canonical semantic weights");

const canonicalExercise = M.resolveExerciseConfig("Bench Press");
assert(canonicalExercise && canonicalExercise.canonicalName === "Bench Press", "exercise canonical match resolves");

const aliasExercise = M.resolveExerciseConfig("Push-Up");
assert(aliasExercise && aliasExercise.canonicalName === "Bench Press", "exercise alias match is case- and punctuation-insensitive");

const substringExercise = M.resolveExerciseConfig("Smith Machine Bench Press");
assert(substringExercise && substringExercise.canonicalName === "Bench Press", "exercise matchTerms preserve substring resolution");

const lateralRaiseExercise = M.resolveExerciseConfig("CableLateralRaise");
assert(lateralRaiseExercise && lateralRaiseExercise.canonicalName === "Shoulder Press", "normalized exercise names still resolve through matchTerms");

const canoeType = M.resolveActivityTypeConfig("Canoe");
assert(canoeType && canoeType.canonicalName === "Canoeing", "activity type alias resolves to canonical config");

const waterSportType = M.resolveActivityTypeConfig("WaterSport");
assert(waterSportType && waterSportType.canonicalName === "WaterSport", "WaterSport stays distinct from Rowing");

const rowingType = M.resolveActivityTypeConfig("Rowing");
assert(rowingType && rowingType.canonicalName === "Rowing", "Rowing resolves independently");

assert(M.resolveActivityTypeConfig("Unknown Activity Type") === null, "unknown activity types return null");

const bodyweightSet = M.parseHevySetLine("12 reps", "Push Up");
assert(bodyweightSet.kind === "parsed" && bodyweightSet.load > 0, "bodyweight-eligible exercise configs still parse rep-only sets");

const hevyStimulus = M.getActivityMuscleStimulus({
  Type: "WeightTraining",
  "Duration (min)": "45",
  Description: "Logged with Hevy\n\nBench Press\n80 kg x 5\n80 kg x 5",
});
assert(hevyStimulus.chest > 0, "Hevy exercise configs still produce chest stimulus");
assert(hevyStimulus.triceps > 0, "Hevy exercise configs still produce supporting muscle stimulus");

const runStimulus = M.getActivityMuscleStimulus({
  Type: "Run",
  "Duration (min)": "60",
  Description: "",
});
assert(runStimulus.quadriceps > 0, "activity type configs still drive non-Hevy stimulus");
assert(runStimulus.calves > 0, "activity type configs preserve multi-muscle load");

const unknowns = M.collectUnknownExercisesFromActivities([
  {
    Type: "WeightTraining",
    Date: M.toIsoDate(new Date()),
    Description: "Logged with Hevy\n\nPhantom Apparatus\n20 kg x 8\n\nphantom-apparatus\n18 kg x 10\n\nBench Press\n80 kg x 5",
  },
  {
    Type: "Unknown Activity Type",
    Date: M.toIsoDate(new Date()),
    Description: "",
  },
]);
const unknownExercise = unknowns.find((item) => item.id === "exercise:phantom apparatus");
const unknownActivityType = unknowns.find((item) => item.id === "activityType:unknown activity type");

assert(unknowns.length === 2, "unknown scan groups unresolved exercises and activity types");
assert(unknownExercise && unknownExercise.timesSeen === 2, "unknown exercise scan aggregates repeated normalized names");
assert(unknownExercise && unknownExercise.rawNames.length === 2, "unknown exercise scan preserves distinct raw names");
assert(unknownActivityType && unknownActivityType.timesSeen === 1, "unknown activity type scan includes unresolved non-Hevy types");

const mixedHevyStimulus = M.getActivityMuscleStimulus({
  Type: "WeightTraining",
  "Duration (min)": "45",
  Description: "Logged with Hevy\n\nPhantom Apparatus\n20 kg x 8\n\nBench Press\n80 kg x 5",
});
assert(mixedHevyStimulus.chest > 0, "mixed Hevy sessions still produce stimulus for resolved exercises");

const todayIso = M.toIsoDate(new Date());
const recentFatigue = M.getMuscleFatigueAnalysis([
  {
    Type: "WeightTraining",
    Date: todayIso,
    "Duration (min)": "45",
    Description: "Logged with Hevy\n\nPhantom Apparatus\n20 kg x 8\n\nBench Press\n80 kg x 5",
  },
  {
    Type: "Unknown Activity Type",
    Date: M.shiftDate(todayIso, -1),
    "Duration (min)": "30",
    Description: "",
  },
  {
    Type: "Another Unknown Type",
    Date: M.shiftDate(todayIso, -20),
    "Duration (min)": "30",
    Description: "",
  },
]);
assert(recentFatigue.unresolved && recentFatigue.unresolved.hasUnresolved, "fatigue analysis exposes unresolved warning state");
assert(recentFatigue.unresolved.count === 2, "fatigue unresolved warning only includes unknowns inside the fatigue window");
assert(
  recentFatigue.unresolved.items.some((item) => item.id === "exercise:phantom apparatus")
    && recentFatigue.unresolved.items.some((item) => item.id === "activityType:unknown activity type"),
  "fatigue unresolved items include both exercise and activity type unknowns"
);
assert(
  !recentFatigue.unresolved.items.some((item) => item.id === "activityType:another unknown type"),
  "fatigue unresolved items exclude unknowns outside the recent fatigue window"
);

M.state.unknownExercises = [
  {
    id: "exercise:phantom apparatus",
    sourceType: "exercise",
    normalizedName: "phantom apparatus",
    rawNames: ["Phantom Apparatus"],
    timesSeen: 2,
    firstSeenAt: "2026-04-20T00:00:00Z",
    lastSeenAt: "2026-04-20T00:00:00Z",
    aiStatus: "not_requested",
  },
  {
    id: "exercise:bird dog",
    sourceType: "exercise",
    normalizedName: "bird dog",
    rawNames: ["Bird Dog"],
    timesSeen: 1,
    firstSeenAt: "2026-04-21T00:00:00Z",
    lastSeenAt: "2026-04-22T00:00:00Z",
    aiStatus: "failed",
  },
];
M.state.exerciseConfigs = [
  {
    id: "bench-press",
    canonicalName: "Bench Press",
    aliases: ["Push Up"],
    matchTerms: ["bench", "push up"],
    muscleWeights: { chest: 1 },
    fatigueMultiplier: 1,
    bodyweightEligible: true,
    setTypeHandling: "weight_reps",
    source: "manual",
    lastUpdatedAt: "2026-04-20T00:00:00Z",
  },
  {
    id: "hammer-curl",
    canonicalName: "Hammer Curl",
    aliases: ["Neutral Grip Curl"],
    matchTerms: ["hammer curl"],
    muscleWeights: { biceps: 1 },
    fatigueMultiplier: 0.85,
    bodyweightEligible: false,
    setTypeHandling: "weight_reps",
    source: "ai_suggested",
    lastUpdatedAt: "2026-04-21T00:00:00Z",
  },
];
M.state.allData = [
  {
    "Activity ID": "workout-1",
    Name: "Upper Body",
    Type: "WeightTraining",
    Date: "2026-04-23T09:43:00Z",
    Description: "Logged with Hevy\n\nPhantom Apparatus\n22 kg x 8\n24 kg x 6\n\nBench Press\n80 kg x 5",
  },
  {
    "Activity ID": "workout-2",
    Name: "Gym Session",
    Type: "WeightTraining",
    Date: "2026-04-22T18:05:00Z",
    Description: "Logged with Hevy\n\nPhantom Apparatus\n20 kg x 10\n\nBird Dog\n12 reps",
  },
  {
    "Activity ID": "workout-3",
    Name: "Technique Day",
    Type: "WeightTraining",
    Date: "2026-04-20T19:01:00Z",
    Description: "Logged with Hevy\n\nPhantom Apparatus\n18 kg x 12",
  },
];

const listItems = M.getExerciseAdminListItems("", "all");
assert(listItems.length === 4, "exercise admin merged list includes unknown and configured exercises");
assert(
  listItems[0].type === "unknown" && listItems[1].type === "unknown" && listItems[2].type === "exercise",
  "exercise admin merged list keeps unknown exercises ahead of configured exercises"
);
assert(
  listItems[0].record.id === "exercise:bird dog" && listItems[1].record.id === "exercise:phantom apparatus",
  "exercise admin merged list orders unknown exercises by newest lastSeenAt first"
);

const filteredExercises = M.filterExerciseAdminList(M.state.exerciseConfigs, "hammer", "all");
assert(filteredExercises.length === 1 && filteredExercises[0].id === "hammer-curl", "exercise admin list filtering respects query");

const filteredMergedList = M.getExerciseAdminListItems("bird", "all");
assert(
  filteredMergedList.length === 1 && filteredMergedList[0].type === "unknown" && filteredMergedList[0].record.id === "exercise:bird dog",
  "exercise admin merged search matches unknown exercises"
);

const filteredConfiguredList = M.getExerciseAdminListItems("neutral grip", "all");
assert(
  filteredConfiguredList.length === 1 && filteredConfiguredList[0].type === "exercise" && filteredConfiguredList[0].record.id === "hammer-curl",
  "exercise admin merged search matches configured exercise aliases"
);

const mergedNameMapping = M.mapExerciseAdminMergedNames(
  {
    aliasesText: "Hammer Curl\nNeutral Grip Curl",
    matchTermsText: "hammer curl\ncurl",
  },
  "Hammer Curl\nNeutral Grip Curl\ncurl\nRope Curl"
);
assert(
  mergedNameMapping.aliasesText === "Hammer Curl\nNeutral Grip Curl\nRope Curl",
  "merged names keep existing aliases and store new entries as aliases"
);
assert(
  mergedNameMapping.matchTermsText === "Hammer Curl\ncurl",
  "merged names preserve legacy match terms for items that already used substring matching"
);

const removedNameMapping = M.mapExerciseAdminMergedNames(
  {
    aliasesText: "Hammer Curl\nNeutral Grip Curl",
    matchTermsText: "hammer curl\ncurl",
  },
  "curl"
);
assert(
  removedNameMapping.aliasesText === "",
  "merged names remove deleted items from the alias list"
);
assert(
  removedNameMapping.matchTermsText === "curl",
  "merged names remove deleted items from whichever stored lists contained them"
);

const editorHtml = M.renderExerciseAdminEditorHtmlForTest(M.state.exerciseConfigs[1]);
assert(
  !editorHtml.includes("exerciseCanonicalName") && !editorHtml.includes('name="canonicalName"'),
  "exercise editor no longer renders a standalone canonical-name field"
);
assert(
  editorHtml.includes('title="Edit exercise name"') && editorHtml.includes("data-exercise-admin-title-edit-start"),
  "exercise editor renders the title pen control with the edit exercise name tooltip"
);
assert(
  editorHtml.includes("data-exercise-admin-names-edit-start") && !editorHtml.includes("data-exercise-admin-names-text"),
  "exercise editor shows recognition names in display mode by default"
);
assert(
  editorHtml.includes("data-exercise-admin-merge-open") && !editorHtml.includes("data-exercise-admin-merge-target"),
  "exercise editor keeps merge controls collapsed until explicitly opened"
);
assert(
  editorHtml.includes("data-exercise-admin-delete-open") && !editorHtml.includes("data-exercise-admin-delete-confirm"),
  "exercise editor opens delete via app state instead of showing the destructive confirm inline"
);
assert(
  editorHtml.includes("data-exercise-admin-regenerate") && !editorHtml.includes("data-exercise-admin-approve"),
  "exercise editor replaces approve actions with regenerate suggestion"
);
assert(
  !editorHtml.includes("Approved") && !editorHtml.includes("Draft") && !editorHtml.includes("Review needed"),
  "exercise editor hides configured lifecycle badges"
);

const unknownEditorHtml = M.renderExerciseAdminUnknownEditorHtmlForTest(M.state.unknownExercises[0]);
assert(
  unknownEditorHtml.includes("data-exercise-admin-suggest") && unknownEditorHtml.includes("data-exercise-admin-save"),
  "unknown editor supports generate suggestion and save actions"
);
assert(
  !unknownEditorHtml.includes("This exercise is unresolved in the fatigue mapping")
    && !unknownEditorHtml.includes("New exercise detected")
    && !unknownEditorHtml.includes("No suggestion yet")
    && !unknownEditorHtml.includes("Normalized name")
    && !unknownEditorHtml.includes("Observed raw names")
    && !unknownEditorHtml.includes("Seen 2 times")
    && !unknownEditorHtml.includes("First seen")
    && !unknownEditorHtml.includes("Last seen"),
  "unknown editor removes redundant onboarding and metadata copy"
);
assert(
  unknownEditorHtml.includes("Found in")
    && !unknownEditorHtml.includes("Recent workouts")
    && unknownEditorHtml.includes('data-activity-id="workout-1"')
    && unknownEditorHtml.includes('data-activity-id="workout-2"')
    && unknownEditorHtml.includes('data-activity-id="workout-3"')
    && unknownEditorHtml.includes("🏋️")
    && unknownEditorHtml.includes("Upper Body")
    && !unknownEditorHtml.includes("Phantom Apparatus: 22 kg x 8, 24 kg x 6"),
  "unknown editor lists compact clickable workout references without matched set previews"
);

const shellHtml = M.renderExerciseAdminShellHtmlForTest({
  query: "",
  selectedKey: "unknown:exercise:bird dog",
});
const unknownListItemHtml = M.renderExerciseAdminListItemHtmlForTest({
  key: "unknown:exercise:bird dog",
  type: "unknown",
  record: M.state.unknownExercises[1],
});
assert(
  shellHtml.includes("Exercises")
    && shellHtml.includes("Unconfigured")
    && !shellHtml.includes("Unknown exercises")
    && !shellHtml.includes("Mapped")
    && !shellHtml.includes("Configured exercises")
    && !shellHtml.includes("Needs attention now")
    && !shellHtml.includes("All exercise configs")
    && !shellHtml.includes("Exercise config admin")
    && !shellHtml.includes("settings-heading exercise-admin-heading")
    && !shellHtml.includes("Review newly detected exercises, fill in fatigue mappings, and adjust configured exercises without leaving the dashboard.")
    && !shellHtml.includes("Exercise list")
    && !shellHtml.includes(" visible"),
  "exercise admin shell moves compact summary metrics into the list header and removes the standalone page header"
);
assert(
  shellHtml.indexOf("Bird Dog") < shellHtml.indexOf("Phantom Apparatus") && shellHtml.indexOf("Phantom Apparatus") < shellHtml.indexOf("Bench Press"),
  "exercise admin shell renders unknown exercises above configured exercises in the merged list"
);
assert(
  !unknownListItemHtml.includes("data-exercise-admin-suggest"),
  "exercise admin merged list removes list-level generate suggestion actions"
);
assert(
  !unknownListItemHtml.includes("exercise-admin-list-meta")
    && !unknownListItemHtml.includes("exercise-admin-list-submeta")
    && !unknownListItemHtml.includes("No suggestion yet")
    && !unknownListItemHtml.includes("Last seen"),
  "unknown exercise list items hide seen and suggestion metadata"
);

const expandedMergeHtml = M.renderExerciseAdminEditorHtmlForTest(M.state.exerciseConfigs[1], {
  mergePanelKey: "exercise:hammer-curl",
  mergeTargetId: "bench-press",
});
assert(
  expandedMergeHtml.includes("data-exercise-admin-merge-target") && expandedMergeHtml.includes("data-exercise-admin-merge-cancel"),
  "exercise editor reveals merge target controls only after merge is opened"
);

const deleteDialogHtml = M.renderExerciseAdminConfirmDialogHtmlForTest({
  confirmDialog: {
    open: true,
    type: "delete",
    key: "exercise:hammer-curl",
  },
});
assert(
  deleteDialogHtml.includes(M.EXERCISE_ADMIN_DELETE_CONFIRMATION_COPY) && deleteDialogHtml.includes("data-exercise-admin-delete-confirm"),
  "exercise admin renders an in-app delete confirmation dialog with the required copy"
);

const total = passed + failed;
console.log(`Exercise config tests: ${passed}/${total} passed`);

if (failed > 0) {
  process.exit(1);
}
