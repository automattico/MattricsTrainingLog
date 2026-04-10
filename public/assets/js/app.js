(function () {
  const M = window.Mattrics;

  M.logout = function logout() {
    fetch('api/auth/logout.php', { method: 'POST' })
      .then(() => {
        window.location.href = 'login.php';
      })
      .catch(() => {
        window.location.href = 'login.php';
      });
  };

  Object.assign(window, {
    closeDetail: M.closeDetail,
    fetchData: M.fetchData,
    generateWorkout: M.generateWorkout,
    logout: M.logout,
    openDetail: M.openDetail,
    saveSettings: M.saveSettings,
    setFilter: M.setFilter,
    setFeedMode: M.setFeedMode,
    setWindow: M.setWindow,
    showSetupHelp: M.showSetupHelp,
    showView: M.showView,
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") M.closeDetail();
  });

  window.addEventListener("resize", () => M.positionRangeSummary());

  const main = document.querySelector(".app-main");
  if (main && !document.getElementById("view-docs")) {
    const view = document.createElement("div");
    view.className = "view";
    view.id = "view-docs";
    view.innerHTML = `<section class="section-shell docs-shell"><div id="docsContent"></div></section>`;
    const aiView = document.getElementById("view-ai");
    main.insertBefore(view, aiView || null);
  }

  const nav = document.querySelector(".nav");
  if (nav) {
    const docsBtn = nav.querySelector('[onclick*="showView(\'docs\'"]');
    const settingsBtn = nav.querySelector('[onclick*="showView(\'settings\'"]');
    if (docsBtn) {
      docsBtn.classList.add("nav-btn--docs");
    } else if (settingsBtn) {
      const button = document.createElement("button");
      button.className = "nav-btn nav-btn--docs";
      button.type = "button";
      button.textContent = "Docs";
      button.addEventListener("click", () => M.showView("docs", button));
      nav.insertBefore(button, settingsBtn);
    }
  }

  document.addEventListener("click", (event) => {
    const trigger = event.target.closest("[data-activity-id]");
    if (!trigger) return;
    M.openDetail(trigger.dataset.activityId);
  });

  M.fetchData({ forceRefresh: false });
}());
