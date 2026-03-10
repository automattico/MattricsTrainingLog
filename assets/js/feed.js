(function () {
  const M = window.Mattrics;

  M.renderFeed = function renderFeed(data) {
    const activities = data || M.applyTypeFilter(M.getWindowedData());
    if (M.state.feedMode === "grouped") {
      M.renderTimeline(activities, "cardList");
      return;
    }
    if (!activities.length) {
      document.getElementById("cardList").innerHTML =
        '<div class="empty-window"><div class="empty-window-icon">🏖️</div>No activities in this window.<br>Try a wider time range.</div>';
      return;
    }

    document.getElementById("cardList").innerHTML = activities.map((activity) => {
      const cfg = M.tc(activity.Type);
      const metrics = M.cardMetrics(activity);
      const primary = metrics[0] || null;
      const secondary = metrics.slice(1, 4);
      const desc = (activity.Description || "").trim();
      const cleanDesc = desc.startsWith("Logged with Hevy") ? "" : (desc.length > 240 ? `${desc.slice(0, 240)}…` : desc);
      const activityId = M.escAttr(activity["Activity ID raw"] || activity["Activity ID"] || activity.Name || "");

      return `<article class="a-card" style="--card-accent:${cfg.color}">
        <button class="a-card-body a-card-btn" onclick="openDetail('${activityId}')" aria-label="Open details for ${M.escAttr(activity.Name || cfg.label)}">
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
}());
