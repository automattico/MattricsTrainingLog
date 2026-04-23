(function () {
  const M = window.Mattrics;

  function buildUnresolvedSummary(activities) {
    const items = M.collectUnknownExercisesFromActivities(activities).map((item) => ({
      id: item.id,
      sourceType: item.sourceType,
      normalizedName: item.normalizedName,
      rawNames: Array.isArray(item.rawNames) ? item.rawNames.slice() : [],
      timesSeen: Number(item.timesSeen || 0),
    }));
    const count = items.length;
    const sampleNames = items
      .slice(0, 3)
      .map((item) => item.rawNames[0] || item.normalizedName)
      .filter(Boolean);
    const warningText = count
      ? `Some recent exercises or activity types are unresolved, so the fatigue map may be incomplete${sampleNames.length ? `: ${sampleNames.join(", ")}${count > sampleNames.length ? ", ..." : ""}` : "."}`
      : "";

    return {
      hasUnresolved: count > 0,
      count,
      items,
      warningText,
    };
  }

  M.getActivityMuscleStimulus = function getActivityMuscleStimulus(activity) {
    const clamp = (value, min, max) => Math.max(min, Math.min(max, value));
    const strengthFactor = (min) => clamp((min || 0) / 45, 0.85, 1.6);
    const sportFactor = (min) => clamp((min || 0) / 60, 0.7, 1.45);
    const scaleWeights = (weights, factor) => Object.fromEntries(
      Object.entries(weights).map(([key, value]) => [key, value * factor])
    );
    const min = parseFloat(activity["Duration (min)"]) || 0;
    const type = activity.Type;
    const hevy = M.parseHevyDescription(activity.Description || "");
    const stimulus = Object.fromEntries(
      M.MUSCLE_REGIONS.map((region) => [region.key, 0])
    );
    const configFatigue = M.MUSCLE_FATIGUE_CONFIG;
    const setLoadDivisor = configFatigue.strengthLoadUnitDivisor || 1500;
    const addStimulus = (weights) => {
      Object.entries(weights).forEach(([key, value]) => {
        if (!(key in stimulus) || !value) return;
        stimulus[key] += value;
      });
    };

    if (hevy && hevy.length) {
      let matchedExercise = false;
      hevy.forEach((exercise) => {
        const match = M.getExerciseMuscleMapping(exercise.name);
        if (!match) return;
        matchedExercise = true;
        const weights = match.muscleWeights || match.weights || {};
        const fatigueMultiplier = Number(match.fatigueMultiplier || 1);
        const mappedNormalizations = Object.keys(weights)
          .map((key) => configFatigue.normalizationLoad[key] || 0)
          .filter(Boolean);
        const smallThreshold = (Math.max(...mappedNormalizations, 0) || 0) * (configFatigue.smallThresholdRatio || 0.02);
        let parsedExerciseLoad = 0;
        let parsedSetCount = 0;
        let ambiguousSetFound = false;
        let nonTimeSetFound = false;

        exercise.sets.forEach((setText) => {
          const parsed = M.parseHevySetLine(setText, exercise.name);
          if (parsed.kind === "time") return;
          nonTimeSetFound = true;
          if (parsed.kind !== "parsed") {
            ambiguousSetFound = true;
            return;
          }

          const scaledSetLoad = parsed.load / setLoadDivisor;
          if (scaledSetLoad < smallThreshold) return;
          parsedExerciseLoad += scaledSetLoad;
          parsedSetCount += 1;
        });

        if (parsedSetCount && !ambiguousSetFound) {
          addStimulus(scaleWeights(weights, parsedExerciseLoad * fatigueMultiplier));
          return;
        }

        if (!parsedSetCount && !nonTimeSetFound) {
          return;
        }

        const setCount = exercise.sets.length || 0;
        const setFactor = clamp(setCount / 3, 0.8, 1.6);
        addStimulus(scaleWeights(weights, strengthFactor(min) * setFactor * fatigueMultiplier));
      });
      if (matchedExercise) return stimulus;
    }

    const typeConfig = M.resolveActivityTypeConfig(type);
    if (!typeConfig) return stimulus;
    const factor = typeConfig.canonicalName === "WeightTraining" ? strengthFactor(min) : sportFactor(min);
    addStimulus(scaleWeights(typeConfig.muscleWeights || {}, factor * Number(typeConfig.fatigueMultiplier || 1)));
    return stimulus;
  };

  M.getMuscleLoadAnalysis = function getMuscleLoadAnalysis(activities) {
    const regions = Object.fromEntries(
      M.MUSCLE_REGIONS.map((region) => [region.key, { ...region, load: 0, hits: 0 }])
    );

    activities.forEach((activity) => {
      const stimulus = M.getActivityMuscleStimulus(activity);
      Object.entries(stimulus).forEach(([key, value]) => {
        if (!regions[key] || !value) return;
        regions[key].load += value;
        regions[key].hits += 1;
      });
    });

    const ranked = Object.values(regions).sort((a, b) => b.load - a.load);
    const maxLoad = ranked[0]?.load || 0;
    const totalLoad = ranked.reduce((sum, region) => sum + region.load, 0);

    if (!maxLoad) {
      return {
        regions: ranked.map((region) => ({ ...region, pct: 0, share: 0, hitLabel: "0 hits" })),
        strongest: [],
        weakest: ranked,
        summary: "No worked-body signal yet",
        detail: "Add more sessions to map which regions are carrying the work.",
      };
    }

    const withMetrics = ranked.map((region) => ({
      ...region,
      pct: Math.round((region.load / maxLoad) * 100),
      share: totalLoad ? region.load / totalLoad : 0,
      hitLabel: `${region.hits} hit${region.hits === 1 ? "" : "s"}`,
    }));
    const strongest = withMetrics.filter((region) => region.load >= maxLoad * 0.8);
    const minNonZero = withMetrics.filter((region) => region.load > 0).slice(-1)[0]?.load || 0;
    const weakest = minNonZero
      ? withMetrics.filter((region) => region.load === minNonZero || region.load === 0)
      : withMetrics;
    const strongestLabel = strongest.slice(0, 2).map((region) => region.label).join(" + ");
    const weakestLabel = weakest.filter((region) => region.load === 0).length
      ? weakest.filter((region) => region.load === 0).slice(0, 2).map((region) => region.label).join(" + ")
      : weakest.slice(0, 2).map((region) => region.label).join(" + ");

    return {
      regions: withMetrics,
      strongest,
      weakest,
      summary: strongest.length > 1 ? `${strongestLabel} carried the work` : `${strongest[0].label} took the load`,
      detail: weakest.some((region) => region.load === 0)
        ? `${weakestLabel} were barely touched.`
        : `${weakestLabel} got the least work.`,
    };
  };

  M.getMuscleFatigueAnalysis = function getMuscleFatigueAnalysis(activities) {
    const configFatigue = M.MUSCLE_FATIGUE_CONFIG;
    const recent = M.getFixedRecentActivities(activities, configFatigue.windowDays);
    const unresolved = buildUnresolvedSummary(recent);
    const now = new Date();
    const regions = Object.fromEntries(
      M.MUSCLE_REGIONS.map((region) => [region.key, {
        ...region,
        rawLoad: 0,
        fatigueScore: 0,
        tier: "Fresh",
        lastWorkedDate: "",
        lastWorkedLabel: "no recent hit",
        recoveryHours: 0,
        recoveryDate: "",
        recoveryLabel: "can likely be trained today",
      }])
    );

    recent.forEach((activity) => {
      const activityDate = M.parseDate(activity.Date);
      activityDate.setHours(12, 0, 0, 0);
      const hoursAgo = Math.max(0, (now - activityDate) / 3600000);
      const stimulus = M.getActivityMuscleStimulus(activity);

      Object.entries(stimulus).forEach(([key, value]) => {
        if (!regions[key] || !value) return;
        const halfLifeHours = configFatigue.halfLifeHours[key] || 60;
        const remaining = value * Math.pow(0.5, hoursAgo / halfLifeHours);
        regions[key].rawLoad += remaining;
        if (!regions[key].lastWorkedDate || activity.Date > regions[key].lastWorkedDate) {
          regions[key].lastWorkedDate = activity.Date;
        }
      });
    });

    const withMetrics = Object.values(regions)
      .map((region) => {
        const normalizationLoad = configFatigue.normalizationLoad[region.key] || 5;
        const threshold = normalizationLoad * (configFatigue.recoveryThresholdRatio || 0.4);
        const halfLifeHours = configFatigue.halfLifeHours[region.key] || 60;
        const recoveryHours = region.rawLoad > threshold && threshold > 0
          ? Math.max(0, Math.ceil(halfLifeHours * Math.log2(region.rawLoad / threshold)))
          : 0;
        const recoveryDate = recoveryHours
          ? new Date(now.getTime() + (recoveryHours * 3600000)).toISOString()
          : now.toISOString();
        const fatigueScore = Math.max(0, Math.min(100, Math.round((region.rawLoad / normalizationLoad) * 100)));
        return {
          ...region,
          rawLoad: Number(region.rawLoad.toFixed(2)),
          fatigueScore,
          tier: M.getMuscleFatigueTier(fatigueScore),
          lastWorkedLabel: M.getRelativeDayLabel(region.lastWorkedDate),
          recoveryHours,
          recoveryDate,
          recoveryLabel: M.getRecoveryLabel(recoveryHours),
        };
      })
      .sort((a, b) => b.fatigueScore - a.fatigueScore || b.rawLoad - a.rawLoad);

    const highestScore = withMetrics[0]?.fatigueScore || 0;
    const lowestScore = withMetrics[withMetrics.length - 1]?.fatigueScore || 0;
    const highestFatigue = highestScore
      ? withMetrics.filter((region) => region.fatigueScore >= Math.max(25, highestScore - 10))
      : [];
    const lowestFatigue = withMetrics.filter((region) => region.fatigueScore === lowestScore);

    if (!recent.length || !highestScore) {
      return {
        regions: withMetrics,
        highestFatigue: [],
        lowestFatigue: withMetrics,
        summary: "Fresh across the board",
        detail: `No meaningful muscle load in the last ${configFatigue.windowDays} days yet.`,
        unresolved,
      };
    }

    const highestLabel = highestFatigue.slice(0, 2).map((region) => region.label).join(" + ");
    const lowestLabel = lowestFatigue.slice(0, 2).map((region) => region.label).join(" + ");
    const topTier = withMetrics[0].tier;

    return {
      regions: withMetrics,
      highestFatigue,
      lowestFatigue,
      summary: `${highestLabel} ${topTier === "Highly fatigued" ? "are carrying the most fatigue" : "need the most recovery"}`,
      detail: `${lowestLabel} look freshest right now.`,
      unresolved,
    };
  };
}());
