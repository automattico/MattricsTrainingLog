(function () {
  const M = window.Mattrics;

  M.renderAiPreview = function renderAiPreview() {
    const cutoff = new Date();
    cutoff.setDate(cutoff.getDate() - 10);
    const recent = M.state.allData.filter((activity) => new Date(activity.Date) >= cutoff);
    M.state.recent = recent;

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

    document.getElementById("aiDesc").textContent = recent.length
      ? `Analyzing your last ${recent.length} sessions to suggest what your body needs today.`
      : "No recent data — will suggest a session based on your training history.";
  };

  M.generateWorkout = async function generateWorkout() {
    if (M.API_KEY === "YOUR_API_KEY_HERE") {
      document.getElementById("aiText").textContent =
        "⚠️ Add your API key first.\n\nOpen this HTML file in a text editor and replace YOUR_API_KEY_HERE.";
      document.getElementById("aiOutput").style.display = "block";
      return;
    }

    const btn = document.querySelector(".gen-btn");
    btn.disabled = true;
    document.getElementById("aiThinking").style.display = "block";
    document.getElementById("aiOutput").style.display = "none";

    const recent = M.state.recent || [];
    const summary = recent.map((activity) => {
      const km = parseFloat(activity["Distance (km)"]) || 0;
      const min = parseFloat(activity["Duration (min)"]) || 0;
      const elev = parseFloat(activity["Elevation Gain (m)"]) || 0;
      const desc = (activity.Description || "").replace(/Logged with Hevy/g, "").slice(0, 200).trim();
      return `• ${M.fmtShort(activity.Date)}: [${activity.Type}] ${activity.Name}${km ? ` — ${km.toFixed(1)}km` : ""}${min ? ` — ${M.fmt(min)}` : ""}${elev ? ` — ${elev}m elev` : ""}${desc ? `\n  ${desc}` : ""}`;
    }).join("\n");

    const prompt = `You are a smart training coach. This athlete has a varied active lifestyle: they canoe rivers for days at a time, do serious gym work (tracked in Hevy), run (including a half marathon), practice yoga regularly, hike, and row on a Concept2. They have a shoulder injury they're managing with targeted rehab (scapular pulls, external rotation, face pulls).

Recent activity (last 10 days):
${summary || "(no recent data — suggest a good general session)"}

Suggest ONE specific workout for today. Be concrete — if strength, give exercises with sets/reps/weights. If cardio, give distance/duration/intensity. Keep it brief.

Format:
**Why this:** (1 sentence based on what they've been doing)

**Session:**
(the workout, specific and actionable)

**Shoulder note:** (only if relevant)`;

    try {
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
      document.getElementById("aiText").textContent =
        (data.content || []).map((content) => content.text || "").join("") || "No response.";
      document.getElementById("aiOutput").style.display = "block";
    } catch (error) {
      document.getElementById("aiText").textContent = `Error: ${error.message}`;
      document.getElementById("aiOutput").style.display = "block";
    }

    document.getElementById("aiThinking").style.display = "none";
    btn.disabled = false;
  };
}());
