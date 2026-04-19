(function () {
  const M = window.Mattrics;

  function updateDataSyncStamp() {
    const meta = M.state.dataMeta || {};
    const stamp = document.getElementById("dataSyncStamp");
    if (!stamp) return;

    const timestamp = M.resolveHeaderTimestamp(meta);
    if (!timestamp) {
      stamp.textContent = "Last updated unavailable";
      return;
    }

    const line1 = `Last updated: ${M.fmtRelativeAge(timestamp, Date.now())}`;
    const line2 = `${M.fmtBerlinDate(timestamp)}, ${M.fmtBerlinTime(timestamp)}`;
    stamp.innerHTML = `<div>${M.esc(line1)}</div><div>${M.esc(line2)}</div>`;
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
    const banner = document.getElementById("dataStatusBanner");

    updateDataSyncStamp();

    if (M.dataSyncStampTicker) {
      window.clearInterval(M.dataSyncStampTicker);
      M.dataSyncStampTicker = null;
    }

    if (M.resolveHeaderTimestamp(meta)) {
      M.dataSyncStampTicker = window.setInterval(updateDataSyncStamp, 1000);
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
