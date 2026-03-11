(function () {
  const M = window.Mattrics;

  Object.assign(window, {
    closeDetail: M.closeDetail,
    fetchData: M.fetchData,
    generateWorkout: M.generateWorkout,
    openDetail: M.openDetail,
    setFilter: M.setFilter,
    setFeedMode: M.setFeedMode,
    setWindow: M.setWindow,
    showSetupHelp: M.showSetupHelp,
    showView: M.showView,
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") M.closeDetail();
  });

  M.fetchData();
}());
