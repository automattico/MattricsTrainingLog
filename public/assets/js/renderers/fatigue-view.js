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
    M.renderFatigueDocumentation();
  };

  M.renderFatigueDocumentation = function renderFatigueDocumentation() {
    const mount = document.getElementById("fatigueDocumentation");
    if (!mount) return;

    mount.innerHTML = `
      <details class="dev-doc">
        <summary>
          <span class="dev-doc-summary-kicker">Developer docs</span>
          <span class="dev-doc-summary-title">How the fatigue model works</span>
          <span class="dev-doc-summary-chevron">▾</span>
        </summary>
        <div class="dev-doc-body">
          <p class="dev-doc-intro">
            A decay-based load accumulation model. Every activity adds a per-muscle stimulus. That stimulus fades
            exponentially over time based on each muscle&rsquo;s half-life. The fatigue score is how much accumulated
            load remains relative to a calibrated &ldquo;fully loaded&rdquo; reference — the normalization load.
          </p>
          <div class="dev-doc-grid">

            <div class="dev-doc-section">
              <div class="dev-doc-heading">Fatigue score (0–100)</div>
              <div class="dev-doc-text">Looks back <strong>10 days</strong>. Each activity contributes a stimulus per muscle (0–1+ scale). Remaining load after decay is summed, then divided by a per-muscle normalization load to give a 0–100 score.</div>
              <pre class="dev-doc-formula">score    = rawLoad / normalizationLoad × 100
rawLoad  = Σ ( stimulus × 0.5^(hoursAgo / halfLife) )</pre>
              <table class="dev-doc-table">
                <tr><td>Normalization load</td><td>Per-muscle &ldquo;fully loaded&rdquo; reference — e.g. upper back 2.45, quads 2.25, biceps 1.55, obliques 1.45</td></tr>
                <tr><td>Score cap</td><td>100 — cannot exceed regardless of load volume</td></tr>
                <tr><td>Lookback window</td><td>10 days rolling from now</td></tr>
              </table>
            </div>

            <div class="dev-doc-section">
              <div class="dev-doc-heading">Decay &amp; half-lives</div>
              <div class="dev-doc-text">Each muscle has its own half-life. After one half-life, 50% of the stimulus remains; after two, 25%. All activity timestamps are normalised to <strong>noon on their date</strong>, so sub-day precision is not available.</div>
              <pre class="dev-doc-formula">remaining = stimulus × 0.5^(hoursElapsed / halfLife)</pre>
              <table class="dev-doc-table">
                <tr><td>72 h</td><td>Chest, upper back, lower back, quads, hamstrings, glutes, adductors</td></tr>
                <tr><td>60 h</td><td>Deltoids, trapezius, calves</td></tr>
                <tr><td>48 h</td><td>Triceps, biceps, abs, obliques</td></tr>
              </table>
            </div>

            <div class="dev-doc-section">
              <div class="dev-doc-heading">Hevy workouts — set load</div>
              <div class="dev-doc-text">Triggered when the description starts with <code class="dev-doc-code">Logged with Hevy</code>. Each exercise block is name-matched against 12 pattern groups. Load is calculated from actual sets.</div>
              <pre class="dev-doc-formula">load         = weight(kg) × reps × effortFactor
effortFactor = 0.5 + RPE/10
               default RPE = 7  →  effortFactor = 1.20
bodyweight   = 75 kg × 0.4 when no weight is logged
scaledLoad   = load / 1500  (unit divisor)</pre>
              <div class="dev-doc-text" style="margin-top:4px">RPE range: 6 → 1.10 &nbsp;|&nbsp; 7 → 1.20 &nbsp;|&nbsp; 8 → 1.30 &nbsp;|&nbsp; 9 → 1.40 &nbsp;|&nbsp; 10 → 1.50. Time-based sets (e.g. &ldquo;3 min&rdquo;) are skipped — no load calculated.</div>
            </div>

            <div class="dev-doc-section">
              <div class="dev-doc-heading">Hevy workouts — exercise patterns</div>
              <div class="dev-doc-text">Name is lowercased and matched by substring. First match wins. 12 groups:</div>
              <table class="dev-doc-table">
                <tr><td>bench / push-up / chest fly / dip / pec deck</td><td>Chest primary</td></tr>
                <tr><td>incline press / chest press</td><td>Chest (incline)</td></tr>
                <tr><td>row / face pull</td><td>Upper back primary</td></tr>
                <tr><td>pulldown / pull-up / chin-up</td><td>Upper back (width)</td></tr>
                <tr><td>deadlift / RDL / romanian deadlift</td><td>Hamstrings + glutes</td></tr>
                <tr><td>shoulder press / OHP / lateral raise / rear delt</td><td>Deltoids primary</td></tr>
                <tr><td>curl / hammer</td><td>Biceps primary</td></tr>
                <tr><td>triceps / pushdown / skull crusher</td><td>Triceps primary</td></tr>
                <tr><td>plank / crunch / twist / dead bug / sit-up / leg raise</td><td>Abs primary</td></tr>
                <tr><td>hip thrust / glute bridge</td><td>Gluteal primary</td></tr>
                <tr><td>calf raise</td><td>Calves primary</td></tr>
                <tr><td>squat / lunge / step-up / leg press / split squat</td><td>Quadriceps primary</td></tr>
              </table>
            </div>

            <div class="dev-doc-section">
              <div class="dev-doc-heading">Unknown exercises</div>
              <div class="dev-doc-text">If an exercise name doesn&rsquo;t match any of the 12 patterns, it contributes <strong>zero</strong> muscle-specific stimulus for that exercise. If <em>no</em> exercise in the session matches, the whole session falls back to the generic <code class="dev-doc-code">WeightTraining</code> type mapping — a light, even spread across all muscles.</div>
              <div class="dev-doc-text" style="margin-top:6px">To model a new exercise: add its name as a substring pattern to the relevant group in <code class="dev-doc-code">getExerciseMuscleMapping()</code> in <code class="dev-doc-code">core/hevy-parser.js</code>.</div>
            </div>

            <div class="dev-doc-section">
              <div class="dev-doc-heading">Activity types (non-Hevy)</div>
              <div class="dev-doc-text">If not a Hevy workout, the activity <strong>Type</strong> field maps to hardcoded per-muscle weights. Duration scales the stimulus.</div>
              <pre class="dev-doc-formula">sportFactor    = clamp(min/60,  0.70, 1.45)  caps at 87 min+
strengthFactor = clamp(min/45,  0.85, 1.60)  caps at 72 min+
stimulus = baseWeights × factor</pre>
              <table class="dev-doc-table">
                <tr><td>Run</td><td>Quads, hamstrings, calves, glutes</td></tr>
                <tr><td>Hike</td><td>Quads, hamstrings, calves, glutes (heavier than run)</td></tr>
                <tr><td>Ride</td><td>Quads dominant, glutes, hamstrings</td></tr>
                <tr><td>Canoeing / Canoe</td><td>Upper back, deltoids, trapezius, biceps, abs</td></tr>
                <tr><td>WaterSport</td><td>Same as canoeing, slightly less intense</td></tr>
                <tr><td>Rowing</td><td>Full body — upper/lower back, quads, hamstrings, biceps</td></tr>
                <tr><td>Surfing</td><td>Upper back + deltoids (paddle), abs + obliques (pop-up)</td></tr>
                <tr><td>Yoga</td><td>Abs, obliques, light stabilisers</td></tr>
                <tr><td>Walk</td><td>Light quads, hamstrings, calves</td></tr>
                <tr><td>WeightTraining / Workout</td><td>Generic full-body fallback (all muscles, light)</td></tr>
                <tr><td>Unrecognised type</td><td>Zero stimulus — not modelled at all</td></tr>
              </table>
            </div>

            <div class="dev-doc-section">
              <div class="dev-doc-heading">When to train again</div>
              <div class="dev-doc-text">Recovery is considered complete when <code class="dev-doc-code">rawLoad &le; threshold</code>. Below that, the muscle is <strong>Fresh</strong> and shows &ldquo;Train now.&rdquo; Above it, recovery hours are calculated and the muscle is placed in &ldquo;Train tomorrow&rdquo; or &ldquo;Needs more recovery.&rdquo;</div>
              <pre class="dev-doc-formula">threshold     = normalizationLoad × 0.25
recoveryHours = halfLife × log₂(rawLoad / threshold)
                (only when rawLoad &gt; threshold)</pre>
              <table class="dev-doc-table">
                <tr><td>Threshold ratio</td><td>0.25 — aligns with the Fresh tier boundary (&lt;25%)</td></tr>
                <tr><td>&ldquo;Train now&rdquo;</td><td>fatigueScore &lt; 25% (Fresh) only</td></tr>
                <tr><td>&ldquo;Train tomorrow&rdquo;</td><td>Recovering — recovery completes within ~24 h</td></tr>
                <tr><td>&ldquo;Needs more recovery&rdquo;</td><td>Fatigued or Highly fatigued — days away</td></tr>
              </table>
            </div>

            <div class="dev-doc-section">
              <div class="dev-doc-heading">Fatigue tiers</div>
              <div class="dev-doc-tiers">
                <div class="dev-doc-tier">
                  <span class="dev-doc-tier-dot" style="background:var(--fatigue-color-none)"></span>
                  <span class="dev-doc-tier-label">None (untrained)</span>
                  <span class="dev-doc-tier-desc">No load recorded in window</span>
                </div>
                <div class="dev-doc-tier">
                  <span class="dev-doc-tier-dot" style="background:var(--fatigue-color-fresh)"></span>
                  <span class="dev-doc-tier-label">Fresh (0–24%)</span>
                  <span class="dev-doc-tier-desc">Ready — train freely</span>
                </div>
                <div class="dev-doc-tier">
                  <span class="dev-doc-tier-dot" style="background:var(--fatigue-color-recovering)"></span>
                  <span class="dev-doc-tier-label">Recovering (25–49%)</span>
                  <span class="dev-doc-tier-desc">Fatigue present — needs hours to a day</span>
                </div>
                <div class="dev-doc-tier">
                  <span class="dev-doc-tier-dot" style="background:var(--fatigue-color-fatigued)"></span>
                  <span class="dev-doc-tier-label">Fatigued (50–74%)</span>
                  <span class="dev-doc-tier-desc">Significant load — wait 1–3 days</span>
                </div>
                <div class="dev-doc-tier">
                  <span class="dev-doc-tier-dot" style="background:var(--fatigue-color-high)"></span>
                  <span class="dev-doc-tier-label">Highly fatigued (75–100%)</span>
                  <span class="dev-doc-tier-desc">Heavy load — rest 3–5 days</span>
                </div>
              </div>
            </div>

          </div>
        </div>
      </details>
    `;
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
