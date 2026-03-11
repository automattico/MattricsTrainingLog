(function () {
  const Mattrics = window.Mattrics || {};
  const config = window.MATTRICS_CONFIG || {};
  const isHttp = typeof window !== "undefined" && /^https?:$/i.test(window.location.protocol);

  Mattrics.DATA_URL = config.DATA_URL || (isHttp ? "api/data.php" : "");
  Mattrics.AI_PROXY_URL = config.AI_PROXY_URL || (isHttp ? "api/ai.php" : "");
  Mattrics.SHEET_URL = config.SHEET_URL || "";
  Mattrics.SHEET_TOKEN = config.SHEET_TOKEN || "";
  Mattrics.API_KEY = config.API_KEY || "";
  Mattrics.AI_ENABLED = typeof config.AI_ENABLED === "boolean"
    ? config.AI_ENABLED
    : Boolean(Mattrics.AI_PROXY_URL || Mattrics.API_KEY);

  Mattrics.TYPES = {
    Canoeing: { icon: "🛶", color: "var(--canoe)", label: "Canoeing" },
    Canoe: { icon: "🛶", color: "var(--canoe)", label: "Canoeing" },
    Run: { icon: "🏃", color: "var(--run)", label: "Run" },
    WeightTraining: { icon: "🏋️", color: "var(--lift)", label: "Weights" },
    Workout: { icon: "💪", color: "var(--workout)", label: "Workout" },
    Yoga: { icon: "🧘", color: "var(--yoga)", label: "Yoga" },
    Ride: { icon: "🚴", color: "var(--ride)", label: "Ride" },
    Walk: { icon: "🚶", color: "var(--walk)", label: "Walk" },
    Hike: { icon: "⛰️", color: "var(--hike)", label: "Hike" },
    WaterSport: { icon: "🚣", color: "var(--water)", label: "Water" },
    Rowing: { icon: "🚣", color: "var(--row)", label: "Rowing" },
    Surfing: { icon: "🏄", color: "var(--surf)", label: "Surf" },
  };

  Mattrics.MUSCLE_REGIONS = [
    { key: "chest", label: "Chest", color: "var(--workout)" },
    { key: "back", label: "Back", color: "var(--ride)" },
    { key: "shoulders", label: "Shoulders", color: "var(--surf)" },
    { key: "arms", label: "Arms", color: "var(--canoe)" },
    { key: "core", label: "Core", color: "var(--yoga)" },
    { key: "legs", label: "Legs", color: "var(--hike)" },
    { key: "glutes", label: "Glutes", color: "var(--walk)" },
  ];

  Mattrics.state = {
    allData: [],
    windowDays: 7,
    typeFilter: "All",
    groupBy: "week",
    feedMode: "list",
    recent: [],
  };

  Mattrics.tc = function tc(type) {
    return Mattrics.TYPES[type] || { icon: "⚡", color: "var(--muted)", label: type };
  };

  Mattrics.canonicalType = function canonicalType(type) {
    if (type === "Canoe") return "Canoeing";
    if (type === "WaterSport") return "Rowing";
    return type;
  };

  Mattrics.escAttr = function escAttr(value) {
    return String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  };

  Mattrics.fmt = function fmt(min) {
    const rounded = Math.round(parseFloat(min));
    if (!rounded) return "—";
    return rounded < 60 ? `${rounded}m` : `${Math.floor(rounded / 60)}h${rounded % 60 ? ` ${rounded % 60}m` : ""}`;
  };

  Mattrics.fmtDate = function fmtDate(ds) {
    return new Date(ds).toLocaleDateString("en-GB", {
      weekday: "short",
      day: "numeric",
      month: "short",
      year: "numeric",
    });
  };

  Mattrics.fmtShort = function fmtShort(ds) {
    return new Date(ds).toLocaleDateString("en-GB", { day: "numeric", month: "short" });
  };

  Mattrics.formatContextRange = function formatContextRange(startDs, endDs) {
    if (!startDs || !endDs) return "";

    const start = new Date(startDs);
    const end = new Date(endDs);

    if (start.toDateString() === end.toDateString()) {
      return start.toLocaleDateString("en-GB", { day: "numeric", month: "short", year: "numeric" });
    }

    const sameYear = start.getFullYear() === end.getFullYear();
    const sameMonth = sameYear && start.getMonth() === end.getMonth();

    if (sameMonth) {
      return `${start.getDate()}-${end.toLocaleDateString("en-GB", { day: "numeric", month: "short", year: "numeric" })}`;
    }

    if (sameYear) {
      return `${start.toLocaleDateString("en-GB", { day: "numeric", month: "short" })} to ${end.toLocaleDateString("en-GB", { day: "numeric", month: "short", year: "numeric" })}`;
    }

    return `${start.toLocaleDateString("en-GB", { day: "numeric", month: "short", year: "numeric" })} to ${end.toLocaleDateString("en-GB", { day: "numeric", month: "short", year: "numeric" })}`;
  };

  Mattrics.formatWeekRange = function formatWeekRange(startIso) {
    const start = new Date(startIso);
    const end = new Date(start);
    end.setDate(end.getDate() + 6);
    return `${start.toLocaleDateString("en-GB", { day: "numeric", month: "short" })} - ${end.toLocaleDateString("en-GB", { day: "numeric", month: "short" })}`;
  };

  Mattrics.weekStart = function weekStart(ds) {
    const date = new Date(ds);
    const day = date.getDay();
    const diff = date.getDate() - day + (day === 0 ? -6 : 1);
    return new Date(new Date(date).setDate(diff)).toISOString().slice(0, 10);
  };

  Mattrics.esc = function esc(value) {
    return (value || "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
  };

  Mattrics.applyTypeFilter = function applyTypeFilter(data) {
    if (Mattrics.state.typeFilter === "All") return data;
    return data.filter((activity) => Mattrics.canonicalType(activity.Type) === Mattrics.state.typeFilter);
  };

  Mattrics.getWindowedData = function getWindowedData() {
    if (Mattrics.state.windowDays === 0) return Mattrics.state.allData;
    const cutoff = new Date();
    cutoff.setDate(cutoff.getDate() - Mattrics.state.windowDays);
    cutoff.setHours(0, 0, 0, 0);
    return Mattrics.state.allData.filter((activity) => new Date(activity.Date) >= cutoff);
  };

  Mattrics.getActivityMix = function getActivityMix(activities, limit = 5) {
    const counts = {};
    activities.forEach((activity) => {
      const type = Mattrics.canonicalType(activity.Type);
      counts[type] = (counts[type] || 0) + 1;
    });

    const sorted = Object.entries(counts)
      .sort((a, b) => b[1] - a[1])
      .map(([type, count]) => {
        const cfg = Mattrics.tc(type);
        return {
          type,
          label: cfg.label,
          icon: cfg.icon,
          color: cfg.color,
          count,
        };
      });

    const visible = sorted.slice(0, limit);
    const otherCount = sorted.slice(limit).reduce((sum, item) => sum + item.count, 0);
    if (otherCount) {
      visible.push({
        type: "Other",
        label: "Other",
        icon: "•",
        color: "var(--muted)",
        count: otherCount,
      });
    }

    const total = activities.length || 1;
    const segments = visible.map((item) => ({
      ...item,
      pct: item.count / total,
      percentLabel: `${Math.round(item.count / total * 100)}%`,
    }));

    return {
      total: activities.length,
      segments,
      dominant: segments[0] || null,
      counts,
    };
  };

  Mattrics.getActiveDayStats = function getActiveDayStats(activities) {
    const days = [...new Set(activities.map((activity) => activity.Date.slice(0, 10)))].sort();
    if (!days.length) {
      return { activeDays: 0, maxStreak: 0, lastDate: "", days: [] };
    }

    let maxStreak = 1;
    let currentStreak = 1;
    for (let i = 1; i < days.length; i += 1) {
      const diff = (new Date(days[i]) - new Date(days[i - 1])) / 86400000;
      currentStreak = diff === 1 ? currentStreak + 1 : 1;
      if (currentStreak > maxStreak) maxStreak = currentStreak;
    }

    return {
      activeDays: days.length,
      maxStreak,
      lastDate: days[days.length - 1],
      days,
    };
  };

  Mattrics.getMuscleLoadAnalysis = function getMuscleLoadAnalysis(activities) {
    const regions = Object.fromEntries(
      Mattrics.MUSCLE_REGIONS.map((region) => [region.key, { ...region, load: 0, hits: 0 }])
    );
    const clamp = (value, min, max) => Math.max(min, Math.min(max, value));
    const addLoad = (weights) => {
      Object.entries(weights).forEach(([key, value]) => {
        if (!regions[key] || !value) return;
        regions[key].load += value;
        regions[key].hits += 1;
      });
    };
    const strengthFactor = (min) => clamp((min || 0) / 45, 0.85, 1.6);
    const sportFactor = (min) => clamp((min || 0) / 60, 0.7, 1.45);
    const normalized = (value) => String(value || "").toLowerCase().replace(/[^a-z0-9]+/g, " ").trim();
    const exerciseMappings = [
      { patterns: ["bench", "push up", "pushup", "chest fly", "pec deck", "dip"], weights: { chest: 1, shoulders: 0.45, arms: 0.35 } },
      { patterns: ["incline press", "incline bench", "chest press"], weights: { chest: 0.95, shoulders: 0.5, arms: 0.35 } },
      { patterns: ["row", "pulldown", "pull up", "pullup", "chin up", "chinup", "face pull"], weights: { back: 1, shoulders: 0.35, arms: 0.35 } },
      { patterns: ["deadlift", "rdl", "romanian deadlift"], weights: { back: 0.8, legs: 0.65, glutes: 0.65, core: 0.45 } },
      { patterns: ["shoulder press", "overhead press", "arnold press", "lateral raise", "front raise", "rear delt"], weights: { shoulders: 1, arms: 0.35, chest: 0.2 } },
      { patterns: ["curl", "triceps", "pushdown", "skull crusher", "hammer"], weights: { arms: 1, shoulders: 0.15 } },
      { patterns: ["plank", "crunch", "twist", "dead bug", "hollow", "sit up", "leg raise", "russian twist"], weights: { core: 1 } },
      { patterns: ["hip thrust", "glute bridge"], weights: { glutes: 1, legs: 0.35, core: 0.2 } },
      { patterns: ["squat", "lunge", "step up", "stepup", "calf raise", "leg press", "split squat"], weights: { legs: 0.9, glutes: 0.45, core: 0.25 } },
    ];
    const typeMappings = {
      Run: { legs: 0.95, glutes: 0.4, core: 0.3 },
      Walk: { legs: 0.55, glutes: 0.2, core: 0.15 },
      Hike: { legs: 0.95, glutes: 0.45, core: 0.3 },
      Ride: { legs: 0.9, glutes: 0.35, core: 0.25 },
      Canoeing: { back: 0.8, shoulders: 0.75, arms: 0.65, core: 0.35 },
      Canoe: { back: 0.8, shoulders: 0.75, arms: 0.65, core: 0.35 },
      WaterSport: { back: 0.85, shoulders: 0.8, arms: 0.7, core: 0.35 },
      Rowing: { back: 0.95, legs: 0.55, glutes: 0.3, arms: 0.45, core: 0.35 },
      Yoga: { core: 0.65, shoulders: 0.35, legs: 0.22, glutes: 0.18, back: 0.2 },
      Surfing: { core: 0.8, shoulders: 0.7, back: 0.45, legs: 0.18, glutes: 0.14 },
      WeightTraining: { chest: 0.45, back: 0.45, shoulders: 0.45, arms: 0.4, core: 0.35, legs: 0.3, glutes: 0.3 },
      Workout: { chest: 0.45, back: 0.45, shoulders: 0.45, arms: 0.4, core: 0.35, legs: 0.3, glutes: 0.3 },
    };

    activities.forEach((activity) => {
      const min = parseFloat(activity["Duration (min)"]) || 0;
      const type = activity.Type;
      const hevy = Mattrics.parseHevyDescription(activity.Description || "");

      if (hevy && hevy.length) {
        const factor = strengthFactor(min);
        let matchedExercise = false;
        hevy.forEach((exercise) => {
          const name = normalized(exercise.name);
          const match = exerciseMappings.find((mapping) => mapping.patterns.some((pattern) => name.includes(pattern)));
          if (!match) return;
          matchedExercise = true;
          const scaled = Object.fromEntries(
            Object.entries(match.weights).map(([key, value]) => [key, value * factor])
          );
          addLoad(scaled);
        });
        if (matchedExercise) return;
      }

      const base = typeMappings[type];
      if (!base) return;
      const factor = ["WeightTraining", "Workout"].includes(type) ? strengthFactor(min) : sportFactor(min);
      addLoad(Object.fromEntries(Object.entries(base).map(([key, value]) => [key, value * factor])));
    });

    const ranked = Object.values(regions).sort((a, b) => b.load - a.load);
    const maxLoad = ranked[0]?.load || 0;
    const totalLoad = ranked.reduce((sum, region) => sum + region.load, 0);

    if (!maxLoad) {
      return {
        regions: ranked.map((region) => ({ ...region, pct: 0, share: 0, hitLabel: "0 hits" })),
        strongest: [],
        weakest: ranked,
        summary: "No worked-body signal yet",
        detail: "Add more sessions to map which regions are carrying the work.",
      };
    }

    const withMetrics = ranked.map((region) => ({
      ...region,
      pct: Math.round((region.load / maxLoad) * 100),
      share: totalLoad ? region.load / totalLoad : 0,
      hitLabel: `${region.hits} hit${region.hits === 1 ? "" : "s"}`,
    }));
    const strongest = withMetrics.filter((region) => region.load >= maxLoad * 0.8);
    const minNonZero = withMetrics.filter((region) => region.load > 0).slice(-1)[0]?.load || 0;
    const weakest = minNonZero
      ? withMetrics.filter((region) => region.load === minNonZero || region.load === 0)
      : withMetrics;
    const strongestLabel = strongest.slice(0, 2).map((region) => region.label).join(" + ");
    const weakestLabel = weakest.filter((region) => region.load === 0).length
      ? weakest.filter((region) => region.load === 0).slice(0, 2).map((region) => region.label).join(" + ")
      : weakest.slice(0, 2).map((region) => region.label).join(" + ");

    return {
      regions: withMetrics,
      strongest,
      weakest,
      summary: strongest.length > 1 ? `${strongestLabel} carried the work` : `${strongest[0].label} took the load`,
      detail: weakest.some((region) => region.load === 0)
        ? `${weakestLabel} were barely touched.`
        : `${weakestLabel} got the least work.`,
    };
  };

  Mattrics.getOverviewMetrics = function getOverviewMetrics(activities) {
    const totalKm = activities.reduce((sum, activity) => sum + (parseFloat(activity["Distance (km)"]) || 0), 0);
    const totalMin = activities.reduce((sum, activity) => sum + (parseFloat(activity["Duration (min)"]) || 0), 0);
    const active = Mattrics.getActiveDayStats(activities);
    const mix = Mattrics.getActivityMix(activities);
    const muscle = Mattrics.getMuscleLoadAnalysis(activities);
    const avgSessionMin = activities.length ? totalMin / activities.length : 0;
    const longestSessionMin = activities.reduce((max, activity) => Math.max(max, parseFloat(activity["Duration (min)"]) || 0), 0);
    const byDay = {};
    const byMonth = {};

    activities.forEach((activity) => {
      const day = activity.Date.slice(0, 10);
      const month = activity.Date.slice(0, 7);
      byDay[day] = (byDay[day] || 0) + 1;
      byMonth[month] = (byMonth[month] || 0) + 1;
    });

    const multiSessionDays = Object.values(byDay).filter((count) => count > 1).length;
    const bestMonth = Object.entries(byMonth).sort((a, b) => b[1] - a[1])[0] || null;
    const windowDays = active.days.length
      ? Math.max(1, Math.round((new Date(active.days[active.days.length - 1]) - new Date(active.days[0])) / 86400000) + 1)
      : 0;
    const weeklyAverageMin = windowDays ? (totalMin / windowDays) * 7 : 0;
    const distanceTypes = [];
    if (activities.some((activity) => ["Run"].includes(activity.Type) && parseFloat(activity["Distance (km)"]) > 0)) distanceTypes.push("run");
    if (activities.some((activity) => ["Canoeing", "Canoe", "WaterSport", "Rowing"].includes(activity.Type) && parseFloat(activity["Distance (km)"]) > 0)) distanceTypes.push("paddle");
    if (activities.some((activity) => ["Hike", "Walk", "Ride"].includes(activity.Type) && parseFloat(activity["Distance (km)"]) > 0)) distanceTypes.push("outdoor");

    return {
      totalSessions: activities.length,
      totalKm,
      totalMin,
      avgSessionMin,
      weeklyAverageMin,
      longestSessionMin,
      multiSessionDays,
      ...active,
      mix,
      muscle,
      bestMonth,
      distanceSummary: totalKm > 0 ? `${totalKm.toFixed(0)} km across ${distanceTypes.slice(0, 2).join(" + ") || "distance work"}` : "",
    };
  };

  Mattrics.cardMetrics = function cardMetrics(activity) {
    const km = parseFloat(activity["Distance (km)"]) || 0;
    const min = parseFloat(activity["Duration (min)"]) || 0;
    const hr = parseFloat(activity["Avg HR"]) || 0;
    const elev = parseFloat(activity["Elevation Gain (m)"]) || 0;
    const pace = parseFloat(activity["Avg Pace (min/km)"]) || 0;
    const cad = parseFloat(activity["Avg Cadence"]) || 0;
    const speed = parseFloat(activity["Avg Speed (km/h)"]) || 0;
    const metrics = [];

    switch (activity.Type) {
      case "Canoeing":
      case "Canoe":
        if (km) metrics.push({ val: km.toFixed(1), lab: "km", color: "var(--canoe)" });
        if (min) metrics.push({ val: Mattrics.fmt(min), lab: "time", color: "var(--text)" });
        if (elev) metrics.push({ val: `${elev}m`, lab: "elev", color: "var(--muted)" });
        if (hr) metrics.push({ val: hr.toFixed(0), lab: "avg♥", color: "var(--muted)" });
        break;
      case "Run":
        if (km) metrics.push({ val: km.toFixed(2), lab: "km", color: "var(--run)" });
        if (pace) metrics.push({ val: pace.toFixed(1), lab: "min/km", color: "var(--text)" });
        if (hr) metrics.push({ val: hr.toFixed(0), lab: "avg♥", color: "var(--muted)" });
        if (elev) metrics.push({ val: `${elev}m`, lab: "elev", color: "var(--muted)" });
        break;
      case "Hike":
        if (km) metrics.push({ val: km.toFixed(1), lab: "km", color: "var(--hike)" });
        if (elev) metrics.push({ val: `${elev}m`, lab: "gain", color: "var(--hike)" });
        if (min) metrics.push({ val: Mattrics.fmt(min), lab: "time", color: "var(--text)" });
        if (hr) metrics.push({ val: hr.toFixed(0), lab: "avg♥", color: "var(--muted)" });
        break;
      case "Walk":
        if (km) metrics.push({ val: km.toFixed(1), lab: "km", color: "var(--walk)" });
        if (min) metrics.push({ val: Mattrics.fmt(min), lab: "time", color: "var(--text)" });
        if (elev) metrics.push({ val: `${elev}m`, lab: "gain", color: "var(--muted)" });
        break;
      case "Ride":
        if (km) metrics.push({ val: km.toFixed(1), lab: "km", color: "var(--ride)" });
        if (speed) metrics.push({ val: speed.toFixed(1), lab: "km/h", color: "var(--text)" });
        if (min) metrics.push({ val: Mattrics.fmt(min), lab: "time", color: "var(--muted)" });
        if (hr) metrics.push({ val: hr.toFixed(0), lab: "avg♥", color: "var(--muted)" });
        break;
      case "Rowing":
      case "WaterSport":
        if (km) metrics.push({ val: km.toFixed(2), lab: "km", color: "var(--row)" });
        if (min) metrics.push({ val: Mattrics.fmt(min), lab: "time", color: "var(--text)" });
        if (hr) metrics.push({ val: hr.toFixed(0), lab: "avg♥", color: "var(--muted)" });
        if (cad) metrics.push({ val: cad.toFixed(0), lab: "s/m", color: "var(--muted)" });
        break;
      case "Yoga":
        if (min) metrics.push({ val: Mattrics.fmt(min), lab: "time", color: "var(--yoga)" });
        if (hr) metrics.push({ val: hr.toFixed(0), lab: "avg♥", color: "var(--muted)" });
        break;
      case "Surfing":
        if (min) metrics.push({ val: Mattrics.fmt(min), lab: "time", color: "var(--surf)" });
        break;
      default:
        if (min) metrics.push({ val: Mattrics.fmt(min), lab: "time", color: "var(--lift)" });
        if (hr) metrics.push({ val: hr.toFixed(0), lab: "avg♥", color: "var(--muted)" });
    }

    return metrics;
  };

  Mattrics.detailFacts = function detailFacts(activity) {
    const facts = [];
    const add = (val, lab) => {
      if (val !== "" && val !== null && val !== undefined) facts.push({ val, lab });
    };
    const km = parseFloat(activity["Distance (km)"]) || 0;
    const min = parseFloat(activity["Duration (min)"]) || 0;
    const elev = parseFloat(activity["Elevation Gain (m)"]) || 0;
    const hr = parseFloat(activity["Avg HR"]) || 0;
    const maxHr = parseFloat(activity["Max HR"]) || 0;
    const pace = parseFloat(activity["Avg Pace (min/km)"]) || 0;
    const speed = parseFloat(activity["Avg Speed (km/h)"]) || 0;
    const cad = parseFloat(activity["Avg Cadence"]) || 0;

    if (km) add(`${km.toFixed(km >= 10 ? 1 : 2)} km`, "Distance");
    if (min) add(Mattrics.fmt(min), "Duration");
    if (elev) add(`${elev} m`, "Elevation");
    if (hr) add(hr.toFixed(0), "Avg HR");
    if (maxHr) add(maxHr.toFixed(0), "Max HR");
    if (pace) add(pace.toFixed(1), "Avg pace");
    if (speed) add(`${speed.toFixed(1)} km/h`, "Avg speed");
    if (cad) add(cad.toFixed(0), "Cadence");
    if (activity["Device Name"]) add(activity["Device Name"], "Device");

    return facts;
  };

  Mattrics.parseHevyDescription = function parseHevyDescription(desc) {
    const text = (desc || "").trim();
    if (!text.startsWith("Logged with Hevy")) return null;

    const blocks = text
      .replace(/^Logged with Hevy\s*/, "")
      .trim()
      .split(/\n\s*\n/)
      .map((block) => block.trim())
      .filter(Boolean);

    return blocks
      .map((block) => {
        const lines = block.split("\n").map((line) => line.trim()).filter(Boolean);
        if (!lines.length) return null;
        return { name: lines[0], sets: lines.slice(1) };
      })
      .filter(Boolean);
  };

  window.Mattrics = Mattrics;
}());
