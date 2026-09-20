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
M.indexExerciseConfigs({
  ...payload,
  meta: {
    seedVersion: 1,
    loadedAt: "2026-04-20T00:00:00Z",
    source: "canonical",
    warning: "",
    lastSuccessfulSyncAt: "2026-06-07T08:00:00Z",
  },
});
M.indexExerciseConfigs({
  ...payload,
  meta: {
    seedVersion: 2,
    loadedAt: "2026-04-21T00:00:00Z",
  },
});

assert(
  M.EXERCISE_ADMIN_DELETE_CONFIRMATION_COPY === "Are you sure you want to permanently delete this exercise? Once deleted it cannot be recovered!",
  "exercise admin exposes the exact delete confirmation copy"
);
assert(M.state.exerciseConfigMeta.source === "canonical", "exercise config meta preserves the last read source when write responses omit it");
assert(M.state.exerciseConfigMeta.lastSuccessfulSyncAt === "2026-06-07T08:00:00Z", "exercise config meta preserves the last canonical sync timestamp when write responses omit it");

assert(M.normalizeExerciseConfigName("CableLateralRaise") === "cable lateral raise", "camelCase names are split and lowercased");
assert(M.normalizeExerciseConfigName("HIITRun") === "hiit run", "acronym-prefixed camelCase names are split");
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
assert(lateralRaiseExercise && lateralRaiseExercise.canonicalName === "Lateral Raise", "normalized exercise names prefer a dedicated lateral raise mapping");

const externalRotationExercise = M.resolveExerciseConfig("External Shoulder Rotation (Cable or Band)");
assert(externalRotationExercise && externalRotationExercise.canonicalName === "External Shoulder Rotation", "specific rotator cuff work resolves instead of staying unknown");

const stretchingExercise = M.resolveExerciseConfig("Stretching");
assert(stretchingExercise && stretchingExercise.fatigueImpact === "none", "stretching can be configured as recognized but excluded from fatigue");

const cyclingExercise = M.resolveExerciseConfig("Cycling");
assert(cyclingExercise && cyclingExercise.setTypeHandling === "time_duration", "cycling can use duration-based Hevy set handling");

const canoeType = M.resolveActivityTypeConfig("Canoe");
assert(canoeType && canoeType.canonicalName === "Canoeing", "activity type alias resolves to canonical config");

const waterSportType = M.resolveActivityTypeConfig("WaterSport");
assert(waterSportType && waterSportType.canonicalName === "WaterSport", "WaterSport stays distinct from Rowing");

const rowingType = M.resolveActivityTypeConfig("Rowing");
assert(rowingType && rowingType.canonicalName === "Rowing", "Rowing resolves independently");
const trailRunType = M.resolveActivityTypeConfig("Trail Running");
assert(trailRunType && trailRunType.canonicalName === "Run", "high-confidence Garmin running aliases resolve to Run");
const indoorRowingType = M.resolveActivityTypeConfig("Indoor Rowing");
assert(indoorRowingType && indoorRowingType.canonicalName === "Rowing", "indoor rowing aliases resolve to Rowing");
const strengthWorkoutType = M.resolveActivityTypeConfig("Strength_Workout");
assert(strengthWorkoutType && strengthWorkoutType.canonicalName === "WeightTraining", "normalized strength workout aliases resolve to WeightTraining");

assert(M.resolveActivityTypeConfig("Unknown Activity Type") === null, "unknown activity types return null");

const bodyweightSet = M.parseHevySetLine("12 reps", "Push Up");
assert(bodyweightSet.kind === "parsed" && bodyweightSet.load > 0, "bodyweight-eligible exercise configs still parse rep-only sets");

const hevyAppExercises = M.parseHevyDescription("Logged with HevyApp.com\n\nBench Press\n80 kg x 5");
assert(
  Array.isArray(hevyAppExercises) && hevyAppExercises.length === 1 && hevyAppExercises[0].name === "Bench Press",
  "Hevy parser recognizes the HevyApp.com export header"
);

const todayIso = M.toIsoDate(new Date());
const canonicalChildActivity = {
  Type: "WeightTraining",
  Date: todayIso,
  "Duration (min)": "45",
  Description: "Logged with Hevy\n\nWrong Exercise\n20 kg x 8",
  "Activity ID": "canonical-activity-1",
  "Activity ID raw": "canonical-raw-1",
};
M.setActivityChildren([
  {
    activityId: "canonical-activity-1",
    activityIdRaw: "canonical-raw-1",
    exercises: [
      {
        exerciseId: "db-bench",
        canonicalExerciseName: "Bench Press",
        sourceExerciseName: "Bench Press",
        normalizedSourceExerciseName: "bench press",
        sets: [
          {
            id: "set-1",
            setOrder: 1,
            parsedKind: "parsed",
            sourceSetText: "80 kg x 5",
            reps: 5,
            weightKg: 80,
            durationMinutes: null,
            distanceKm: null,
            rpe: 8.5,
            effortFactor: 1.35,
            computedLoad: 540,
            notes: null,
          },
        ],
      },
    ],
  },
]);
const canonicalWorkoutBlocks = M.getActivityWorkoutBlocks(canonicalChildActivity);
assert(
  Array.isArray(canonicalWorkoutBlocks)
    && canonicalWorkoutBlocks.length === 1
    && canonicalWorkoutBlocks[0].name === "Bench Press"
    && canonicalWorkoutBlocks[0].sets[0] === "80 kg x 5",
  "activity workout blocks prefer canonical child rows over description parsing"
);
assert(
  M.state.activityChildrenById["canonical-raw-1"] && M.state.activityChildrenById["canonical-activity-1"],
  "activity child rows are indexed by both raw and stable activity ids"
);

M.setActivityChildren([]);
const fallbackWorkoutBlocks = M.getActivityWorkoutBlocks({
  Type: "WeightTraining",
  Date: todayIso,
  "Duration (min)": "45",
  Description: "Logged with Hevy\n\nBench Press\n80 kg x 5",
});
assert(
  Array.isArray(fallbackWorkoutBlocks)
    && fallbackWorkoutBlocks.length === 1
    && fallbackWorkoutBlocks[0].name === "Bench Press"
    && fallbackWorkoutBlocks[0].source === "description",
  "activity workout blocks fall back to description parsing when canonical child rows are unavailable"
);

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

assert(canonicalExercise.exerciseFamily === "horizontal_press", "exercise configs expose internal exercise family metadata");
assert(canonicalExercise.fatigueArchetype === "freeweight_compound", "exercise configs expose internal fatigue archetype metadata");
assert(rowingType.fatigueArchetype === "conditioning_hybrid", "activity type configs expose internal fatigue archetype metadata");

const previousUserSettings = M.state.userSettings;
M.state.userSettings = {
  bodyWeightKg: 90,
  defaultRpe: 8.5,
  experienceLevel: "Intermediate",
};

const personalizedBodyweightSet = M.parseHevySetLine("12 reps", "Push Up");
assert(
  personalizedBodyweightSet.kind === "parsed"
    && personalizedBodyweightSet.weightKg === 36
    && personalizedBodyweightSet.rpe === 8.5,
  "set parsing uses saved bodyweight and default RPE fallbacks"
);

const explicitRpeSet = M.parseHevySetLine("80 kg x 5 @9", "Bench Press");
assert(explicitRpeSet.kind === "parsed" && explicitRpeSet.rpe === 9, "set parsing prefers explicit RPE over the user fallback");

const weightForRepsSet = M.parseHevySetLine("80 kg for 5 reps", "Bench Press");
assert(
  weightForRepsSet.kind === "parsed" && weightForRepsSet.weightKg === 80 && weightForRepsSet.reps === 5,
  "set parsing handles weight-for-reps formats without falling back"
);

const timeSet = M.parseHevySetLine("6km - 12min", "Cycling");
assert(timeSet.kind === "time" && timeSet.minutes === 12 && timeSet.distanceKm === 6, "time-based Hevy cardio sets expose duration and distance");

const referenceHistory = [
  {
    Type: "WeightTraining",
    Date: M.shiftDate(todayIso, -3),
    "Duration (min)": "45",
    Description: "Logged with Hevy\n\nBench Press\n60 kg x 5\n62.5 kg x 5\n65 kg x 5",
  },
  {
    Type: "WeightTraining",
    Date: M.shiftDate(todayIso, -2),
    "Duration (min)": "45",
    Description: "Logged with Hevy\n\nIncline Press\n50 kg x 5\n50 kg x 5\n52.5 kg x 5",
  },
];

const canonicalReferenceHistory = [
  {
    Type: "WeightTraining",
    Date: M.shiftDate(todayIso, -3),
    "Duration (min)": "45",
    Description: "",
    "Activity ID raw": "canonical-history-1",
  },
  {
    Type: "WeightTraining",
    Date: M.shiftDate(todayIso, -2),
    "Duration (min)": "45",
    Description: "",
    "Activity ID raw": "canonical-history-2",
  },
];
M.setActivityChildren([
  {
    activityIdRaw: "canonical-history-1",
    activityId: "history-1",
    exercises: [
      {
        exerciseId: "db-bench",
        canonicalExerciseName: "Bench Press",
        sourceExerciseName: "Bench Press",
        normalizedSourceExerciseName: "bench press",
        sets: [
          { id: "1", setOrder: 1, parsedKind: "parsed", sourceSetText: "60 kg x 5", reps: 5, weightKg: 60, durationMinutes: null, distanceKm: null, rpe: 8, effortFactor: 1.3, computedLoad: 390, notes: null },
          { id: "2", setOrder: 2, parsedKind: "parsed", sourceSetText: "62.5 kg x 5", reps: 5, weightKg: 62.5, durationMinutes: null, distanceKm: null, rpe: 8, effortFactor: 1.3, computedLoad: 406.25, notes: null },
          { id: "3", setOrder: 3, parsedKind: "parsed", sourceSetText: "65 kg x 5", reps: 5, weightKg: 65, durationMinutes: null, distanceKm: null, rpe: 8, effortFactor: 1.3, computedLoad: 422.5, notes: null },
        ],
      },
    ],
  },
  {
    activityIdRaw: "canonical-history-2",
    activityId: "history-2",
    exercises: [
      {
        exerciseId: "db-incline",
        canonicalExerciseName: "Bench Press",
        sourceExerciseName: "Incline Press",
        normalizedSourceExerciseName: "incline press",
        sets: [
          { id: "4", setOrder: 1, parsedKind: "parsed", sourceSetText: "50 kg x 5", reps: 5, weightKg: 50, durationMinutes: null, distanceKm: null, rpe: 8, effortFactor: 1.3, computedLoad: 325, notes: null },
          { id: "5", setOrder: 2, parsedKind: "parsed", sourceSetText: "50 kg x 5", reps: 5, weightKg: 50, durationMinutes: null, distanceKm: null, rpe: 8, effortFactor: 1.3, computedLoad: 325, notes: null },
          { id: "6", setOrder: 3, parsedKind: "parsed", sourceSetText: "52.5 kg x 5", reps: 5, weightKg: 52.5, durationMinutes: null, distanceKm: null, rpe: 8, effortFactor: 1.3, computedLoad: 341.25, notes: null },
        ],
      },
    ],
  },
]);

const benchReference = M.getPersonalReferenceLoadDetails(canonicalExercise, 5, referenceHistory);
assert(
  benchReference.source === "exercise_history" && Math.abs(benchReference.loadKg - 62.5) < 0.00001,
  "personal reference load prefers same-exercise working-set history"
);
const canonicalBenchReference = M.getPersonalReferenceLoadDetails(canonicalExercise, 5, canonicalReferenceHistory);
assert(
  canonicalBenchReference.source === "exercise_history" && Math.abs(canonicalBenchReference.loadKg - 62.5) < 0.00001,
  "personal reference load also works through canonical child-row workout blocks"
);

const typedCanonicalReferenceHistory = [
  {
    Type: "WeightTraining",
    Date: M.shiftDate(todayIso, -3),
    "Duration (min)": "45",
    Description: "Logged with Hevy\n\nMystery Exercise\n20 kg x 10",
    "Activity ID raw": "canonical-typed-history-1",
  },
  {
    Type: "WeightTraining",
    Date: M.shiftDate(todayIso, -2),
    "Duration (min)": "45",
    Description: "Logged with Hevy\n\nMystery Exercise\n20 kg x 10",
    "Activity ID raw": "canonical-typed-history-2",
  },
];
M.setActivityChildren([
  {
    activityIdRaw: "canonical-typed-history-1",
    activityId: "typed-history-1",
    exercises: [
      {
        exerciseId: "db-bench",
        canonicalExerciseName: "Bench Press",
        sourceExerciseName: "Bench Press",
        normalizedSourceExerciseName: "bench press",
        sets: [
          { id: "typed-1", setOrder: 1, parsedKind: "parsed", sourceSetText: "top set", reps: 5, weightKg: 60, durationMinutes: null, distanceKm: null, rpe: 8, effortFactor: 1.3, computedLoad: 390, notes: null },
          { id: "typed-2", setOrder: 2, parsedKind: "parsed", sourceSetText: "top set", reps: 5, weightKg: 62.5, durationMinutes: null, distanceKm: null, rpe: 8, effortFactor: 1.3, computedLoad: 406.25, notes: null },
          { id: "typed-3", setOrder: 3, parsedKind: "parsed", sourceSetText: "top set", reps: 5, weightKg: 65, durationMinutes: null, distanceKm: null, rpe: 8, effortFactor: 1.3, computedLoad: 422.5, notes: null },
        ],
      },
    ],
  },
  {
    activityIdRaw: "canonical-typed-history-2",
    activityId: "typed-history-2",
    exercises: [
      {
        exerciseId: "db-incline",
        canonicalExerciseName: "Bench Press",
        sourceExerciseName: "Incline Press",
        normalizedSourceExerciseName: "incline press",
        sets: [
          { id: "typed-4", setOrder: 1, parsedKind: "parsed", sourceSetText: "top set", reps: 5, weightKg: 50, durationMinutes: null, distanceKm: null, rpe: 8, effortFactor: 1.3, computedLoad: 325, notes: null },
          { id: "typed-5", setOrder: 2, parsedKind: "parsed", sourceSetText: "top set", reps: 5, weightKg: 50, durationMinutes: null, distanceKm: null, rpe: 8, effortFactor: 1.3, computedLoad: 325, notes: null },
          { id: "typed-6", setOrder: 3, parsedKind: "parsed", sourceSetText: "top set", reps: 5, weightKg: 52.5, durationMinutes: null, distanceKm: null, rpe: 8, effortFactor: 1.3, computedLoad: 341.25, notes: null },
        ],
      },
    ],
  },
]);
const typedCanonicalBenchReference = M.getPersonalReferenceLoadDetails(canonicalExercise, 5, typedCanonicalReferenceHistory);
assert(
  typedCanonicalBenchReference.source === "exercise_history" && Math.abs(typedCanonicalBenchReference.loadKg - 62.5) < 0.00001,
  "personal reference load uses canonical setDetails directly when child-row display text is not parseable"
);

const familyReference = M.getPersonalReferenceLoadDetails(
  { id: "ghost-press", canonicalName: "Ghost Press", exerciseFamily: "horizontal_press" },
  5,
  referenceHistory
);
assert(
  familyReference.source === "family_history" && Math.abs(familyReference.loadKg - 56.25) < 0.00001,
  "personal reference load falls back to same-family history"
);

const heuristicReference = M.getPersonalReferenceLoadDetails(
  { id: "new-press", canonicalName: "New Press", exerciseFamily: "horizontal_press" },
  5,
  []
);
assert(
  heuristicReference.source === "heuristic" && Math.abs(heuristicReference.loadKg - 82.8) < 0.00001,
  "personal reference load falls back to bodyweight and experience heuristic without novice"
);

const heavyBenchStimulus = M.getActivityFatigueStimulus({
  Type: "WeightTraining",
  Date: todayIso,
  "Duration (min)": "45",
  Description: "Logged with Hevy\n\nBench Press\n200 kg x 5",
}, { allActivities: [] });
assert(
  Math.abs(heavyBenchStimulus.localStimulus.chest - 0.8505) < 0.0001 && Math.abs(heavyBenchStimulus.systemicStimulus - 0.3645) < 0.0001,
  "parsed strength stimulus applies max relative clamp and splits local/systemic fatigue"
);

const lightBenchStimulus = M.getActivityFatigueStimulus({
  Type: "WeightTraining",
  Date: todayIso,
  "Duration (min)": "45",
  Description: "Logged with Hevy\n\nBench Press\n45 kg x 5",
}, { allActivities: [] });
assert(
  Math.abs(lightBenchStimulus.localStimulus.chest - 0.078) < 0.001,
  "parsed strength stimulus applies min relative clamp for light sets"
);

M.setActivityChildren([
  {
    activityIdRaw: "canonical-fatigue-1",
    activityId: "fatigue-1",
    exercises: [
      {
        exerciseId: "db-bench",
        canonicalExerciseName: "Bench Press",
        sourceExerciseName: "Bench Press",
        normalizedSourceExerciseName: "bench press",
        sets: [
          { id: "cf-1", setOrder: 1, parsedKind: "parsed", sourceSetText: "top set", reps: 5, weightKg: 80, durationMinutes: null, distanceKm: null, rpe: 8.5, effortFactor: 1.35, computedLoad: 540, notes: null },
        ],
      },
    ],
  },
]);
const canonicalTypedStimulus = M.getActivityFatigueStimulus({
  Type: "WeightTraining",
  Date: todayIso,
  "Duration (min)": "45",
  Description: "Logged with Hevy\n\nStretching\n20 min",
  "Activity ID raw": "canonical-fatigue-1",
}, { allActivities: [] });
const parsedBenchStimulus = M.getActivityFatigueStimulus({
  Type: "WeightTraining",
  Date: todayIso,
  "Duration (min)": "45",
  Description: "Logged with Hevy\n\nBench Press\n80 kg x 5",
}, { allActivities: [] });
assert(
  Math.abs(canonicalTypedStimulus.localStimulus.chest - parsedBenchStimulus.localStimulus.chest) < 0.0001
    && Math.abs(canonicalTypedStimulus.systemicStimulus - parsedBenchStimulus.systemicStimulus) < 0.0001,
  "canonical child rows beat a conflicting Description and use typed parsed-set metrics instead of reparsing display text"
);

const fallbackBenchStimulus = M.getActivityFatigueStimulus({
  Type: "WeightTraining",
  Date: todayIso,
  "Duration (min)": "45",
  Description: "Logged with Hevy\n\nBench Press\nworking sets felt good\nkept rest short",
}, { allActivities: [] });
assert(
  fallbackBenchStimulus.localStimulus.chest > 0 && fallbackBenchStimulus.systemicStimulus > 0,
  "multiplier-free fallback still produces stable nonzero stimulus for matched Hevy sessions"
);

const systemicFatigue = M.getMuscleFatigueAnalysis([
  {
    Type: "WeightTraining",
    Date: todayIso,
    "Duration (min)": "45",
    Description: "Logged with Hevy\n\nSquat\n200 kg x 5",
  },
]);
const systemicQuad = systemicFatigue.regions.find((region) => region.key === "quadriceps");
assert(systemicFatigue.systemic && systemicFatigue.systemic.fatigueScore > 0, "fatigue analysis exposes systemic fatigue score");
assert(systemicQuad && systemicQuad.fatigueScore >= systemicQuad.localFatigueScore && systemicQuad.systemicPenalty > 0, "systemic fatigue applies a secondary muscle readiness penalty");

const lightRecovery = M.getMuscleFatigueAnalysis([
  {
    Type: "WeightTraining",
    Date: todayIso,
    "Duration (min)": "45",
    Description: "Logged with Hevy\n\nBench Press\n45 kg x 5",
  },
]).regions.find((region) => region.key === "chest");
const heavyRecovery = M.getMuscleFatigueAnalysis([
  {
    Type: "WeightTraining",
    Date: todayIso,
    "Duration (min)": "45",
    Description: "Logged with Hevy\n\nBench Press\n400 kg x 5",
  },
]).regions.find((region) => region.key === "chest");
assert(
  heavyRecovery && lightRecovery && heavyRecovery.recoveryHours > lightRecovery.recoveryHours,
  "severity-adjusted recovery keeps heavier sessions recovering longer than lighter sessions"
);
assert(
  heavyRecovery && typeof heavyRecovery.freshRecoveryHours === "number" && heavyRecovery.freshRecoveryHours > heavyRecovery.recoveryHours,
  "trainable-again timing is less conservative than fully-fresh timing"
);

const stretchingStimulus = M.getActivityFatigueStimulus({
  Type: "WeightTraining",
  Date: todayIso,
  "Duration (min)": "45",
  Description: "Logged with Hevy\n\nStretching\n15min 0s",
});
assert(
  Object.values(stretchingStimulus.localStimulus).every((value) => value === 0) && stretchingStimulus.systemicStimulus === 0,
  "no-fatigue exercise configs contribute zero local and systemic fatigue"
);

M.setActivityChildren([
  {
    activityIdRaw: "canonical-cycling-1",
    activityId: "cycling-1",
    exercises: [
      {
        exerciseId: "db-cycling",
        canonicalExerciseName: "Cycling",
        sourceExerciseName: "Cycling",
        normalizedSourceExerciseName: "cycling",
        sets: [
          { id: "cycle-1", setOrder: 1, parsedKind: "time", sourceSetText: "steady state", reps: null, weightKg: null, durationMinutes: 12, distanceKm: 6, rpe: null, effortFactor: null, computedLoad: null, notes: null },
        ],
      },
    ],
  },
]);
const canonicalCyclingStimulus = M.getActivityFatigueStimulus({
  Type: "WeightTraining",
  Date: todayIso,
  "Duration (min)": "60",
  Description: "Logged with Hevy\n\nBench Press\n80 kg x 5",
  "Activity ID raw": "canonical-cycling-1",
});
const cyclingStimulus = M.getActivityFatigueStimulus({
  Type: "WeightTraining",
  Date: todayIso,
  "Duration (min)": "60",
  Description: "Logged with Hevy\n\nCycling\n6km - 12min",
});
assert(
  cyclingStimulus.localStimulus.quadriceps > 0
    && cyclingStimulus.localStimulus.quadriceps < runStimulus.quadriceps
    && cyclingStimulus.systemicStimulus > 0,
  "duration-based Hevy cycling contributes small lower-body fatigue from set minutes"
);
assert(
  Math.abs(canonicalCyclingStimulus.localStimulus.quadriceps - cyclingStimulus.localStimulus.quadriceps) < 0.0001
    && Math.abs(canonicalCyclingStimulus.systemicStimulus - cyclingStimulus.systemicStimulus) < 0.0001
    && canonicalCyclingStimulus.localStimulus.chest === 0,
  "canonical time-based child rows drive time_duration fatigue from typed minutes and ignore conflicting description text"
);

M.setActivityChildren([
  {
    activityIdRaw: "canonical-fallback-1",
    activityId: "fallback-1",
    exercises: [
      {
        exerciseId: "db-bench",
        canonicalExerciseName: "Bench Press",
        sourceExerciseName: "Bench Press",
        normalizedSourceExerciseName: "bench press",
        sets: [
          { id: "fallback-1", setOrder: 1, parsedKind: "unknown", sourceSetText: "felt strong", reps: null, weightKg: null, durationMinutes: null, distanceKm: null, rpe: null, effortFactor: null, computedLoad: null, notes: "felt strong" },
          { id: "fallback-2", setOrder: 2, parsedKind: "unknown", sourceSetText: "slow negative", reps: null, weightKg: null, durationMinutes: null, distanceKm: null, rpe: null, effortFactor: null, computedLoad: null, notes: "slow negative" },
        ],
      },
    ],
  },
]);
const canonicalFallbackStimulus = M.getActivityFatigueStimulus({
  Type: "WeightTraining",
  Date: todayIso,
  "Duration (min)": "45",
  Description: "",
  "Activity ID raw": "canonical-fallback-1",
}, { allActivities: [] });
assert(
  canonicalFallbackStimulus.localStimulus.chest > 0 && canonicalFallbackStimulus.systemicStimulus > 0,
  "canonical unknown child rows fall back to the existing duration and set-count heuristic instead of dropping the exercise"
);

M.state.userSettings = previousUserSettings;

const unknowns = M.collectUnknownExercisesFromActivities([
  {
    Type: "WeightTraining",
    Date: M.toIsoDate(new Date()),
    Description: "Logged with Hevy\n\nPhantom Apparatus\n20 kg x 8\n\nphantom-apparatus\n18 kg x 10\n\nStretching\n15min 0s\n\nBench Press\n80 kg x 5",
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
assert(!unknowns.some((item) => item.id === "exercise:stretching"), "configured no-fatigue exercises do not appear as unknowns");

M.setActivityChildren([
  {
    activityIdRaw: "canonical-unknown-1",
    activityId: "unknown-1",
    exercises: [
      {
        exerciseId: null,
        canonicalExerciseName: null,
        sourceExerciseName: "Phantom Apparatus",
        normalizedSourceExerciseName: "phantom apparatus",
        sets: [
          { id: "7", setOrder: 1, parsedKind: "parsed", sourceSetText: "20 kg x 8", reps: 8, weightKg: 20, durationMinutes: null, distanceKm: null, rpe: 8, effortFactor: 1.3, computedLoad: 208, notes: null },
        ],
      },
    ],
  },
]);
const canonicalUnknowns = M.collectUnknownExercisesFromActivities([
  {
    Type: "WeightTraining",
    Date: M.toIsoDate(new Date()),
    Description: "",
    "Activity ID raw": "canonical-unknown-1",
  },
]);
assert(
  canonicalUnknowns.some((item) => item.id === "exercise:phantom apparatus"),
  "unknown scan uses canonical child-row exercise names when available"
);

const mixedHevyStimulus = M.getActivityMuscleStimulus({
  Type: "WeightTraining",
  "Duration (min)": "45",
  Description: "Logged with Hevy\n\nPhantom Apparatus\n20 kg x 8\n\nBench Press\n80 kg x 5",
});
assert(mixedHevyStimulus.chest > 0, "mixed Hevy sessions still produce stimulus for resolved exercises");

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
const unknownExerciseItems = listItems.filter((item) => item.kind === "unknownExercise");
const configuredExerciseItems = listItems.filter((item) => item.kind === "exercise");
const configuredActivityTypeItems = listItems.filter((item) => item.kind === "activityType");
assert(
  unknownExerciseItems.length >= 2 && configuredExerciseItems.length >= 2 && configuredActivityTypeItems.length >= 1,
  "exercise admin merged list includes unknown exercises, configured exercises, and configured activity types"
);
assert(
  unknownExerciseItems[0].record.id === "exercise:bird dog"
    && unknownExerciseItems[1].record.id === "exercise:phantom apparatus",
  "exercise admin merged list keeps unknown exercises ahead of configured exercises within the merged list"
);
assert(
  unknownExerciseItems[0].record.id === "exercise:bird dog" && unknownExerciseItems[1].record.id === "exercise:phantom apparatus",
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
  filteredConfiguredList.length === 1 && filteredConfiguredList[0].kind === "exercise" && filteredConfiguredList[0].record.id === "hammer-curl",
  "exercise admin merged search matches configured exercise aliases"
);

const filteredActivityTypeList = M.getExerciseAdminListItems("canoe", "all");
assert(
  filteredActivityTypeList.some((item) => item.kind === "activityType" && item.record.id === "canoeing"),
  "exercise admin merged search matches configured activity type aliases"
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
  editorHtml.includes("data-exercise-admin-save-status")
    && editorHtml.includes(">Save exercise<")
    && editorHtml.includes("data-exercise-admin-cancel-changes")
    && editorHtml.includes("No changes."),
  "exercise editor keeps primary save and cancel actions in a compact sticky action bar"
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
assert(
  editorHtml.includes('name="exerciseFamily"')
    && editorHtml.includes('name="fatigueArchetype"')
    && editorHtml.includes("<summary")
    && editorHtml.includes("Advanced")
    && editorHtml.includes("Horizontal press")
    && editorHtml.includes("Freeweight compound"),
  "exercise editor moves family and archetype selectors into advanced settings"
);
assert(
  editorHtml.includes("Names")
    && editorHtml.includes("How this should count")
    && editorHtml.includes("Muscles worked")
    && editorHtml.includes("Exercise format")
    && editorHtml.includes("Strength / reps")
    && editorHtml.includes("Use bodyweight when reps are logged without a weight")
    && !editorHtml.includes("Names and matching")
    && !editorHtml.includes("Fatigue and set handling")
    && !editorHtml.includes("Muscle involvement"),
  "exercise editor uses one plain-language heading per form section"
);

const configuredDirtyEditorHtml = M.renderExerciseAdminEditorHtmlForTest(M.state.exerciseConfigs[1], {
  formDraft: {
    key: "exercise:hammer-curl",
    values: {
      canonicalName: "Hammer Curl",
      aliasesText: "Hammer Curl\nNeutral Grip Curl\nDB Hammer Curl",
      matchTermsText: "hammer curl\ncurl",
      fatigueImpact: "normal",
      bodyweightEligible: false,
      setTypeHandling: "weight_reps",
      exerciseFamily: "arm_isolation",
      fatigueArchetype: "isolation",
      muscleWeights: {
        biceps: 1,
        forearms: 0.45,
      },
      source: "manual",
    },
  },
});
assert(
  configuredDirtyEditorHtml.includes("Unsaved changes.")
    && configuredDirtyEditorHtml.includes(">Save exercise<")
    && !configuredDirtyEditorHtml.includes("data-exercise-admin-save disabled"),
  "configured exercise editor enables save when manual form edits change a saveable value"
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
assert(
  unknownEditorHtml.includes(">Create exercise<")
    && unknownEditorHtml.includes("No changes."),
  "unknown editor reframes the primary action around creating a config before the first save"
);

const unknownDirtyEditorHtml = M.renderExerciseAdminUnknownEditorHtmlForTest(M.state.unknownExercises[0], {
  formDraft: {
    key: `unknown:${M.state.unknownExercises[0].id}`,
    values: {
      canonicalName: M.state.unknownExercises[0].rawNames[0],
      aliasesText: M.state.unknownExercises[0].rawNames.join("\n"),
      matchTermsText: [
        M.state.unknownExercises[0].normalizedName,
        ...M.state.unknownExercises[0].rawNames,
      ].join("\n"),
      fatigueImpact: "normal",
      bodyweightEligible: false,
      setTypeHandling: "weight_reps",
      exerciseFamily: "core",
      fatigueArchetype: "",
      muscleWeights: {
        chest: 1,
      },
      source: "manual",
    },
  },
});
assert(
  unknownDirtyEditorHtml.includes("Unsaved changes.")
    && unknownDirtyEditorHtml.includes(">Create exercise<")
    && !unknownDirtyEditorHtml.includes("data-exercise-admin-save disabled"),
  "unknown exercise editor compares manual edits against the persisted baseline instead of the draft state"
);

const activityTypeEditorHtml = M.renderActivityTypeAdminEditorHtmlForTest(
  M.state.activityTypeConfigs.find((record) => record.id === "rowing")
);
assert(
  activityTypeEditorHtml.includes("Activity type") && !activityTypeEditorHtml.includes("data-exercise-admin-regenerate"),
  "activity type editor uses the activity type mode without exercise-only AI controls"
);
assert(
  !activityTypeEditorHtml.includes('name="setTypeHandling"') && !activityTypeEditorHtml.includes('name="bodyweightEligible"'),
  "activity type editor hides exercise-only set handling and bodyweight fields"
);
assert(
  activityTypeEditorHtml.includes('name="exerciseFamily"')
    && activityTypeEditorHtml.includes('name="fatigueArchetype"')
    && activityTypeEditorHtml.includes("Conditioning lower")
    && activityTypeEditorHtml.includes("Conditioning hybrid"),
  "activity type editor exposes family and archetype selectors for manual tuning"
);

const timeBasedEditorHtml = M.renderExerciseAdminEditorHtmlForTest({
  ...M.state.exerciseConfigs[1],
  id: "cycling-test",
  canonicalName: "Cycling Test",
  setTypeHandling: "time_duration",
});
assert(
  timeBasedEditorHtml.includes("Time / cardio")
    && !timeBasedEditorHtml.includes('name="bodyweightEligible"'),
  "time/cardio exercise editor hides the bodyweight parser option"
);

const unknownActivityTypeEditorHtml = M.renderExerciseAdminUnknownEditorHtmlForTest({
  id: "activityType:mobility flow",
  sourceType: "activityType",
  normalizedName: "mobility flow",
  rawNames: ["Mobility Flow"],
  timesSeen: 1,
  firstSeenAt: "2026-04-24T00:00:00Z",
  lastSeenAt: "2026-04-24T00:00:00Z",
  aiStatus: "not_requested",
});
assert(
  unknownActivityTypeEditorHtml.includes("Activity type")
    && unknownActivityTypeEditorHtml.includes("data-exercise-admin-save")
    && !unknownActivityTypeEditorHtml.includes("data-exercise-admin-suggest"),
  "unknown activity type editor allows saving without exercise-only suggestion actions"
);

const shellHtml = M.renderExerciseAdminShellHtmlForTest({
  query: "",
  selectedKey: "unknown:exercise:bird dog",
});
const activityTypeShellHtml = M.renderExerciseAdminShellHtmlForTest({
  filter: "activityType",
  selectedKey: "activityType:rowing",
});
const unknownListItemHtml = M.renderExerciseAdminListItemHtmlForTest({
  key: "unknown:exercise:bird dog",
  type: "unknown",
  kind: "unknownExercise",
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
  shellHtml.includes("Activity type") && shellHtml.includes("Exercise"),
  "exercise admin shell labels both configured exercises and activity types in the merged list"
);
assert(
  shellHtml.includes('data-exercise-admin-filter="all"')
    && shellHtml.includes('data-exercise-admin-filter="exercise"')
    && shellHtml.includes('data-exercise-admin-filter="activityType"')
    && activityTypeShellHtml.includes('data-exercise-admin-filter="activityType" aria-pressed="true"'),
  "exercise admin shell renders config-type filters and reflects the active selection"
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
    kind: "exercise",
  },
});
assert(
  deleteDialogHtml.includes(M.EXERCISE_ADMIN_DELETE_CONFIRMATION_COPY) && deleteDialogHtml.includes("data-exercise-admin-delete-confirm"),
  "exercise admin renders an in-app delete confirmation dialog with the required copy"
);

const activityTypeDeleteDialogHtml = M.renderExerciseAdminConfirmDialogHtmlForTest({
  confirmDialog: {
    open: true,
    type: "delete",
    key: "activityType:rowing",
    kind: "activityType",
  },
});
assert(
  activityTypeDeleteDialogHtml.includes("Delete activity type") && activityTypeDeleteDialogHtml.includes("permanently delete this activity type"),
  "exercise admin renders a dedicated delete confirmation dialog for activity types"
);

const total = passed + failed;
console.log(`Exercise config tests: ${passed}/${total} passed`);

if (failed > 0) {
  process.exit(1);
}
