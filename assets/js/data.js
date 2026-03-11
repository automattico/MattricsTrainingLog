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

  M.showSetupHelp = function showSetupHelp() {
    document.getElementById("errorMsg").textContent =
      "SETUP STEPS:\n\n1. Open your Google Sheet\n2. Extensions -> Apps Script\n3. Paste the contents of Code.gs\n4. Deploy -> New Deployment -> Web App\n5. Execute as: Me | Access: Anyone\n6. Add MATTRICS_SHARED_SECRET in Script Properties\n7. Copy config.example.js to config.js\n8. Set MATTRICS_CONFIG.SHEET_URL and MATTRICS_CONFIG.SHEET_TOKEN in config.js";
  };

  M.fetchData = async function fetchData() {
    M.showLoading();
    if (!M.SHEET_URL || M.SHEET_URL === "PASTE_YOUR_WEB_APP_URL_HERE" || M.SHEET_URL === "YOUR_GOOGLE_APPS_SCRIPT_URL_HERE") {
      M.showError(
        "No Sheet URL configured.\n\nCreate config.js next to dashboard.html by copying config.example.js, then set MATTRICS_CONFIG.SHEET_URL and MATTRICS_CONFIG.SHEET_TOKEN.\n\nSee README.md and apps-script/Code.gs for setup instructions."
      );
      return;
    }

    try {
      const url = new URL(M.SHEET_URL);
      if (M.SHEET_TOKEN) {
        url.searchParams.set("key", M.SHEET_TOKEN);
      }

      const res = await fetch(url.toString(), { redirect: "follow" });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const text = await res.text();
      let json;
      try {
        json = JSON.parse(text);
      } catch {
        throw new Error("Response was not valid JSON. Check your Apps Script doGet().");
      }
      if (json.error) throw new Error(json.error);

      M.state.allData = json.rows
        .filter((row) => row.Date && row.Type)
        .sort((a, b) => new Date(b.Date) - new Date(a.Date));
      M.state.typeFilter = "All";
      M.showApp();
      M.renderAll();
    } catch (error) {
      const msg = String(error && error.message ? error.message : error);
      const isCors = msg === "Failed to fetch" ||
        msg === "Load failed" ||
        msg.includes("NetworkError") ||
        msg.includes("CORS") ||
        msg.includes("fetch");

      M.showError(
        isCors
          ? "Browser blocked the request.\n\nYour Apps Script endpoint is live, but some browsers block fetches from a local file. Try opening this in Chrome, or serve this folder on localhost instead of opening dashboard.html via file://.\n\nIf needed, redeploy Apps Script as a Web App with Execute as: Me and Access: Anyone."
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
    M.renderHeader(windowed);
    M.renderFilters(windowed);
    M.renderFeed(windowed);
    M.renderAiPreview();
  };

  M.renderContextBar = function renderContextBar(data) {
    document.querySelectorAll(".window-context").forEach((node) => {
      node.textContent = "";
    });

    const activeContext = document.querySelector(".window-option.active .window-context");
    if (!activeContext) return;

    if (!data.length) {
      activeContext.textContent = "No activities in this range.";
      return;
    }

    const newest = data[0].Date;
    const oldest = data[data.length - 1].Date;
    activeContext.textContent = M.formatContextRange(oldest, newest);
  };

  M.renderHeader = function renderHeader(data) {
    const summary = M.getOverviewMetrics(data);
    const recentItems = data.slice(0, 5);
    const sessionsPerDay = summary.activeDays ? (summary.totalSessions / summary.activeDays).toFixed(1) : "0.0";
    const donutStops = [];
    let offset = 0;
    summary.mix.segments.forEach((segment) => {
      const next = offset + (segment.pct * 100);
      donutStops.push(`${segment.color} ${offset.toFixed(1)}% ${next.toFixed(1)}%`);
      offset = next;
    });
    const donutStyle = summary.mix.segments.length
      ? `conic-gradient(${donutStops.join(", ")})`
      : "conic-gradient(rgba(255,255,255,0.08) 0 100%)";
    const dominant = summary.mix.dominant;
    const bestMonth = summary.bestMonth
      ? new Date(`${summary.bestMonth[0]}-01`).toLocaleDateString("en-GB", { month: "short", year: "numeric" })
      : "";
    const recentDate = summary.lastDate ? M.fmtDate(summary.lastDate) : "";

    document.getElementById("dashboardOverview").innerHTML = `
      <article class="overview-card overview-kpi overview-sessions">
        <div class="overview-label">Sessions</div>
        <div class="overview-value">${summary.totalSessions}</div>
        <div class="overview-meta">${summary.activeDays} active day${summary.activeDays === 1 ? "" : "s"} · ${sessionsPerDay}/day</div>
        <div class="overview-foot">${summary.totalSessions ? `${summary.maxStreak} day best streak` : "No activity yet"}</div>
      </article>
      <article class="overview-card overview-kpi overview-time">
        <div class="overview-label">Time</div>
        <div class="overview-value">${summary.totalMin ? M.fmt(summary.totalMin) : "0h"}</div>
        <div class="overview-meta">${summary.avgSessionMin ? `${M.fmt(summary.avgSessionMin)} avg session` : "No duration logged"}</div>
        <div class="overview-foot">${summary.avgActiveDayMin ? `${M.fmt(summary.avgActiveDayMin)} per active day` : ""}</div>
      </article>
      <article class="overview-card overview-chart">
        <div class="overview-label">Activity mix</div>
        ${summary.totalSessions ? `
          <div class="overview-chart-shell">
            <div class="overview-donut" style="--donut-fill:${donutStyle}">
              <div class="overview-donut-center">
                <div class="overview-donut-kicker">${dominant ? dominant.label : "No mix"}</div>
                <div class="overview-donut-value">${dominant ? dominant.percentLabel : "0%"}</div>
              </div>
            </div>
            <div class="overview-legend">
              ${summary.mix.segments.map((segment) => `
                <div class="overview-legend-item">
                  <div class="overview-legend-main">
                    <span class="overview-legend-dot" style="--legend-color:${segment.color}"></span>
                    <span>${segment.icon} ${segment.label}</span>
                  </div>
                  <div class="overview-legend-meta">${segment.count} · ${segment.percentLabel}</div>
                </div>
              `).join("")}
            </div>
          </div>
        ` : `<div class="overview-empty">No activities in this window yet.</div>`}
      </article>
      <article class="overview-card overview-insight overview-balance">
        <div class="overview-label">Training balance</div>
        <div class="overview-insight-title">${summary.balance.summary}</div>
        <div class="overview-meta">${summary.balance.detail}</div>
        <div class="overview-chip-row">
          ${summary.balance.buckets.slice(0, 3).map((bucket) => `
            <span class="overview-chip" style="--chip-color:${bucket.color}">${bucket.label} ${bucket.count}</span>
          `).join("")}
        </div>
        <div class="overview-foot">${summary.distanceSummary || ""}</div>
      </article>
      <article class="overview-card overview-insight overview-momentum">
        <div class="overview-label">Momentum</div>
        <div class="overview-insight-title">${summary.maxStreak || 0} day${summary.maxStreak === 1 ? "" : "s"} in a row</div>
        <div class="overview-meta">${summary.activeDays} active day${summary.activeDays === 1 ? "" : "s"}${recentDate ? ` · latest ${recentDate}` : ""}</div>
        <div class="overview-chip-row">
          ${bestMonth ? `<span class="overview-chip">${bestMonth} peak</span>` : ""}
          ${dominant ? `<span class="overview-chip">${dominant.label} leads</span>` : ""}
        </div>
        <div class="overview-foot">${bestMonth ? `${summary.bestMonth[1]} sessions in peak month` : ""}</div>
      </article>
      <article class="overview-card overview-insight overview-recent">
        <div class="overview-label">Recent sessions</div>
        ${recentItems.length ? `
          <div class="overview-recent-list">
            ${recentItems.map((activity) => {
              const cfg = M.tc(activity.Type);
              const primary = (M.cardMetrics(activity)[0] || {}).val || "";
              const activityId = M.escAttr(activity["Activity ID raw"] || activity["Activity ID"] || activity.Name || "");
              return `<button class="overview-recent-link" onclick="openDetail('${activityId}')" aria-label="Open details for ${M.escAttr(activity.Name || cfg.label)}">
                <span class="overview-recent-main">
                  <span class="overview-recent-icon" aria-hidden="true">${cfg.icon}</span>
                  <span class="overview-recent-name">${M.esc(activity.Name || cfg.label)}</span>
                </span>
                <span class="overview-recent-meta">${M.fmtDate(activity.Date)}${primary ? ` · ${primary}` : ""}</span>
              </button>`;
            }).join("")}
          </div>
        ` : `<div class="overview-empty overview-empty-compact">No sessions in this window yet.</div>`}
      </article>
    `;
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
  };
}());
