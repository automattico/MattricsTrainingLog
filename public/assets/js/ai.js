(function () {
  const M = window.Mattrics;

  M.renderAiPreview = function renderAiPreview() {
    const recent = M.getFixedRecentActivities(M.state.allData);
    const fatigue = M.getMuscleFatigueAnalysis(M.state.allData);
    M.state.recent = recent;
    M.state.currentFatigue = fatigue;

    const box = document.getElementById("recentPreview");
    if (recent.length) {
      box.style.display = "block";
      document.getElementById("recentItems").innerHTML = recent.map((activity) => `
        <div class="rp-row">
          <span>${M.tc(activity.Type).icon}</span>
          <span class="rp-name">${M.esc(activity.Name)}</span>
          <span class="rp-date">${M.fmtShort(activity.Date)}</span>
        </div>`).join("");
    } else {
      box.style.display = "none";
      document.getElementById("recentItems").innerHTML = "";
    }

    document.getElementById("aiDesc").textContent = !M.AI_ENABLED
      ? "AI suggestions are currently disabled for this deployment."
      : recent.length
        ? `Analyzing your last ${recent.length} sessions and current muscle fatigue to suggest what your body needs today.`
        : "No recent data — will suggest a session based on your training history.";
  };

  M.generateWorkout = async function generateWorkout() {
    if (!M.AI_PROXY_URL) {
      document.getElementById("aiText").textContent =
        "AI is not configured.\n\nFor secure hosting, configure private/config.php and use api/ai.php.";
      document.getElementById("aiOutput").style.display = "block";
      return;
    }

    const btn = document.querySelector(".gen-btn");
    btn.disabled = true;
    document.getElementById("aiThinking").style.display = "block";
    document.getElementById("aiOutput").style.display = "none";

    const recent = M.state.recent || [];
    const fatigue = M.state.currentFatigue || M.getMuscleFatigueAnalysis(M.state.allData);
    const summary = recent.map((activity) => {
      const km = parseFloat(activity["Distance (km)"]) || 0;
      const min = parseFloat(activity["Duration (min)"]) || 0;
      const elev = parseFloat(activity["Elevation Gain (m)"]) || 0;
      const desc = M.stripHevyHeader(activity.Description || "").slice(0, 200).trim();
      return `• ${M.fmtShort(activity.Date)}: [${activity.Type}] ${activity.Name}${km ? ` — ${km.toFixed(1)}km` : ""}${min ? ` — ${M.fmt(min)}` : ""}${elev ? ` — ${elev}m elev` : ""}${desc ? `\n  ${desc}` : ""}`;
    }).join("\n");
    const fatigueSummary = fatigue ? `${fatigue.summary} ${fatigue.detail}` : "";
    const fatigueRegions = fatigue
      ? fatigue.regions.map((region) => ({
        key: region.key,
        slug: region.slug || region.key,
        label: region.label,
        fatigueScore: region.fatigueScore,
        tier: region.tier,
        lastWorkedDate: region.lastWorkedDate,
        lastWorkedLabel: region.lastWorkedLabel,
        recoveryHours: region.recoveryHours,
        recoveryDate: region.recoveryDate,
        recoveryLabel: region.recoveryLabel,
      }))
      : [];

    try {
      const res = await M.apiFetch(M.AI_PROXY_URL, {
          method: "POST",
          credentials: "same-origin",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({
            recent,
            summary,
            fatigueSummary,
            fatigueRegions,
          }),
        });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`);
      const output = data.text || "No response.";

      document.getElementById("aiText").textContent = output;
      document.getElementById("aiOutput").style.display = "block";
    } catch (error) {
      document.getElementById("aiText").textContent = `Error: ${error.message}`;
      document.getElementById("aiOutput").style.display = "block";
    }

    document.getElementById("aiThinking").style.display = "none";
    btn.disabled = false;
  };
}());
