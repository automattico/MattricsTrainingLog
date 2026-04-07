(function () {
  const M = window.Mattrics;

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
    const stamp = document.getElementById("dataSyncStamp");
    const banner = document.getElementById("dataStatusBanner");

    if (stamp) {
      const formatted = M.fmtDateTime(meta.lastSuccessfulSyncAt);
      stamp.textContent = formatted ? `Last updated ${formatted}` : "Last updated unavailable";
    }

    if (!banner) return;

    if (meta.stale && meta.warning) {
      banner.textContent = `${meta.warning}${meta.lastSuccessfulSyncAt ? ` Last good sync: ${M.fmtDateTime(meta.lastSuccessfulSyncAt)}.` : ""}`;
      banner.hidden = false;
      return;
    }

    banner.hidden = true;
    banner.textContent = "";
  };
}());
