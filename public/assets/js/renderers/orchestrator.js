(function () {
  const M = window.Mattrics;

  M.fetchData = async function fetchData(options = {}) {
    M.showLoading();
    const forceRefresh = Boolean(options.forceRefresh);
    let sourceUrl = M.DATA_URL || M.SHEET_URL;

    if (!sourceUrl || sourceUrl === "PASTE_YOUR_WEB_APP_URL_HERE" || sourceUrl === "YOUR_GOOGLE_APPS_SCRIPT_URL_HERE") {
      M.showError(
        "No data source configured.\n\nFor secure hosting, use api/data.php with private/config.php on the server. For local fallback, set MATTRICS_CONFIG.SHEET_URL and MATTRICS_CONFIG.SHEET_TOKEN.\n\nSee README.md and the Hetzner deploy guide."
      );
      return;
    }

    try {
      if (M.DATA_URL) {
        const url = new URL(sourceUrl, window.location.href);
        if (forceRefresh) {
          url.searchParams.set("refresh", "1");
        }
        sourceUrl = url.toString();
      } else if (M.SHEET_TOKEN) {
        const url = new URL(sourceUrl);
        url.searchParams.set("key", M.SHEET_TOKEN);
        sourceUrl = url.toString();
      }

      const res = await fetch(sourceUrl, {
        redirect: "follow",
        credentials: "same-origin",
        headers: {},
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const text = await res.text();
      let json;
      try {
        json = JSON.parse(text);
      } catch {
        throw new Error("Response was not valid JSON. Check your Apps Script doGet().");
      }
      if (json.error) throw new Error(json.error);

      M.state.dataMeta = {
        source: json.meta && json.meta.source ? json.meta.source : "live",
        stale: Boolean(json.meta && json.meta.stale),
        warning: json.meta && json.meta.warning ? json.meta.warning : "",
        lastSuccessfulSyncAt: json.meta && json.meta.lastSuccessfulSyncAt ? json.meta.lastSuccessfulSyncAt : "",
        lastLiveAttemptAt: json.meta && json.meta.lastLiveAttemptAt ? json.meta.lastLiveAttemptAt : "",
      };
      M.state.allData = json.rows
        .filter((row) => row.Date && row.Type)
        .map((row) => ({
          ...row,
          Date: M.normalizeDateValue(row.Date),
        }))
        .filter((row) => row.Date)
        .sort((a, b) => new Date(b.Date) - new Date(a.Date));
      M.state.typeFilter = "All";
      M.showApp();
      M.renderDataStatus();
      await M.loadUserSettings();
      M.renderAll();
      if (window.__MATTRICS_INITIAL_VIEW__) {
        M.showView(window.__MATTRICS_INITIAL_VIEW__);
        window.__MATTRICS_INITIAL_VIEW__ = '';
      }
    } catch (error) {
      const msg = String(error && error.message ? error.message : error);
      const isCors = msg === "Failed to fetch" ||
        msg === "Load failed" ||
        msg.includes("NetworkError") ||
        msg.includes("CORS") ||
        msg.includes("fetch");

      M.showError(
        isCors
          ? "Browser blocked the request.\n\nYour Apps Script endpoint is live, but some browsers block fetches from a local file. Try opening this in Chrome, or serve the public folder on localhost instead of opening public/index.html via file://.\n\nIf needed, redeploy Apps Script as a Web App with Execute as: Me and Access: Anyone."
          : `Could not load data.\n\n${msg}`
      );
    }
  };

  M.setWindow = function setWindow(days, el) {
    M.state.windowDays = days;
    M.state.typeFilter = "All";
    document.querySelectorAll(".window-btn").forEach((button) => button.classList.remove("active"));
    document.querySelectorAll(".window-option").forEach((option) => option.classList.remove("active"));
    el.classList.add("active");
    if (el.parentElement) el.parentElement.classList.add("active");
    M.renderAll();
  };

  M.renderAll = function renderAll() {
    const windowed = M.getWindowedData();
    M.renderContextBar(windowed);
    M.renderDashboard(windowed);
    M.renderFatigueView(windowed);
    M.renderFilters(windowed);
    M.renderFeed(windowed);
    M.renderAiPreview();
  };

  M.renderContextBar = function renderContextBar(data) {
    const activeContext = document.getElementById("rangeSummary");
    if (!activeContext) return;

    if (!data.length) {
      activeContext.textContent = "No activities in this range";
      return;
    }

    const range = M.getWindowRange();
    activeContext.textContent = M.formatContextRange(range.start || data[data.length - 1].Date, range.end || data[0].Date);
  };

  M.renderFilters = function renderFilters(data) {
    const counts = {};
    data.forEach((activity) => {
      const type = M.canonicalType(activity.Type);
      counts[type] = (counts[type] || 0) + 1;
    });

    const sorted = Object.entries(counts).sort((a, b) => b[1] - a[1]);
    const allOn = M.state.typeFilter === "All";
    let html = `<button class="filter-pill ${allOn ? "on" : ""}" onclick="setFilter('All')">
      All <span class="filter-count">${data.length}</span></button>`;

    sorted.forEach(([type, count]) => {
      const cfg = M.tc(type);
      const on = M.state.typeFilter === type;
      html += `<button class="filter-pill ${on ? "on" : ""}" onclick="setFilter('${type}')">
        <span class="a-card-type-icon" aria-hidden="true">${cfg.icon}</span>
        ${cfg.label} <span class="filter-count">${count}</span></button>`;
    });

    document.getElementById("filterRow").innerHTML = html;
  };

  M.setFilter = function setFilter(filter) {
    M.state.typeFilter = filter;
    const windowed = M.getWindowedData();
    M.renderFilters(windowed);
    M.renderFeed(M.applyTypeFilter(windowed));
  };

  M.setFeedMode = function setFeedMode(mode, el) {
    if (mode === "list") {
      M.state.feedMode = "list";
    } else {
      M.state.feedMode = "grouped";
      M.state.groupBy = mode;
    }
    document.querySelectorAll(".feed-mode-switch .tl-sw-btn").forEach((button) => button.classList.remove("active"));
    if (el) el.classList.add("active");
    M.renderFeed();
  };

  M.showView = function showView(id, el) {
    document.querySelectorAll(".view").forEach((view) => view.classList.remove("active"));
    document.querySelectorAll(".nav-btn").forEach((button) => button.classList.remove("active"));
    document.querySelectorAll(".ai-top-btn").forEach((button) => button.classList.remove("active"));
    document.getElementById(`view-${id}`).classList.add("active");
    if (el && el.classList.contains("nav-btn")) el.classList.add("active");
    if (id === "ai") {
      document.querySelectorAll(".ai-top-btn").forEach((button) => button.classList.add("active"));
    }
    if (id === "settings") {
      M.renderSettingsView();
    }
  };
}());
