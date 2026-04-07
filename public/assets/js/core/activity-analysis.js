(function () {
  const M = window.Mattrics;

  M.getActivityMix = function getActivityMix(activities, limit = 5) {
    const counts = {};
    activities.forEach((activity) => {
      const type = M.canonicalType(activity.Type);
      counts[type] = (counts[type] || 0) + 1;
    });

    const sorted = Object.entries(counts)
      .sort((a, b) => b[1] - a[1])
      .map(([type, count]) => {
        const cfg = M.tc(type);
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

  M.getActiveDayStats = function getActiveDayStats(activities) {
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

  M.getOverviewMetrics = function getOverviewMetrics(activities) {
    const totalKm = activities.reduce((sum, activity) => sum + (parseFloat(activity["Distance (km)"]) || 0), 0);
    const totalMin = activities.reduce((sum, activity) => sum + (parseFloat(activity["Duration (min)"]) || 0), 0);
    const active = M.getActiveDayStats(activities);
    const mix = M.getActivityMix(activities);
    const fatigue = M.getMuscleFatigueAnalysis(M.state.allData);
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
      fatigue,
      bestMonth,
      distanceSummary: totalKm > 0 ? `${totalKm.toFixed(0)} km across ${distanceTypes.slice(0, 2).join(" + ") || "distance work"}` : "",
    };
  };
}());
