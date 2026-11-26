const fs = require("node:fs");
const path = require("node:path");
const vm = require("node:vm");

const { data, workoutId, before, after } = JSON.parse(fs.readFileSync(0, "utf8"));
const core = path.join(__dirname, "../../public/assets/js/core");
const context = vm.createContext({ window: { Mattrics: {} }, URL });
for (const file of ["constants", "state", "exercise-config", "hevy-parser", "fatigue-engine"]) {
  vm.runInContext(fs.readFileSync(path.join(core, file + ".js"), "utf8"), context, { filename: file });
}

const M = context.window.Mattrics;
const activity = data.rows.find((row) => row["Activity ID raw"] === workoutId);
M.setActivityChildren(data.activityChildren);

function analyze(catalog) {
  M.indexExerciseConfigs(catalog);
  const blocks = M.getActivityWorkoutBlocks(activity);
  return {
    targets: blocks.map((block) => M.resolveExerciseConfig(block.name, block.hevyTemplateKey)?.id ?? null),
    unknowns: M.collectUnknownExercisesFromActivities([activity]),
    stimulus: M.getActivityMuscleStimulus(activity, { allActivities: [activity] }),
    references: Object.fromEntries(catalog.exercises.map((exercise) => [
      exercise.id, M.getPersonalReferenceLoadDetails(exercise, 8, [activity]),
    ])),
  };
}

process.stdout.write(JSON.stringify({
  before: analyze(before),
  // Apply the review response to the workout records already loaded in the dashboard.
  after: after ? analyze(after) : null,
}));
