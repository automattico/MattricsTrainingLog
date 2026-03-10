(function () {
  const M = window.Mattrics;

  M.showLoading = function showLoading() {
    document.getElementById("loadScreen").classList.remove("hidden");
    document.getElementById("app").classList.remove("visible");
    document.getElementById("app").style.display = "none";
    document.getElementById("loadSpinner").style.display = "block";
    document.getElementById("loadMsg").style.display = "block";
    document.getElementById("errorBox").style.display = "none";
  };

  M.showError = function showError(msg) {
    document.getElementById("loadSpinner").style.display = "none";
    document.getElementById("loadMsg").style.display = "none";
    document.getElementById("errorMsg").textContent = msg;
    document.getElementById("errorBox").style.display = "flex";
  };

  M.showApp = function showApp() {
    document.getElementById("loadScreen").classList.add("hidden");
    document.getElementById("app").style.display = "block";
    requestAnimationFrame(() => document.getElementById("app").classList.add("visible"));
  };

  M.showSetupHelp = function showSetupHelp() {
    document.getElementById("errorMsg").textContent =
      "SETUP STEPS:\n\n1. Open your Google Sheet\n2. Extensions -> Apps Script\n3. Paste the contents of Code.gs\n4. Deploy -> New Deployment -> Web App\n5. Execute as: Me | Access: Anyone\n6. Copy the Web App URL\n7. Copy config.example.js to config.js\n8. Set MATTRICS_CONFIG.SHEET_URL in config.js";
  };

  M.fetchData = async function fetchData() {
    M.showLoading();
    if (!M.SHEET_URL || M.SHEET_URL === "PASTE_YOUR_WEB_APP_URL_HERE" || M.SHEET_URL === "YOUR_GOOGLE_APPS_SCRIPT_URL_HERE") {
      M.showError(
        "No Sheet URL configured.\n\nCreate config.js next to dashboard.html by copying config.example.js, then set MATTRICS_CONFIG.SHEET_URL to your Google Apps Script Web App URL.\n\nSee README.md and apps-script/Code.gs for setup instructions."
      );
      return;
    }

    try {
      const res = await fetch(M.SHEET_URL, { redirect: "follow" });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const text = await res.text();
      let json;
      try {
        json = JSON.parse(text);
      } catch {
        throw new Error("Response was not valid JSON. Check your Apps Script doGet().");
      }
      if (json.error) throw new Error(json.error);

      M.state.allData = json.rows
        .filter((row) => row.Date && row.Type)
        .sort((a, b) => new Date(b.Date) - new Date(a.Date));
      M.state.typeFilter = "All";
      M.showApp();
      M.renderAll();
    } catch (error) {
      const msg = String(error && error.message ? error.message : error);
      const isCors = msg === "Failed to fetch" ||
        msg === "Load failed" ||
        msg.includes("NetworkError") ||
        msg.includes("CORS") ||
        msg.includes("fetch");

      M.showError(
        isCors
          ? "Browser blocked the request.\n\nYour Apps Script endpoint is live, but some browsers block fetches from a local file. Try opening this in Chrome, or serve this folder on localhost instead of opening dashboard.html via file://.\n\nIf needed, redeploy Apps Script as a Web App with Execute as: Me and Access: Anyone."
          : `Could not load data.\n\n${msg}`
      );
    }
  };

  M.setWindow = function setWindow(days, el) {
    M.state.windowDays = days;
    M.state.typeFilter = "All";
    document.querySelectorAll(".window-btn").forEach((button) => button.classList.remove("active"));
    el.classList.add("active");
    M.renderAll();
  };

  M.renderAll = function renderAll() {
    const windowed = M.getWindowedData();
    M.renderContextBar(windowed);
    M.renderHeader(windowed);
    M.renderFilters(windowed);
    M.renderFeed(windowed);
    M.renderTimeline(windowed);
    M.renderInsights(windowed);
    M.renderAiPreview();
  };

  M.renderContextBar = function renderContextBar(data) {
    if (!data.length) {
      document.getElementById("contextBar").textContent = "No activities in this window.";
      return;
    }
    const newest = data[0].Date;
    const oldest = data[data.length - 1].Date;
    const label = M.state.windowDays === 0 ? "All time" : `Last ${M.state.windowDays} day${M.state.windowDays > 1 ? "s" : ""}`;
    document.getElementById("contextBar").innerHTML =
      `<span class="context-period">${label}</span><span class="context-dot">·</span>${data.length} sessions<span class="context-dot">·</span>${M.fmtShort(oldest)} → ${M.fmtShort(newest)}`;
  };

  M.renderHeader = function renderHeader(data) {
    const km = data.reduce((sum, activity) => sum + (parseFloat(activity["Distance (km)"]) || 0), 0);
    const min = data.reduce((sum, activity) => sum + (parseFloat(activity["Duration (min)"]) || 0), 0);
    document.getElementById("headerStats").innerHTML = `
      <div class="hstat"><div class="hstat-val">${data.length}</div><div class="hstat-lab">Sessions</div></div>
      <div class="hstat"><div class="hstat-val">${km.toFixed(0)} km</div><div class="hstat-lab">Distance</div></div>
      <div class="hstat"><div class="hstat-val">${Math.round(min / 60)} h</div><div class="hstat-lab">Time</div></div>
    `;
  };

  M.renderFilters = function renderFilters(data) {
    const counts = {};
    data.forEach((activity) => {
      const type = M.canonicalType(activity.Type);
      counts[type] = (counts[type] || 0) + 1;
    });

    const sorted = Object.entries(counts).sort((a, b) => b[1] - a[1]);
    const allOn = M.state.typeFilter === "All";
    let html = `<button class="filter-pill ${allOn ? "on" : ""}" onclick="setFilter('All')" style="${allOn ? "background:#fff;border-color:#fff;" : "border-color:var(--border2)"}">
      All <span class="filter-count">${data.length}</span></button>`;

    sorted.forEach(([type, count]) => {
      const cfg = M.tc(type);
      const on = M.state.typeFilter === type;
      html += `<button class="filter-pill ${on ? "on" : ""}" onclick="setFilter('${type}')" style="${on ? `background:${cfg.color};border-color:${cfg.color};` : ""}">
        ${cfg.icon} ${cfg.label} <span class="filter-count">${count}</span></button>`;
    });

    document.getElementById("filterRow").innerHTML = html;
  };

  M.setFilter = function setFilter(filter) {
    M.state.typeFilter = filter;
    const windowed = M.getWindowedData();
    M.renderFilters(windowed);
    M.renderFeed(M.applyTypeFilter(windowed));
  };

  M.showView = function showView(id, el) {
    document.querySelectorAll(".view").forEach((view) => view.classList.remove("active"));
    document.querySelectorAll(".nav-btn").forEach((button) => button.classList.remove("active"));
    document.getElementById(`view-${id}`).classList.add("active");
    el.classList.add("active");
  };
}());
