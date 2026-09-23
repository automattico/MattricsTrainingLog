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

  function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
  }

  function zeroMuscleStimulus() {
    return Object.fromEntries(M.MUSCLE_REGIONS.map((region) => [region.key, 0]));
  }

  function getRecoveryHoursForThreshold(rawLoad, threshold, halfLifeHours) {
    if (!(rawLoad > threshold) || !(threshold > 0) || !(halfLifeHours > 0)) return 0;
    return Math.max(0, Math.ceil(halfLifeHours * Math.log2(rawLoad / threshold)));
  }

  function getCombinedSeverityAtHours(hoursElapsed, options) {
    const localRemaining = (options.localRawLoad > 0 && options.localHalfLifeHours > 0)
      ? options.localRawLoad * Math.pow(0.5, hoursElapsed / options.localHalfLifeHours)
      : 0;
    const systemicRemaining = (options.systemicRawLoad > 0 && options.systemicHalfLifeHours > 0)
      ? options.systemicRawLoad * Math.pow(0.5, hoursElapsed / options.systemicHalfLifeHours)
      : 0;
    const localSeverity = options.localNormalizationLoad > 0 ? localRemaining / options.localNormalizationLoad : 0;
    const systemicSeverity = options.systemicNormalizationLoad > 0 ? systemicRemaining / options.systemicNormalizationLoad : 0;
    return localSeverity + (systemicSeverity * options.systemicPenaltyWeight);
  }

  function getProjectedRecoveryHours(options) {
    const thresholdRatio = Number(options.thresholdRatio || 0);
    if (!(thresholdRatio > 0)) return 0;
    const initialSeverity = getCombinedSeverityAtHours(0, options);
    if (!(initialSeverity > thresholdRatio)) return 0;

    let low = 0;
    let high = 1;
    const maxProjectionHours = Number(options.maxProjectionHours || (24 * 21));

    while (high < maxProjectionHours && getCombinedSeverityAtHours(high, options) > thresholdRatio) {
      low = high;
      high *= 2;
    }

    high = Math.min(high, maxProjectionHours);
    if (getCombinedSeverityAtHours(high, options) > thresholdRatio) {
      return high;
    }

    while (high - low > 1) {
      const mid = Math.floor((low + high) / 2);
      if (getCombinedSeverityAtHours(mid, options) > thresholdRatio) {
        low = mid;
      } else {
        high = mid;
      }
    }

    return high;
  }

  function strengthFactor(min) {
    return clamp((min || 0) / 45, 0.85, 1.6);
  }

  function sportFactor(min) {
    return clamp((min || 0) / 60, 0.7, 1.45);
  }

  function addScaledStimulus(target, weights, factor) {
    Object.entries(weights || {}).forEach(([key, value]) => {
      if (!(key in target) || !value) return;
      target[key] += value * factor;
    });
  }

  function normalizeName(value) {
    return typeof M.normalizeExerciseConfigName === "function"
      ? M.normalizeExerciseConfigName(value)
      : String(value == null ? "" : value).toLowerCase().trim();
  }

  function inferExerciseFamily(record) {
    const haystack = normalizeName([
      record && record.id,
      record && record.canonicalName,
      ...(Array.isArray(record && record.aliases) ? record.aliases : []),
      ...(Array.isArray(record && record.matchTerms) ? record.matchTerms : []),
    ].join(" "));

    if (/\b(squat|lunge|leg press|split squat|step up|stepup)\b/.test(haystack)) return "squat";
    if (/\b(deadlift|rdl|romanian|hinge)\b/.test(haystack)) return "hinge";
    if (/\b(hip thrust|glute bridge|back extension)\b/.test(haystack)) return "hip_dominant";
    if (/\b(hip abduction|hip adduction|glute kick)\b/.test(haystack)) return "hip_isolation";
    if (/\b(bench|push up|pushup|chest press|incline|dip)\b/.test(haystack)) return "horizontal_press";
    if (/\b(row|face pull)\b/.test(haystack)) return "horizontal_pull";
    if (/\b(pulldown|pull up|pullup|chin up|chinup)\b/.test(haystack)) return "vertical_pull";
    if (/\b(shoulder press|overhead press|arnold press)\b/.test(haystack)) return "vertical_press";
    if (/\b(lateral raise|front raise|rear delt|rotation)\b/.test(haystack)) return "shoulder_isolation";
    if (/\b(curl|triceps|pushdown|skull crusher|extension)\b/.test(haystack)) return "arm_isolation";
    if (/\b(calf)\b/.test(haystack)) return "calf";
    if (/\b(core|plank|crunch|twist|dead bug|hollow|sit up|leg raise|bird dog|heel taps)\b/.test(haystack)) return "core";
    if (/\b(treadmill|run|running)\b/.test(haystack)) return "conditioning_lower";
    return "horizontal_press";
  }

  function getExerciseFamily(record) {
    return (record && record.exerciseFamily) || inferExerciseFamily(record);
  }

  function inferFatigueArchetype(record) {
    const family = getExerciseFamily(record);
    const haystack = normalizeName([
      record && record.id,
      record && record.canonicalName,
      ...(Array.isArray(record && record.aliases) ? record.aliases : []),
      ...(Array.isArray(record && record.matchTerms) ? record.matchTerms : []),
    ].join(" "));

    if (family === "squat" || family === "hinge") return "hinge_squat";
    if (family === "conditioning_lower") return "conditioning_hybrid";
    if (["arm_isolation", "shoulder_isolation", "calf", "core", "hip_isolation", "knee_isolation"].includes(family)) return "isolation";
    if (/\b(machine|cable)\b/.test(haystack)) return "machine_compound";
    return "freeweight_compound";
  }

  function getFatigueArchetype(record) {
    return (record && record.fatigueArchetype) || inferFatigueArchetype(record);
  }

  M.getFatigueArchetypeShares = function getFatigueArchetypeShares(record) {
    const configFatigue = M.MUSCLE_FATIGUE_CONFIG || {};
    const archetype = getFatigueArchetype(record);
    const configured = configFatigue.fatigueArchetypeShares || {};
    return configured[archetype] || { localShare: 1, systemicShare: 0 };
  };

  M.getStrengthExperienceScale = function getStrengthExperienceScale() {
    const configFatigue = M.MUSCLE_FATIGUE_CONFIG || {};
    const scales = configFatigue.experienceStrengthScale || {};
    const rawLevel = (M.state && M.state.userSettings && M.state.userSettings.experienceLevel) || "";
    const matchingKey = Object.keys(scales).find((key) => key.toLowerCase() === String(rawLevel).toLowerCase());
    return matchingKey ? Number(scales[matchingKey] || 1) : 1;
  };

  M.getRepRangeKey = function getRepRangeKey(reps) {
    const value = Number(reps || 0);
    if (value <= 5) return "1-5";
    if (value <= 8) return "6-8";
    if (value <= 12) return "9-12";
    return "13+";
  };

  function median(values) {
    const sorted = values.filter((value) => Number.isFinite(value) && value > 0).sort((a, b) => a - b);
    if (!sorted.length) return 0;
    const mid = Math.floor(sorted.length / 2);
    return sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
  }

  function workingSetLoads(values) {
    const configFatigue = M.MUSCLE_FATIGUE_CONFIG || {};
    const minCount = Number(configFatigue.minimumReferenceSetCount || 3);
    const clean = values.filter((value) => Number.isFinite(value) && value > 0);
    if (clean.length < minCount) return [];
    const max = Math.max(...clean);
    const filtered = clean.filter((value) => value >= max * 0.55);
    return filtered.length >= minCount ? filtered : clean;
  }

  function getUserBodyweightKg() {
    const configFatigue = M.MUSCLE_FATIGUE_CONFIG || {};
    const settingsWeight = M.state && M.state.userSettings && M.state.userSettings.bodyWeightKg;
    return Number(settingsWeight || configFatigue.estimatedBodyweightKg || 75);
  }

  function getCanonicalWorkoutBlockSetInputs(exercise) {
    const setDetails = Array.isArray(exercise && exercise.setDetails) ? exercise.setDetails : [];
    return setDetails.map((setDetail) => {
      const parsedKind = String(setDetail && setDetail.parsedKind || "");
      if (parsedKind === "parsed") {
        const reps = Number(setDetail && setDetail.reps);
        const weightKg = Number(setDetail && setDetail.weightKg);
        const effortFactor = Number(setDetail && setDetail.effortFactor);
        if (!Number.isFinite(reps) || reps <= 0 || !Number.isFinite(weightKg) || weightKg <= 0) {
          return { kind: "unknown" };
        }
        return {
          kind: "parsed",
          reps: Math.round(reps),
          weightKg,
          effortFactor: Number.isFinite(effortFactor) && effortFactor > 0 ? effortFactor : 1,
        };
      }

      if (parsedKind === "time") {
        const minutes = Number(setDetail && setDetail.durationMinutes);
        const distanceKm = Number(setDetail && setDetail.distanceKm);
        if ((!Number.isFinite(minutes) || minutes <= 0) && (!Number.isFinite(distanceKm) || distanceKm <= 0)) {
          return { kind: "unknown" };
        }
        return {
          kind: "time",
          minutes: Number.isFinite(minutes) && minutes > 0 ? minutes : 0,
          distanceKm: Number.isFinite(distanceKm) && distanceKm > 0 ? distanceKm : 0,
        };
      }

      return { kind: "unknown" };
    });
  }

  function getWorkoutBlockSetInputs(exercise) {
    if (exercise && exercise.source === "canonical") {
      return getCanonicalWorkoutBlockSetInputs(exercise);
    }

    return (Array.isArray(exercise && exercise.sets) ? exercise.sets : []).map((setText) => (
      M.parseHevySetLine(setText, exercise && exercise.name)
    ));
  }

  function getWorkoutBlockSetCount(exercise) {
    if (exercise && exercise.source === "canonical") {
      return Array.isArray(exercise && exercise.setDetails) ? exercise.setDetails.length : 0;
    }

    return Array.isArray(exercise && exercise.sets) ? exercise.sets.length : 0;
  }

  M.getPersonalReferenceLoadDetails = function getPersonalReferenceLoadDetails(exerciseConfig, reps, activities, options = {}) {
    const configFatigue = M.MUSCLE_FATIGUE_CONFIG || {};
    const allActivities = Array.isArray(activities) ? activities : [];
    const targetFamily = getExerciseFamily(exerciseConfig);
    const targetId = exerciseConfig && exerciseConfig.id ? String(exerciseConfig.id) : "";
    const targetName = normalizeName(exerciseConfig && exerciseConfig.canonicalName);
    const targetRepRange = M.getRepRangeKey(reps);
    const exerciseLoads = [];
    const familyLoads = [];

    allActivities.forEach((activity) => {
      if (options.excludeActivity && activity === options.excludeActivity) return;
      const hevy = M.getActivityWorkoutBlocks(activity);
      if (!Array.isArray(hevy) || !hevy.length) return;

      hevy.forEach((exercise) => {
        const candidateConfig = M.resolveExerciseConfig(exercise && exercise.name);
        if (!candidateConfig) return;
        const candidateFamily = getExerciseFamily(candidateConfig);
        const sameExercise = (
          (targetId && candidateConfig.id === targetId)
          || (targetName && normalizeName(candidateConfig.canonicalName) === targetName)
        );
        const sameFamily = targetFamily && candidateFamily === targetFamily;
        if (!sameExercise && !sameFamily) return;

        getWorkoutBlockSetInputs(exercise).forEach((parsed) => {
          if (parsed.kind !== "parsed" || M.getRepRangeKey(parsed.reps) !== targetRepRange) return;
          if (sameExercise) exerciseLoads.push(parsed.weightKg);
          if (sameFamily) familyLoads.push(parsed.weightKg);
        });
      });
    });

    const exerciseReference = median(workingSetLoads(exerciseLoads));
    if (exerciseReference > 0) {
      return { loadKg: exerciseReference, source: "exercise_history", family: targetFamily, repRange: targetRepRange };
    }

    const familyReference = median(workingSetLoads(familyLoads));
    if (familyReference > 0) {
      return { loadKg: familyReference, source: "family_history", family: targetFamily, repRange: targetRepRange };
    }

    const defaults = configFatigue.exerciseFamilyReferenceLoadKg || {};
    const baseReference = Number(defaults[targetFamily] || 50);
    const bodyweightAdjustment = clamp(getUserBodyweightKg() / 75, 0.8, 1.25);
    const loadKg = baseReference * M.getStrengthExperienceScale() * bodyweightAdjustment;
    return { loadKg, source: "heuristic", family: targetFamily, repRange: targetRepRange };
  };

  M.getPersonalReferenceLoadKg = function getPersonalReferenceLoadKg(exerciseConfig, reps, activities, options = {}) {
    return M.getPersonalReferenceLoadDetails(exerciseConfig, reps, activities, options).loadKg;
  };

  M.getActivityFatigueStimulus = function getActivityFatigueStimulus(activity, options = {}) {
    const min = parseFloat(activity["Duration (min)"]) || 0;
    const type = activity.Type;
    const hevy = M.getActivityWorkoutBlocks(activity);
    const localStimulus = zeroMuscleStimulus();
    let systemicStimulus = 0;
    const configFatigue = M.MUSCLE_FATIGUE_CONFIG;
    const setLoadDivisor = configFatigue.strengthLoadUnitDivisor || 1500;
    const allActivities = Array.isArray(options.allActivities) ? options.allActivities : (M.state && M.state.allData) || [];
    const splitStimulus = (value, configRecord) => {
      const shares = M.getFatigueArchetypeShares(configRecord);
      return {
        local: value * Number(shares.localShare || 0),
        systemic: value * Number(shares.systemicShare || 0),
      };
    };

    if (hevy && hevy.length) {
      let matchedExercise = false;
      hevy.forEach((exercise) => {
        const match = M.getExerciseMuscleMapping(exercise.name);
        if (!match) return;
        matchedExercise = true;
        if (match.fatigueImpact === "none") return;
        const weights = match.muscleWeights || match.weights || {};
        const setTypeHandling = String(match.setTypeHandling || "weight_reps");
        const mappedNormalizations = Object.keys(weights)
          .map((key) => configFatigue.normalizationLoad[key] || 0)
          .filter(Boolean);
        const smallThreshold = (Math.max(...mappedNormalizations, 0) || 0) * (configFatigue.smallThresholdRatio || 0.02);
        let parsedExerciseLoad = 0;
        let parsedSetCount = 0;
        let timeDurationMinutes = 0;
        let ambiguousSetFound = false;
        let nonTimeSetFound = false;
        const normalizedSets = getWorkoutBlockSetInputs(exercise);

        normalizedSets.forEach((parsed) => {
          if (parsed.kind === "time") {
            if (setTypeHandling === "time_duration") {
              timeDurationMinutes += Number(parsed.minutes || 0);
            }
            return;
          }
          nonTimeSetFound = true;
          if (parsed.kind !== "parsed") {
            ambiguousSetFound = true;
            return;
          }

          const referenceLoadKg = M.getPersonalReferenceLoadKg(match, parsed.reps, allActivities, {
            excludeActivity: options.excludeActivity || activity,
          });
          const relativeFactor = referenceLoadKg > 0
            ? clamp(parsed.weightKg / referenceLoadKg, configFatigue.relativeFactorMin || 0.55, configFatigue.relativeFactorMax || 1.35)
            : 1;
          const setStimulus = ((parsed.weightKg * parsed.reps) / setLoadDivisor) * parsed.effortFactor * relativeFactor;
          if (setStimulus < smallThreshold) return;
          parsedExerciseLoad += setStimulus;
          parsedSetCount += 1;
        });

        if (parsedSetCount && !ambiguousSetFound) {
          const split = splitStimulus(parsedExerciseLoad, match);
          addScaledStimulus(localStimulus, weights, split.local);
          systemicStimulus += split.systemic;
          return;
        }

        if (setTypeHandling === "time_duration" && timeDurationMinutes > 0 && !nonTimeSetFound) {
          const timeFactor = clamp(timeDurationMinutes / 60, 0, 1.45);
          const split = splitStimulus(timeFactor, match);
          addScaledStimulus(localStimulus, weights, split.local);
          systemicStimulus += split.systemic;
          return;
        }

        if (!parsedSetCount && !nonTimeSetFound) {
          return;
        }

        const setCount = getWorkoutBlockSetCount(exercise);
        const setFactor = clamp(setCount / 3, 0.8, 1.6);
        // Fallback load now comes only from duration, set count, and the existing
        // archetype share split when a Hevy exercise cannot be parsed into sets.
        const split = splitStimulus(strengthFactor(min) * setFactor, match);
        addScaledStimulus(localStimulus, weights, split.local);
        systemicStimulus += split.systemic;
      });
      if (matchedExercise) return { localStimulus, systemicStimulus };
    }

    const typeConfig = M.resolveActivityTypeConfig(type);
    if (!typeConfig) return { localStimulus, systemicStimulus };
    if (typeConfig.fatigueImpact === "none") return { localStimulus, systemicStimulus };
    const factor = typeConfig.canonicalName === "WeightTraining" ? strengthFactor(min) : sportFactor(min);
    const split = splitStimulus(factor, typeConfig);
    addScaledStimulus(localStimulus, typeConfig.muscleWeights || {}, split.local);
    systemicStimulus += split.systemic;
    return { localStimulus, systemicStimulus };
  };

  M.getActivityMuscleStimulus = function getActivityMuscleStimulus(activity, options = {}) {
    return M.getActivityFatigueStimulus(activity, options).localStimulus;
  };

  M.getMuscleFatigueAnalysis = function getMuscleFatigueAnalysis(activities) {
    const configFatigue = M.MUSCLE_FATIGUE_CONFIG;
    const recent = M.getFixedRecentActivities(activities, configFatigue.windowDays);
    const unresolved = buildUnresolvedSummary(recent);
    const now = new Date();
    const systemicNormalizationLoad = Number(configFatigue.systemicNormalizationLoad || 2.5);
    const systemicBaseHalfLife = Number(configFatigue.systemicBaseHalfLifeHours || 24);
    const systemicPenaltyWeight = Number(configFatigue.systemicPenaltyWeight || 0.25);
    const systemic = {
      rawLoad: 0,
      fatigueScore: 0,
      tier: "Fresh",
      recoveryHours: 0,
      recoveryDate: "",
      recoveryLabel: "can likely be trained today",
    };
    const regions = Object.fromEntries(
      M.MUSCLE_REGIONS.map((region) => [region.key, {
        ...region,
        rawLoad: 0,
        localFatigueScore: 0,
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
      const fatigueStimulus = M.getActivityFatigueStimulus(activity, {
        allActivities: activities,
        excludeActivity: activity,
      });
      const stimulus = fatigueStimulus.localStimulus || {};

      Object.entries(stimulus).forEach(([key, value]) => {
        if (!regions[key] || !value) return;
        const normalizationLoad = configFatigue.normalizationLoad[key] || 5;
        const baseHalfLife = configFatigue.halfLifeHours[key] || 60;
        const severity = normalizationLoad > 0 ? value / normalizationLoad : 0;
        const halfLifeHours = baseHalfLife * clamp(
          0.85 + (0.35 * severity),
          configFatigue.localHalfLifeScaleMin || 0.85,
          configFatigue.localHalfLifeScaleMax || 1.35
        );
        const remaining = value * Math.pow(0.5, hoursAgo / halfLifeHours);
        regions[key].rawLoad += remaining;
        if (!regions[key].lastWorkedDate || activity.Date > regions[key].lastWorkedDate) {
          regions[key].lastWorkedDate = activity.Date;
        }
      });

      if (fatigueStimulus.systemicStimulus > 0) {
        const systemicSeverity = systemicNormalizationLoad > 0 ? fatigueStimulus.systemicStimulus / systemicNormalizationLoad : 0;
        const adjustedSystemicHalfLife = systemicBaseHalfLife * clamp(
          0.9 + (0.4 * systemicSeverity),
          configFatigue.systemicHalfLifeScaleMin || 0.9,
          configFatigue.systemicHalfLifeScaleMax || 1.5
        );
        systemic.rawLoad += fatigueStimulus.systemicStimulus * Math.pow(0.5, hoursAgo / adjustedSystemicHalfLife);
      }
    });

    const systemicThreshold = systemicNormalizationLoad * (configFatigue.recoveryThresholdRatio || 0.25);
    const systemicTrainableThreshold = systemicNormalizationLoad * (configFatigue.trainableThresholdRatio || 0.5);
    const systemicSeverity = systemicNormalizationLoad > 0 ? systemic.rawLoad / systemicNormalizationLoad : 0;
    const adjustedSystemicRecoveryHalfLife = systemicBaseHalfLife * clamp(
      0.9 + (0.4 * systemicSeverity),
      configFatigue.systemicHalfLifeScaleMin || 0.9,
      configFatigue.systemicHalfLifeScaleMax || 1.5
    );
    systemic.fatigueScore = Math.max(0, Math.min(100, Math.round((systemic.rawLoad / systemicNormalizationLoad) * 100)));
    systemic.tier = M.getMuscleFatigueTier(systemic.fatigueScore);
    systemic.freshRecoveryHours = getRecoveryHoursForThreshold(
      systemic.rawLoad,
      systemicThreshold,
      adjustedSystemicRecoveryHalfLife
    );
    systemic.recoveryHours = getRecoveryHoursForThreshold(
      systemic.rawLoad,
      systemicTrainableThreshold,
      adjustedSystemicRecoveryHalfLife
    );
    systemic.recoveryDate = systemic.recoveryHours
      ? new Date(now.getTime() + (systemic.recoveryHours * 3600000)).toISOString()
      : now.toISOString();
    systemic.recoveryLabel = M.getRecoveryLabel(systemic.recoveryHours);
    systemic.rawLoad = Number(systemic.rawLoad.toFixed(2));

    const withMetrics = Object.values(regions)
      .map((region) => {
        const normalizationLoad = configFatigue.normalizationLoad[region.key] || 5;
        const threshold = normalizationLoad * (configFatigue.recoveryThresholdRatio || 0.25);
        const trainableThreshold = normalizationLoad * (configFatigue.trainableThresholdRatio || 0.5);
        const baseHalfLife = configFatigue.halfLifeHours[region.key] || 60;
        const localSeverity = normalizationLoad > 0 ? region.rawLoad / normalizationLoad : 0;
        const halfLifeHours = baseHalfLife * clamp(
          0.85 + (0.35 * localSeverity),
          configFatigue.localHalfLifeScaleMin || 0.85,
          configFatigue.localHalfLifeScaleMax || 1.35
        );
        const systemicPenalty = systemic.fatigueScore * systemicPenaltyWeight;
        const recoveryProjection = {
          localRawLoad: region.rawLoad,
          localNormalizationLoad: normalizationLoad,
          localHalfLifeHours: halfLifeHours,
          systemicRawLoad: systemic.rawLoad,
          systemicNormalizationLoad,
          systemicHalfLifeHours: adjustedSystemicRecoveryHalfLife,
          systemicPenaltyWeight,
        };
        const recoveryHours = getProjectedRecoveryHours({
          ...recoveryProjection,
          thresholdRatio: configFatigue.trainableThresholdRatio || 0.5,
        });
        const freshRecoveryHours = getProjectedRecoveryHours({
          ...recoveryProjection,
          thresholdRatio: configFatigue.recoveryThresholdRatio || 0.25,
        });
        const recoveryDate = recoveryHours
          ? new Date(now.getTime() + (recoveryHours * 3600000)).toISOString()
          : now.toISOString();
        const localFatigueScore = Math.max(0, Math.min(100, Math.round((region.rawLoad / normalizationLoad) * 100)));
        const fatigueScore = Math.max(0, Math.min(100, Math.round(localFatigueScore + systemicPenalty)));
        return {
          ...region,
          rawLoad: Number(region.rawLoad.toFixed(2)),
          localFatigueScore,
          systemicPenalty: Number(systemicPenalty.toFixed(1)),
          hasFatigueSignal: Boolean(region.rawLoad || systemicPenalty),
          fatigueScore,
          tier: M.getMuscleFatigueTier(fatigueScore),
          lastWorkedLabel: M.getRelativeDayLabel(region.lastWorkedDate),
          freshRecoveryHours,
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
        systemic,
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
      systemic,
      unresolved,
    };
  };
}());
