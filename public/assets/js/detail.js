(function () {
  const M = window.Mattrics;

  M.openDetail = function openDetail(activityId) {
    const activity = M.state.allData.find(
      (row) => M.getActivityId(row) === String(activityId)
    );
    if (!activity) return;

    const cfg = M.tc(activity.Type);
    const facts = M.detailFacts(activity);
    const notes = (activity.Description || "").trim();
    const hevy = M.parseHevyDescription(notes);
    const exerciseCount = hevy ? hevy.length : 0;
    const setCount = hevy ? hevy.reduce((sum, exercise) => sum + exercise.sets.length, 0) : 0;
    const deviceName = (activity["Device Name"] || "").trim();
    const metricFacts = facts.filter((fact) => fact.lab !== "Device");
    const summaryItems = [];

    if (exerciseCount) summaryItems.push({ val: `${exerciseCount}`, lab: "Exercises", color: cfg.color });
    if (setCount) summaryItems.push({ val: `${setCount}`, lab: "Sets", color: cfg.color });
    summaryItems.push(...metricFacts.map((fact, index) => ({
      val: fact.val,
      lab: fact.lab,
      color: index === 0 ? cfg.color : "var(--text)",
    })));

    document.getElementById("detailKicker").innerHTML = `
      <span class="detail-kicker-icon" aria-hidden="true">${cfg.icon}</span>
      <span>${M.esc(cfg.label)}</span>
      <span class="detail-kicker-sep">·</span>
      <span>${M.esc(M.fmtDate(activity.Date))}</span>`;
    document.getElementById("detailKicker").style.color = "";
    document.getElementById("detailTitle").textContent = activity.Name || cfg.label;
    document.getElementById("detailDate").textContent = "";
    document.getElementById("detailMetrics").innerHTML = summaryItems.map((metric) => `
      <div class="metric">
        <div class="metric-val" style="color:${metric.color}">${M.esc(metric.val)}</div>
        <div class="metric-lab">${metric.lab}</div>
      </div>`).join("");
    document.getElementById("detailMeta").textContent = deviceName ? `Tracked with ${deviceName}` : "";
    document.getElementById("detailMeta").style.display = deviceName ? "block" : "none";
    document.getElementById("detailFacts").innerHTML = "";
    document.getElementById("detailFactsSection").style.display = "none";

    if (hevy && hevy.length) {
      document.getElementById("detailWorkoutList").innerHTML = hevy.map((exercise) => `
        <div class="hevy-exercise">
          <div class="hevy-ex-name">${M.esc(exercise.name)}</div>
          <div class="hevy-set-list">
            ${exercise.sets.map((set) => `<div class="hevy-set">${M.esc(set)}</div>`).join("")}
          </div>
        </div>`).join("");
      document.getElementById("detailWorkoutSection").style.display = "block";
    } else {
      document.getElementById("detailWorkoutList").innerHTML = "";
      document.getElementById("detailWorkoutSection").style.display = "none";
    }

    const cleanNotes = hevy ? "" : notes;
    if (cleanNotes) {
      document.getElementById("detailNotes").textContent = cleanNotes;
      document.getElementById("detailNotesSection").style.display = "block";
    } else {
      document.getElementById("detailNotes").textContent = "";
      document.getElementById("detailNotesSection").style.display = "none";
    }

    document.getElementById("detailOverlay").classList.add("open");
    document.body.style.overflow = "hidden";
  };

  M.closeDetail = function closeDetail(event) {
    if (event && event.target && event.target !== event.currentTarget) return;
    document.getElementById("detailOverlay").classList.remove("open");
    document.body.style.overflow = "";
  };
}());
