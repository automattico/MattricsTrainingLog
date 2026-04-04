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

  M.renderFatigueBodyFigure = function renderFatigueBodyFigure(fatigue, view) {
    const bodyMap = M.MUSCLE_FATIGUE_BODY_MAP || {};
    const config = bodyMap[view];
    if (!config) return "";

    const palette = {
      none: "var(--fatigue-color-none)",
      fresh: "var(--fatigue-color-fresh)",
      recovering: "var(--fatigue-color-recovering)",
      fatigued: "var(--fatigue-color-fatigued)",
      high: "var(--fatigue-color-high)",
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
      const tooltipLabel = region ? region.label : part.slug;
      const tooltipStatus = region ? M.getFatigueDisplayTier(region) : "No recent load";
      const tooltipWhen = region ? M.getFatigueReadinessToken(region) : "Now";
      const tooltipPercent = region ? `${Math.max(0, Math.min(100, Math.round(region.fatigueScore || 0)))}%` : "0%";

      return `<g
        class="fatigue-body-region"
        data-fatigue-state="${state}"
        data-region="${M.escAttr(key || part.slug)}"
        data-slug="${M.escAttr(part.slug)}"
        data-muscle-label="${M.escAttr(tooltipLabel)}"
        data-muscle-status="${M.escAttr(tooltipStatus)}"
        data-muscle-when="${M.escAttr(tooltipWhen)}"
        data-muscle-percent="${M.escAttr(tooltipPercent)}"
        data-muscle-summary="${M.escAttr(region ? region.recoveryLabel : "no recent load recorded")}"
        style="--fatigue-fill:${palette[state]}; --fatigue-opacity:${opacity}">
        ${part.pathArray.map((path) => `<path d="${M.escAttr(path)}"></path>`).join("")}
      </g>`;
    }).join("");

    return `<div class="overview-body-figure overview-body-figure-${M.escAttr(view)}">
      <svg class="overview-body-svg" viewBox="${config.viewBox}" role="img" aria-label="${config.label} muscle fatigue map" preserveAspectRatio="xMidYMin meet">
        <path class="fatigue-body-outline" d="${M.escAttr(config.outlinePath || "")}"></path>
        ${partMarkup}
      </svg>
      <div class="overview-body-caption">${config.label}</div>
    </div>`;
  };

  M.renderFatigueLegendPanel = function renderFatigueLegendPanel() {
    const items = [
      { state: "high", label: "Highly fatigued", detail: M.getFatigueTierMeaning("Highly fatigued") },
      { state: "fatigued", label: "Fatigued", detail: M.getFatigueTierMeaning("Fatigued") },
      { state: "recovering", label: "Recovering", detail: M.getFatigueTierMeaning("Recovering") },
      { state: "fresh", label: "Fresh", detail: M.getFatigueTierMeaning("Fresh") },
      { state: "none", label: "No recent load", detail: M.getFatigueTierMeaning("No recent load") },
    ];

    return `<div class="overview-fatigue-legend">
      <div class="overview-fatigue-legend-head">
        <div class="overview-fatigue-legend-title">Recovery scale</div>
        <div class="overview-fatigue-legend-meta">Trainability grouped from now to later</div>
      </div>
      <div class="overview-fatigue-legend-list">
        ${items.map((item) => `<div class="overview-fatigue-legend-item" data-fatigue-state="${item.state}">
          <span class="overview-fatigue-swatch"></span>
          <div class="overview-fatigue-legend-copy">
            <div class="overview-fatigue-scale-label">${item.label}</div>
            <div class="overview-fatigue-scale-detail">${item.detail}</div>
          </div>
        </div>`).join("")}
      </div>
    </div>`;
  };

  M.getFatigueReadinessBucket = function getFatigueReadinessBucket(region) {
    if (!region || !region.recoveryHours) return "today";
    if (region.recoveryHours <= 24) return "tomorrow";
    return "later";
  };

  M.getFatigueReadinessToken = function getFatigueReadinessToken(region) {
    const hours = Math.max(0, Math.ceil((region && region.recoveryHours) || 0));
    if (!hours) return "Now";
    if (hours < 24) return `+${hours}h`;
    if (hours === 24) return "Tomorrow";
    const days = Math.ceil(hours / 24);
    return `${days}d`;
  };

  M.getFatiguePercentLabel = function getFatiguePercentLabel(region) {
    const pct = Math.max(0, Math.min(100, Math.round((region && region.fatigueScore) || 0)));
    return `${pct}%`;
  };

  M.getMuscleContextLabel = function getMuscleContextLabel(region) {
    const views = Array.isArray(region && region.views) ? region.views : [];
    if (views.includes("front") && views.includes("back")) return "front + back";
    if (views.includes("front")) return "front";
    if (views.includes("back")) return "back";
    return "";
  };

  M.renderFatigueReadinessTables = function renderFatigueReadinessTables(fatigue) {
    const regionsByKey = Object.fromEntries(fatigue.regions.map((region) => [region.key, region]));
    const orderedKeys = fatigue.regions
      .slice()
      .sort((a, b) => a.recoveryHours - b.recoveryHours || a.fatigueScore - b.fatigueScore || a.order - b.order || a.label.localeCompare(b.label));

    const groups = {
      today: {
        title: "Train today",
        subtitle: "Ready now or no meaningful recent load",
        items: [],
      },
      tomorrow: {
        title: "Train tomorrow",
        subtitle: "Close to ready, but still recovering",
        items: [],
      },
      later: {
        title: "Needs more recovery",
        subtitle: "Best left for a later session",
        items: [],
      },
    };

    orderedKeys.forEach((region) => {
      const bucket = M.getFatigueReadinessBucket(region);
      if (!groups[bucket]) return;
      groups[bucket].items.push(region);
    });

    const renderRow = (region) => {
      const state = M.getFatigueVisualState(region);
      const tier = M.getFatigueDisplayTier(region);
      return `<tr data-fatigue-state="${state}">
        <td class="overview-fatigue-table-muscle">
          <div class="overview-fatigue-row-name">${region.label}</div>
        </td>
        <td class="overview-fatigue-table-status">
          <span class="overview-fatigue-table-status-inner">
            <span class="overview-fatigue-swatch"></span>
            <span class="overview-fatigue-status-label">${tier} <span class="overview-fatigue-status-percent">(${M.getFatiguePercentLabel(region)})</span></span>
          </span>
        </td>
        <td class="overview-fatigue-table-token">
          <div class="overview-fatigue-token">${M.getFatigueReadinessToken(region)}</div>
        </td>
      </tr>`;
    };

    return `<div class="overview-fatigue-board">
      ${Object.values(groups).map((group) => `<section class="overview-fatigue-column">
        <div class="overview-fatigue-table-group-head">
          <div>
            <div class="overview-fatigue-column-title">${group.title}</div>
            <div class="overview-fatigue-column-subtitle">${group.subtitle}</div>
          </div>
        </div>
        ${group.items.length ? `
          <table class="overview-fatigue-table">
            <thead>
              <tr>
                <th scope="col">Muscle</th>
                <th scope="col">Status</th>
                <th scope="col">When</th>
              </tr>
            </thead>
            <tbody>
              ${group.items.map(renderRow).join("")}
            </tbody>
          </table>
        ` : `<div class="overview-fatigue-empty">No muscles in this readiness window.</div>`}
      </section>`).join("")}
    </div>`;
  };

  M.renderDashboard = function renderDashboard(data) {
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

  M.renderFatigueView = function renderFatigueView(data) {
    const summary = M.getOverviewMetrics(data);
    const mount = document.getElementById("fatigueOverview");
    if (!mount) return;

    mount.innerHTML = `
      <div class="overview-insight overview-balance overview-balance-page">
        <div class="overview-label">Muscle fatigue map</div>
        <div class="overview-insight-title">Muscle Fatigue Map</div>
        <div class="overview-meta">Current recovery state based on recent training. Read the map, then scan what can be trained now or soon.</div>
        <div class="overview-heatmap-shell">
          <div class="overview-fatigue-top">
            ${M.renderFatigueBodyFigure(summary.fatigue, "front")}
            ${M.renderFatigueLegendPanel()}
            ${M.renderFatigueBodyFigure(summary.fatigue, "back")}
          </div>
          ${M.renderFatigueReadinessTables(summary.fatigue)}
        </div>
        <div class="overview-fatigue-tooltip" id="fatigueTooltip" aria-hidden="true" hidden>
          <div class="overview-fatigue-tooltip-title" id="fatigueTooltipTitle"></div>
          <div class="overview-fatigue-tooltip-status" id="fatigueTooltipStatus"></div>
          <div class="overview-fatigue-tooltip-when" id="fatigueTooltipWhen"></div>
          <div class="overview-fatigue-tooltip-summary" id="fatigueTooltipSummary"></div>
        </div>
      </div>
    `;

    M.bindFatigueHoverCard();
  };

  M.bindFatigueHoverCard = function bindFatigueHoverCard() {
    const mount = document.getElementById("fatigueOverview");
    if (!mount || mount.dataset.tooltipBound === "1") return;
    mount.dataset.tooltipBound = "1";

    const getTooltip = () => document.getElementById("fatigueTooltip");
    const getTooltipField = (id) => document.getElementById(id);

    const showTooltip = (regionEl) => {
      if (!regionEl) return;
      const tooltip = getTooltip();
      const title = getTooltipField("fatigueTooltipTitle");
      const status = getTooltipField("fatigueTooltipStatus");
      const when = getTooltipField("fatigueTooltipWhen");
      const summary = getTooltipField("fatigueTooltipSummary");
      if (!tooltip || !title || !status || !when || !summary) return;

      if (!title || !status || !when || !summary) return;

      title.textContent = regionEl.dataset.muscleLabel || "";
      status.textContent = `${regionEl.dataset.muscleStatus || ""} (${regionEl.dataset.musclePercent || "0%"})`;
      when.textContent = regionEl.dataset.muscleWhen || "";
      summary.textContent = regionEl.dataset.muscleSummary || "";
      tooltip.hidden = false;
      tooltip.setAttribute("aria-hidden", "false");
      tooltip.dataset.state = regionEl.dataset.fatigueState || "none";
      const rect = regionEl.getBoundingClientRect();
      const mountRect = mount.getBoundingClientRect();
      const x = Math.max(12, Math.min(rect.left - mountRect.left + rect.width + 14, mountRect.width - 236));
      const y = Math.max(12, Math.min(rect.top - mountRect.top + rect.height * 0.15, mountRect.height - 120));
      tooltip.style.transform = `translate(${Math.round(x)}px, ${Math.round(y)}px)`;
    };

    const hideTooltip = () => {
      const tooltip = getTooltip();
      if (!tooltip) return;
      tooltip.hidden = true;
      tooltip.setAttribute("aria-hidden", "true");
    };

    mount.addEventListener("pointerover", (event) => {
      const region = event.target.closest(".fatigue-body-region");
      if (!region) return;
      if (event.pointerType === "touch") return;
      showTooltip(region);
    });

    mount.addEventListener("pointerout", (event) => {
      const from = event.target.closest(".fatigue-body-region");
      if (!from) return;
      const to = event.relatedTarget && event.relatedTarget.closest ? event.relatedTarget.closest(".fatigue-body-region") : null;
      if (to === from) return;
      hideTooltip();
    });

    mount.addEventListener("focusin", (event) => {
      const region = event.target.closest(".fatigue-body-region");
      if (region) showTooltip(region);
    });

    mount.addEventListener("focusout", (event) => {
      const region = event.target.closest(".fatigue-body-region");
      if (!region) return;
      if (event.relatedTarget && event.relatedTarget.closest && event.relatedTarget.closest(".fatigue-body-region")) return;
      hideTooltip();
    });

    mount.addEventListener("click", (event) => {
      const region = event.target.closest(".fatigue-body-region");
      if (!region) return;
      event.preventDefault();
      const tooltip = getTooltip();
      if (!tooltip) return;
      const sameRegion = tooltip.dataset.activeRegion === region.dataset.region;
      if (sameRegion && !tooltip.hidden) {
        hideTooltip();
        tooltip.dataset.activeRegion = "";
        return;
      }
      tooltip.dataset.activeRegion = region.dataset.region || "";
      showTooltip(region);
    });

    document.addEventListener("click", (event) => {
      if (!mount.contains(event.target)) {
        hideTooltip();
        const tooltip = getTooltip();
        if (tooltip) tooltip.dataset.activeRegion = "";
      }
    });
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
