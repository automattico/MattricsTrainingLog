(function () {
  const M = window.Mattrics;

  M.getExerciseMuscleMapping = function getExerciseMuscleMapping(exerciseName) {
    return M.resolveExerciseConfig(exerciseName);
  };

  M.parseHevySetLine = function parseHevySetLine(setText, exerciseName) {
    const fatigueConfig = M.MUSCLE_FATIGUE_CONFIG;
    const us = (M.state && M.state.userSettings) || {};
    const estimatedBodyweightKg = (us.bodyWeightKg != null ? us.bodyWeightKg : null) || fatigueConfig.estimatedBodyweightKg || 75;
    const bodyweightLoadFactor = fatigueConfig.bodyweightLoadFactor || 0.4;
    const defaultRpe = (us.defaultRpe != null ? us.defaultRpe : null) || fatigueConfig.defaultRpe || 7.5;
    const rawText = String(setText || "").trim();
    const text = rawText.toLowerCase();
    const exercise = String(exerciseName || "").toLowerCase();
    const exerciseConfig = M.resolveExerciseConfig(exerciseName);
    const isBodyweightExercise = Boolean(exerciseConfig && exerciseConfig.bodyweightEligible)
      || /(push ?up|pull ?up|chin ?up|dip|sit ?up|crunch|leg raise|bodyweight|bw|air squat|pistol squat)/i.test(exercise);
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
