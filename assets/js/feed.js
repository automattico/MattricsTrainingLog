(function () {
  const M = window.Mattrics;

  M.renderFeed = function renderFeed(data) {
    const activities = data || M.applyTypeFilter(M.getWindowedData());
    if (!activities.length) {
      document.getElementById("cardList").innerHTML =
        '<div class="empty-window"><div class="empty-window-icon">🏖️</div>No activities in this window.<br>Try a wider time range.</div>';
      return;
    }

    document.getElementById("cardList").innerHTML = activities.map((activity) => {
      const cfg = M.tc(activity.Type);
      const metrics = M.cardMetrics(activity);
      const desc = (activity.Description || "").trim();
      const cleanDesc = desc.startsWith("Logged with Hevy") ? "" : (desc.length > 240 ? `${desc.slice(0, 240)}…` : desc);
      const activityId = M.escAttr(activity["Activity ID raw"] || activity["Activity ID"] || activity.Name || "");

      return `<div class="a-card" style="border-left-color:${cfg.color}">
        <div class="a-card-stripe" style="background:${cfg.color}"></div>
        <button class="a-card-body a-card-btn" onclick="openDetail('${activityId}')" aria-label="Open details for ${M.escAttr(activity.Name || cfg.label)}">
          <div class="a-card-top">
            <div class="a-card-left">
              <div class="a-card-icon">${cfg.icon}</div>
              <div class="a-card-main">
                <div class="a-card-name ${activity.Type === "Yoga" ? "yoga-card" : ""}">${M.esc(activity.Name)}</div>
                <div class="a-card-date">${M.fmtDate(activity.Date)}</div>
              </div>
            </div>
            ${metrics.length ? `<div class="a-card-metrics">${metrics.map((metric) => `
              <div class="metric">
                <div class="metric-val" style="color:${metric.color}">${metric.val}</div>
                <div class="metric-lab">${metric.lab}</div>
              </div>`).join("")}</div>` : ""}
          </div>
          ${cleanDesc ? `<div class="a-card-desc">${M.esc(cleanDesc)}</div>` : ""}
        </button>
      </div>`;
    }).join("");
  };
}());
