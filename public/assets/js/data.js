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

      const res = await fetch(sourceUrl, { redirect: "follow", credentials: "same-origin" });
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
    M.renderHeader(windowed);
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

  M.renderFatigueBodyFigure = function renderFatigueBodyFigure(fatigue, view) {
    const bodyMap = M.MUSCLE_FATIGUE_BODY_MAP || {};
    const config = bodyMap[view];
    if (!config) return "";

    const palette = {
      none: "var(--fatigue-none)",
      fresh: "var(--fatigue-fresh)",
      recovering: "var(--fatigue-recovering)",
      fatigued: "var(--fatigue-fatigued)",
      high: "var(--fatigue-high)",
    };
    const slugToKey = bodyMap.slugToKey || {};
    const regionsByKey = Object.fromEntries(fatigue.regions.map((region) => [region.key, region]));
    const partMarkup = config.parts.map((part) => {
      const key = slugToKey[part.slug];
      const region = regionsByKey[key];
      const state = M.getFatigueVisualState(region);
      const label = region
        ? `${region.label}: ${region.fatigueScore}/100, ${M.getFatigueDisplayTier(region)}, ${region.lastWorkedLabel}, ${region.recoveryLabel}`
        : part.slug;
      const opacity = region && region.rawLoad
        ? (0.56 + (region.fatigueScore / 100) * 0.36).toFixed(2)
        : "0.42";

      return `<g class="fatigue-body-region" data-fatigue-state="${state}" data-region="${M.escAttr(key || part.slug)}" data-slug="${M.escAttr(part.slug)}" style="--fatigue-fill:${palette[state]}; --fatigue-opacity:${opacity}">
        <title>${M.esc(label)}</title>
        ${part.pathArray.map((path) => `<path d="${M.escAttr(path)}"></path>`).join("")}
      </g>`;
    }).join("");

    return `<div class="overview-body-figure">
      <div class="overview-body-caption">${config.label}</div>
      <svg class="overview-body-svg" viewBox="${config.viewBox}" role="img" aria-label="${config.label} muscle fatigue map" preserveAspectRatio="xMidYMin meet">
        <path class="fatigue-body-outline" d="${M.escAttr(config.outlinePath || "")}"></path>
        ${partMarkup}
      </svg>
    </div>`;
  };

  M.renderFatigueStateLegend = function renderFatigueStateLegend() {
    const items = [
      { state: "high", label: "Highly fatigued", detail: M.getFatigueTierMeaning("Highly fatigued") },
      { state: "fatigued", label: "Fatigued", detail: M.getFatigueTierMeaning("Fatigued") },
      { state: "recovering", label: "Recovering", detail: M.getFatigueTierMeaning("Recovering") },
      { state: "fresh", label: "Fresh", detail: M.getFatigueTierMeaning("Fresh") },
      { state: "none", label: "No recent load", detail: M.getFatigueTierMeaning("No recent load") },
    ];

    return `<div class="overview-fatigue-scale">
      ${items.map((item) => `<div class="overview-fatigue-scale-item" data-fatigue-state="${item.state}">
        <span class="overview-fatigue-swatch"></span>
        <div class="overview-fatigue-scale-copy">
          <div class="overview-fatigue-scale-label">${item.label}</div>
          <div class="overview-fatigue-scale-detail">${item.detail}</div>
        </div>
      </div>`).join("")}
    </div>`;
  };

  M.renderFatigueTable = function renderFatigueTable(fatigue) {
    const regionsByKey = Object.fromEntries(fatigue.regions.map((region) => [region.key, region]));
    const columns = [
      {
        label: "Front",
        keys: ["deltoids", "chest", "biceps", "abs", "obliques", "adductors", "quadriceps", "calves"],
      },
      {
        label: "Back",
        keys: ["trapezius", "upperBack", "deltoids", "triceps", "lowerBack", "gluteal", "hamstrings", "calves"],
      },
    ];

    return `<div class="overview-fatigue-columns">
      ${columns.map((column) => `<section class="overview-fatigue-column">
        <div class="overview-fatigue-column-title">${column.label}</div>
        <div class="overview-fatigue-rows">
          ${column.keys.map((key) => {
            const region = regionsByKey[key];
            if (!region) return "";
            const state = M.getFatigueVisualState(region);
            const tier = M.getFatigueDisplayTier(region);
            return `<div class="overview-fatigue-row" data-fatigue-state="${state}">
              <div class="overview-fatigue-row-head">
                <div class="overview-fatigue-row-name">
                  <span class="overview-fatigue-swatch"></span>
                  <span>${region.label}</span>
                </div>
                <div class="overview-fatigue-row-tier">${tier}</div>
              </div>
              <div class="overview-fatigue-row-recovery">${region.recoveryLabel}</div>
            </div>`;
          }).join("")}
        </div>
      </section>`).join("")}
    </div>`;
  };

  M.renderHeader = function renderHeader(data) {
    const summary = M.getOverviewMetrics(data);
    const recentItems = data.slice(0, 5);
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

    document.getElementById("dashboardOverview").innerHTML = `
      <article class="overview-card overview-kpi overview-sessions">
        <div class="overview-label">Sessions</div>
        <div class="overview-value">${summary.totalSessions}</div>
        <div class="overview-meta">${summary.activeDays} active day${summary.activeDays === 1 ? "" : "s"} in this window</div>
        <div class="overview-foot">${summary.totalSessions ? `${summary.maxStreak} day best streak${summary.multiSessionDays ? ` · ${summary.multiSessionDays} double-session day${summary.multiSessionDays === 1 ? "" : "s"}` : ""}` : "No activity yet"}</div>
      </article>
      <article class="overview-card overview-kpi overview-time">
        <div class="overview-label">Time</div>
        <div class="overview-value">${summary.totalMin ? M.fmt(summary.totalMin) : "0h"}</div>
        <div class="overview-meta">${summary.avgSessionMin ? `${M.fmt(summary.avgSessionMin)} avg session` : "No duration logged"}</div>
        <div class="overview-foot">${summary.weeklyAverageMin ? `${M.fmt(summary.weeklyAverageMin)} per week · ${M.fmt(summary.longestSessionMin)} longest` : ""}</div>
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
        <div class="overview-label">Muscle fatigue map</div>
        <div class="overview-insight-title">Muscle Fatigue Map</div>
        <div class="overview-meta">Current recovery state based on recent training</div>
        <div class="overview-heatmap-shell">
          <div class="overview-body-grid">
            ${M.renderFatigueBodyFigure(summary.fatigue, "front")}
            ${M.renderFatigueBodyFigure(summary.fatigue, "back")}
          </div>
          ${M.renderFatigueStateLegend()}
          ${M.renderFatigueTable(summary.fatigue)}
        </div>
        <div class="overview-foot">${summary.fatigue.summary}. ${summary.fatigue.detail}</div>
      </article>
      <article class="overview-card overview-insight overview-recent">
        <div class="overview-label">Recent sessions</div>
        ${recentItems.length ? `
          <div class="overview-recent-list">
            ${recentItems.map((activity) => {
              const cfg = M.tc(activity.Type);
              const primary = (M.cardMetrics(activity)[0] || {}).val || "";
              const activityId = M.escAttr(M.getActivityId(activity));
              return `<button class="overview-recent-link" type="button" data-activity-id="${activityId}" aria-label="Open details for ${M.escAttr(activity.Name || cfg.label)}">
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
