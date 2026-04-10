<header class="site-header">
  <div class="header-row">
    <div class="brand-block">
      <div class="site-title">Mattrics</div>
      <div class="site-subtitle">Training Log</div>
    </div>

    <div class="header-controls">
      <div class="window-controls">
        <div class="window-switcher" id="windowSwitcher">
          <div class="window-option active">
            <button class="window-btn active" data-days="7" onclick="setWindow(7,this)">7 days</button>
          </div>
          <div class="window-option">
            <button class="window-btn" data-days="14" onclick="setWindow(14,this)">14 days</button>
          </div>
          <div class="window-option">
            <button class="window-btn" data-days="30" onclick="setWindow(30,this)">30 days</button>
          </div>
          <div class="window-option">
            <button class="window-btn" data-days="90" onclick="setWindow(90,this)">3 months</button>
          </div>
          <div class="window-option">
            <button class="window-btn" data-days="180" onclick="setWindow(180,this)">6 months</button>
          </div>
          <div class="window-option">
            <button class="window-btn" data-days="0" onclick="setWindow(0,this)">All</button>
          </div>
        </div>
        <div class="context-period range-summary-text" id="rangeSummary" aria-live="polite"></div>
      </div>

      <div class="header-actions">
        <button class="icon-btn logout-btn" onclick="fetch('/api/auth/logout.php',{method:'POST',credentials:'same-origin',headers:{'X-CSRF-Token':window.MATTRICS_AUTH&&window.MATTRICS_AUTH.csrfToken||''}}).then(()=>window.location.href='/login.php')" title="Log out" aria-label="Log out">
          <span>Log out</span>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h7"/><polyline points="17 8 21 12 17 16"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </button>
        <div class="header-actions-row">
          <div class="data-sync-stamp" id="dataSyncStamp">Last updated unavailable</div>
          <button class="icon-btn refresh-btn" onclick="fetchData({ forceRefresh: true })" title="Refresh from sheet" aria-label="Refresh data">
            <span class="refresh-icon" aria-hidden="true">↻</span>
          </button>
        </div>
      </div>
    </div>
  </div>
  <div class="data-status-banner" id="dataStatusBanner" hidden></div>
</header>
