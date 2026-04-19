(function () {
  const M = window.Mattrics;

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
              const primaryMetric = M.cardMetrics(activity)[0] || null;
              const primary = primaryMetric
                ? `${primaryMetric.val}${primaryMetric.lab && primaryMetric.lab !== "time" ? ` ${primaryMetric.lab}` : ""}`
                : "";
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
}());
