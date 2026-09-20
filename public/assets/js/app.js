(function () {
  const M = window.Mattrics;

  const dynamicStyleMap = {
    cssColor: "color",
    cssBackground: "background",
    cssCardAccent: "--card-accent",
    cssFatigueFill: "--fatigue-fill",
    cssFatigueOpacity: "--fatigue-opacity",
    cssPreviewFill: "--preview-fill",
    cssPreviewOpacity: "--preview-opacity",
    cssMarkerIndex: "--marker-index",
    cssSliderFill: "--slider-fill",
    cssDonutFill: "--donut-fill",
    cssLegendColor: "--legend-color",
  };

  function applyDynamicStyles(root) {
    if (!root || root.nodeType !== Node.ELEMENT_NODE) return;
    const nodes = [root, ...root.querySelectorAll("[data-css-color], [data-css-background], [data-css-card-accent], [data-css-fatigue-fill], [data-css-fatigue-opacity], [data-css-preview-fill], [data-css-preview-opacity], [data-css-marker-index], [data-css-slider-fill], [data-css-donut-fill], [data-css-legend-color]")];
    nodes.forEach((node) => {
      Object.entries(dynamicStyleMap).forEach(([key, property]) => {
        if (node.dataset[key] !== undefined) node.style.setProperty(property, node.dataset[key]);
      });
    });
  }

  new MutationObserver((records) => {
    records.forEach((record) => record.addedNodes.forEach(applyDynamicStyles));
  }).observe(document.body, { childList: true, subtree: true });
  applyDynamicStyles(document.body);

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
    openDetail: M.openDetail,
    saveSettings: M.saveSettings,
    setDashboardWindow: M.setDashboardWindow,
    setFilter: M.setFilter,
    setFeedMode: M.setFeedMode,
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
    const docsBtn = nav.querySelector('[data-view="docs"]');
    const settingsBtn = nav.querySelector('[data-view="settings"]');
    if (docsBtn) {
      docsBtn.classList.add("nav-btn--docs");
    } else if (settingsBtn) {
      const button = document.createElement("button");
      button.className = "nav-btn nav-btn--docs";
      button.type = "button";
      button.textContent = "Docs";
      button.dataset.view = "docs";
      nav.insertBefore(button, settingsBtn);
    }
  }

  document.addEventListener("click", (event) => {
    const viewTrigger = event.target.closest("[data-view]");
    if (viewTrigger) {
      M.showView(viewTrigger.dataset.view, viewTrigger);
      if (viewTrigger.hasAttribute("data-close-drawer")) M.toggleDrawer();
      return;
    }

    const action = event.target.closest("[data-action]");
    if (action) {
      switch (action.dataset.action) {
        case "retry": M.fetchData(); return;
        case "refresh": M.fetchData({ forceRefresh: true }); return;
        case "drawer": M.toggleDrawer(); return;
        case "dashboard-window": M.setDashboardWindow(Number(action.dataset.days), action); return;
        case "feed-mode": M.setFeedMode(action.dataset.mode, action); return;
        case "generate-workout": M.generateWorkout(); return;
        case "close-detail": M.closeDetail(); return;
        case "close-detail-overlay":
          if (event.target === action) M.closeDetail();
          return;
        default: break;
      }
    }

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

  M.bootstrapSession()
    .then(() => M.fetchData())
    .catch((error) => M.showError(String(error && error.message ? error.message : error)));
}());
