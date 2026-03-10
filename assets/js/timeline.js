(function () {
  const M = window.Mattrics;

  M.renderTimeline = function renderTimeline(data) {
    const activities = data || M.getWindowedData();
    const groups = {};

    activities.forEach((activity) => {
      const key = M.state.groupBy === "week" ? M.weekStart(activity.Date) : activity.Date.slice(0, 7);
      (groups[key] = groups[key] || []).push(activity);
    });

    if (!Object.keys(groups).length) {
      document.getElementById("tlContent").innerHTML =
        '<div class="empty-window"><div class="empty-window-icon">📭</div>No activities in this window.</div>';
      return;
    }

    document.getElementById("tlContent").innerHTML = Object.entries(groups)
      .sort((a, b) => b[0].localeCompare(a[0]))
      .map(([key, acts]) => {
        const label = M.state.groupBy === "week"
          ? M.formatWeekRange(key)
          : new Date(`${key}-01`).toLocaleDateString("en-GB", { month: "long", year: "numeric" });
        const km = acts.reduce((sum, activity) => sum + (parseFloat(activity["Distance (km)"]) || 0), 0);
        const min = acts.reduce((sum, activity) => sum + (parseFloat(activity["Duration (min)"]) || 0), 0);
        const icons = [...new Set(acts.map((activity) => M.tc(activity.Type).icon))].join(" ");
        const tiles = acts.map((activity) => {
          const cfg = M.tc(activity.Type);
          const km2 = parseFloat(activity["Distance (km)"]) || 0;
          const min2 = parseFloat(activity["Duration (min)"]) || 0;
          return `<div class="tl-mini" style="border-top-color:${cfg.color}">
            <div class="tl-mini-icon">${cfg.icon}</div>
            <div class="tl-mini-name">${M.esc(activity.Name.length > 28 ? `${activity.Name.slice(0, 28)}…` : activity.Name)}</div>
            <div class="tl-mini-stat" style="color:${cfg.color}">${km2 > 0 ? `${km2.toFixed(1)} km` : M.fmt(min2)}</div>
            <div class="tl-mini-date">${M.fmtShort(activity.Date)}</div>
          </div>`;
        }).join("");

        return `<div class="tl-period">
          <div class="tl-period-header">
            <div class="tl-period-title">${label} <span style="font-size:13px;opacity:0.45">${icons}</span></div>
            <div class="tl-period-meta">${acts.length} sessions${km > 0 ? ` · ${km.toFixed(0)} km` : ""} · ${M.fmt(min)}</div>
          </div>
          <div class="tl-grid">${tiles}</div>
        </div>`;
      }).join("");
  };

  M.setGroup = function setGroup(group, el) {
    M.state.groupBy = group;
    document.querySelectorAll(".tl-sw-btn").forEach((button) => button.classList.remove("active"));
    el.classList.add("active");
    M.renderTimeline();
  };
}());
