(function () {
  const M = window.Mattrics;
  const SET_TYPE_OPTIONS = [
    { value: "weight_reps", label: "Strength / reps" },
    { value: "time_duration", label: "Time / cardio" },
  ];
  const EXERCISE_FAMILY_OPTIONS = [
    { value: "", label: "Auto / unset" },
    { value: "horizontal_press", label: "Horizontal press" },
    { value: "vertical_press", label: "Vertical press" },
    { value: "horizontal_pull", label: "Horizontal pull" },
    { value: "vertical_pull", label: "Vertical pull" },
    { value: "squat", label: "Squat" },
    { value: "hinge", label: "Hinge" },
    { value: "hip_dominant", label: "Hip dominant" },
    { value: "knee_isolation", label: "Knee isolation" },
    { value: "hip_isolation", label: "Hip isolation" },
    { value: "arm_isolation", label: "Arm isolation" },
    { value: "shoulder_isolation", label: "Shoulder isolation" },
    { value: "calf", label: "Calf" },
    { value: "core", label: "Core" },
    { value: "conditioning_lower", label: "Conditioning lower" },
  ];
  const FATIGUE_ARCHETYPE_OPTIONS = [
    { value: "", label: "Auto / unset" },
    { value: "isolation", label: "Isolation" },
    { value: "machine_compound", label: "Machine compound" },
    { value: "freeweight_compound", label: "Freeweight compound" },
    { value: "hinge_squat", label: "Hinge / squat" },
    { value: "conditioning_hybrid", label: "Conditioning hybrid" },
  ];
  const LIST_FILTER_OPTIONS = [
    { value: "all", label: "All configs" },
    { value: "exercise", label: "Exercises" },
    { value: "activityType", label: "Activity types" },
  ];
  const AI_STATUS_LABELS = {
    not_requested: "No suggestion yet",
    succeeded: "Suggestion ready",
    invalid_response: "Last suggestion failed validation",
    failed: "Last suggestion request failed",
  };
  const DELETE_CONFIRMATION_COPY = "Are you sure you want to permanently delete this exercise? Once deleted it cannot be recovered!";

  M.EXERCISE_ADMIN_DELETE_CONFIRMATION_COPY = DELETE_CONFIRMATION_COPY;

  function esc(value) {
    const input = String(value == null ? "" : value);
    if (typeof M.esc === "function") {
      return M.esc(input);
    }
    return input.replace(/[&<>"']/g, (char) => ({
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#39;",
    }[char]));
  }

  function escAttr(value) {
    const input = String(value == null ? "" : value);
    if (typeof M.escAttr === "function") {
      return M.escAttr(input);
    }
    return esc(input);
  }

  function getAdminState() {
    if (!M.state.exerciseAdmin || typeof M.state.exerciseAdmin !== "object") {
      M.state.exerciseAdmin = {
        query: "",
        filter: "all",
        selectedKey: "",
        feedback: null,
        pendingAction: "",
        pendingKey: "",
        pendingUnknownId: "",
        mergeTargetId: "",
        titleEditOpen: false,
        titleDraft: "",
        namesEditOpen: false,
        namesDraft: "",
        mergePanelKey: "",
        confirmDialog: {
          open: false,
          type: "",
          key: "",
          kind: "",
        },
        formDraft: null,
        formErrors: null,
      };
    }
    return M.state.exerciseAdmin;
  }

  function tooltip(text) {
    return `<span class="tooltip-wrap" tabindex="0" aria-label="${escAttr(text)}">
      <span class="tooltip-icon" aria-hidden="true">?</span>
      <span class="tooltip-text" role="tooltip">${esc(text)}</span>
    </span>`;
  }

  function exerciseConfigEndpoint(path) {
    const base = String(M.EXERCISE_CONFIG_URL || "").replace(/\/+$/, "");
    if (!path) return base;
    return `${base}/${path}`;
  }

  function toArray(value) {
    return Array.isArray(value) ? value : [];
  }

  function normalizeQuery(value) {
    return M.normalizeExerciseConfigName(String(value == null ? "" : value));
  }

  function normalizeSetTypeHandling(value) {
    const raw = String(value || "").trim();
    return raw === "time_duration" ? "time_duration" : "weight_reps";
  }

  function parseListInput(value) {
    const items = String(value == null ? "" : value)
      .split(/[\n,]/)
      .map((part) => part.trim())
      .filter(Boolean);
    const seen = new Set();
    return items.filter((item) => {
      const normalized = normalizeQuery(item);
      if (!normalized || seen.has(normalized)) return false;
      seen.add(normalized);
      return true;
    });
  }

  function renderIconButton(action, label, icon, extraClass = "") {
    return `<button type="button" class="exercise-admin-icon-btn${extraClass ? ` ${extraClass}` : ""}" ${action} title="${escAttr(label)}" aria-label="${escAttr(label)}">${icon}</button>`;
  }

  function getMergedRecognitionNames(values) {
    const merged = [];
    const seen = new Set();
    const addItems = (items) => {
      items.forEach((item) => {
        const normalized = normalizeQuery(item);
        if (!normalized || seen.has(normalized)) return;
        seen.add(normalized);
        merged.push(item);
      });
    };
    addItems(parseListInput(values && values.aliasesText));
    addItems(parseListInput(values && values.matchTermsText));
    return merged;
  }

  function mapMergedNamesToStoredLists(values, mergedText) {
    const currentAliases = parseListInput(values && values.aliasesText);
    const currentMatchTerms = parseListInput(values && values.matchTermsText);
    const existingAliasSet = new Set(currentAliases.map(normalizeQuery));
    const existingMatchSet = new Set(currentMatchTerms.map(normalizeQuery));
    const nextAliases = [];
    const nextMatchTerms = [];

    parseListInput(mergedText).forEach((item) => {
      const normalized = normalizeQuery(item);
      if (!normalized) return;
      const wasAlias = existingAliasSet.has(normalized);
      const wasMatchTerm = existingMatchSet.has(normalized);

      if (wasAlias || !wasMatchTerm) {
        nextAliases.push(item);
      }
      if (wasMatchTerm) {
        nextMatchTerms.push(item);
      }
    });

    return {
      aliasesText: nextAliases.join("\n"),
      matchTermsText: nextMatchTerms.join("\n"),
    };
  }

  M.getExerciseAdminMergedRecognitionNames = function getExerciseAdminMergedRecognitionNames(values) {
    return getMergedRecognitionNames(values);
  };

  M.mapExerciseAdminMergedNames = function mapExerciseAdminMergedNames(values, mergedText) {
    return mapMergedNamesToStoredLists(values, mergedText);
  };

  function formatDateTime(iso) {
    if (!iso) return "Unknown";
    return typeof M.fmtDateTime === "function" ? M.fmtDateTime(iso) : iso;
  }

  function formatActivityDate(value) {
    if (!value) return "Unknown date";
    if (typeof M.fmtDateTime === "function" && /T\d{2}:\d{2}/.test(String(value))) {
      return M.fmtDateTime(value);
    }
    if (typeof M.fmtDate === "function") {
      return M.fmtDate(value);
    }
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleDateString("en-GB", {
      weekday: "short",
      day: "numeric",
      month: "short",
      year: "numeric",
    });
  }

  function getActivityId(activity) {
    if (typeof M.getActivityId === "function") {
      return M.getActivityId(activity);
    }
    return String((activity && (activity["Activity ID raw"] || activity["Activity ID"] || activity.Name)) || "");
  }

  function getUnknownRecentWorkouts(record, limit = 3) {
    if (!record || !record.normalizedName) return [];
    const normalizedName = normalizeQuery(record.normalizedName);
    if (!normalizedName) return [];

    return toArray(M.state.allData)
      .map((activity) => {
        if (!activity || typeof activity !== "object") return null;

        if (record.sourceType === "activityType") {
          const normalizedType = normalizeQuery(activity.Type);
          if (normalizedType !== normalizedName) return null;
          return { activity, matches: [] };
        }

        const exercises = typeof M.getActivityWorkoutBlocks === "function"
          ? M.getActivityWorkoutBlocks(activity)
          : M.parseHevyDescription(activity.Description);
        if (!Array.isArray(exercises) || !exercises.length) return null;
        const matches = exercises.filter((exercise) => normalizeQuery(exercise && exercise.name) === normalizedName);
        if (!matches.length) return null;
        return { activity, matches };
      })
      .filter(Boolean)
      .sort((left, right) => String(right.activity.Date || "").localeCompare(String(left.activity.Date || "")))
      .slice(0, limit);
  }

  function renderUnknownRecentWorkouts(record) {
    const recentWorkouts = getUnknownRecentWorkouts(record);
    if (!recentWorkouts.length) return "";

    return `<div class="exercise-admin-recent-workouts">
      <div class="exercise-admin-detail-label">Found in</div>
      <div class="exercise-admin-recent-list">
        ${recentWorkouts.map((entry) => {
          const activity = entry.activity;
          const activityId = getActivityId(activity);
          const cfg = M.tc(activity.Type || "");
          const date = formatActivityDate(activity.Date || activity["Start Date"] || activity["Start Date Local"]);
          const title = activity.Name || cfg.label || activity.Type || "Workout";
          return `<button type="button" class="exercise-admin-recent-link" data-activity-id="${escAttr(activityId)}" aria-label="Open details for ${escAttr(title)}">
            <span class="exercise-admin-recent-main">
              <span class="exercise-admin-recent-icon" aria-hidden="true">${esc(cfg.icon || "⚡")}</span>
              <span class="exercise-admin-recent-title">${esc(title)}</span>
            </span>
            <span class="exercise-admin-recent-date">${esc(date)}</span>
          </button>`;
        }).join("")}
      </div>
    </div>`;
  }

  function formatUnknownSuggestionNote(status) {
    return AI_STATUS_LABELS[status] || String(status || "Unknown");
  }

  function buildBadge(label, tone) {
    return `<span class="exercise-admin-badge${tone ? ` exercise-admin-badge--${tone}` : ""}">${esc(label)}</span>`;
  }

  function getExerciseRecordById(id) {
    return toArray(M.state.exerciseConfigs).find((record) => record && record.id === id) || null;
  }

  function getActivityTypeRecordById(id) {
    return toArray(M.state.activityTypeConfigs).find((record) => record && record.id === id) || null;
  }

  function getUnknownRecordById(id) {
    return toArray(M.state.unknownExercises).find((record) => record && record.id === id) || null;
  }

  function getSelectedItem() {
    const selectedKey = String(getAdminState().selectedKey || "");
    if (!selectedKey) return null;

    if (selectedKey.startsWith("exercise:")) {
      const record = getExerciseRecordById(selectedKey.slice("exercise:".length));
      return record ? { key: selectedKey, type: "configured", kind: "exercise", record } : null;
    }

    if (selectedKey.startsWith("activityType:")) {
      const record = getActivityTypeRecordById(selectedKey.slice("activityType:".length));
      return record ? { key: selectedKey, type: "configured", kind: "activityType", record } : null;
    }

    if (selectedKey.startsWith("unknown:")) {
      const record = getUnknownRecordById(selectedKey.slice("unknown:".length));
      if (!record) return null;
      return {
        key: selectedKey,
        type: "unknown",
        kind: record.sourceType === "activityType" ? "unknownActivityType" : "unknownExercise",
        record,
      };
    }

    return null;
  }

  function getExerciseBaselineFormValues(record) {
    return {
      canonicalName: record.canonicalName || "",
      aliasesText: toArray(record.aliases).join("\n"),
      matchTermsText: toArray(record.matchTerms).join("\n"),
      fatigueImpact: record.fatigueImpact || "normal",
      bodyweightEligible: Boolean(record.bodyweightEligible),
      setTypeHandling: normalizeSetTypeHandling(record.setTypeHandling || "weight_reps"),
      exerciseFamily: record.exerciseFamily || "",
      fatigueArchetype: record.fatigueArchetype || "",
      muscleWeights: { ...(record.muscleWeights || {}) },
      source: record.source || "manual",
    };
  }

  function getUnknownBaselineFormValues(record) {
    const aliases = toArray(record.rawNames);
    const matchTerms = [];
    if (record.normalizedName) matchTerms.push(record.normalizedName);
    aliases.forEach((name) => {
      if (!matchTerms.includes(name)) {
        matchTerms.push(name);
      }
    });

    return {
      canonicalName: aliases[0] || record.normalizedName || "",
      aliasesText: aliases.join("\n"),
      matchTermsText: record.sourceType === "exercise" ? matchTerms.join("\n") : "",
      fatigueImpact: "normal",
      bodyweightEligible: false,
      setTypeHandling: normalizeSetTypeHandling("weight_reps"),
      exerciseFamily: "",
      fatigueArchetype: "",
      muscleWeights: {},
      source: "manual",
    };
  }

  function getActivityTypeBaselineFormValues(record) {
    return {
      canonicalName: record.canonicalName || "",
      aliasesText: toArray(record.aliases).join("\n"),
      matchTermsText: "",
      fatigueImpact: "normal",
      bodyweightEligible: false,
      setTypeHandling: "",
      exerciseFamily: record.exerciseFamily || "",
      fatigueArchetype: record.fatigueArchetype || "",
      muscleWeights: { ...(record.muscleWeights || {}) },
      source: record.source || "manual",
    };
  }

  function getExerciseFormValues(record) {
    const state = getAdminState();
    if (
      state.formDraft
      && state.formDraft.key === `exercise:${record.id}`
      && state.formDraft.values
      && typeof state.formDraft.values === "object"
    ) {
      return state.formDraft.values;
    }

    return getExerciseBaselineFormValues(record);
  }

  function getUnknownFormValues(record) {
    const state = getAdminState();
    if (
      state.formDraft
      && state.formDraft.key === `unknown:${record.id}`
      && state.formDraft.values
      && typeof state.formDraft.values === "object"
    ) {
      return state.formDraft.values;
    }

    return getUnknownBaselineFormValues(record);
  }

  function getActivityTypeFormValues(record) {
    const state = getAdminState();
    if (
      state.formDraft
      && state.formDraft.key === `activityType:${record.id}`
      && state.formDraft.values
      && typeof state.formDraft.values === "object"
    ) {
      return state.formDraft.values;
    }

    return getActivityTypeBaselineFormValues(record);
  }

  function getSelectedFormValues(selected) {
    if (!selected) return null;
    if (selected.kind === "activityType") return getActivityTypeFormValues(selected.record);
    if (selected.type === "unknown") return getUnknownFormValues(selected.record);
    return getExerciseFormValues(selected.record);
  }

  function updateExerciseDraftValues(record, patch) {
    const state = getAdminState();
    state.formDraft = {
      key: `exercise:${record.id}`,
      values: {
        ...getExerciseFormValues(record),
        ...patch,
      },
    };
    state.formErrors = null;
    return state.formDraft.values;
  }

  function updateUnknownDraftValues(record, patch) {
    const state = getAdminState();
    state.formDraft = {
      key: `unknown:${record.id}`,
      values: {
        ...getUnknownFormValues(record),
        ...patch,
      },
    };
    state.formErrors = null;
    return state.formDraft.values;
  }

  function updateActivityTypeDraftValues(record, patch) {
    const state = getAdminState();
    state.formDraft = {
      key: `activityType:${record.id}`,
      values: {
        ...getActivityTypeFormValues(record),
        ...patch,
      },
    };
    state.formErrors = null;
    return state.formDraft.values;
  }

  function updateSelectedDraftValues(selected, patch) {
    if (!selected) return null;
    if (selected.kind === "activityType") return updateActivityTypeDraftValues(selected.record, patch);
    return selected.type === "unknown"
      ? updateUnknownDraftValues(selected.record, patch)
      : updateExerciseDraftValues(selected.record, patch);
  }

  function clearCanonicalNameError() {
    const state = getAdminState();
    if (!state.formErrors || !state.formErrors.fields || !state.formErrors.fields.canonicalName) return;
    const fields = { ...state.formErrors.fields };
    delete fields.canonicalName;
    if (!Object.keys(fields).length) {
      state.formErrors = null;
      return;
    }
    state.formErrors = {
      key: state.formErrors.key,
      fields,
    };
  }

  function isExerciseKind(selected) {
    return Boolean(selected && (selected.kind === "exercise" || selected.kind === "unknownExercise"));
  }

  function isActivityTypeKind(selected) {
    return Boolean(selected && (selected.kind === "activityType" || selected.kind === "unknownActivityType"));
  }

  function getFilteredExercises(query, filter) {
    return M.filterExerciseAdminList(toArray(M.state.exerciseConfigs), query, filter, "exercise");
  }

  function getFilteredActivityTypes(query, filter) {
    return M.filterExerciseAdminList(toArray(M.state.activityTypeConfigs), query, filter, "activityType");
  }

  function getMergeTargetOptions(selected) {
    const selectedExerciseId = selected && selected.kind === "exercise" ? selected.record.id : "";
    return toArray(M.state.exerciseConfigs)
      .filter((record) => record && record.id !== selectedExerciseId)
      .sort((left, right) => String(left.canonicalName || "").localeCompare(String(right.canonicalName || "")));
  }

  function getMergeTargetId(selected) {
    const state = getAdminState();
    const options = getMergeTargetOptions(selected);
    if (!options.length) {
      state.mergeTargetId = "";
      return "";
    }

    if (options.some((record) => record.id === state.mergeTargetId)) {
      return state.mergeTargetId;
    }

    state.mergeTargetId = options[0].id;
    return state.mergeTargetId;
  }

  function countUnknownItems() {
    return toArray(M.state.unknownExercises).filter(Boolean).length;
  }

  M.filterExerciseAdminList = function filterExerciseAdminList(records, query, filter, sourceType = "exercise") {
    const normalizedQuery = normalizeQuery(query);

    return toArray(records)
      .filter((record) => Boolean(record))
      .filter((record) => {
        if (filter === "exercise") return sourceType === "exercise";
        if (filter === "activityType") return sourceType === "activityType";
        return true;
      })
      .filter((record) => {
        if (!normalizedQuery) return true;
        const haystack = [
          record.canonicalName,
          ...(toArray(record.aliases)),
          ...(toArray(record.matchTerms)),
        ].map(normalizeQuery);
        return haystack.some((value) => value.includes(normalizedQuery));
      })
      .sort((left, right) => String(left.canonicalName || "").localeCompare(String(right.canonicalName || "")));
  };

  M.getExerciseAdminListItems = function getExerciseAdminListItems(query, filter) {
    const normalizedQuery = normalizeQuery(query);
    const matchesQuery = (values) => {
      if (!normalizedQuery) return true;
      return values
        .map(normalizeQuery)
        .some((value) => value.includes(normalizedQuery));
    };

    const unknownItems = toArray(M.state.unknownExercises)
      .filter((record) => Boolean(record))
      .filter((record) => {
        if (filter === "exercise") return record.sourceType === "exercise";
        if (filter === "activityType") return record.sourceType === "activityType";
        return true;
      })
      .filter((record) => matchesQuery([
        record.normalizedName,
        ...(toArray(record.rawNames)),
      ]))
      .sort((left, right) => String(right.lastSeenAt || "").localeCompare(String(left.lastSeenAt || "")))
      .map((record) => ({
        key: `unknown:${record.id}`,
        type: "unknown",
        kind: record.sourceType === "activityType" ? "unknownActivityType" : "unknownExercise",
        record,
      }));

    const exerciseItems = M.filterExerciseAdminList(toArray(M.state.exerciseConfigs), query, filter)
      .map((record) => ({
        key: `exercise:${record.id}`,
        type: "configured",
        kind: "exercise",
        record,
      }));

    const activityTypeItems = M.filterExerciseAdminList(toArray(M.state.activityTypeConfigs), query, filter, "activityType")
      .map((record) => ({
        key: `activityType:${record.id}`,
        type: "configured",
        kind: "activityType",
        record,
      }));

    return unknownItems.concat(exerciseItems, activityTypeItems);
  };

  function ensureSelection(options = {}) {
    const state = getAdminState();
    const selected = getSelectedItem();
    const visibleItems = M.getExerciseAdminListItems(state.query, state.filter);
    if (selected) {
      if (!options.preferVisible) {
        return selected;
      }
      if (visibleItems.some((item) => item.key === selected.key)) {
        return selected;
      }
    }

    if (visibleItems.length) {
      state.selectedKey = visibleItems[0].key;
      return visibleItems[0];
    }

    state.selectedKey = "";
    return null;
  }

  function renderListItem(item, state) {
    const selected = state.selectedKey === item.key;
    if (item.type === "unknown") {
      const record = item.record;
      const title = (record.rawNames && record.rawNames[0]) || record.normalizedName;
      return `<button type="button" class="exercise-admin-list-item exercise-admin-list-item--unknown${selected ? " is-selected" : ""}" data-exercise-admin-select="${escAttr(item.key)}">
        <div class="exercise-admin-list-row">
          <strong>${esc(title)}</strong>
          <span class="exercise-admin-list-badges">${buildBadge(item.kind === "unknownActivityType" ? "Activity type" : "Exercise")}${buildBadge("Unconfigured", "danger")}</span>
        </div>
      </button>`;
    }

    const record = item.record;
    return `<button type="button" class="exercise-admin-list-item${selected ? " is-selected" : ""}" data-exercise-admin-select="${escAttr(item.key)}">
      <div class="exercise-admin-list-row">
        <strong>${esc(record.canonicalName)}</strong>
        <span class="exercise-admin-list-badges">${buildBadge(item.kind === "activityType" ? "Activity type" : "Exercise")}</span>
      </div>
      <div class="exercise-admin-list-meta">${esc(toArray(record.aliases).slice(0, 3).join(", ") || "No aliases yet")}</div>
    </button>`;
  }

  function renderFieldError(errors, field) {
    if (!errors || !errors[field]) return "";
    return `<div class="settings-error">${esc(errors[field])}</div>`;
  }

  function formatMuscleWeightValue(value) {
    return Number(value || 0).toFixed(2);
  }

  function getMuscleValidationMessage(muscleWeights, fatigueImpact = "normal") {
    const values = Object.values(muscleWeights || {});
    const hasInvalidWeight = values.some((value) => !Number.isFinite(value) || value < 0);
    if (hasInvalidWeight) return "Muscle weights must be zero or positive numbers.";
    if (fatigueImpact === "none") return "";
    if (!values.some((value) => Number.isFinite(value) && value > 0)) {
      return "At least one muscle group must be primary.";
    }
    if (typeof M.hasExerciseMusclePrimary === "function" && !M.hasExerciseMusclePrimary(muscleWeights || {})) {
      return "At least one muscle group must be primary.";
    }
    return "";
  }

  function normalizeFormValuesForComparison(values) {
    const input = values && typeof values === "object" ? values : {};
    const muscleWeights = {};
    Object.keys(input.muscleWeights || {})
      .sort()
      .forEach((key) => {
        const numericValue = Number((input.muscleWeights || {})[key]);
        if (Number.isFinite(numericValue) && numericValue > 0) {
          muscleWeights[key] = Number(formatMuscleWeightValue(numericValue));
        }
      });

    return {
      canonicalName: String(input.canonicalName || "").trim(),
      aliasesText: parseListInput(input.aliasesText || "").join("\n"),
      matchTermsText: parseListInput(input.matchTermsText || "").join("\n"),
      fatigueImpact: String(input.fatigueImpact || "normal"),
      bodyweightEligible: Boolean(input.bodyweightEligible),
      setTypeHandling: normalizeSetTypeHandling(input.setTypeHandling || ""),
      exerciseFamily: String(input.exerciseFamily || ""),
      fatigueArchetype: String(input.fatigueArchetype || ""),
      muscleWeights,
      source: String(input.source || "manual"),
    };
  }

  function getPersistedFormValues(selected) {
    if (!selected) return null;
    if (selected.kind === "activityType") return getActivityTypeBaselineFormValues(selected.record);
    if (selected.type === "unknown") return getUnknownBaselineFormValues(selected.record);
    return getExerciseBaselineFormValues(selected.record);
  }

  function hasSaveableChanges(selected) {
    if (!selected) return false;
    const currentValues = normalizeFormValuesForComparison(getSelectedFormValues(selected));
    const persistedValues = normalizeFormValuesForComparison(getPersistedFormValues(selected));

    return JSON.stringify(currentValues) !== JSON.stringify(persistedValues);
  }

  function getPrimarySaveLabel(selected) {
    if (!selected) return "Save";
    if (selected.type === "unknown") {
      return isActivityTypeKind(selected) ? "Create activity type" : "Create exercise";
    }
    return isActivityTypeKind(selected) ? "Save activity type" : "Save exercise";
  }

  function getSaveActionState(selected, pendingAction = "") {
    if (!selected) {
      return {
        buttonLabel: "Save",
        statusTone: "idle",
        statusText: "No item selected.",
        hasChanges: false,
        validationErrors: null,
        hasValidationErrors: false,
        canSave: false,
      };
    }

    const hasChanges = hasSaveableChanges(selected);
    const validationErrors = hasChanges ? getFormValidationErrors(getSelectedFormValues(selected), selected) : null;
    const hasValidationErrors = Boolean(validationErrors && Object.keys(validationErrors).length);
    const pendingSave = pendingAction === "save";

    let statusTone = "idle";
    let statusText = "No changes.";

    if (pendingSave) {
      statusTone = "busy";
      statusText = "Saving changes.";
    } else if (hasValidationErrors) {
      statusTone = "warning";
      statusText = "Unsaved changes need attention.";
    } else if (hasChanges) {
      statusTone = "ready";
      statusText = "Unsaved changes.";
    }

    return {
      buttonLabel: getPrimarySaveLabel(selected),
      statusTone,
      statusText,
      hasChanges,
      validationErrors,
      hasValidationErrors,
      canSave: hasChanges && !hasValidationErrors && !pendingSave,
    };
  }

  function getFormValidationErrors(values, selected) {
    const normalizedValues = normalizeFormValuesForComparison(values);
    const errors = {};
    const isExercise = isExerciseKind(selected);

    if (!normalizedValues.canonicalName) {
      errors.canonicalName = "Canonical name is required.";
    }
    if (isExercise && !SET_TYPE_OPTIONS.some((option) => option.value === normalizedValues.setTypeHandling)) {
      errors.setTypeHandling = "Choose how sets should be interpreted.";
    }

    const muscleError = getMuscleValidationMessage(normalizedValues.muscleWeights, normalizedValues.fatigueImpact);
    if (muscleError) {
      errors.muscleWeights = muscleError;
    }

    return errors;
  }

  function renderMuscleErrorMessage(errors, muscleWeights, fatigueImpact = "normal") {
    const message = (errors && errors.muscleWeights) || getMuscleValidationMessage(muscleWeights, fatigueImpact);
    return `<div class="settings-error${message ? "" : " exercise-admin-inline-error--hidden"}" data-exercise-admin-muscle-error>${esc(message || "")}</div>`;
  }

  function getPreviewVisual(levelKey) {
    switch (levelKey) {
      case "stabilizer":
        return { fill: "var(--fatigue-color-fresh)", opacity: "0.42" };
      case "minor":
        return { fill: "var(--fatigue-color-recovering)", opacity: "0.56" };
      case "secondary":
        return { fill: "var(--fatigue-color-fatigued)", opacity: "0.72" };
      case "strongSecondary":
        return { fill: "var(--fatigue-color-high)", opacity: "0.82" };
      case "primary":
        return { fill: "var(--fatigue-color-high)", opacity: "0.96" };
      default:
        return { fill: "var(--fatigue-color-none)", opacity: "0.24" };
    }
  }

  function renderExerciseMusclePreviewFigure(muscleWeights, view) {
    const bodyMap = M.MUSCLE_FATIGUE_BODY_MAP || {};
    const config = bodyMap[view];
    if (!config) return "";

    const slugToKey = bodyMap.slugToKey || {};
    const editorState = typeof M.getExerciseMuscleEditorState === "function"
      ? M.getExerciseMuscleEditorState(muscleWeights)
      : {};

    const partMarkup = config.parts.map((part) => {
      const key = slugToKey[part.slug] || "";
      const muscleState = editorState[key] || { involved: false, label: "Not involved", levelKey: "" };
      const visual = getPreviewVisual(muscleState.levelKey);
      const title = muscleState.involved
        ? `${key ? M.MUSCLE_REGIONS.find((region) => region.key === key)?.label || key : part.slug}: ${muscleState.label}`
        : `${key ? M.MUSCLE_REGIONS.find((region) => region.key === key)?.label || key : part.slug}: Not involved`;

      return `<g
        class="exercise-admin-preview-region"
        data-preview-level="${escAttr(muscleState.levelKey || "none")}"
        style="--preview-fill:${visual.fill}; --preview-opacity:${visual.opacity}">
        <title>${esc(title)}</title>
        ${part.pathArray.map((path) => `<path d="${escAttr(path)}"></path>`).join("")}
      </g>`;
    }).join("");

    return `<div class="exercise-admin-preview-figure exercise-admin-preview-figure--${escAttr(view)}">
      <svg class="exercise-admin-preview-svg" viewBox="${escAttr(config.viewBox)}" role="img" aria-label="${escAttr(`Live ${config.label.toLowerCase()} muscle involvement preview`)}" preserveAspectRatio="xMidYMin meet">
        <path class="fatigue-body-outline" d="${escAttr(config.outlinePath || "")}"></path>
        ${partMarkup}
      </svg>
      <div class="exercise-admin-preview-caption">${esc(config.label)}</div>
    </div>`;
  }

  function renderExerciseMusclePreview(muscleWeights) {
    return `<div class="exercise-admin-preview-shell">
      ${renderExerciseMusclePreviewFigure(muscleWeights, "front")}
      ${renderExerciseMusclePreviewFigure(muscleWeights, "back")}
    </div>`;
  }

  function renderMuscleWeightInputs(values, errors) {
    const levels = typeof M.getExerciseMuscleUiLevels === "function" ? M.getExerciseMuscleUiLevels() : [];
    const defaultLevel = typeof M.getExerciseMuscleUiLevel === "function"
      ? M.getExerciseMuscleUiLevel(M.EXERCISE_MUSCLE_DEFAULT_LEVEL)
      : null;
    const editorState = typeof M.getExerciseMuscleEditorState === "function"
      ? M.getExerciseMuscleEditorState(values.muscleWeights)
      : {};

    return M.MUSCLE_REGIONS
      .slice()
      .sort((left, right) => (left.order || 0) - (right.order || 0))
      .map((region) => {
        const current = editorState[region.key] || {
          involved: false,
          levelKey: "",
          levelIndex: -1,
          label: "Not involved",
          meaning: "",
          weight: 0,
        };
        const sliderValue = current.involved && current.levelIndex >= 0
          ? current.levelIndex + 1
          : (defaultLevel ? defaultLevel.index + 1 : 3);
        const sliderFill = getMuscleSliderFillPercent(sliderValue);
        const statusText = current.involved ? current.label : "Not involved";
        const statusTitle = current.involved ? (current.meaning || current.label) : "Not involved";

        return `<div class="exercise-admin-muscle-field${current.involved ? " is-involved" : " is-compact"}" data-exercise-admin-muscle-row data-muscle-key="${escAttr(region.key)}" data-muscle-level="${escAttr(current.levelKey || "none")}">
          <input type="hidden" name="muscle-${escAttr(region.key)}" value="${escAttr(formatMuscleWeightValue(current.weight))}" data-exercise-admin-muscle-hidden data-muscle-key="${escAttr(region.key)}">
          <div class="exercise-admin-muscle-head">
            <label class="exercise-admin-muscle-toggle">
              <input type="checkbox" data-exercise-admin-muscle-toggle data-muscle-key="${escAttr(region.key)}"${current.involved ? " checked" : ""}>
              <span class="exercise-admin-muscle-name">${esc(region.label)}</span>
            </label>
            <span class="exercise-admin-muscle-status" data-exercise-admin-muscle-status title="${escAttr(statusTitle)}">${esc(statusText)}</span>
          </div>
          <div class="exercise-admin-muscle-controls"${current.involved ? "" : " hidden"} data-exercise-admin-muscle-controls>
            <div class="exercise-admin-muscle-slider-shell">
              <div class="exercise-admin-muscle-slider-overlay" aria-hidden="true">
                <span class="exercise-admin-muscle-slider-zero"></span>
                <div class="exercise-admin-muscle-slider-track-markers">
                  ${levels.map((level) => {
                    const tickValue = Number(level.index) + 1;
                    return `<span class="exercise-admin-muscle-slider-marker${tickValue <= sliderValue ? " is-active" : ""}" style="--marker-index:${escAttr(String(tickValue))}" data-exercise-admin-muscle-tick data-tick-value="${escAttr(String(tickValue))}"></span>`;
                  }).join("")}
                </div>
              </div>
              <div class="exercise-admin-muscle-slider-input-row">
                <span class="exercise-admin-muscle-slider-spacer" aria-hidden="true"></span>
                <input
                  class="exercise-admin-muscle-slider"
                  type="range"
                  min="1"
                  max="${escAttr(String(Math.max(1, levels.length)))}"
                  step="1"
                  value="${escAttr(String(sliderValue))}"
                  style="--slider-fill:${escAttr(`${sliderFill}%`)}"
                  data-exercise-admin-muscle-slider
                  data-muscle-key="${escAttr(region.key)}"
                  aria-label="${escAttr(`${region.label} involvement level`)}"
                  aria-valuetext="${escAttr(current.involved ? `${current.label}: ${current.meaning}` : "Not involved")}">
              </div>
            </div>
          </div>
        </div>`;
      })
      .join("");
  }

  function renderRecognitionNamesSection(record, values, state) {
    const mergedNames = getMergedRecognitionNames(values);
    const isEditing = state.namesEditOpen;
    const draftText = isEditing ? state.namesDraft : mergedNames.join("\n");

    return `<div class="settings-field">
      <div class="exercise-admin-section-head">
        <label class="settings-label" for="exerciseRecognitionNames">
          Also match these names
          ${tooltip("Other names that should map to this exercise.")}
        </label>
        ${isEditing
          ? ""
          : renderIconButton(
              'data-exercise-admin-names-edit-start',
              'Edit synonyms',
              `<svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M10.9 2.6a1.5 1.5 0 1 1 2.1 2.1L5.4 12.3 2 13l.7-3.4 8.2-7z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>`
            )}
      </div>
      ${isEditing
        ? `<textarea class="settings-input exercise-admin-textarea" id="exerciseRecognitionNames" data-exercise-admin-names-text>${esc(draftText)}</textarea>
          <div class="exercise-admin-editor-actions exercise-admin-editor-actions--inline">
            <button type="button" class="settings-save-btn" data-exercise-admin-names-save>Save names</button>
            <button type="button" class="passkey-add-btn exercise-admin-secondary-btn" data-exercise-admin-names-cancel>Cancel</button>
          </div>`
        : `<div class="exercise-admin-synonyms-text" data-exercise-admin-synonyms-text>
            ${mergedNames.length
              ? esc(mergedNames.join(", "))
              : '<span class="exercise-admin-synonyms-empty">No synonyms added.</span>'}
          </div>`}
    </div>`;
  }

  function renderMergeAction(selected, state, options = {}) {
    if (!isExerciseKind(selected)) return "";
    const mergeOptions = getMergeTargetOptions(selected);
    const mergeTargetId = getMergeTargetId(selected);
    const isExpanded = state.mergePanelKey === selected.key;
    const pendingAction = state.pendingKey === selected.key ? state.pendingAction : "";
    const emptyMessage = options.emptyMessage || "No other exercise config is available as a merge target.";

    if (!mergeOptions.length) {
      return `<div class="exercise-admin-empty exercise-admin-empty--compact">${esc(emptyMessage)}</div>`;
    }

    if (!isExpanded) {
      return `<button type="button" class="passkey-add-btn exercise-admin-secondary-btn" data-exercise-admin-merge-open>Merge with other exercise</button>`;
    }

    return `<div class="exercise-admin-inline-action">
      <div class="exercise-admin-inline-copy">Merge this item into another exercise and keep the target as the live config.</div>
      <select class="settings-input settings-select exercise-admin-merge-select" data-exercise-admin-merge-target>
        ${mergeOptions.map((option) => `<option value="${escAttr(option.id)}"${mergeTargetId === option.id ? " selected" : ""}>${esc(option.canonicalName)}</option>`).join("")}
      </select>
      <div class="exercise-admin-editor-actions exercise-admin-editor-actions--inline">
        <button type="button" class="settings-save-btn" data-exercise-admin-merge${pendingAction === "merge" ? " disabled" : ""}>Merge</button>
        <button type="button" class="passkey-add-btn exercise-admin-secondary-btn" data-exercise-admin-merge-cancel${pendingAction === "merge" ? " disabled" : ""}>Cancel</button>
      </div>
    </div>`;
  }

  function renderSaveAssistant(selected, state, record, editorLabel, saveState, pendingAction) {
    const isUnknown = selected.type === "unknown";
    const isExercise = isExerciseKind(selected);
    const canCancel = saveState.hasChanges || state.titleEditOpen || state.namesEditOpen;

    return `<div class="exercise-admin-savebar">
      <div class="exercise-admin-savebar-actions">
        ${isUnknown && isExercise
          ? `<button type="button" class="gen-btn" data-exercise-admin-suggest="${escAttr(record.id)}"${state.pendingUnknownId === record.id ? " disabled" : ""}>✨ Generate suggestion</button>`
          : (!isUnknown && isExercise
            ? `<button type="button" class="gen-btn" data-exercise-admin-regenerate="${escAttr(record.id)}"${pendingAction === "suggest" ? " disabled" : ""}>✨ Regenerate suggestion</button>`
            : "")}
        <div class="exercise-admin-savebar-save-group">
          <button type="button" class="settings-save-btn exercise-admin-savebar-primary" data-exercise-admin-save${saveState.canSave ? "" : " disabled"}>${esc(saveState.buttonLabel)}</button>
          <div class="exercise-admin-savebar-status exercise-admin-savebar-status--${escAttr(saveState.statusTone)}" data-exercise-admin-save-status role="status" aria-live="polite">${esc(saveState.statusText)}</div>
        </div>
        <button type="button" class="passkey-add-btn exercise-admin-secondary-btn" data-exercise-admin-cancel-changes${canCancel ? "" : " disabled"}>Cancel</button>
      </div>
    </div>`;
  }

  function renderAdvancedPanel(selected, state, values, editorLabel, pendingAction, metaBits) {
    const isExercise = isExerciseKind(selected);
    const isActivityType = isActivityTypeKind(selected);
    const mergeAction = renderMergeAction(selected, state, {
      emptyMessage: "Create at least one exercise config before merging unknowns as aliases.",
    });
    const deleteAction = selected.type === "unknown"
      ? ""
      : `<button type="button" class="settings-save-btn exercise-admin-delete-btn" data-exercise-admin-delete-open${pendingAction === "delete" ? " disabled" : ""}>Delete ${esc(editorLabel.toLowerCase())}</button>`;
    const isOpen = state.mergePanelKey === selected.key;

    return `<details class="exercise-admin-advanced" ${isOpen ? "open" : ""}>
      <summary class="exercise-admin-advanced-summary">Advanced</summary>
      <div class="exercise-admin-advanced-body">
        ${metaBits.length ? `<div class="exercise-admin-editor-meta exercise-admin-editor-meta--advanced">${metaBits.map((bit) => `<span>${bit}</span>`).join("")}</div>` : ""}
        <div class="exercise-admin-form-grid">
          <div class="settings-field">
            <label class="settings-label" for="exerciseFamily">
              Exercise family
              ${tooltip("Optional internal classification used by the fatigue model for load references and heuristics. Leave unset to keep automatic inference.")}
            </label>
            <select class="settings-input settings-select" id="exerciseFamily" name="exerciseFamily">
              ${EXERCISE_FAMILY_OPTIONS.map((option) => `<option value="${escAttr(option.value)}"${values.exerciseFamily === option.value ? " selected" : ""}>${esc(option.label)}</option>`).join("")}
            </select>
          </div>

          <div class="settings-field">
            <label class="settings-label" for="fatigueArchetype">
              Fatigue archetype
              ${tooltip("Optional internal split between local and systemic fatigue. Leave unset to keep automatic inference from the selected family and movement style.")}
            </label>
            <select class="settings-input settings-select" id="fatigueArchetype" name="fatigueArchetype">
              ${FATIGUE_ARCHETYPE_OPTIONS.map((option) => `<option value="${escAttr(option.value)}"${values.fatigueArchetype === option.value ? " selected" : ""}>${esc(option.label)}</option>`).join("")}
            </select>
          </div>
        </div>
        ${isExercise && (mergeAction || deleteAction) ? `<div class="exercise-admin-advanced-actions">${mergeAction}${deleteAction}</div>` : ""}
        ${isActivityType && deleteAction ? `<div class="exercise-admin-advanced-actions">${deleteAction}</div>` : ""}
      </div>
    </details>`;
  }

  function renderConfigEditor(selected, state) {
    const record = selected.record;
    const values = getSelectedFormValues(selected);
    const errors = state.formErrors && state.formErrors.key === selected.key ? state.formErrors.fields : null;
    const pendingAction = state.pendingKey === selected.key ? state.pendingAction : "";
    const isUnknown = selected.type === "unknown";
    const isExercise = isExerciseKind(selected);
    const isActivityType = isActivityTypeKind(selected);
    const isTimeBasedExercise = values.setTypeHandling === "time_duration";
    const saveState = getSaveActionState(selected, pendingAction);
    const isTitleEditing = state.titleEditOpen;
    const titleValue = isTitleEditing ? state.titleDraft : values.canonicalName;
    const suggestionNote = isUnknown && isExercise ? formatUnknownSuggestionNote(record.aiStatus) : "";
    const editorLabel = isActivityType ? "Activity type" : "Exercise";
    const metaBits = isUnknown
      ? []
      : [
          `ID: <code>${esc(record.id)}</code>`,
          `Last updated ${esc(formatDateTime(record.lastUpdatedAt))}`,
        ];

    return `<section class="exercise-admin-editor-shell">
      <div class="exercise-admin-editor-head">
        <div class="exercise-admin-editor-main">
          ${isTitleEditing
            ? `<div class="exercise-admin-title-edit">
                <input class="settings-input exercise-admin-title-input" id="exerciseTitleInput" type="text" value="${escAttr(titleValue)}" data-exercise-admin-title-input>
                <div class="exercise-admin-editor-actions exercise-admin-editor-actions--inline">
                  <button type="button" class="settings-save-btn" data-exercise-admin-title-save>Save name</button>
                  <button type="button" class="passkey-add-btn exercise-admin-secondary-btn" data-exercise-admin-title-cancel>Cancel</button>
                </div>
              </div>`
            : `<div class="exercise-admin-title-row">
                <h2 class="exercise-admin-editor-title">${esc(values.canonicalName || (isUnknown ? ((record.rawNames && record.rawNames[0]) || record.normalizedName) : record.canonicalName))}</h2>
                ${renderIconButton(
                  'data-exercise-admin-title-edit-start',
                  `Edit ${editorLabel.toLowerCase()} name`,
                  `<svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M10.9 2.6a1.5 1.5 0 1 1 2.1 2.1L5.4 12.3 2 13l.7-3.4 8.2-7z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>`
                )}
              </div>`}
          ${renderFieldError(errors, "canonicalName")}
        </div>
      </div>
      <div class="exercise-admin-editor-kind">${esc(editorLabel)}${isUnknown ? " · Unconfigured" : ""}</div>
      ${isUnknown ? renderUnknownRecentWorkouts(record) : ""}
      ${isUnknown && suggestionNote && suggestionNote !== AI_STATUS_LABELS.not_requested
        ? `<div class="exercise-admin-feedback exercise-admin-feedback--subtle">${esc(suggestionNote)}</div>`
        : ""}
      ${errors && errors.general ? `<div class="exercise-admin-feedback exercise-admin-feedback--error">${esc(errors.general)}</div>` : ""}
      <form id="exerciseAdminForm" class="exercise-admin-form" novalidate>
        ${renderSaveAssistant(selected, state, record, editorLabel, saveState, pendingAction)}

        <section class="exercise-admin-form-section">
          <div class="exercise-admin-form-section-headline">
            <h3 class="exercise-admin-form-section-title">Names</h3>
          </div>
          ${renderRecognitionNamesSection(record, values, state)}
        </section>

        ${isExercise ? `<section class="exercise-admin-form-section">
          <div class="exercise-admin-form-section-headline">
            <h3 class="exercise-admin-form-section-title">How this should count</h3>
          </div>

          <div class="settings-field">
            <label class="settings-label exercise-admin-switch-row" for="exerciseFatigueIncluded">
              <span>Include in fatigue calculations ${tooltip("Turn this off for mobility, stretching, Yin Yoga, or other logged movements that should be recognized without adding fatigue.")}</span>
              <span class="exercise-admin-switch">
                <input type="checkbox" id="exerciseFatigueIncluded" name="fatigueImpactIncluded"${values.fatigueImpact !== "none" ? " checked" : ""}>
                <span class="exercise-admin-switch-ui" aria-hidden="true"></span>
              </span>
            </label>
          </div>

          <div class="exercise-admin-form-grid">
            <div class="settings-field">
              <label class="settings-label" for="exerciseSetTypeHandling">
                Exercise format
                ${tooltip("Choose strength for normal gym sets with reps. Choose time/cardio only when this exercise is logged by duration, like bike warmups or cooldowns.")}
              </label>
              <select class="settings-input settings-select" id="exerciseSetTypeHandling" name="setTypeHandling">
                ${SET_TYPE_OPTIONS.map((option) => `<option value="${escAttr(option.value)}"${values.setTypeHandling === option.value ? " selected" : ""}>${esc(option.label)}</option>`).join("")}
              </select>
              ${renderFieldError(errors, "setTypeHandling")}
            </div>
          </div>

          ${isTimeBasedExercise ? "" : `<div class="settings-field">
            <label class="settings-label exercise-admin-checkbox">
              <input type="checkbox" name="bodyweightEligible"${values.bodyweightEligible ? " checked" : ""}>
              <span>Use bodyweight when reps are logged without a weight ${tooltip("Turn this on for movements like push-ups, pull-ups, dips, or air squats if the log often records only reps and no added weight.")}</span>
            </label>
          </div>`}
        </section>` : ""}

        <section class="exercise-admin-form-section exercise-admin-form-section--muscles">
          <div class="exercise-admin-form-section-headline">
            <h3 class="exercise-admin-form-section-title">Muscles worked</h3>
            <p class="exercise-admin-form-section-copy">Set the relative load spread across muscle groups. These values are relative, not percentages.</p>
          </div>
          ${values.fatigueImpact === "none" ? '<div class="settings-help">No muscle selection is required while this exercise is tracked without fatigue.</div>' : ""}
          <div class="exercise-admin-muscle-workspace">
            <div class="exercise-admin-muscle-panel">
              <div class="exercise-admin-muscle-grid">
                ${renderMuscleWeightInputs(values, errors)}
              </div>
              ${renderMuscleErrorMessage(errors, values.muscleWeights, values.fatigueImpact)}
            </div>
            <aside class="exercise-admin-muscle-preview-panel">
              <div class="exercise-admin-muscle-preview-head">Live body map</div>
              <div data-exercise-admin-preview-root>
                ${renderExerciseMusclePreview(values.muscleWeights)}
              </div>
            </aside>
          </div>
        </section>
        ${renderAdvancedPanel(selected, state, values, editorLabel, pendingAction, metaBits)}
      </form>
    </section>`;
  }

  function renderUnknownEditor(record, state) {
    return renderConfigEditor({
      key: `unknown:${record.id}`,
      type: "unknown",
      kind: record.sourceType === "activityType" ? "unknownActivityType" : "unknownExercise",
      record,
    }, state);
  }

  function renderExerciseEditor(record, state) {
    return renderConfigEditor({ key: `exercise:${record.id}`, type: "configured", kind: "exercise", record }, state);
  }

  function renderActivityTypeEditor(record, state) {
    return renderConfigEditor({ key: `activityType:${record.id}`, type: "configured", kind: "activityType", record }, state);
  }

  function renderEditor(selected, state) {
    if (!selected) {
      return `<section class="exercise-admin-editor-shell exercise-admin-editor-shell--empty">
        <div class="settings-kicker">Config editor</div>
        <h2 class="exercise-admin-editor-title">No item selected</h2>
        <p class="exercise-admin-editor-copy">Choose an unknown item, exercise, or activity type to inspect and edit it.</p>
      </section>`;
    }

    if (selected.type === "unknown") {
      return renderUnknownEditor(selected.record, state);
    }

    if (selected.kind === "activityType") {
      return renderActivityTypeEditor(selected.record, state);
    }

    return renderExerciseEditor(selected.record, state);
  }

  function renderListContent(state) {
    const listItems = M.getExerciseAdminListItems(state.query, state.filter);
    return listItems.length
      ? listItems.map((item) => renderListItem(item, state)).join("")
      : `<div class="exercise-admin-empty">No exercises or activity types match the current search.</div>`;
  }

  function renderConfirmDialog() {
    const state = getAdminState();
    if (!state.confirmDialog || !state.confirmDialog.open || state.confirmDialog.type !== "delete") {
      return "";
    }

    return `<div class="detail-overlay exercise-admin-dialog-overlay open" data-exercise-admin-dialog-overlay>
      <div class="detail-modal exercise-admin-dialog" role="dialog" aria-modal="true" aria-labelledby="exerciseAdminDeleteTitle">
        <div class="exercise-admin-dialog-head">
          <div>
            <div class="settings-kicker">Delete ${esc(state.confirmDialog.kind === "activityType" ? "activity type" : "exercise")}</div>
            <h2 class="exercise-admin-dialog-title" id="exerciseAdminDeleteTitle">Confirm deletion</h2>
          </div>
          <button type="button" class="detail-close" aria-label="Close dialog" data-exercise-admin-dialog-close>&times;</button>
        </div>
        <div class="exercise-admin-dialog-body">
          <p class="exercise-admin-dialog-copy">${esc(state.confirmDialog.kind === "activityType" ? "Are you sure you want to permanently delete this activity type? Once deleted it cannot be recovered!" : DELETE_CONFIRMATION_COPY)}</p>
          <p class="exercise-admin-dialog-note">Delete removes this config from the live resolver. If matching data still exists, it can reappear as unknown after the review snapshot refreshes.</p>
          <div class="exercise-admin-editor-actions">
            <button type="button" class="settings-save-btn exercise-admin-delete-btn" data-exercise-admin-delete-confirm>Delete ${esc(state.confirmDialog.kind === "activityType" ? "activity type" : "exercise")}</button>
            <button type="button" class="passkey-add-btn exercise-admin-secondary-btn" data-exercise-admin-dialog-close>Cancel</button>
          </div>
        </div>
      </div>
    </div>`;
  }

  function renderShell() {
    const state = getAdminState();
    const selected = ensureSelection();
    const feedback = state.feedback;

    return `<div class="exercise-admin-shell">
      ${feedback ? `<div class="exercise-admin-feedback exercise-admin-feedback--${feedback.kind === "error" ? "error" : "ok"}">${esc(feedback.message)}</div>` : ""}

      <div class="exercise-admin-workspace">
        <section class="exercise-admin-panel exercise-admin-panel--list">
          <div class="exercise-admin-panel-head exercise-admin-panel-head--list">
            <div>
              <h2 class="exercise-admin-panel-title">Exercises</h2>
            </div>
            <div class="exercise-admin-metrics" aria-label="Exercise summary">
              ${buildBadge(`${countUnknownItems()} unconfigured`, "danger")}
            </div>
          </div>
          <div class="exercise-admin-list-tools">
            <input class="settings-input exercise-admin-search" type="search" placeholder="Search exercises, activity types, aliases, or match terms" value="${escAttr(state.query)}" data-exercise-admin-query>
            <div class="exercise-admin-filter-row" aria-label="Config type filter">
              ${LIST_FILTER_OPTIONS.map((option) => {
                const selectedFilter = state.filter === option.value;
                return `<button type="button" class="filter-pill${selectedFilter ? " active" : ""}" data-exercise-admin-filter="${escAttr(option.value)}" aria-pressed="${selectedFilter ? "true" : "false"}">${esc(option.label)}</button>`;
              }).join("")}
            </div>
          </div>
          <div class="exercise-admin-list">${renderListContent(state)}</div>
        </section>

        <div class="exercise-admin-editor" data-exercise-admin-editor>
          ${renderEditor(selected, state)}
        </div>
      </div>
      ${renderConfirmDialog()}
    </div>`;
  }

  function updateListAndEditor(options = {}) {
    const mount = document.getElementById("exerciseAdminContent");
    if (!mount) return;

    const state = getAdminState();
    const selected = options && options.preferVisibleSelection
      ? ensureSelection({ preferVisible: true })
      : ensureSelection();

    const listMount = mount.querySelector(".exercise-admin-list");
    if (listMount) {
      listMount.innerHTML = renderListContent(state);
    }

    const editorMount = mount.querySelector("[data-exercise-admin-editor]");
    if (editorMount) {
      editorMount.innerHTML = renderEditor(selected, state);
    }
  }

  function setFeedback(kind, message) {
    getAdminState().feedback = message ? { kind, message } : null;
  }

  function clearFormState() {
    const state = getAdminState();
    state.formDraft = null;
    state.formErrors = null;
  }

  function clearInlineEditorState() {
    const state = getAdminState();
    state.titleEditOpen = false;
    state.titleDraft = "";
    state.namesEditOpen = false;
    state.namesDraft = "";
    state.mergePanelKey = "";
    state.confirmDialog = {
      open: false,
      type: "",
      key: "",
      kind: "",
    };
  }

  function clearSelectionUiState() {
    const state = getAdminState();
    state.mergeTargetId = "";
    clearInlineEditorState();
    clearFormState();
  }

  function getActiveMergeTargetId(selected) {
    const state = getAdminState();
    const targetId = getMergeTargetId(selected);
    if (!targetId) {
      throw new Error("Choose a target exercise before merging.");
    }
    state.mergeTargetId = targetId;
    return targetId;
  }

  async function refreshUnknownSnapshotAfterMutation() {
    if (!Array.isArray(M.state.allData) || !M.state.allData.length || typeof M.scanAndSyncUnknownExercises !== "function") {
      return "";
    }

    try {
      await M.scanAndSyncUnknownExercises(M.state.allData);
      return "";
    } catch (error) {
      const message = String(error && error.message ? error.message : error);
      if (typeof M.setUnknownExerciseSnapshot === "function" && typeof M.collectUnknownExercisesFromActivities === "function") {
        M.setUnknownExerciseSnapshot(M.collectUnknownExercisesFromActivities(M.state.allData), {
          loadedAt: M.state.exerciseConfigMeta.loadedAt || "",
          syncedAt: new Date().toISOString(),
          lastSyncError: message,
        });
      }
      return message;
    }
  }

  function readMuscleWeightsFromForm(form) {
    const muscleWeights = {};
    M.MUSCLE_REGIONS.forEach((region) => {
      const element = form.elements[`muscle-${region.key}`];
      const raw = String(element && element.value != null ? element.value : "").trim();
      if (raw === "") return;
      muscleWeights[region.key] = Number(raw);
    });
    return muscleWeights;
  }

  function getMuscleLevelFromSliderValue(value) {
    const levels = typeof M.getExerciseMuscleUiLevels === "function" ? M.getExerciseMuscleUiLevels() : [];
    const numericValue = Math.max(1, Math.min(levels.length, Number(value || 1)));
    const index = numericValue - 1;
    return levels[index] || null;
  }

  function getMuscleSliderFillPercent(value) {
    const levels = typeof M.getExerciseMuscleUiLevels === "function" ? M.getExerciseMuscleUiLevels() : [];
    const minValue = 1;
    const maxValue = Math.max(minValue, levels.length);
    const numericValue = Math.max(minValue, Math.min(maxValue, Number(value || minValue)));
    if (maxValue === minValue) return 100;
    return ((numericValue - minValue) / (maxValue - minValue)) * 100;
  }

  function syncMuscleSliderVisuals(row, slider, involved, levelKey) {
    if (!row || !slider) return;
    slider.style.setProperty("--slider-fill", `${getMuscleSliderFillPercent(slider.value)}%`);
    slider.dataset.level = involved && levelKey ? levelKey : "none";

    Array.from(row.querySelectorAll("[data-exercise-admin-muscle-tick]")).forEach((tick) => {
      const tickValue = Number(tick.getAttribute("data-tick-value") || 0);
      tick.classList.toggle("is-active", involved && tickValue <= Number(slider.value || 0));
    });
  }

  function updateMuscleRowState(form, muscleKey) {
    const row = form.querySelector(`[data-exercise-admin-muscle-row][data-muscle-key="${muscleKey}"]`);
    if (!row) return;

    const toggle = row.querySelector("[data-exercise-admin-muscle-toggle]");
    const slider = row.querySelector("[data-exercise-admin-muscle-slider]");
    const hidden = row.querySelector("[data-exercise-admin-muscle-hidden]");
    const controls = row.querySelector("[data-exercise-admin-muscle-controls]");
    const status = row.querySelector("[data-exercise-admin-muscle-status]");
    if (!toggle || !slider || !hidden || !controls || !status) return;

    const involved = Boolean(toggle.checked);
    const defaultLevel = typeof M.getExerciseMuscleUiLevel === "function"
      ? M.getExerciseMuscleUiLevel(M.EXERCISE_MUSCLE_DEFAULT_LEVEL)
      : null;

    if (involved && Number(hidden.value || 0) <= 0 && defaultLevel) {
      slider.value = String(defaultLevel.index + 1);
    }

    const level = getMuscleLevelFromSliderValue(slider.value) || defaultLevel;
    const levelWeight = involved && level ? Number(level.weight) : 0;

    hidden.value = formatMuscleWeightValue(levelWeight);
    slider.disabled = !involved;
    controls.hidden = !involved;
    if (involved && level) {
      status.textContent = level.label;
      status.title = level.meaning || level.label;
      slider.setAttribute("aria-valuetext", `${level.label}: ${level.meaning}`);
    } else {
      status.textContent = "Not involved";
      status.title = "Not involved";
      slider.setAttribute("aria-valuetext", "Not involved");
    }

    row.classList.toggle("is-involved", involved);
    row.classList.toggle("is-compact", !involved);
    row.dataset.muscleLevel = involved && level ? level.key : "none";
    syncMuscleSliderVisuals(row, slider, involved, involved && level ? level.key : "");
  }

  function updateExerciseMusclePreview(form) {
    const previewMount = form.querySelector("[data-exercise-admin-preview-root]");
    if (previewMount) {
      previewMount.innerHTML = renderExerciseMusclePreview(readMuscleWeightsFromForm(form));
    }

    const errorMount = form.querySelector("[data-exercise-admin-muscle-error]");
    if (errorMount) {
      const fatigueImpact = Boolean((form.elements.fatigueImpactIncluded || {}).checked) ? "normal" : "none";
      const message = getMuscleValidationMessage(readMuscleWeightsFromForm(form), fatigueImpact);
      errorMount.textContent = message;
      errorMount.classList.toggle("exercise-admin-inline-error--hidden", !message);
    }
  }

  function updateSaveActionUi(form, selected) {
    if (!form || !selected) return;
    const state = getAdminState();
    const pendingAction = state.pendingKey === selected.key ? state.pendingAction : "";
    const saveState = getSaveActionState(selected, pendingAction);
    const saveButton = form.querySelector("[data-exercise-admin-save]");
    const cancelButton = form.querySelector("[data-exercise-admin-cancel-changes]");
    const status = form.querySelector("[data-exercise-admin-save-status]");

    if (saveButton) {
      saveButton.disabled = !saveState.canSave;
      saveButton.textContent = saveState.buttonLabel;
    }

    if (cancelButton) {
      cancelButton.disabled = !saveState.hasChanges;
    }

    if (status) {
      status.textContent = saveState.statusText;
      status.className = `exercise-admin-savebar-status exercise-admin-savebar-status--${saveState.statusTone}`;
    }
  }

  function syncExerciseFormDraftFromDom() {
    const form = document.getElementById("exerciseAdminForm");
    const selected = getSelectedItem();
    if (!form || !selected) return;

    const parsed = readExerciseFormPayload();
    if (!parsed) return;

    const state = getAdminState();
    state.formDraft = {
      key: selected.key,
      values: parsed.values,
    };
    state.formErrors = null;
    updateSaveActionUi(form, selected);
  }

  function readExerciseFormPayload() {
    const form = document.getElementById("exerciseAdminForm");
    const selected = getSelectedItem();
    if (!form || !selected) return null;

    const draftValues = getSelectedFormValues(selected);
    const isExercise = isExerciseKind(selected);
    const canonicalName = String(draftValues.canonicalName || "").trim();
    const aliases = parseListInput(draftValues.aliasesText || "");
    const matchTerms = isExercise ? parseListInput(draftValues.matchTermsText || "") : [];
    const fatigueImpact = isExercise && Boolean((form.elements.fatigueImpactIncluded || {}).checked) ? "normal" : "none";
    const setTypeHandling = isExercise ? normalizeSetTypeHandling(String((form.elements.setTypeHandling || {}).value || "")) : "";
    const bodyweightEligible = isExercise && setTypeHandling !== "time_duration"
      ? Boolean((form.elements.bodyweightEligible || {}).checked)
      : false;
    const exerciseFamily = String((form.elements.exerciseFamily || {}).value || "").trim();
    const fatigueArchetype = String((form.elements.fatigueArchetype || {}).value || "").trim();
    const muscleWeights = readMuscleWeightsFromForm(form);

    const values = {
      canonicalName,
      aliasesText: aliases.join("\n"),
      matchTermsText: isExercise ? matchTerms.join("\n") : "",
      fatigueImpact,
      bodyweightEligible,
      setTypeHandling,
      exerciseFamily,
      fatigueArchetype,
      muscleWeights,
      source: draftValues.source || "manual",
    };

    const errors = getFormValidationErrors(values, selected);

    return {
      values,
      errors,
      payload: {
        configType: isActivityTypeKind(selected) ? "activityType" : "exercise",
        canonicalName,
        aliases,
        ...(isExercise ? { matchTerms } : {}),
        muscleWeights,
        ...(isExercise ? {
          bodyweightEligible,
          setTypeHandling,
          fatigueImpact,
        } : {}),
        exerciseFamily: exerciseFamily || null,
        fatigueArchetype: fatigueArchetype || null,
        source: draftValues.source || "manual",
      },
    };
  }

  function startTitleEdit() {
    const selected = getSelectedItem();
    if (!selected) return;
    const state = getAdminState();
    state.titleEditOpen = true;
    state.titleDraft = (getSelectedFormValues(selected) || {}).canonicalName || "";
    M.renderExerciseAdminView();
  }

  function cancelTitleEdit() {
    const state = getAdminState();
    state.titleEditOpen = false;
    state.titleDraft = "";
    clearCanonicalNameError();
    M.renderExerciseAdminView();
  }

  function saveTitleEdit() {
    const selected = getSelectedItem();
    if (!selected) return;
    const state = getAdminState();
    const nextTitle = String(state.titleDraft || "").trim();
    if (!nextTitle) {
      state.formErrors = {
        key: selected.key,
        fields: {
          ...(state.formErrors && state.formErrors.key === selected.key ? state.formErrors.fields : {}),
          canonicalName: "Canonical name is required.",
        },
      };
      setFeedback("error", "Please fix the highlighted fields.");
      M.renderExerciseAdminView();
      return;
    }

    updateSelectedDraftValues(selected, { canonicalName: nextTitle });
    state.titleEditOpen = false;
    state.titleDraft = "";
    clearCanonicalNameError();
    M.renderExerciseAdminView();
  }

  function startNamesEdit() {
    const selected = getSelectedItem();
    if (!selected) return;
    const state = getAdminState();
    state.namesEditOpen = true;
    state.namesDraft = getMergedRecognitionNames(getSelectedFormValues(selected)).join("\n");
    M.renderExerciseAdminView();
  }

  function cancelNamesEdit() {
    const state = getAdminState();
    state.namesEditOpen = false;
    state.namesDraft = "";
    M.renderExerciseAdminView();
  }

  function saveNamesEdit() {
    const selected = getSelectedItem();
    if (!selected) return;
    const state = getAdminState();
    const values = getSelectedFormValues(selected);
    updateSelectedDraftValues(selected, mapMergedNamesToStoredLists(values, state.namesDraft || ""));
    state.namesEditOpen = false;
    state.namesDraft = "";
    M.renderExerciseAdminView();
  }

  function openMergePanel() {
    const selected = getSelectedItem();
    if (!selected) return;
    const state = getAdminState();
    state.mergePanelKey = selected.key;
    getMergeTargetId(selected);
    M.renderExerciseAdminView();
  }

  function closeMergePanel() {
    const state = getAdminState();
    state.mergePanelKey = "";
    M.renderExerciseAdminView();
  }

  function openDeleteDialog() {
    const selected = getSelectedItem();
    if (!selected || selected.type !== "configured") return;
    const state = getAdminState();
    state.confirmDialog = {
      open: true,
      type: "delete",
      key: selected.key,
      kind: selected.kind === "activityType" ? "activityType" : "exercise",
    };
    M.renderExerciseAdminView();
  }

  function closeConfirmDialog() {
    const state = getAdminState();
    state.confirmDialog = {
      open: false,
      type: "",
      key: "",
      kind: "",
    };
    M.renderExerciseAdminView();
  }

  function cancelEditorChanges() {
    clearInlineEditorState();
    clearFormState();
    setFeedback(null, "");
    M.renderExerciseAdminView();
  }

  function applySuggestionToSelectedDraft(selected, suggestion) {
    if (!selected || !suggestion || typeof suggestion !== "object") return;
    updateSelectedDraftValues(selected, {
      canonicalName: suggestion.canonicalName || "",
      aliasesText: toArray(suggestion.aliases).join("\n"),
      matchTermsText: toArray(suggestion.matchTerms).join("\n"),
      fatigueImpact: suggestion.fatigueImpact || "normal",
      bodyweightEligible: Boolean(suggestion.bodyweightEligible),
      setTypeHandling: suggestion.setTypeHandling || "weight_reps",
      exerciseFamily: suggestion.exerciseFamily || "",
      fatigueArchetype: suggestion.fatigueArchetype || "",
      muscleWeights: { ...(suggestion.muscleWeights || {}) },
      source: suggestion.source || "manual",
    });
  }

  async function handleGenerateSuggestion(unknownId) {
    const state = getAdminState();
    if (state.pendingUnknownId) return;

    state.pendingUnknownId = unknownId;
    setFeedback(null, "");
    M.renderExerciseAdminView();

    try {
      const response = await fetch(exerciseConfigEndpoint(`unknowns/${encodeURIComponent(unknownId)}/suggest`), {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "X-CSRF-Token": (window.MATTRICS_AUTH && window.MATTRICS_AUTH.csrfToken) || "",
        },
      });
      const json = await response.json();
      if (!response.ok) {
        throw new Error((json && json.error) || `Suggestion failed. HTTP ${response.status}`);
      }

      if (Array.isArray(json.exercises) && Array.isArray(json.activityTypes) && Array.isArray(json.unknowns)) {
        M.indexExerciseConfigs(json);
      }

      clearSelectionUiState();
      state.selectedKey = `unknown:${unknownId}`;
      applySuggestionToSelectedDraft(getSelectedItem(), json.suggestion);
      setFeedback("ok", "Suggestion applied to the form. Save to configure this exercise.");
      M.renderAll();
    } catch (error) {
      setFeedback("error", String(error && error.message ? error.message : error));
    } finally {
      state.pendingUnknownId = "";
      M.renderExerciseAdminView();
    }
  }

  async function handleRegenerateSuggestion(exerciseId) {
    const state = getAdminState();
    if (state.pendingAction) return;

    state.pendingAction = "suggest";
    state.pendingKey = `exercise:${exerciseId}`;
    setFeedback(null, "");
    M.renderExerciseAdminView();

    try {
      const response = await fetch(exerciseConfigEndpoint(`${encodeURIComponent(exerciseId)}/suggest`), {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "X-CSRF-Token": (window.MATTRICS_AUTH && window.MATTRICS_AUTH.csrfToken) || "",
        },
      });
      const json = await response.json();
      if (!response.ok) {
        throw new Error((json && json.error) || `Suggestion failed. HTTP ${response.status}`);
      }

      if (Array.isArray(json.exercises) && Array.isArray(json.activityTypes) && Array.isArray(json.unknowns)) {
        M.indexExerciseConfigs(json);
      }

      clearInlineEditorState();
      state.selectedKey = `exercise:${exerciseId}`;
      applySuggestionToSelectedDraft(getSelectedItem(), json.suggestion);
      setFeedback("ok", "Suggestion applied to the form. Save to keep the changes.");
      M.renderAll();
    } catch (error) {
      setFeedback("error", String(error && error.message ? error.message : error));
    } finally {
      state.pendingAction = "";
      state.pendingKey = "";
      M.renderExerciseAdminView();
    }
  }

  async function handleExerciseSave() {
    const state = getAdminState();
    const selected = getSelectedItem();
    if (!selected || state.pendingAction) return;

    const parsed = readExerciseFormPayload();
    if (!parsed) return;

    state.formDraft = {
      key: selected.key,
      values: parsed.values,
    };

    if (Object.keys(parsed.errors).length) {
      state.formErrors = {
        key: selected.key,
        fields: parsed.errors,
      };
      setFeedback("error", "Please fix the highlighted fields.");
      M.renderExerciseAdminView();
      return;
    }

    state.pendingAction = "save";
    state.pendingKey = selected.key;
    state.formErrors = null;
    setFeedback(null, "");
    M.renderExerciseAdminView();

    try {
      const response = await fetch(
        selected.type === "unknown"
          ? exerciseConfigEndpoint("")
          : exerciseConfigEndpoint(encodeURIComponent(selected.record.id)),
        {
        method: selected.type === "unknown" ? "POST" : "PATCH",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-Token": (window.MATTRICS_AUTH && window.MATTRICS_AUTH.csrfToken) || "",
        },
        body: JSON.stringify(selected.type === "unknown"
          ? {
              ...parsed.payload,
              unknownId: selected.record.id,
            }
          : parsed.payload),
      });
      const json = await response.json();
      if (!response.ok) {
        throw new Error((json && json.error) || `Save failed. HTTP ${response.status}`);
      }

      if (Array.isArray(json.exercises) && Array.isArray(json.activityTypes) && Array.isArray(json.unknowns)) {
        M.indexExerciseConfigs(json);
      }

      clearSelectionUiState();
      if (json.activityType && json.activityType.id) {
        state.selectedKey = `activityType:${json.activityType.id}`;
      } else if (json.exercise && json.exercise.id) {
        state.selectedKey = `exercise:${json.exercise.id}`;
      } else {
        state.selectedKey = selected.key;
      }
      const savedLabel = isActivityTypeKind(selected) ? "Activity type" : "Exercise";
      setFeedback("ok", selected.type === "unknown" ? `${savedLabel} created and applied.` : `${savedLabel} saved.`);
      M.renderAll();
    } catch (error) {
      state.formErrors = {
        key: selected.key,
        fields: {
          general: String(error && error.message ? error.message : error),
        },
      };
      setFeedback("error", "Could not save the exercise config.");
    } finally {
      state.pendingAction = "";
      state.pendingKey = "";
      M.renderExerciseAdminView();
    }
  }

  async function handleMergeAlias() {
    const state = getAdminState();
    const selected = getSelectedItem();
    if (!selected || state.pendingAction || !isExerciseKind(selected)) return;

    let targetExerciseId = "";
    try {
      targetExerciseId = getActiveMergeTargetId(selected);
    } catch (error) {
      setFeedback("error", String(error && error.message ? error.message : error));
      M.renderExerciseAdminView();
      return;
    }

    const target = getExerciseRecordById(targetExerciseId);
    const targetLabel = target ? target.canonicalName : "the selected target";

    state.pendingAction = "merge";
    state.pendingKey = selected.key;
    setFeedback(null, "");
    M.renderExerciseAdminView();

    try {
      const path = selected.type === "unknown"
        ? `unknowns/${encodeURIComponent(selected.record.id)}/merge-alias`
        : `${encodeURIComponent(selected.record.id)}/merge-alias`;
      const response = await fetch(exerciseConfigEndpoint(path), {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-Token": (window.MATTRICS_AUTH && window.MATTRICS_AUTH.csrfToken) || "",
        },
        body: JSON.stringify({ targetExerciseId }),
      });
      const json = await response.json();
      if (!response.ok) {
        throw new Error((json && json.error) || `Merge failed. HTTP ${response.status}`);
      }

      if (Array.isArray(json.exercises) && Array.isArray(json.activityTypes) && Array.isArray(json.unknowns)) {
        M.indexExerciseConfigs(json);
      }

      clearSelectionUiState();
      state.selectedKey = json.exercise && json.exercise.id ? `exercise:${json.exercise.id}` : `exercise:${targetExerciseId}`;
      setFeedback("ok", `Merged into ${targetLabel} and refreshed the live mapping.`);
      M.renderAll();
    } catch (error) {
      setFeedback("error", String(error && error.message ? error.message : error));
    } finally {
      state.pendingAction = "";
      state.pendingKey = "";
      M.renderExerciseAdminView();
    }
  }

  async function handleExerciseDelete() {
    const state = getAdminState();
    const selected = getSelectedItem();
    if (!selected || selected.type !== "configured" || state.pendingAction) return;
    const deletedLabel = selected.kind === "activityType" ? "Activity type" : "Exercise";

    state.pendingAction = "delete";
    state.pendingKey = selected.key;
    state.confirmDialog = {
      open: false,
      type: "",
      key: "",
    };
    setFeedback(null, "");
    M.renderExerciseAdminView();

    try {
      const response = await fetch(exerciseConfigEndpoint(encodeURIComponent(selected.record.id)), {
        method: "DELETE",
        credentials: "same-origin",
        headers: {
          "X-CSRF-Token": (window.MATTRICS_AUTH && window.MATTRICS_AUTH.csrfToken) || "",
        },
      });
      const json = await response.json();
      if (!response.ok) {
        throw new Error((json && json.error) || `Delete failed. HTTP ${response.status}`);
      }

      if (Array.isArray(json.exercises) && Array.isArray(json.activityTypes) && Array.isArray(json.unknowns)) {
        M.indexExerciseConfigs(json);
      }

      const unknownRefreshWarning = await refreshUnknownSnapshotAfterMutation();
      clearSelectionUiState();
      state.selectedKey = "";
      setFeedback(
        "ok",
        unknownRefreshWarning
          ? `${deletedLabel} deleted and fatigue recalculated. The unknown review snapshot was rebuilt locally because the server sync failed: ${unknownRefreshWarning}`
          : `${deletedLabel} deleted. Fatigue and review queue refreshed.`
      );
      M.renderAll();
    } catch (error) {
      setFeedback("error", String(error && error.message ? error.message : error));
    } finally {
      state.pendingAction = "";
      state.pendingKey = "";
      M.renderExerciseAdminView();
    }
  }

  function bindExerciseAdminEvents() {
    const mount = document.getElementById("exerciseAdminContent");
    if (!mount || mount.dataset.bound === "1") return;
    mount.dataset.bound = "1";

    mount.addEventListener("click", (event) => {
      if (event.target.matches("[data-exercise-admin-dialog-overlay]")) {
        event.preventDefault();
        closeConfirmDialog();
        return;
      }

      if (event.target.closest("[data-exercise-admin-dialog-close]")) {
        event.preventDefault();
        closeConfirmDialog();
        return;
      }

      const filterButton = event.target.closest("[data-exercise-admin-filter]");
      if (filterButton) {
        event.preventDefault();
        const state = getAdminState();
        state.filter = String(filterButton.getAttribute("data-exercise-admin-filter") || "all");
        updateListAndEditor({ preferVisibleSelection: true });
        return;
      }

      const suggestButton = event.target.closest("[data-exercise-admin-suggest]");
      if (suggestButton) {
        event.preventDefault();
        handleGenerateSuggestion(String(suggestButton.getAttribute("data-exercise-admin-suggest") || ""));
        return;
      }

      const selectButton = event.target.closest("[data-exercise-admin-select]");
      if (selectButton) {
        event.preventDefault();
        const state = getAdminState();
        state.selectedKey = String(selectButton.getAttribute("data-exercise-admin-select") || "");
        clearSelectionUiState();
        M.renderExerciseAdminView();
        return;
      }

      if (event.target.closest("[data-exercise-admin-title-edit-start]")) {
        event.preventDefault();
        startTitleEdit();
        return;
      }

      if (event.target.closest("[data-exercise-admin-title-save]")) {
        event.preventDefault();
        saveTitleEdit();
        return;
      }

      if (event.target.closest("[data-exercise-admin-title-cancel]")) {
        event.preventDefault();
        cancelTitleEdit();
        return;
      }

      if (event.target.closest("[data-exercise-admin-names-edit-start]")) {
        event.preventDefault();
        startNamesEdit();
        return;
      }

      if (event.target.closest("[data-exercise-admin-names-save]")) {
        event.preventDefault();
        saveNamesEdit();
        return;
      }

      if (event.target.closest("[data-exercise-admin-names-cancel]")) {
        event.preventDefault();
        cancelNamesEdit();
        return;
      }

      if (event.target.closest("[data-exercise-admin-merge-open]")) {
        event.preventDefault();
        openMergePanel();
        return;
      }

      if (event.target.closest("[data-exercise-admin-merge-cancel]")) {
        event.preventDefault();
        closeMergePanel();
        return;
      }

      if (event.target.closest("[data-exercise-admin-save]")) {
        event.preventDefault();
        handleExerciseSave();
        return;
      }

      if (event.target.closest("[data-exercise-admin-cancel-changes]")) {
        event.preventDefault();
        cancelEditorChanges();
        return;
      }

      if (event.target.closest("[data-exercise-admin-regenerate]")) {
        event.preventDefault();
        handleRegenerateSuggestion(String(event.target.closest("[data-exercise-admin-regenerate]").getAttribute("data-exercise-admin-regenerate") || ""));
        return;
      }

      if (event.target.closest("[data-exercise-admin-merge]")) {
        event.preventDefault();
        handleMergeAlias();
        return;
      }

      if (event.target.closest("[data-exercise-admin-delete-open]")) {
        event.preventDefault();
        openDeleteDialog();
        return;
      }

      if (event.target.closest("[data-exercise-admin-delete-confirm]")) {
        event.preventDefault();
        handleExerciseDelete();
        return;
      }
    });

    mount.addEventListener("input", (event) => {
      const search = event.target.closest("[data-exercise-admin-query]");
      if (search) {
        const state = getAdminState();
        state.query = String(search.value || "");
        updateListAndEditor({ preferVisibleSelection: true });
        return;
      }

      if (event.target.closest("[data-exercise-admin-title-input]")) {
        getAdminState().titleDraft = String(event.target.value || "");
        clearCanonicalNameError();
        return;
      }

      if (event.target.closest("[data-exercise-admin-names-text]")) {
        getAdminState().namesDraft = String(event.target.value || "");
        return;
      }

      const muscleSlider = event.target.closest("[data-exercise-admin-muscle-slider]");
      if (muscleSlider) {
        const form = muscleSlider.closest("form");
        if (!form) return;
        updateMuscleRowState(form, String(muscleSlider.getAttribute("data-muscle-key") || ""));
        updateExerciseMusclePreview(form);
        syncExerciseFormDraftFromDom();
        return;
      }

      if (event.target.closest("#exerciseAdminForm")) {
        syncExerciseFormDraftFromDom();
      }
    });

    mount.addEventListener("change", (event) => {
      const muscleToggle = event.target.closest("[data-exercise-admin-muscle-toggle]");
      if (muscleToggle) {
        const form = muscleToggle.closest("form");
        if (!form) return;
        updateMuscleRowState(form, String(muscleToggle.getAttribute("data-muscle-key") || ""));
        updateExerciseMusclePreview(form);
        syncExerciseFormDraftFromDom();
        return;
      }

      if (event.target.matches('input[name="fatigueImpactIncluded"]')) {
        syncExerciseFormDraftFromDom();
        M.renderExerciseAdminView();
        return;
      }

      const mergeTarget = event.target.closest("[data-exercise-admin-merge-target]");
      if (mergeTarget) {
        getAdminState().mergeTargetId = String(mergeTarget.value || "");
        return;
      }

      if (event.target.closest("#exerciseAdminForm")) {
        syncExerciseFormDraftFromDom();
      }
    });
  }

  M.renderExerciseAdminEditorHtmlForTest = function renderExerciseAdminEditorHtmlForTest(record, statePatch = {}) {
    const previous = M.state.exerciseAdmin;
    M.state.exerciseAdmin = {
      ...getAdminState(),
      ...statePatch,
      confirmDialog: {
        ...getAdminState().confirmDialog,
        ...((statePatch && statePatch.confirmDialog) || {}),
      },
    };
    try {
      return renderExerciseEditor(record, M.state.exerciseAdmin);
    } finally {
      M.state.exerciseAdmin = previous;
    }
  };

  M.renderActivityTypeAdminEditorHtmlForTest = function renderActivityTypeAdminEditorHtmlForTest(record, statePatch = {}) {
    const previous = M.state.exerciseAdmin;
    M.state.exerciseAdmin = {
      ...getAdminState(),
      ...statePatch,
      confirmDialog: {
        ...getAdminState().confirmDialog,
        ...((statePatch && statePatch.confirmDialog) || {}),
      },
    };
    try {
      return renderActivityTypeEditor(record, M.state.exerciseAdmin);
    } finally {
      M.state.exerciseAdmin = previous;
    }
  };

  M.renderExerciseAdminUnknownEditorHtmlForTest = function renderExerciseAdminUnknownEditorHtmlForTest(record, statePatch = {}) {
    const previous = M.state.exerciseAdmin;
    M.state.exerciseAdmin = {
      ...getAdminState(),
      ...statePatch,
      confirmDialog: {
        ...getAdminState().confirmDialog,
        ...((statePatch && statePatch.confirmDialog) || {}),
      },
    };
    try {
      return renderUnknownEditor(record, M.state.exerciseAdmin);
    } finally {
      M.state.exerciseAdmin = previous;
    }
  };

  M.renderExerciseAdminConfirmDialogHtmlForTest = function renderExerciseAdminConfirmDialogHtmlForTest(statePatch = {}) {
    const previous = M.state.exerciseAdmin;
    M.state.exerciseAdmin = {
      ...getAdminState(),
      ...statePatch,
      confirmDialog: {
        ...getAdminState().confirmDialog,
        ...((statePatch && statePatch.confirmDialog) || {}),
      },
    };
    try {
      return renderConfirmDialog();
    } finally {
      M.state.exerciseAdmin = previous;
    }
  };

  M.renderExerciseAdminListItemHtmlForTest = function renderExerciseAdminListItemHtmlForTest(item, statePatch = {}) {
    const previous = M.state.exerciseAdmin;
    M.state.exerciseAdmin = {
      ...getAdminState(),
      ...statePatch,
      confirmDialog: {
        ...getAdminState().confirmDialog,
        ...((statePatch && statePatch.confirmDialog) || {}),
      },
    };
    try {
      return renderListItem(item, M.state.exerciseAdmin);
    } finally {
      M.state.exerciseAdmin = previous;
    }
  };

  M.renderExerciseAdminShellHtmlForTest = function renderExerciseAdminShellHtmlForTest(statePatch = {}) {
    const previous = M.state.exerciseAdmin;
    M.state.exerciseAdmin = {
      ...getAdminState(),
      ...statePatch,
      confirmDialog: {
        ...getAdminState().confirmDialog,
        ...((statePatch && statePatch.confirmDialog) || {}),
      },
    };
    try {
      return renderShell();
    } finally {
      M.state.exerciseAdmin = previous;
    }
  };

  M.renderExerciseAdminView = function renderExerciseAdminView(options = {}) {
    const mount = document.getElementById("exerciseAdminContent");
    if (!mount) return;
    bindExerciseAdminEvents();
    if (options && options.preferVisibleSelection) {
      ensureSelection({ preferVisible: true });
    } else {
      ensureSelection();
    }
    mount.innerHTML = renderShell();
    if (document.body) {
      if (getAdminState().confirmDialog && getAdminState().confirmDialog.open) {
        document.body.style.overflow = "hidden";
      } else if (!document.querySelector || !document.querySelector("#detailOverlay.open")) {
        document.body.style.overflow = "";
      }
    }
  };
}());
