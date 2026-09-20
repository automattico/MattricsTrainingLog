(function () {
  const M = window.Mattrics;
  const ACTIVITY_ID_RAW_KEY = "Activity ID raw";
  const ACTIVITY_ID_KEY = "Activity ID";

  M.getExerciseMuscleMapping = function getExerciseMuscleMapping(exerciseName) {
    return M.resolveExerciseConfig(exerciseName);
  };

  const HEVY_HEADER_RE = /^Logged with Hevy(?:App\.com)?\s*/i;

  function ensureArray(value) {
    return Array.isArray(value) ? value : [];
  }

  function formatSetNumber(value) {
    const numeric = Number(value);
    if (!Number.isFinite(numeric)) return "";
    return String(Math.round(numeric * 100) / 100);
  }

  function getActivityIdentityKeys(activity) {
    if (!activity || typeof activity !== "object") return [];

    return Array.from(new Set([
      activity[ACTIVITY_ID_RAW_KEY],
      activity[ACTIVITY_ID_KEY],
      activity.activityIdRaw,
      activity.activityId,
    ].map((value) => String(value == null ? "" : value).trim()).filter(Boolean)));
  }

  function formatCanonicalChildSet(setRecord) {
    const sourceSetText = String(setRecord && setRecord.sourceSetText || "").trim();
    if (sourceSetText) return sourceSetText;

    const parsedKind = String(setRecord && setRecord.parsedKind || "");
    const reps = Number(setRecord && setRecord.reps);
    const weightKg = Number(setRecord && setRecord.weightKg);
    const durationMinutes = Number(setRecord && setRecord.durationMinutes);
    const distanceKm = Number(setRecord && setRecord.distanceKm);

    if (parsedKind === "parsed" && Number.isFinite(reps) && reps > 0 && Number.isFinite(weightKg) && weightKg > 0) {
      return `${formatSetNumber(weightKg)} kg x ${Math.round(reps)}`;
    }

    if (parsedKind === "time") {
      if (Number.isFinite(distanceKm) && distanceKm > 0 && Number.isFinite(durationMinutes) && durationMinutes > 0) {
        return `${formatSetNumber(distanceKm)} km - ${formatSetNumber(durationMinutes)} min`;
      }
      if (Number.isFinite(durationMinutes) && durationMinutes > 0) {
        return `${formatSetNumber(durationMinutes)} min`;
      }
    }

    return String(setRecord && setRecord.notes || "").trim();
  }

  function cloneCanonicalSet(setRecord) {
    return {
      id: String(setRecord && setRecord.id || ""),
      setOrder: Number.isFinite(Number(setRecord && setRecord.setOrder)) ? Number(setRecord.setOrder) : null,
      parsedKind: String(setRecord && setRecord.parsedKind || ""),
      sourceSetText: String(setRecord && setRecord.sourceSetText || ""),
      reps: Number.isFinite(Number(setRecord && setRecord.reps)) ? Number(setRecord.reps) : null,
      weightKg: Number.isFinite(Number(setRecord && setRecord.weightKg)) ? Number(setRecord.weightKg) : null,
      durationMinutes: Number.isFinite(Number(setRecord && setRecord.durationMinutes)) ? Number(setRecord.durationMinutes) : null,
      distanceKm: Number.isFinite(Number(setRecord && setRecord.distanceKm)) ? Number(setRecord.distanceKm) : null,
      rpe: Number.isFinite(Number(setRecord && setRecord.rpe)) ? Number(setRecord.rpe) : null,
      effortFactor: Number.isFinite(Number(setRecord && setRecord.effortFactor)) ? Number(setRecord.effortFactor) : null,
      computedLoad: Number.isFinite(Number(setRecord && setRecord.computedLoad)) ? Number(setRecord.computedLoad) : null,
      notes: String(setRecord && setRecord.notes || ""),
    };
  }

  function cloneActivityChildRecord(activityChild) {
    return {
      activityIdRaw: String(activityChild && activityChild.activityIdRaw || ""),
      activityId: String(activityChild && activityChild.activityId || ""),
      exercises: ensureArray(activityChild && activityChild.exercises).map((exercise) => ({
        exerciseId: exercise && exercise.exerciseId ? String(exercise.exerciseId) : null,
        canonicalExerciseName: exercise && exercise.canonicalExerciseName ? String(exercise.canonicalExerciseName) : null,
        sourceExerciseName: String(exercise && exercise.sourceExerciseName || ""),
        normalizedSourceExerciseName: String(exercise && exercise.normalizedSourceExerciseName || ""),
        sets: ensureArray(exercise && exercise.sets).map(cloneCanonicalSet),
      })),
    };
  }

  function buildWorkoutBlockFromCanonicalExercise(exercise) {
    const setDetails = ensureArray(exercise && exercise.sets).map(cloneCanonicalSet);
    const sourceExerciseName = String(exercise && exercise.sourceExerciseName || "").trim();
    const canonicalExerciseName = String(exercise && exercise.canonicalExerciseName || "").trim();
    const name = sourceExerciseName || canonicalExerciseName;

    return {
      name,
      sourceExerciseName: sourceExerciseName || name,
      canonicalExerciseName: canonicalExerciseName || null,
      normalizedSourceExerciseName: String(exercise && exercise.normalizedSourceExerciseName || "").trim(),
      exerciseId: exercise && exercise.exerciseId ? String(exercise.exerciseId) : null,
      sets: setDetails.map(formatCanonicalChildSet).filter(Boolean),
      setDetails,
      source: "canonical",
    };
  }

  M.isHevyDescription = function isHevyDescription(desc) {
    return HEVY_HEADER_RE.test(String(desc || "").trim());
  };

  M.stripHevyHeader = function stripHevyHeader(desc) {
    return String(desc || "").trim().replace(HEVY_HEADER_RE, "").trim();
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
    const parseDurationMinutes = () => {
      let total = 0;
      const unitPattern = /(\d+(?:[.,]\d+)?)\s*(hr|hrs|hour|hours|min|mins|minute|minutes|sec|secs|second|seconds)\b/gi;
      let match;
      while ((match = unitPattern.exec(rawText)) !== null) {
        const amount = parseNumber(match[1]);
        if (!Number.isFinite(amount)) continue;
        const unit = match[2].toLowerCase();
        if (/^h/.test(unit)) total += amount * 60;
        else if (/^s/.test(unit)) total += amount / 60;
        else total += amount;
      }
      if (total > 0) return total;

      const clockMatch = rawText.match(/\b(\d{1,2}):(\d{2})(?::(\d{2}))?\b/);
      if (!clockMatch) return 0;
      const first = parseInt(clockMatch[1], 10) || 0;
      const second = parseInt(clockMatch[2], 10) || 0;
      const third = parseInt(clockMatch[3] || "0", 10) || 0;
      return clockMatch[3] ? (first * 60) + second + (third / 60) : first + (second / 60);
    };

    let weightKg = 0;
    let reps = 0;

    if (isTimeBased) {
      const distanceMatch = rawText.match(/(\d+(?:[.,]\d+)?)\s*km\b/i);
      const distanceKm = distanceMatch ? parseNumber(distanceMatch[1]) : 0;
      return {
        kind: "time",
        minutes: parseDurationMinutes(),
        distanceKm: Number.isFinite(distanceKm) ? distanceKm : 0,
      };
    }

    const weightFirst = rawText.match(/(\d+(?:[.,]\d+)?)\s*(kg|kgs|lb|lbs)\s*(?:x|×)\s*(\d+)/i);
    const weightForReps = rawText.match(/(\d+(?:[.,]\d+)?)\s*(kg|kgs|lb|lbs)\s*(?:for|-|–|—)?\s*(\d+)\s*reps?\b/i);
    const repsFirst = rawText.match(/(\d+)\s*(?:reps?)?\s*(?:x|×|@)\s*(\d+(?:[.,]\d+)?)\s*(kg|kgs|lb|lbs)/i);
    const repsAtWeight = rawText.match(/(\d+)\s*reps?\s*(?:at|with|-|–|—)?\s*(\d+(?:[.,]\d+)?)\s*(kg|kgs|lb|lbs)\b/i);
    const weightOnly = rawText.match(/(\d+(?:[.,]\d+)?)\s*(kg|kgs|lb|lbs)/i);
    const repsOnly = rawText.match(/(?:^|[^\d])(\d+)\s*(?:reps?|x)?(?:$|[^\d])/i);

    if (weightFirst) {
      weightKg = convertWeightKg(weightFirst[1], weightFirst[2]);
      reps = parseInt(weightFirst[3], 10) || 0;
    } else if (weightForReps) {
      weightKg = convertWeightKg(weightForReps[1], weightForReps[2]);
      reps = parseInt(weightForReps[3], 10) || 0;
    } else if (repsFirst) {
      reps = parseInt(repsFirst[1], 10) || 0;
      weightKg = convertWeightKg(repsFirst[2], repsFirst[3]);
    } else if (repsAtWeight) {
      reps = parseInt(repsAtWeight[1], 10) || 0;
      weightKg = convertWeightKg(repsAtWeight[2], repsAtWeight[3]);
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
    const text = String(desc || "").trim();
    if (!M.isHevyDescription(text)) return null;

    const blocks = M.stripHevyHeader(text)
      .trim()
      .split(/\n\s*\n/)
      .map((block) => block.trim())
      .filter(Boolean);

    return blocks
      .map((block) => {
        const lines = block.split("\n").map((line) => line.trim()).filter(Boolean);
        if (!lines.length) return null;
        return {
          name: lines[0],
          sourceExerciseName: lines[0],
          canonicalExerciseName: null,
          normalizedSourceExerciseName: "",
          exerciseId: null,
          sets: lines.slice(1),
          setDetails: [],
          source: "description",
        };
      })
      .filter(Boolean);
  };

  M.indexActivityChildren = function indexActivityChildren(children) {
    const indexed = {};
    ensureArray(children).forEach((activityChild) => {
      getActivityIdentityKeys(activityChild).forEach((key) => {
        if (!indexed[key]) {
          indexed[key] = cloneActivityChildRecord(activityChild);
        }
      });
    });
    return indexed;
  };

  M.setActivityChildren = function setActivityChildren(children) {
    const cloned = ensureArray(children).map(cloneActivityChildRecord);
    M.state.activityChildren = cloned;
    M.state.activityChildrenById = M.indexActivityChildren(cloned);
    return cloned;
  };

  M.getActivityWorkoutBlocks = function getActivityWorkoutBlocks(activity) {
    const activityChildrenById = M.state && M.state.activityChildrenById;
    const childRecord = getActivityIdentityKeys(activity).reduce((match, key) => {
      if (match) return match;
      return activityChildrenById && activityChildrenById[key] ? activityChildrenById[key] : null;
    }, null);

    if (childRecord && Array.isArray(childRecord.exercises) && childRecord.exercises.length) {
      return childRecord.exercises.map(buildWorkoutBlockFromCanonicalExercise).filter((exercise) => exercise.name);
    }

    return M.parseHevyDescription(activity && activity.Description);
  };
}());
