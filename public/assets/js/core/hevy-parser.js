(function () {
  const M = window.Mattrics;

  M.getExerciseMuscleMapping = function getExerciseMuscleMapping(exerciseName) {
    const normalized = String(exerciseName || "").toLowerCase().replace(/[^a-z0-9]+/g, " ").trim();
    const exerciseMappings = [
      { patterns: ["bench", "push up", "pushup", "chest fly", "pec deck", "dip"], weights: { chest: 1, deltoids: 0.45, triceps: 0.52, abs: 0.08, trapezius: 0.06 } },
      { patterns: ["incline press", "incline bench", "chest press"], weights: { chest: 0.95, deltoids: 0.5, triceps: 0.46, trapezius: 0.08, abs: 0.08 } },
      { patterns: ["row", "face pull"], weights: { upperBack: 0.82, trapezius: 0.58, biceps: 0.34, deltoids: 0.22, lowerBack: 0.12 } },
      { patterns: ["pulldown", "pull up", "pullup", "chin up", "chinup"], weights: { upperBack: 0.74, trapezius: 0.28, biceps: 0.48, deltoids: 0.14, lowerBack: 0.08 } },
      { patterns: ["deadlift", "rdl", "romanian deadlift"], weights: { hamstrings: 0.92, gluteal: 0.72, lowerBack: 0.46, upperBack: 0.28, trapezius: 0.18, quadriceps: 0.18, abs: 0.2 } },
      { patterns: ["shoulder press", "overhead press", "arnold press", "lateral raise", "front raise", "rear delt"], weights: { deltoids: 1, triceps: 0.42, trapezius: 0.24, chest: 0.15, upperBack: 0.12 } },
      { patterns: ["curl", "hammer"], weights: { biceps: 1, deltoids: 0.08 } },
      { patterns: ["triceps", "pushdown", "skull crusher"], weights: { triceps: 1, deltoids: 0.08 } },
      { patterns: ["plank", "crunch", "twist", "dead bug", "hollow", "sit up", "leg raise", "russian twist"], weights: { abs: 0.78, obliques: 0.52, lowerBack: 0.18 } },
      { patterns: ["hip thrust", "glute bridge"], weights: { gluteal: 1, hamstrings: 0.35, abs: 0.14, lowerBack: 0.12, adductors: 0.1 } },
      { patterns: ["calf raise"], weights: { calves: 1, quadriceps: 0.12 } },
      { patterns: ["squat", "lunge", "step up", "stepup", "leg press", "split squat"], weights: { quadriceps: 0.95, gluteal: 0.35, hamstrings: 0.42, adductors: 0.24, abs: 0.18, lowerBack: 0.08, calves: 0.14 } },
    ];

    return exerciseMappings.find((mapping) => mapping.patterns.some((pattern) => normalized.includes(pattern))) || null;
  };

  M.parseHevySetLine = function parseHevySetLine(setText, exerciseName) {
    const fatigueConfig = M.MUSCLE_FATIGUE_CONFIG;
    const estimatedBodyweightKg = fatigueConfig.estimatedBodyweightKg || 75;
    const bodyweightLoadFactor = fatigueConfig.bodyweightLoadFactor || 0.4;
    const defaultRpe = fatigueConfig.defaultRpe || 7;
    const rawText = String(setText || "").trim();
    const text = rawText.toLowerCase();
    const exercise = String(exerciseName || "").toLowerCase();
    const isBodyweightExercise = /(push ?up|pull ?up|chin ?up|dip|sit ?up|crunch|leg raise|bodyweight|bw|air squat|pistol squat)/i.test(exercise);
    const isTimeBased = /\b\d+\s*(?:sec|secs|second|seconds|min|mins|minute|minutes|hr|hrs|hour|hours)\b/i.test(text)
      || /\b\d{1,2}:\d{2}\b/.test(text)
      || /\bfor time\b/i.test(text)
      || /\btime\b/i.test(text);

    if (!rawText) return { kind: "unknown" };
    if (isTimeBased) return { kind: "time" };

    const parseNumber = (value) => parseFloat(String(value || "").replace(",", "."));
    const convertWeightKg = (value, unit) => {
      const amount = parseNumber(value);
      if (!Number.isFinite(amount)) return 0;
      return /^lb/.test(unit || "") ? amount * 0.453592 : amount;
    };
    const rpeMatch = rawText.match(/(?:\brpe\b\s*[:@]?\s*|@\s*)(\d+(?:[.,]\d+)?)/i);
    const rpe = Number.isFinite(parseNumber(rpeMatch && rpeMatch[1])) ? parseNumber(rpeMatch[1]) : defaultRpe;
    const effortFactor = 0.5 + (rpe / 10);
    const bodyweightTag = /\b(?:bw|body ?weight)\b/i.test(rawText);

    let weightKg = 0;
    let reps = 0;

    const weightFirst = rawText.match(/(\d+(?:[.,]\d+)?)\s*(kg|kgs|lb|lbs)\s*(?:x|×)\s*(\d+)/i);
    const repsFirst = rawText.match(/(\d+)\s*(?:reps?)?\s*(?:x|×|@)\s*(\d+(?:[.,]\d+)?)\s*(kg|kgs|lb|lbs)/i);
    const weightOnly = rawText.match(/(\d+(?:[.,]\d+)?)\s*(kg|kgs|lb|lbs)/i);
    const repsOnly = rawText.match(/(?:^|[^\d])(\d+)\s*(?:reps?|x)?(?:$|[^\d])/i);

    if (weightFirst) {
      weightKg = convertWeightKg(weightFirst[1], weightFirst[2]);
      reps = parseInt(weightFirst[3], 10) || 0;
    } else if (repsFirst) {
      reps = parseInt(repsFirst[1], 10) || 0;
      weightKg = convertWeightKg(repsFirst[2], repsFirst[3]);
    } else if ((bodyweightTag || isBodyweightExercise) && repsOnly) {
      reps = parseInt(repsOnly[1], 10) || 0;
      weightKg = estimatedBodyweightKg * bodyweightLoadFactor;
    } else if (weightOnly && /\bx\b|×|\breps?\b/i.test(rawText) && repsOnly) {
      reps = parseInt(repsOnly[1], 10) || 0;
      weightKg = convertWeightKg(weightOnly[1], weightOnly[2]);
    }

    if (!Number.isFinite(weightKg) || !Number.isFinite(reps) || weightKg <= 0 || reps <= 0) {
      return { kind: "unknown" };
    }

    return {
      kind: "parsed",
      reps,
      weightKg,
      rpe,
      effortFactor,
      load: weightKg * reps * effortFactor,
    };
  };

  M.parseHevyDescription = function parseHevyDescription(desc) {
    const text = (desc || "").trim();
    if (!text.startsWith("Logged with Hevy")) return null;

    const blocks = text
      .replace(/^Logged with Hevy\s*/, "")
      .trim()
      .split(/\n\s*\n/)
      .map((block) => block.trim())
      .filter(Boolean);

    return blocks
      .map((block) => {
        const lines = block.split("\n").map((line) => line.trim()).filter(Boolean);
        if (!lines.length) return null;
        return { name: lines[0], sets: lines.slice(1) };
      })
      .filter(Boolean);
  };
}());
