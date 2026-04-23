(function () {
  const M = window.Mattrics;
  const VIEW_IDS = ["dashboard", "fatigue", "sessions", "exercises", "ai", "docs", "settings"];

  function isValidView(id) {
    return VIEW_IDS.includes(id) && Boolean(document.getElementById(`view-${id}`));
  }

  function getUrlView() {
    try {
      const id = new URL(window.location.href).searchParams.get("view");
      return isValidView(id) ? id : "";
    } catch {
      return "";
    }
  }

  function updateViewUrl(id) {
    if (!window.history || !window.history.replaceState) return;
    try {
      const url = new URL(window.location.href);
      if (id === "dashboard") {
        url.searchParams.delete("view");
      } else {
        url.searchParams.set("view", id);
      }
      window.history.replaceState({}, "", url.toString());
    } catch {
      // Ignore URL persistence when the browser does not allow it.
    }
  }

  function getInitialView() {
    const serverView = window.__MATTRICS_INITIAL_VIEW__;
    if (isValidView(serverView)) return serverView;
    return getUrlView();
  }

  function renderDocsView() {
    const mount = document.getElementById("docsContent");
    if (!mount) return;

    if (typeof M.renderDocsView === "function") {
      M.renderDocsView();
      return;
    }

    mount.innerHTML = `<div class="docs-page"><div class="docs-hero">
      <div class="docs-kicker">Documentation</div>
      <h1 class="docs-title">Loading docs...</h1>
      <p class="docs-intro">Preparing the documentation hub.</p>
    </div></div>`;

    if (M.docsRendererLoading) return;
    M.docsRendererLoading = true;

    const script = document.createElement("script");
    script.src = `assets/js/renderers/docs.js?v=${Date.now()}`;
    script.onload = () => {
      M.docsRendererLoading = false;
      const docsView = document.getElementById("view-docs");
      if (typeof M.renderDocsView === "function" && docsView && docsView.classList.contains("active")) {
        M.renderDocsView();
      }
    };
    script.onerror = () => {
      M.docsRendererLoading = false;
      mount.innerHTML = `<div class="docs-page"><div class="docs-hero">
        <div class="docs-kicker">Documentation</div>
        <h1 class="docs-title">Documentation unavailable</h1>
        <p class="docs-intro">The documentation renderer could not be loaded. Refresh the page and try again.</p>
      </div></div>`;
    };
    document.head.appendChild(script);
  }

  M.fetchData = async function fetchData(options = {}) {
    M.showLoading();
    const forceRefresh = Boolean(options.forceRefresh);
    let sourceUrl = M.DATA_URL || M.SHEET_URL;
    const configUrl = M.EXERCISE_CONFIG_URL;

    if (!sourceUrl || sourceUrl === "PASTE_YOUR_WEB_APP_URL_HERE" || sourceUrl === "YOUR_GOOGLE_APPS_SCRIPT_URL_HERE") {
      M.showError(
        "No data source configured.\n\nFor secure hosting, use api/data.php with private/config.php on the server. For local fallback, set MATTRICS_CONFIG.SHEET_URL and MATTRICS_CONFIG.SHEET_TOKEN.\n\nSee README.md and the Hetzner deploy guide."
      );
      return;
    }
    if (!configUrl) {
      M.showError(
        "No exercise config source configured.\n\nServe the app through PHP so api/exercises.php can read the private JSON seed files."
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
      const [res] = await Promise.all([
        fetch(sourceUrl, {
          redirect: "follow",
          credentials: "same-origin",
          headers: {},
        }),
        M.loadExerciseConfigs(),
      ]);
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
        lastFetchAt: new Date().toISOString(),
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
      await M.scanAndSyncUnknownExercises(M.state.allData);
      M.showApp();
      M.renderDataStatus();
      await M.loadUserSettings();
      M.renderAll();
      const initialView = getInitialView();
      if (initialView) {
        M.showView(initialView, null, { persist: false });
        window.__MATTRICS_INITIAL_VIEW__ = "";
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

  M.setDashboardWindow = function setDashboardWindow(days, el) {
    M.state.dashboardWindowDays = days;
    const controls = el ? el.closest(".dashboard-window-controls") : document.getElementById("dashboardWindowControls");
    if (controls) {
      controls.querySelectorAll(".window-btn").forEach((button) => button.classList.remove("active"));
      controls.querySelectorAll(".window-option").forEach((option) => option.classList.remove("active"));
    }
    if (el) {
      el.classList.add("active");
      if (el.parentElement) el.parentElement.classList.add("active");
    }
    M.renderAll();
  };

  M.renderAll = function renderAll() {
    const dashboardWindowed = M.getDashboardWindowedData();
    const sessionActivities = M.applyTypeFilter(M.state.allData);
    M.renderContextBar(dashboardWindowed);
    M.renderDashboard(dashboardWindowed);
    M.renderFatigueView(M.state.allData);
    M.renderFilters(M.state.allData);
    M.renderFeed(sessionActivities);
    M.renderAiPreview();
    const exerciseView = document.getElementById("view-exercises");
    if (exerciseView && exerciseView.classList.contains("active") && typeof M.renderExerciseAdminView === "function") {
      M.renderExerciseAdminView();
    }
  };

  M.renderContextBar = function renderContextBar(data) {
    const activeContext = document.getElementById("dashboardRangeSummary");
    if (!activeContext) return;

    if (!data.length) {
      activeContext.textContent = "No activities in this range";
    } else {
      const range = M.getDashboardWindowRange();
      activeContext.textContent = M.formatContextRange(range.start || data[data.length - 1].Date, range.end || data[0].Date);
    }

    M.positionRangeSummary();
  };

  M.positionRangeSummary = function positionRangeSummary() {
    const el = document.getElementById("dashboardRangeSummary");
    if (!el) return;
    el.style.left = "";
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
    const activities = M.state.allData;
    M.renderFilters(activities);
    M.renderFeed(M.applyTypeFilter(activities));
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
    M.renderFeed(M.applyTypeFilter(M.state.allData));
  };

  M.showView = function showView(id, el, options = {}) {
    if (!isValidView(id)) return;
    document.querySelectorAll(".view").forEach((view) => view.classList.remove("active"));
    document.querySelectorAll(".nav-btn").forEach((button) => button.classList.remove("active"));
    document.querySelectorAll(".nav-bottom-btn").forEach((b) => b.classList.remove("active"));
    document.querySelectorAll(".nav-drawer-btn").forEach((b) => b.classList.remove("active"));
    document.getElementById(`view-${id}`).classList.add("active");
    // Mark active on whichever nav the clicked element belongs to
    if (el && (el.classList.contains("nav-btn") || el.classList.contains("nav-bottom-btn") || el.classList.contains("nav-drawer-btn"))) {
      el.classList.add("active");
    }
    // Also sync the other navs by matching onclick
    const sel = `[onclick*="showView('${id}'"]`;
    document.querySelectorAll(`.nav-btn${sel}, .nav-bottom-btn${sel}, .nav-drawer-btn${sel}`).forEach((b) => b.classList.add("active"));
    if (options.persist !== false) updateViewUrl(id);
    if (id === "docs") {
      renderDocsView();
    }
    if (id === "exercises" && typeof M.renderExerciseAdminView === "function") {
      M.renderExerciseAdminView();
    }
    if (id === "settings") {
      M.renderSettingsView();
    }
  };

  M.toggleDrawer = function toggleDrawer() {
    const drawer = document.getElementById("navDrawer");
    const overlay = document.querySelector(".nav-drawer-overlay");
    const hamburger = document.getElementById("navHamburger");
    if (!drawer) return;
    const isOpen = drawer.classList.toggle("open");
    if (overlay) overlay.classList.toggle("open", isOpen);
    if (hamburger) {
      hamburger.classList.toggle("open", isOpen);
      hamburger.setAttribute("aria-expanded", isOpen);
    }
    document.body.classList.toggle("drawer-open", isOpen);
  };

  window.toggleDrawer = M.toggleDrawer;
}());
