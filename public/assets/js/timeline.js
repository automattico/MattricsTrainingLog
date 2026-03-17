(function () {
  const M = window.Mattrics;

  M.renderTimeline = function renderTimeline(data, targetId = "tlContent") {
    const activities = data || M.getWindowedData();
    const groups = {};
    const periodDays = M.state.groupBy === "week" ? 7 : 30;

    activities.forEach((activity) => {
      const period = M.getRollingPeriod(activity.Date, periodDays);
      groups[period.key] = groups[period.key] || { acts: [], start: period.start, end: period.end };
      groups[period.key].acts.push(activity);
    });

    if (!Object.keys(groups).length) {
      document.getElementById(targetId).innerHTML =
        '<div class="empty-window"><div class="empty-window-icon">📭</div>No activities in this window.</div>';
      return;
    }

    document.getElementById(targetId).innerHTML = Object.entries(groups)
      .sort((a, b) => b[0].localeCompare(a[0]))
      .map(([key, group]) => {
        const { acts, start, end } = group;
        const label = M.formatContextRange(start, end);
        const km = acts.reduce((sum, activity) => sum + (parseFloat(activity["Distance (km)"]) || 0), 0);
        const min = acts.reduce((sum, activity) => sum + (parseFloat(activity["Duration (min)"]) || 0), 0);
        const icons = [...new Set(acts.map((activity) => M.tc(activity.Type).icon))].join(" ");
        const tiles = acts.map((activity) => {
          const cfg = M.tc(activity.Type);
          const km2 = parseFloat(activity["Distance (km)"]) || 0;
          const min2 = parseFloat(activity["Duration (min)"]) || 0;
          const activityId = M.escAttr(M.getActivityId(activity));
          return `<button class="tl-mini" type="button" data-activity-id="${activityId}" style="--card-accent:${cfg.color}" aria-label="Open details for ${M.escAttr(activity.Name || cfg.label)}">
            <div class="tl-mini-type">
              <span class="a-card-type-icon" aria-hidden="true">${cfg.icon}</span>
              <span>${M.esc(cfg.label)}</span>
            </div>
            <div class="tl-mini-name">${M.esc(activity.Name.length > 40 ? `${activity.Name.slice(0, 40)}…` : activity.Name)}</div>
            <div class="tl-mini-stat" style="color:${cfg.color}">${km2 > 0 ? `${km2.toFixed(1)} km` : M.fmt(min2)}</div>
            <div class="tl-mini-date">${M.fmtShort(activity.Date)}</div>
          </button>`;
        }).join("");

        return `<div class="tl-period">
          <div class="tl-period-header">
            <div class="tl-period-title">${label}<span class="tl-period-icons">${icons}</span></div>
            <div class="tl-period-meta">${acts.length} sessions${km > 0 ? ` · ${km.toFixed(0)} km` : ""} · ${M.fmt(min)}</div>
          </div>
          <div class="tl-grid">${tiles}</div>
        </div>`;
      }).join("");
  };
}());
