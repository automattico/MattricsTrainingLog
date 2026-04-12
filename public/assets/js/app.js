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

  // Apply saved mobile nav style before first paint
  document.body.dataset.navStyle = localStorage.getItem("mobileNavStyle") || "scroll";

  window.setMobileNavStyle = function(style) {
    localStorage.setItem("mobileNavStyle", style);
    document.body.dataset.navStyle = style;
    if (style !== "drawer") {
      const drawer = document.getElementById("navDrawer");
      const overlay = document.querySelector(".nav-drawer-overlay");
      const hamburger = document.getElementById("navHamburger");
      if (drawer) drawer.classList.remove("open");
      if (overlay) overlay.classList.remove("open");
      if (hamburger) { hamburger.classList.remove("open"); hamburger.setAttribute("aria-expanded", false); }
      document.body.classList.remove("drawer-open");
    }
    // Re-render settings to reflect the updated pill selection
    const el = document.getElementById("settingsContent");
    if (el && el.innerHTML) M.renderSettingsView();
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
    toggleDrawer: M.toggleDrawer,
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

  function fixTooltipPosition(wrap) {
    const tip = wrap.querySelector(".tooltip-text");
    if (!tip) return;
    tip.style.marginLeft = "";
    const rect = tip.getBoundingClientRect();
    const pad = 12;
    if (rect.right > window.innerWidth - pad) {
      tip.style.marginLeft = `-${Math.ceil(rect.right - (window.innerWidth - pad))}px`;
    } else if (rect.left < pad) {
      tip.style.marginLeft = `${Math.ceil(pad - rect.left)}px`;
    }
  }

  document.addEventListener("mouseenter", (e) => {
    if (!e.target.closest) return;
    const wrap = e.target.closest(".tooltip-wrap");
    if (wrap) fixTooltipPosition(wrap);
  }, true);

  document.addEventListener("focusin", (e) => {
    const wrap = e.target.closest(".tooltip-wrap");
    if (wrap) fixTooltipPosition(wrap);
  });

  M.fetchData({ forceRefresh: false });
}());
