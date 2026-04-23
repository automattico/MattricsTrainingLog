(function () {
  const M = window.Mattrics;
  const SET_TYPE_OPTIONS = [
    { value: "weight_reps", label: "Weight + reps" },
    { value: "bodyweight_reps", label: "Bodyweight + reps" },
    { value: "time_based_ignore", label: "Ignore time-based sets" },
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

        const exercises = M.parseHevyDescription(activity.Description);
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

  function getUnknownRecordById(id) {
    return toArray(M.state.unknownExercises).find((record) => record && record.id === id) || null;
  }

  function getSelectedItem() {
    const selectedKey = String(getAdminState().selectedKey || "");
    if (!selectedKey) return null;

    if (selectedKey.startsWith("exercise:")) {
      const record = getExerciseRecordById(selectedKey.slice("exercise:".length));
      return record ? { key: selectedKey, type: "exercise", record } : null;
    }

    if (selectedKey.startsWith("unknown:")) {
      const record = getUnknownRecordById(selectedKey.slice("unknown:".length));
      return record ? { key: selectedKey, type: "unknown", record } : null;
    }

    return null;
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

    return {
      canonicalName: record.canonicalName || "",
      aliasesText: toArray(record.aliases).join("\n"),
      matchTermsText: toArray(record.matchTerms).join("\n"),
      fatigueMultiplier: String(record.fatigueMultiplier == null ? "1" : record.fatigueMultiplier),
      bodyweightEligible: Boolean(record.bodyweightEligible),
      setTypeHandling: record.setTypeHandling || "weight_reps",
      muscleWeights: { ...(record.muscleWeights || {}) },
      source: record.source || "manual",
    };
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
      matchTermsText: matchTerms.join("\n"),
      fatigueMultiplier: "1",
      bodyweightEligible: false,
      setTypeHandling: "weight_reps",
      muscleWeights: {},
      source: "manual",
    };
  }

  function getSelectedFormValues(selected) {
    if (!selected) return null;
    return selected.type === "unknown"
      ? getUnknownFormValues(selected.record)
      : getExerciseFormValues(selected.record);
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

  function updateSelectedDraftValues(selected, patch) {
    if (!selected) return null;
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

  function getFilteredExercises(query, filter) {
    return M.filterExerciseAdminList(toArray(M.state.exerciseConfigs), query, filter);
  }

  function getMergeTargetOptions(selected) {
    const selectedExerciseId = selected && selected.type === "exercise" ? selected.record.id : "";
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

  function countUnknownExercises() {
    return toArray(M.state.unknownExercises).filter((record) => record && record.sourceType === "exercise").length;
  }

  M.filterExerciseAdminList = function filterExerciseAdminList(records, query, filter) {
    const normalizedQuery = normalizeQuery(query);

    return toArray(records)
      .filter((record) => Boolean(record))
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
      .filter((record) => record && record.sourceType === "exercise")
      .filter((record) => matchesQuery([
        record.normalizedName,
        ...(toArray(record.rawNames)),
      ]))
      .sort((left, right) => String(right.lastSeenAt || "").localeCompare(String(left.lastSeenAt || "")))
      .map((record) => ({
        key: `unknown:${record.id}`,
        type: "unknown",
        record,
      }));

    const exerciseItems = M.filterExerciseAdminList(toArray(M.state.exerciseConfigs), query, filter)
      .map((record) => ({
        key: `exercise:${record.id}`,
        type: "exercise",
        record,
      }));

    return unknownItems.concat(exerciseItems);
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
          <span class="exercise-admin-list-badges">${buildBadge("Unconfigured", "danger")}</span>
        </div>
      </button>`;
    }

    const record = item.record;
    return `<button type="button" class="exercise-admin-list-item${selected ? " is-selected" : ""}" data-exercise-admin-select="${escAttr(item.key)}">
      <div class="exercise-admin-list-row">
        <strong>${esc(record.canonicalName)}</strong>
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

  function getMuscleValidationMessage(muscleWeights) {
    const values = Object.values(muscleWeights || {});
    const hasInvalidWeight = values.some((value) => !Number.isFinite(value) || value < 0);
    if (hasInvalidWeight) return "Muscle weights must be zero or positive numbers.";
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
      fatigueMultiplier: String(input.fatigueMultiplier == null ? "" : input.fatigueMultiplier).trim(),
      bodyweightEligible: Boolean(input.bodyweightEligible),
      setTypeHandling: String(input.setTypeHandling || ""),
      muscleWeights,
      source: String(input.source || "manual"),
    };
  }

  function hasSaveableChanges(selected) {
    if (!selected) return false;
    const currentValues = normalizeFormValuesForComparison(getSelectedFormValues(selected));
    const persistedValues = normalizeFormValuesForComparison(
      selected.type === "unknown"
        ? getUnknownFormValues({ ...selected.record, id: "__baseline__", rawNames: selected.record.rawNames, normalizedName: selected.record.normalizedName })
        : {
            canonicalName: selected.record.canonicalName || "",
            aliasesText: toArray(selected.record.aliases).join("\n"),
            matchTermsText: toArray(selected.record.matchTerms).join("\n"),
            fatigueMultiplier: String(selected.record.fatigueMultiplier == null ? "1" : selected.record.fatigueMultiplier),
            bodyweightEligible: Boolean(selected.record.bodyweightEligible),
            setTypeHandling: selected.record.setTypeHandling || "weight_reps",
            muscleWeights: { ...(selected.record.muscleWeights || {}) },
            source: selected.record.source || "manual",
          }
    );

    return JSON.stringify(currentValues) !== JSON.stringify(persistedValues);
  }

  function getFormValidationErrors(values) {
    const normalizedValues = normalizeFormValuesForComparison(values);
    const errors = {};

    if (!normalizedValues.canonicalName) {
      errors.canonicalName = "Canonical name is required.";
    }
    if (
      normalizedValues.fatigueMultiplier === ""
      || !Number.isFinite(Number(normalizedValues.fatigueMultiplier))
    ) {
      errors.fatigueMultiplier = "Fatigue multiplier must be numeric.";
    }
    if (!SET_TYPE_OPTIONS.some((option) => option.value === normalizedValues.setTypeHandling)) {
      errors.setTypeHandling = "Choose how sets should be interpreted.";
    }

    const muscleError = getMuscleValidationMessage(normalizedValues.muscleWeights);
    if (muscleError) {
      errors.muscleWeights = muscleError;
    }

    return errors;
  }

  function renderMuscleErrorMessage(errors, muscleWeights) {
    const message = (errors && errors.muscleWeights) || getMuscleValidationMessage(muscleWeights);
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
          Synonyms
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

  function renderConfigEditor(selected, state) {
    const record = selected.record;
    const values = getSelectedFormValues(selected);
    const errors = state.formErrors && state.formErrors.key === selected.key ? state.formErrors.fields : null;
    const pendingAction = state.pendingKey === selected.key ? state.pendingAction : "";
    const isUnknown = selected.type === "unknown";
    const hasChanges = hasSaveableChanges(selected);
    const validationErrors = hasChanges ? getFormValidationErrors(values) : null;
    const hasValidationErrors = Boolean(validationErrors && Object.keys(validationErrors).length);
    const canSave = hasChanges && !hasValidationErrors && pendingAction !== "save";
    const isTitleEditing = state.titleEditOpen;
    const titleValue = isTitleEditing ? state.titleDraft : values.canonicalName;
    const suggestionNote = isUnknown ? formatUnknownSuggestionNote(record.aiStatus) : "";
    const metaBits = isUnknown
      ? []
      : [
          `ID: <code>${esc(record.id)}</code>`,
          `Last updated ${esc(formatDateTime(record.lastUpdatedAt))}`,
        ];

    return `<section class="exercise-admin-editor-shell">
      <div class="exercise-admin-editor-head">
        <div class="exercise-admin-editor-main">
          ${isUnknown ? "" : '<div class="settings-kicker">Exercise editor</div>'}
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
                  'Edit exercise name',
                  `<svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M10.9 2.6a1.5 1.5 0 1 1 2.1 2.1L5.4 12.3 2 13l.7-3.4 8.2-7z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>`
                )}
              </div>`}
          ${renderFieldError(errors, "canonicalName")}
          ${isUnknown ? "" : '<p class="exercise-admin-editor-copy">Update the fatigue mapping directly here, then save the configured exercise.</p>'}
        </div>
        ${isUnknown ? `<div class="exercise-admin-badges">${buildBadge("Unconfigured", "danger")}</div>` : ""}
      </div>
      ${metaBits.length ? `<div class="exercise-admin-editor-meta">${metaBits.map((bit) => `<span>${bit}</span>`).join("")}</div>` : ""}
      ${isUnknown ? renderUnknownRecentWorkouts(record) : ""}
      ${isUnknown && suggestionNote && suggestionNote !== AI_STATUS_LABELS.not_requested
        ? `<div class="exercise-admin-feedback exercise-admin-feedback--subtle">${esc(suggestionNote)}</div>`
        : ""}
      ${errors && errors.general ? `<div class="exercise-admin-feedback exercise-admin-feedback--error">${esc(errors.general)}</div>` : ""}
      <form id="exerciseAdminForm" class="exercise-admin-form" novalidate>
        <div class="exercise-admin-editor-actions exercise-admin-editor-actions--top">
          <button type="button" class="settings-save-btn" data-exercise-admin-save${canSave ? "" : " disabled"}>Save</button>
          ${isUnknown
            ? `<button type="button" class="gen-btn" data-exercise-admin-suggest="${escAttr(record.id)}"${state.pendingUnknownId === record.id ? " disabled" : ""}>✨ Generate suggestion</button>`
            : `<button type="button" class="gen-btn" data-exercise-admin-regenerate="${escAttr(record.id)}"${pendingAction === "suggest" ? " disabled" : ""}>✨ Regenerate suggestion</button>`}
        </div>

        <div class="exercise-admin-secondary-actions exercise-admin-secondary-actions--top">
          ${renderMergeAction(selected, state, {
            emptyMessage: "Create at least one exercise config before merging unknowns as aliases.",
          })}
          ${isUnknown
            ? ""
            : `<button type="button" class="settings-save-btn exercise-admin-delete-btn" data-exercise-admin-delete-open${pendingAction === "delete" ? " disabled" : ""}>Delete exercise</button>`}
        </div>

        ${renderRecognitionNamesSection(record, values, state)}

        <div class="exercise-admin-form-grid">
          <div class="settings-field">
            <label class="settings-label" for="exerciseSetTypeHandling">
              Set type handling
              ${tooltip("Controls how exercise sets are interpreted before scaled load is computed. Use bodyweight mode when rep-only sets should use bodyweight, or ignore time-based sets when the movement should not contribute load from durations.")}
            </label>
            <select class="settings-input settings-select" id="exerciseSetTypeHandling" name="setTypeHandling">
              ${SET_TYPE_OPTIONS.map((option) => `<option value="${escAttr(option.value)}"${values.setTypeHandling === option.value ? " selected" : ""}>${esc(option.label)}</option>`).join("")}
            </select>
            ${renderFieldError(errors, "setTypeHandling")}
          </div>

          <div class="settings-field">
            <label class="settings-label" for="exerciseFatigueMultiplier">
              Fatigue multiplier
              ${tooltip("Intensity adjustment applied after scaled load is estimated. Values above 1.0 amplify fatigue stimulus, while values below 1.0 soften it for lighter or less taxing movements.")}
            </label>
            <input class="settings-input settings-input--narrow" id="exerciseFatigueMultiplier" name="fatigueMultiplier" type="number" min="0" step="0.05" value="${escAttr(values.fatigueMultiplier)}">
            ${renderFieldError(errors, "fatigueMultiplier")}
          </div>
        </div>

        <div class="settings-field">
          <label class="settings-label exercise-admin-checkbox">
            <input type="checkbox" name="bodyweightEligible"${values.bodyweightEligible ? " checked" : ""}>
            <span>Bodyweight eligible</span>
          </label>
        </div>

        <div class="settings-field">
          <label class="settings-label">
            Muscle involvement
            ${tooltip("Each level is a relative multiplier used to distribute scaled load across the fatigue model. These values are not percentages and do not need to add up to 100.")}
          </label>
          <div class="settings-error">At least one muscle group must be primary.</div>
          <div class="exercise-admin-muscle-workspace">
            <div class="exercise-admin-muscle-panel">
              <div class="exercise-admin-muscle-grid">
                ${renderMuscleWeightInputs(values, errors)}
              </div>
              ${renderMuscleErrorMessage(errors, values.muscleWeights)}
            </div>
            <aside class="exercise-admin-muscle-preview-panel">
              <div class="exercise-admin-muscle-preview-head">Live body map</div>
              <div data-exercise-admin-preview-root>
                ${renderExerciseMusclePreview(values.muscleWeights)}
              </div>
            </aside>
          </div>
        </div>
      </form>
    </section>`;
  }

  function renderUnknownEditor(record, state) {
    return renderConfigEditor({ key: `unknown:${record.id}`, type: "unknown", record }, state);
  }

  function renderExerciseEditor(record, state) {
    return renderConfigEditor({ key: `exercise:${record.id}`, type: "exercise", record }, state);
  }

  function renderEditor(selected, state) {
    if (!selected) {
      return `<section class="exercise-admin-editor-shell exercise-admin-editor-shell--empty">
        <div class="settings-kicker">Exercise editor</div>
        <h2 class="exercise-admin-editor-title">No exercise selected</h2>
        <p class="exercise-admin-editor-copy">Choose an unknown exercise or an existing config to inspect and edit it.</p>
      </section>`;
    }

    if (selected.type === "unknown") {
      return renderUnknownEditor(selected.record, state);
    }

    return renderExerciseEditor(selected.record, state);
  }

  function renderListContent(state) {
    const listItems = M.getExerciseAdminListItems(state.query, state.filter);
    return listItems.length
      ? listItems.map((item) => renderListItem(item, state)).join("")
      : `<div class="exercise-admin-empty">No exercises match the current search.</div>`;
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
            <div class="settings-kicker">Delete exercise</div>
            <h2 class="exercise-admin-dialog-title" id="exerciseAdminDeleteTitle">Confirm deletion</h2>
          </div>
          <button type="button" class="detail-close" aria-label="Close dialog" data-exercise-admin-dialog-close>&times;</button>
        </div>
        <div class="exercise-admin-dialog-body">
          <p class="exercise-admin-dialog-copy">${esc(DELETE_CONFIRMATION_COPY)}</p>
          <p class="exercise-admin-dialog-note">Delete removes this config from the live resolver. If matching data still exists, it can reappear as unknown after the review snapshot refreshes.</p>
          <div class="exercise-admin-editor-actions">
            <button type="button" class="settings-save-btn exercise-admin-delete-btn" data-exercise-admin-delete-confirm>Delete exercise</button>
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
              ${buildBadge(`${countUnknownExercises()} unconfigured`, "danger")}
            </div>
          </div>
          <div class="exercise-admin-list-tools">
            <input class="settings-input exercise-admin-search" type="search" placeholder="Search exercises, aliases, or match terms" value="${escAttr(state.query)}" data-exercise-admin-query>
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
      const message = getMuscleValidationMessage(readMuscleWeightsFromForm(form));
      errorMount.textContent = message;
      errorMount.classList.toggle("exercise-admin-inline-error--hidden", !message);
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
  }

  function readExerciseFormPayload() {
    const form = document.getElementById("exerciseAdminForm");
    const selected = getSelectedItem();
    if (!form || !selected) return null;

    const draftValues = getSelectedFormValues(selected);
    const canonicalName = String(draftValues.canonicalName || "").trim();
    const aliases = parseListInput(draftValues.aliasesText || "");
    const matchTerms = parseListInput(draftValues.matchTermsText || "");
    const fatigueMultiplierRaw = String((form.elements.fatigueMultiplier || {}).value || "").trim();
    const setTypeHandling = String((form.elements.setTypeHandling || {}).value || "");
    const bodyweightEligible = Boolean((form.elements.bodyweightEligible || {}).checked);
    const muscleWeights = readMuscleWeightsFromForm(form);

    const values = {
      canonicalName,
      aliasesText: aliases.join("\n"),
      matchTermsText: matchTerms.join("\n"),
      fatigueMultiplier: fatigueMultiplierRaw,
      bodyweightEligible,
      setTypeHandling,
      muscleWeights,
      source: draftValues.source || "manual",
    };

    const errors = getFormValidationErrors(values);

    return {
      values,
      errors,
      payload: {
        canonicalName,
        aliases,
        matchTerms,
        muscleWeights,
        fatigueMultiplier: Number(fatigueMultiplierRaw),
        bodyweightEligible,
        setTypeHandling,
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
    if (!selected || selected.type !== "exercise") return;
    const state = getAdminState();
    state.confirmDialog = {
      open: true,
      type: "delete",
      key: selected.key,
    };
    M.renderExerciseAdminView();
  }

  function closeConfirmDialog() {
    const state = getAdminState();
    state.confirmDialog = {
      open: false,
      type: "",
      key: "",
    };
    M.renderExerciseAdminView();
  }

  function applySuggestionToSelectedDraft(selected, suggestion) {
    if (!selected || !suggestion || typeof suggestion !== "object") return;
    updateSelectedDraftValues(selected, {
      canonicalName: suggestion.canonicalName || "",
      aliasesText: toArray(suggestion.aliases).join("\n"),
      matchTermsText: toArray(suggestion.matchTerms).join("\n"),
      fatigueMultiplier: String(suggestion.fatigueMultiplier == null ? "1" : suggestion.fatigueMultiplier),
      bodyweightEligible: Boolean(suggestion.bodyweightEligible),
      setTypeHandling: suggestion.setTypeHandling || "weight_reps",
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
      state.selectedKey = json.exercise && json.exercise.id ? `exercise:${json.exercise.id}` : selected.key;
      setFeedback("ok", selected.type === "unknown" ? "Exercise created and applied." : "Exercise saved.");
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
    if (!selected || state.pendingAction) return;

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
    if (!selected || selected.type !== "exercise" || state.pendingAction) return;

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
          ? `Exercise deleted and fatigue recalculated. The unknown review snapshot was rebuilt locally because the server sync failed: ${unknownRefreshWarning}`
          : "Exercise deleted. Fatigue and review queue refreshed."
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
