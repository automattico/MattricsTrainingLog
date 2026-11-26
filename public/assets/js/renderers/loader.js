(function () {
  const M = window.Mattrics;

  function hasLinkedWorkoutSources(activities, catalog) {
    return activities.source === "canonical" && catalog.source === "linked-workout-json";
  }

  function resolveStatusTimestamp(activities, catalog) {
    if (hasLinkedWorkoutSources(activities, catalog)) {
      return M.parseTimestamp(activities.lastSuccessfulSyncAt);
    }
    return M.resolveHeaderTimestamp(activities) || M.resolveHeaderTimestamp(catalog);
  }

  function formatSourceLabel(source) {
    switch (String(source || "").trim()) {
      case "canonical": return "Canonical";
      case "cache": return "Cached snapshot";
      case "live": return "Live sheet";
      case "legacy": return "Legacy JSON";
      case "linked-workout-json": return "Local JSON catalog";
      default: return source ? String(source) : "Unavailable";
    }
  }

  function buildWarningLine(label, warning, timestamp) {
    const cleanWarning = String(warning || "").trim();
    if (!cleanWarning) return "";
    const suffix = timestamp ? ` Last good sync: ${M.fmtDateTime(timestamp)}.` : "";
    return `${label}: ${cleanWarning}${suffix}`;
  }

  function updateDataSyncStamp() {
    const meta = M.state.dataMeta || {};
    const exerciseConfigMeta = M.state.exerciseConfigMeta || {};
    const stamp = document.getElementById("dataSyncStamp");
    if (!stamp) return;

    const localHistory = hasLinkedWorkoutSources(meta, exerciseConfigMeta);
    const timestamp = resolveStatusTimestamp(meta, exerciseConfigMeta);
    const timestampLabel = localHistory ? "Last import" : "Last updated";
    const lines = localHistory
      ? ["Workouts: Local Postgres", "Catalog: Local JSON"]
      : [
        `Activities: ${formatSourceLabel(meta.source)}`,
        `Config: ${formatSourceLabel(exerciseConfigMeta.source)}`,
      ];
    for (const [provider, health] of Object.entries(meta.directRefresh || {})) {
      const last = health.last_success ? M.fmtDateTime(health.last_success) : "never";
      lines.push(`${provider}: ${health.status} · last success ${last}`);
    }
    if (!timestamp) {
      lines.push(`${timestampLabel} unavailable`);
      stamp.innerHTML = lines.map((line) => `<div>${M.esc(line)}</div>`).join("");
      return;
    }

    lines.push(`${timestampLabel}: ${M.fmtRelativeAge(timestamp, Date.now())}`);
    lines.push(`${M.fmtBerlinDate(timestamp)}, ${M.fmtBerlinTime(timestamp)}`);
    stamp.innerHTML = lines.map((line) => `<div>${M.esc(line)}</div>`).join("");
  }

  M.showLoading = function showLoading() {
    document.getElementById("loadScreen").classList.remove("hidden");
    document.getElementById("app").classList.remove("visible");
    document.getElementById("app").style.display = "none";
    document.getElementById("loadSpinner").style.display = "block";
    document.getElementById("loadMsg").style.display = "block";
    document.getElementById("errorBox").style.display = "none";
  };

  M.showError = function showError(msg) {
    document.getElementById("loadSpinner").style.display = "none";
    document.getElementById("loadMsg").style.display = "none";
    document.getElementById("errorMsg").textContent = msg;
    document.getElementById("errorBox").style.display = "flex";
  };

  M.showApp = function showApp() {
    document.getElementById("loadScreen").classList.add("hidden");
    document.getElementById("app").style.display = "block";
    requestAnimationFrame(() => document.getElementById("app").classList.add("visible"));
  };

  M.renderDataStatus = function renderDataStatus() {
    const meta = M.state.dataMeta || {};
    const exerciseConfigMeta = M.state.exerciseConfigMeta || {};
    const banner = document.getElementById("dataStatusBanner");

    updateDataSyncStamp();
    const expectedLinkedWorkoutSources = hasLinkedWorkoutSources(meta, exerciseConfigMeta);
    const refresh = document.getElementById("refreshDataButton");
    if (refresh) {
      refresh.title = expectedLinkedWorkoutSources ? "Reload imported workouts" : "Refresh from sheet";
      if (Object.keys(meta.directRefresh || {}).length) refresh.title = "Check direct workout sources";
      refresh.setAttribute("aria-label", Object.keys(meta.directRefresh || {}).length ? refresh.title : expectedLinkedWorkoutSources ? "Reload imported workouts" : "Refresh data");
    }

    if (M.dataSyncStampTicker) {
      window.clearInterval(M.dataSyncStampTicker);
      M.dataSyncStampTicker = null;
    }

    if (resolveStatusTimestamp(meta, exerciseConfigMeta)) {
      M.dataSyncStampTicker = window.setInterval(updateDataSyncStamp, 1000);
    }

    if (!banner) return;

    const warnings = [
      buildWarningLine("Activities", meta.warning, meta.lastSuccessfulSyncAt),
      buildWarningLine("Config", exerciseConfigMeta.warning, exerciseConfigMeta.lastSuccessfulSyncAt),
    ].filter(Boolean);

    if (
      !expectedLinkedWorkoutSources
      && meta.source
      && exerciseConfigMeta.source
      && (meta.source === "canonical") !== (exerciseConfigMeta.source === "canonical")
    ) {
      warnings.push(
        `Read-source mismatch: activities are ${formatSourceLabel(meta.source)} while config is ${formatSourceLabel(exerciseConfigMeta.source)}. This can happen during migration or fallback.`
      );
    }

    if (warnings.length) {
      banner.textContent = warnings.join(" ");
      banner.hidden = false;
      return;
    }

    banner.hidden = true;
    banner.textContent = "";
  };
}());
