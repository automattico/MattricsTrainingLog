(function () {
  const M = window.Mattrics;

  M.renderInsights = function renderInsights(data) {
    const activities = data || M.getWindowedData();
    if (!activities.length) {
      document.getElementById("insightsGrid").innerHTML =
        '<div class="empty-window" style="grid-column:1/-1"><div class="empty-window-icon">📊</div>No data in this window.</div>';
      return;
    }

    const counts = {};
    activities.forEach((activity) => {
      counts[activity.Type] = (counts[activity.Type] || 0) + 1;
    });
    const topTypes = Object.entries(counts).sort((a, b) => b[1] - a[1]).slice(0, 8);
    const maxC = topTypes[0][1];

    const canoes = activities.filter((activity) => activity.Type === "Canoeing" || activity.Type === "Canoe");
    const canoeKm = canoes.reduce((sum, activity) => sum + (parseFloat(activity["Distance (km)"]) || 0), 0);
    const runs = activities.filter((activity) => activity.Type === "Run");
    const runKm = runs.reduce((sum, activity) => sum + (parseFloat(activity["Distance (km)"]) || 0), 0);
    const hikes = activities.filter((activity) => activity.Type === "Hike");
    const hikKm = hikes.reduce((sum, activity) => sum + (parseFloat(activity["Distance (km)"]) || 0), 0);
    const hikElev = hikes.reduce((sum, activity) => sum + (parseFloat(activity["Elevation Gain (m)"]) || 0), 0);
    const yoga = activities.filter((activity) => activity.Type === "Yoga");
    const yogaMin = yoga.reduce((sum, activity) => sum + (parseFloat(activity["Duration (min)"]) || 0), 0);
    const halfM = activities.find((activity) => activity.Type === "Run" && parseFloat(activity["Distance (km)"]) >= 21);

    const byMonth = {};
    activities.forEach((activity) => {
      const month = activity.Date.slice(0, 7);
      byMonth[month] = (byMonth[month] || 0) + 1;
    });
    const bestMonth = Object.entries(byMonth).sort((a, b) => b[1] - a[1])[0];

    const days = [...new Set(activities.map((activity) => activity.Date.slice(0, 10)))].sort();
    let maxStreak = 1;
    let currentStreak = 1;
    for (let i = 1; i < days.length; i += 1) {
      const diff = (new Date(days[i]) - new Date(days[i - 1])) / 86400000;
      currentStreak = diff === 1 ? currentStreak + 1 : 1;
      if (currentStreak > maxStreak) maxStreak = currentStreak;
    }

    const winLabel = M.state.windowDays === 0 ? "all time" : `last ${M.state.windowDays}d`;

    document.getElementById("insightsGrid").innerHTML = `
      <div class="ins-card">
        <div class="ins-label">Activity mix · ${winLabel}</div>
        <div class="bar-group">
          ${topTypes.map(([type, count]) => {
    const cfg = M.tc(type);
    return `<div class="bar-row">
              <div class="bar-name">${cfg.icon} ${cfg.label}</div>
              <div class="bar-track"><div class="bar-fill" style="width:${(count / maxC * 100).toFixed(0)}%;background:${cfg.color}"></div></div>
              <div class="bar-num">${count}</div>
            </div>`;
  }).join("")}
        </div>
      </div>

      <div class="ins-card">
        <div class="ins-label">Distance · ${winLabel}</div>
        ${canoeKm > 0 ? `<div class="ins-big" style="color:var(--canoe)">${canoeKm.toFixed(0)}<span style="font-size:18px"> km</span></div>
        <div class="ins-big-sub">paddled · ${canoes.length} canoe session${canoes.length !== 1 ? "s" : ""}</div>` : ""}
        ${runKm > 0 ? `<div style="margin-top:${canoeKm > 0 ? 14 : 0}px">
          <div class="ins-big" style="color:var(--run);font-size:${canoeKm > 0 ? 28 : 40}px">${runKm.toFixed(0)}<span style="font-size:16px"> km</span></div>
          <div class="ins-big-sub">run · ${runs.length} session${runs.length !== 1 ? "s" : ""}</div>
        </div>` : ""}
        ${hikKm > 0 ? `<div style="margin-top:14px">
          <div class="ins-big" style="color:var(--hike);font-size:${canoeKm > 0 || runKm > 0 ? 24 : 40}px">${hikKm.toFixed(0)}<span style="font-size:14px"> km</span></div>
          <div class="ins-big-sub">hiked${hikElev > 0 ? ` · ${hikElev.toFixed(0)}m gain` : ""}</div>
        </div>` : ""}
        ${!canoeKm && !runKm && !hikKm ? '<div class="ins-big-sub" style="margin-top:8px">No distance-based activities in this window.</div>' : ""}
      </div>

      <div class="ins-card">
        <div class="ins-label">Highlights</div>
        <div class="highlight-list">
          ${yoga.length ? `<div class="hl-item">
            <div class="hl-val" style="color:var(--yoga)">${yoga.length}× yoga</div>
            <div class="hl-desc">${M.fmt(yogaMin)} total · best recovery tool in the log</div>
          </div>` : ""}
          <div class="hl-item">
            <div class="hl-val">${maxStreak} day${maxStreak !== 1 ? "s" : ""}</div>
            <div class="hl-desc">Longest active streak in this window</div>
          </div>
          ${halfM ? `<div class="hl-item">
            <div class="hl-val" style="color:var(--run)">${parseFloat(halfM["Distance (km)"]).toFixed(1)} km</div>
            <div class="hl-desc">${M.esc(halfM.Name)} · ${M.fmtShort(halfM.Date)}</div>
          </div>` : ""}
          ${bestMonth ? `<div class="hl-item">
            <div class="hl-val">${new Date(`${bestMonth[0]}-01`).toLocaleDateString("en-GB", { month: "short", year: "numeric" })}</div>
            <div class="hl-desc">Most active month (${bestMonth[1]} sessions)</div>
          </div>` : ""}
        </div>
      </div>

      <div class="ins-card">
        <div class="ins-label">Training balance · ${winLabel}</div>
        <div class="bar-group">
          ${[
    ["Strength & Gym", activities.filter((activity) => activity.Type === "WeightTraining" || activity.Type === "Workout").length, "var(--lift)"],
    ["Paddling", activities.filter((activity) => ["Canoeing", "Canoe", "WaterSport"].includes(activity.Type)).length, "var(--canoe)"],
    ["Running", runs.length, "var(--run)"],
    ["Mind & Body", yoga.length, "var(--yoga)"],
    ["Hiking / Walk", activities.filter((activity) => ["Hike", "Walk"].includes(activity.Type)).length, "var(--hike)"],
    ["Rowing", activities.filter((activity) => activity.Type === "Rowing").length, "var(--row)"],
    ["Cycling", activities.filter((activity) => activity.Type === "Ride").length, "var(--ride)"],
    ["Surfing", activities.filter((activity) => activity.Type === "Surfing").length, "var(--surf)"],
  ].filter(([, count]) => count > 0).map(([label, count, color]) => `<div class="bar-row">
            <div class="bar-name">${label}</div>
            <div class="bar-track"><div class="bar-fill" style="width:${(count / activities.length * 100).toFixed(0)}%;background:${color}"></div></div>
            <div class="bar-num">${(count / activities.length * 100).toFixed(0)}%</div>
          </div>`).join("")}
        </div>
      </div>
    `;
  };
}());
