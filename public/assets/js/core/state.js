(function () {
  const M = window.Mattrics;

  M.state = {
    allData: [],
    dataMeta: {
      source: "",
      stale: false,
      warning: "",
      lastSuccessfulSyncAt: "",
      lastLiveAttemptAt: "",
    },
    windowDays: 7,
    typeFilter: "All",
    groupBy: "week",
    feedMode: "list",
    recent: [],
    currentFatigue: null,
    userSettings: null,
  };
}());
