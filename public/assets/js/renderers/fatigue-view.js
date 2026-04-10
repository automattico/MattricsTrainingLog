(function () {
  const M = window.Mattrics;

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
}());
