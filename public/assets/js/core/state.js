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
      lastFetchAt: "",
    },
    dashboardWindowDays: 7,
    typeFilter: "All",
    groupBy: "week",
    feedMode: "list",
    recent: [],
    currentFatigue: null,
    userSettings: null,
    exerciseConfigs: [],
    activityTypeConfigs: [],
    exerciseConfigMeta: {
      loadedAt: "",
      seedVersion: 0,
    },
    exerciseConfigIndex: null,
    unknownExercises: [],
    unknownExerciseMeta: {
      loadedAt: "",
      syncedAt: "",
      lastSyncError: "",
    },
    exerciseAdmin: {
      query: "",
      filter: "all",
      selectedKey: "",
      feedback: null,
      pendingAction: "",
      pendingKey: "",
      pendingUnknownId: "",
      mergeTargetId: "",
      titleEditOpen: false,
      titleDraft: "",
      namesEditOpen: false,
      namesDraft: "",
      mergePanelKey: "",
      confirmDialog: {
        open: false,
        type: "",
        key: "",
      },
      formDraft: null,
      formErrors: null,
    },
  };
}());
