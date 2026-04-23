(function () {
  const M = window.Mattrics;

  function cloneResolvedConfig(record) {
    if (!record) return null;
    return {
      ...record,
      aliases: Array.isArray(record.aliases) ? record.aliases.slice() : [],
      matchTerms: Array.isArray(record.matchTerms) ? record.matchTerms.slice() : [],
      muscleWeights: { ...(record.muscleWeights || {}) },
      weights: { ...(record.muscleWeights || {}) },
    };
  }

  function ensureArray(value) {
    return Array.isArray(value) ? value : [];
  }

  function cloneUnknownRecord(record) {
    if (!record) return null;
    return {
      ...record,
      rawNames: ensureArray(record.rawNames).slice(),
    };
  }

  function setUnknownExerciseState(records, meta = {}) {
    M.state.unknownExercises = ensureArray(records).map(cloneUnknownRecord);
    M.state.unknownExerciseMeta = {
      loadedAt: meta.loadedAt || M.state.exerciseConfigMeta.loadedAt || "",
      syncedAt: meta.syncedAt || "",
      lastSyncError: meta.lastSyncError || "",
    };
  }

  M.setUnknownExerciseSnapshot = function setUnknownExerciseSnapshot(records, meta = {}) {
    setUnknownExerciseState(records, meta);
    return M.state.unknownExercises;
  };

  M.normalizeExerciseConfigName = function normalizeExerciseConfigName(name) {
    return String(name == null ? "" : name)
      .replace(/([a-z0-9])([A-Z])/g, "$1 $2")
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, " ")
      .replace(/\s+/g, " ")
      .trim();
  };

  function getExerciseMuscleUiLevels() {
    return Array.isArray(M.EXERCISE_MUSCLE_UI_LEVELS) ? M.EXERCISE_MUSCLE_UI_LEVELS.slice() : [];
  }

  function getExerciseMuscleUiLevelMap() {
    return Object.fromEntries(getExerciseMuscleUiLevels().map((level) => [level.key, level]));
  }

  M.getExerciseMuscleUiLevel = function getExerciseMuscleUiLevel(levelKey) {
    return getExerciseMuscleUiLevelMap()[String(levelKey || "")] || null;
  };

  M.getExerciseMuscleUiLevels = function getExerciseMuscleUiLevelsExport() {
    return getExerciseMuscleUiLevels();
  };

  M.getExerciseMuscleLevelKeyForWeight = function getExerciseMuscleLevelKeyForWeight(weight) {
    const value = Number(weight || 0);
    if (!Number.isFinite(value) || value <= 0) return "";

    const thresholds = M.EXERCISE_MUSCLE_UI_THRESHOLDS || {};
    if (value <= Number(thresholds.stabilizerMax || 0.14)) return "stabilizer";
    if (value <= Number(thresholds.minorMax || 0.32)) return "minor";
    if (value <= Number(thresholds.secondaryMax || 0.55)) return "secondary";
    if (value <= Number(thresholds.strongSecondaryMax || 0.82)) return "strongSecondary";
    return "primary";
  };

  M.getExerciseMuscleWeightForLevelKey = function getExerciseMuscleWeightForLevelKey(levelKey) {
    const level = M.getExerciseMuscleUiLevel(levelKey);
    return level ? Number(level.weight) : 0;
  };

  M.getExerciseMuscleEditorState = function getExerciseMuscleEditorState(muscleWeights) {
    const weights = muscleWeights && typeof muscleWeights === "object" ? muscleWeights : {};
    const highestPositive = M.MUSCLE_REGIONS.reduce((max, region) => {
      const value = Number(weights[region.key] || 0);
      return Number.isFinite(value) && value > max ? value : max;
    }, 0);

    const hasExplicitPrimary = M.MUSCLE_REGIONS.some((region) => (
      M.getExerciseMuscleLevelKeyForWeight(weights[region.key]) === "primary"
    ));

    return Object.fromEntries(M.MUSCLE_REGIONS.map((region) => {
      const rawWeight = Number(weights[region.key] || 0);
      const involved = Number.isFinite(rawWeight) && rawWeight > 0;
      let levelKey = M.getExerciseMuscleLevelKeyForWeight(rawWeight);

      if (
        involved
        && !hasExplicitPrimary
        && highestPositive > 0
        && rawWeight === highestPositive
      ) {
        levelKey = "primary";
      }

      const level = M.getExerciseMuscleUiLevel(levelKey || M.EXERCISE_MUSCLE_DEFAULT_LEVEL);
      return [
        region.key,
        {
          involved,
          levelKey: involved ? levelKey : "",
          levelIndex: involved && level ? level.index : -1,
          label: involved && level ? level.label : "Not involved",
          meaning: involved && level ? level.meaning : "",
          weight: involved && level ? Number(level.weight) : 0,
          rawWeight: involved ? rawWeight : 0,
        },
      ];
    }));
  };

  M.hasExerciseMusclePrimary = function hasExerciseMusclePrimary(muscleWeights) {
    const editorState = M.getExerciseMuscleEditorState(muscleWeights);
    return Object.values(editorState).some((item) => item && item.involved && item.levelKey === "primary");
  };

  M.indexExerciseConfigs = function indexExerciseConfigs(payload) {
    const exercises = ensureArray(payload && payload.exercises);
    const activityTypes = ensureArray(payload && payload.activityTypes);
    const unknowns = ensureArray(payload && payload.unknowns);
    const meta = payload && payload.meta && typeof payload.meta === "object" ? payload.meta : {};
    const normalize = M.normalizeExerciseConfigName;

    const index = {
      exercisesByCanonical: new Map(),
      exercisesByAlias: new Map(),
      exerciseMatchTerms: [],
      activityTypesByCanonical: new Map(),
      activityTypesByAlias: new Map(),
    };

    exercises.forEach((record) => {
      const normalizedName = normalize(record && record.normalizedName ? record.normalizedName : record && record.canonicalName);
      if (normalizedName && !index.exercisesByCanonical.has(normalizedName)) {
        index.exercisesByCanonical.set(normalizedName, record);
      }

      ensureArray(record && record.aliases).forEach((alias) => {
        const normalizedAlias = normalize(alias);
        if (normalizedAlias && !index.exercisesByAlias.has(normalizedAlias)) {
          index.exercisesByAlias.set(normalizedAlias, record);
        }
      });

      ensureArray(record && record.matchTerms).forEach((term) => {
        const normalizedTerm = normalize(term);
        if (!normalizedTerm) return;
        index.exerciseMatchTerms.push({ term: normalizedTerm, record });
      });
    });

    activityTypes.forEach((record) => {
      const normalizedName = normalize(record && record.normalizedName ? record.normalizedName : record && record.canonicalName);
      if (normalizedName && !index.activityTypesByCanonical.has(normalizedName)) {
        index.activityTypesByCanonical.set(normalizedName, record);
      }

      ensureArray(record && record.aliases).forEach((alias) => {
        const normalizedAlias = normalize(alias);
        if (normalizedAlias && !index.activityTypesByAlias.has(normalizedAlias)) {
          index.activityTypesByAlias.set(normalizedAlias, record);
        }
      });
    });

    M.state.exerciseConfigs = exercises;
    M.state.activityTypeConfigs = activityTypes;
    M.state.exerciseConfigMeta = {
      loadedAt: meta.loadedAt || "",
      seedVersion: Number(meta.seedVersion || 0),
    };
    M.state.exerciseConfigIndex = index;
    setUnknownExerciseState(unknowns, {
      loadedAt: meta.loadedAt || "",
      syncedAt: meta.syncedAt || "",
      lastSyncError: "",
    });

    return index;
  };

  M.resolveExerciseConfig = function resolveExerciseConfig(inputName) {
    const index = M.state && M.state.exerciseConfigIndex;
    if (!index) return null;

    const normalized = M.normalizeExerciseConfigName(inputName);
    if (!normalized) return null;

    const canonicalMatch = index.exercisesByCanonical.get(normalized);
    if (canonicalMatch) return cloneResolvedConfig(canonicalMatch);

    const aliasMatch = index.exercisesByAlias.get(normalized);
    if (aliasMatch) return cloneResolvedConfig(aliasMatch);

    const substringMatch = index.exerciseMatchTerms.find((entry) => normalized.includes(entry.term));
    return substringMatch ? cloneResolvedConfig(substringMatch.record) : null;
  };

  M.resolveActivityTypeConfig = function resolveActivityTypeConfig(inputType) {
    const index = M.state && M.state.exerciseConfigIndex;
    if (!index) return null;

    const normalized = M.normalizeExerciseConfigName(inputType);
    if (!normalized) return null;

    const canonicalMatch = index.activityTypesByCanonical.get(normalized);
    if (canonicalMatch) return cloneResolvedConfig(canonicalMatch);

    const aliasMatch = index.activityTypesByAlias.get(normalized);
    return aliasMatch ? cloneResolvedConfig(aliasMatch) : null;
  };

  M.makeUnknownExerciseId = function makeUnknownExerciseId(sourceType, normalizedName) {
    return `${sourceType}:${normalizedName}`;
  };

  M.collectUnknownExercisesFromActivities = function collectUnknownExercisesFromActivities(activities) {
    const unknownsById = new Map();
    const addUnknown = (sourceType, rawName) => {
      const normalizedName = M.normalizeExerciseConfigName(rawName);
      if (!normalizedName) return;
      const id = M.makeUnknownExerciseId(sourceType, normalizedName);
      const existing = unknownsById.get(id);
      const cleanRawName = String(rawName == null ? "" : rawName).trim();
      if (existing) {
        existing.timesSeen += 1;
        if (cleanRawName && !existing.rawNames.includes(cleanRawName)) {
          existing.rawNames.push(cleanRawName);
        }
        return;
      }
      unknownsById.set(id, {
        id,
        sourceType,
        normalizedName,
        rawNames: cleanRawName ? [cleanRawName] : [],
        timesSeen: 1,
      });
    };

    ensureArray(activities).forEach((activity) => {
      const hevyExercises = M.parseHevyDescription(activity && activity.Description);
      if (Array.isArray(hevyExercises) && hevyExercises.length) {
        hevyExercises.forEach((exercise) => {
          if (!M.resolveExerciseConfig(exercise && exercise.name)) {
            addUnknown("exercise", exercise && exercise.name);
          }
        });
        return;
      }

      if (!M.resolveActivityTypeConfig(activity && activity.Type)) {
        addUnknown("activityType", activity && activity.Type);
      }
    });

    return Array.from(unknownsById.values())
      .map((record) => ({
        ...record,
        rawNames: record.rawNames.slice().sort((left, right) => left.localeCompare(right)),
      }))
      .sort((left, right) => left.id.localeCompare(right.id));
  };

  M.syncUnknownExercises = async function syncUnknownExercises(unknowns) {
    const payload = ensureArray(unknowns).map((record) => ({
      id: M.makeUnknownExerciseId(record.sourceType, record.normalizedName),
      sourceType: record.sourceType,
      normalizedName: record.normalizedName,
      rawNames: ensureArray(record.rawNames),
      timesSeen: Number(record.timesSeen || 0),
    }));

    const res = await fetch(M.EXERCISE_CONFIG_URL, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": (window.MATTRICS_AUTH && window.MATTRICS_AUTH.csrfToken) || "",
      },
      body: JSON.stringify({ unknowns: payload }),
    });
    if (!res.ok) {
      let message = `Unknown sync request failed. HTTP ${res.status}`;
      try {
        const json = await res.json();
        if (json && json.error) message = json.error;
      } catch {
        // Fall back to the status-based message.
      }
      throw new Error(message);
    }

    const json = await res.json();
    if (!Array.isArray(json.unknowns)) {
      throw new Error("Unknown sync response was not valid.");
    }

    setUnknownExerciseState(json.unknowns, {
      loadedAt: M.state.exerciseConfigMeta.loadedAt || "",
      syncedAt: json.meta && json.meta.syncedAt ? json.meta.syncedAt : new Date().toISOString(),
      lastSyncError: "",
    });
    return json.unknowns;
  };

  M.scanAndSyncUnknownExercises = async function scanAndSyncUnknownExercises(activities) {
    const unknowns = M.collectUnknownExercisesFromActivities(activities);
    try {
      return await M.syncUnknownExercises(unknowns);
    } catch (error) {
      M.state.unknownExerciseMeta = {
        ...M.state.unknownExerciseMeta,
        lastSyncError: String(error && error.message ? error.message : error),
      };
      throw error;
    }
  };

  M.loadExerciseConfigs = async function loadExerciseConfigs() {
    if (!M.EXERCISE_CONFIG_URL) {
      throw new Error(
        "No exercise config source configured.\n\nUse api/exercises.php from a local or deployed PHP server so the private config seed files stay outside the public web root."
      );
    }

    const res = await fetch(M.EXERCISE_CONFIG_URL, {
      credentials: "same-origin",
      headers: {},
      redirect: "follow",
    });
    if (!res.ok) throw new Error(`Exercise config request failed. HTTP ${res.status}`);

    const json = await res.json();
    if (json.error) throw new Error(json.error);
    if (!Array.isArray(json.exercises) || !Array.isArray(json.activityTypes) || !Array.isArray(json.unknowns)) {
      throw new Error("Exercise config response was not valid.");
    }

    M.indexExerciseConfigs(json);
    return json;
  };
}());
