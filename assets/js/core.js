(function () {
  const Mattrics = window.Mattrics || {};

  Mattrics.SHEET_URL = (window.MATTRICS_CONFIG && window.MATTRICS_CONFIG.SHEET_URL) || "";
  Mattrics.API_KEY = (window.MATTRICS_CONFIG && window.MATTRICS_CONFIG.API_KEY) || "";

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

  Mattrics.getTrainingBalance = function getTrainingBalance(activities) {
    const buckets = [
      { label: "Endurance", color: "var(--run)", types: ["Run", "Ride", "Rowing"] },
      { label: "Paddling", color: "var(--canoe)", types: ["Canoeing", "Canoe", "WaterSport"] },
      { label: "Strength", color: "var(--lift)", types: ["WeightTraining", "Workout"] },
      { label: "Recovery", color: "var(--yoga)", types: ["Yoga", "Walk"] },
      { label: "Outdoors", color: "var(--hike)", types: ["Hike", "Surfing"] },
    ].map((bucket) => ({
      ...bucket,
      count: activities.filter((activity) => bucket.types.includes(activity.Type)).length,
    })).filter((bucket) => bucket.count > 0)
      .sort((a, b) => b.count - a.count);

    if (!buckets.length) {
      return {
        buckets: [],
        dominant: null,
        summary: "No clear training shape yet.",
        detail: "Add a few sessions to see how this window balances out.",
      };
    }

    const dominant = buckets[0];
    const share = dominant.count / activities.length;
    const summary = share >= 0.55
      ? `${dominant.label}-heavy window`
      : share >= 0.4
        ? `${dominant.label} leads the mix`
        : "Well balanced mix";
    const detail = share >= 0.55
      ? `${Math.round(share * 100)}% of sessions landed in ${dominant.label.toLowerCase()}.`
      : `${buckets.length} training modes showed up in this window.`;

    return { buckets, dominant, summary, detail };
  };

  Mattrics.getOverviewMetrics = function getOverviewMetrics(activities) {
    const totalKm = activities.reduce((sum, activity) => sum + (parseFloat(activity["Distance (km)"]) || 0), 0);
    const totalMin = activities.reduce((sum, activity) => sum + (parseFloat(activity["Duration (min)"]) || 0), 0);
    const active = Mattrics.getActiveDayStats(activities);
    const mix = Mattrics.getActivityMix(activities);
    const balance = Mattrics.getTrainingBalance(activities);
    const avgSessionMin = activities.length ? totalMin / activities.length : 0;
    const avgActiveDayMin = active.activeDays ? totalMin / active.activeDays : 0;
    const byMonth = {};

    activities.forEach((activity) => {
      const month = activity.Date.slice(0, 7);
      byMonth[month] = (byMonth[month] || 0) + 1;
    });

    const bestMonth = Object.entries(byMonth).sort((a, b) => b[1] - a[1])[0] || null;
    const distanceTypes = [];
    if (activities.some((activity) => ["Run"].includes(activity.Type) && parseFloat(activity["Distance (km)"]) > 0)) distanceTypes.push("run");
    if (activities.some((activity) => ["Canoeing", "Canoe", "WaterSport", "Rowing"].includes(activity.Type) && parseFloat(activity["Distance (km)"]) > 0)) distanceTypes.push("paddle");
    if (activities.some((activity) => ["Hike", "Walk", "Ride"].includes(activity.Type) && parseFloat(activity["Distance (km)"]) > 0)) distanceTypes.push("outdoor");

    return {
      totalSessions: activities.length,
      totalKm,
      totalMin,
      avgSessionMin,
      avgActiveDayMin,
      ...active,
      mix,
      balance,
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
