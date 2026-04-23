(function () {
  const M = window.Mattrics;

  M.renderActivityCards = function renderActivityCards(targetId, activities, options = {}) {
    const target = document.getElementById(targetId);
    if (!target) return;

    const { compact = false } = options;
    if (!activities.length) {
      target.innerHTML =
        '<div class="empty-window"><div class="empty-window-icon">🏖️</div>No activities in this window.<br>Try a wider time range.</div>';
      return;
    }

    target.innerHTML = activities.map((activity) => {
      const cfg = M.tc(activity.Type);
      const metrics = M.cardMetrics(activity);
      const primary = metrics[0] || null;
      const secondary = metrics.slice(1, compact ? 3 : 4);
      const desc = (activity.Description || "").trim();
      const cleanDesc = desc.startsWith("Logged with Hevy") ? "" : (desc.length > (compact ? 120 : 240) ? `${desc.slice(0, compact ? 120 : 240)}…` : desc);
      const activityId = M.escAttr(M.getActivityId(activity));

      return `<article class="a-card ${compact ? "a-card-compact" : ""}" style="--card-accent:${cfg.color}">
        <button class="a-card-body a-card-btn" type="button" data-activity-id="${activityId}" aria-label="Open details for ${M.escAttr(activity.Name || cfg.label)}">
          <div class="a-card-top">
            <div class="a-card-main">
              <div class="a-card-type">
                <span class="a-card-type-icon" aria-hidden="true">${cfg.icon}</span>
                <span>${M.esc(cfg.label)}</span>
                <span class="a-card-type-sep">·</span>
                <span>${M.fmtDate(activity.Date)}</span>
              </div>
              <div class="a-card-name">${M.esc(activity.Name || cfg.label)}</div>
            </div>
            ${metrics.length ? `<div class="a-card-metrics">
              ${primary ? `<div class="metric primary">
                <div class="metric-val" style="color:${primary.color}">${primary.val}</div>
                <div class="metric-lab">${primary.lab}</div>
              </div>` : ""}
              ${secondary.map((metric) => `
              <div class="metric">
                <div class="metric-val" style="color:${metric.color}">${metric.val}</div>
                <div class="metric-lab">${metric.lab}</div></div>`).join("")}
            </div>` : ""}
          </div>
          ${cleanDesc ? `<div class="a-card-desc">${M.esc(cleanDesc)}</div>` : ""}
        </button>
      </article>`;
    }).join("");
  };
  M.renderFeed = function renderFeed(data) {
    const activities = data || M.applyTypeFilter(M.state.allData);
    if (M.state.feedMode === "grouped") {
      M.renderTimeline(activities, "cardList");
      return;
    }

    M.renderActivityCards("cardList", activities);
  };
}());
