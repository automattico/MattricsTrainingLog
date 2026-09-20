(function () {
  const M = window.Mattrics;

  function resolveStatusTimestamp() {
    return M.resolveHeaderTimestamp(M.state.dataMeta || {}) || M.resolveHeaderTimestamp(M.state.exerciseConfigMeta || {});
  }

  function formatSourceLabel(source) {
    switch (String(source || "").trim()) {
      case "canonical": return "Canonical";
      case "cache": return "Cached snapshot";
      case "live": return "Live sheet";
      case "legacy": return "Legacy JSON";
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

    const timestamp = resolveStatusTimestamp();
    const lines = [
      `Activities: ${formatSourceLabel(meta.source)}`,
      `Config: ${formatSourceLabel(exerciseConfigMeta.source)}`,
    ];
    if (!timestamp) {
      lines.push("Last updated unavailable");
      stamp.innerHTML = lines.map((line) => `<div>${M.esc(line)}</div>`).join("");
      return;
    }

    lines.push(`Last updated: ${M.fmtRelativeAge(timestamp, Date.now())}`);
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

    if (M.dataSyncStampTicker) {
      window.clearInterval(M.dataSyncStampTicker);
      M.dataSyncStampTicker = null;
    }

    if (resolveStatusTimestamp()) {
      M.dataSyncStampTicker = window.setInterval(updateDataSyncStamp, 1000);
    }

    if (!banner) return;

    const warnings = [
      buildWarningLine("Activities", meta.warning, meta.lastSuccessfulSyncAt),
      buildWarningLine("Config", exerciseConfigMeta.warning, exerciseConfigMeta.lastSuccessfulSyncAt),
    ].filter(Boolean);

    if (
      meta.source
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
