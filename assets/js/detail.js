(function () {
  const M = window.Mattrics;

  M.openDetail = function openDetail(activityId) {
    const activity = M.state.allData.find(
      (row) => String(row["Activity ID raw"] || row["Activity ID"]) === String(activityId)
    );
    if (!activity) return;

    const cfg = M.tc(activity.Type);
    const metrics = M.cardMetrics(activity);
    const facts = M.detailFacts(activity);
    const notes = (activity.Description || "").trim();
    const hevy = M.parseHevyDescription(notes);

    document.getElementById("detailKicker").textContent = `${cfg.label} session`;
    document.getElementById("detailKicker").style.color = cfg.color;
    document.getElementById("detailTitle").textContent = activity.Name || cfg.label;
    document.getElementById("detailDate").textContent = M.fmtDate(activity.Date);
    document.getElementById("detailMetrics").innerHTML = metrics.map((metric) => `
      <div class="metric">
        <div class="metric-val" style="color:${metric.color}">${metric.val}</div>
        <div class="metric-lab">${metric.lab}</div>
      </div>`).join("");
    document.getElementById("detailFacts").innerHTML = facts.map((fact) => `
      <div class="detail-fact">
        <div class="detail-fact-val">${M.esc(fact.val)}</div>
        <div class="detail-fact-lab">${M.esc(fact.lab)}</div>
      </div>`).join("");
    document.getElementById("detailFactsSection").style.display = facts.length ? "block" : "none";

    if (hevy && hevy.length) {
      document.getElementById("detailWorkoutList").innerHTML = hevy.map((exercise) => `
        <div class="hevy-exercise">
          <div class="hevy-ex-name">${M.esc(exercise.name)}</div>
          ${exercise.sets.map((set) => `<div class="hevy-set">${M.esc(set)}</div>`).join("")}
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
