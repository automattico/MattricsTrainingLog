(function () {
  const M = window.Mattrics;

  M.getDashboardWindowRange = function getDashboardWindowRange() {
    if (!M.state.allData.length) return { start: "", end: "" };

    if (M.state.dashboardWindowDays === 0) {
      return {
        start: M.state.allData[M.state.allData.length - 1].Date,
        end: M.state.allData[0].Date,
      };
    }

    const end = M.startOfDay(new Date());
    const start = new Date(end);
    start.setDate(start.getDate() - (M.state.dashboardWindowDays - 1));
    return {
      start: M.toIsoDate(start),
      end: M.toIsoDate(end),
    };
  };

  M.getRollingPeriod = function getRollingPeriod(ds, periodDays) {
    const today = M.startOfDay(new Date());
    const activityDate = M.parseDate(ds);
    const distance = Math.max(0, M.diffDays(today, activityDate));
    const bucket = Math.floor(distance / periodDays);
    const end = new Date(today);
    end.setDate(end.getDate() - (bucket * periodDays));
    const start = new Date(end);
    start.setDate(start.getDate() - (periodDays - 1));
    return {
      key: M.toIsoDate(start),
      start: M.toIsoDate(start),
      end: M.toIsoDate(end),
    };
  };

  M.applyTypeFilter = function applyTypeFilter(data) {
    if (M.state.typeFilter === "All") return data;
    return data.filter((activity) => M.canonicalType(activity.Type) === M.state.typeFilter);
  };

  M.getDashboardWindowedData = function getDashboardWindowedData() {
    if (M.state.dashboardWindowDays === 0) return M.state.allData;
    const { start } = M.getDashboardWindowRange();
    return M.state.allData.filter((activity) => activity.Date >= start);
  };

  M.getFixedRecentActivities = function getFixedRecentActivities(activities, days = M.MUSCLE_FATIGUE_CONFIG.windowDays) {
    const end = M.startOfDay(new Date());
    const start = new Date(end);
    start.setDate(start.getDate() - (days - 1));
    const startIso = M.toIsoDate(start);

    return activities.filter((activity) => activity.Date >= startIso);
  };
}());
