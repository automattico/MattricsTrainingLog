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
    const hasDirectKey = M.API_KEY && M.API_KEY !== "YOUR_API_KEY_HERE" && M.API_KEY !== "YOUR_ANTHROPIC_API_KEY_HERE";
    const hasProxy = Boolean(M.AI_PROXY_URL);

    if (!hasDirectKey && !hasProxy) {
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
      const desc = (activity.Description || "").replace(/Logged with Hevy/g, "").slice(0, 200).trim();
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
      let output = "";

      if (hasProxy) {
        const res = await fetch(M.AI_PROXY_URL, {
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
        output = data.text || "No response.";
      } else {
        const prompt = `You are a smart training coach. This athlete has a varied active lifestyle: they canoe rivers for days at a time, do serious gym work (tracked in Hevy), run (including a half marathon), practice yoga regularly, hike, and row on a Concept2. They have a shoulder injury they're managing with targeted rehab (scapular pulls, external rotation, face pulls).

Recent activity (last 10 days):
${summary || "(no recent data — suggest a good general session)"}

Current muscle fatigue estimate (fixed last 10 days):
${fatigueSummary || "No meaningful recent muscle fatigue signal."}
${fatigueRegions.length ? `\n${fatigueRegions.map((region) => `- ${region.label}: ${region.fatigueScore}/100 (${region.tier}, ${region.lastWorkedLabel}, ${region.recoveryLabel})`).join("\n")}` : ""}

Suggest ONE specific workout for today. Avoid heavily loading muscle groups that are currently highly fatigued. Prefer fresher muscle groups, cardio, mobility, rehab, or recovery work when that fits better. Be concrete — if strength, give exercises with sets/reps/weights. If cardio, give distance/duration/intensity. Keep it brief.

Format:
**Why this:** (1 sentence based on what they've been doing)

**Session:**
(the workout, specific and actionable)

**Shoulder note:** (only if relevant)`;

        const res = await fetch("https://api.anthropic.com/v1/messages", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "x-api-key": M.API_KEY,
            "anthropic-version": "2023-06-01",
            "anthropic-dangerous-direct-browser-access": "true",
          },
          body: JSON.stringify({
            model: "claude-sonnet-4-20250514",
            max_tokens: 900,
            messages: [{ role: "user", content: prompt }],
          }),
        });
        const data = await res.json();
        output = (data.content || []).map((content) => content.text || "").join("") || "No response.";
      }

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
