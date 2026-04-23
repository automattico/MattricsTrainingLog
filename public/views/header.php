<header class="site-header">
  <div class="header-row">
    <div class="brand-block">
      <div class="site-title">Mattrics</div>
      <div class="site-subtitle">Training Log</div>
    </div>

    <div class="header-controls">
      <div class="header-actions">
        <div class="data-sync-stamp" id="dataSyncStamp">Last updated unavailable</div>
        <div class="header-actions-row">
          <button class="icon-btn refresh-btn" onclick="fetchData({ forceRefresh: true })" title="Refresh from sheet" aria-label="Refresh data">
            <span class="refresh-icon" aria-hidden="true">↻</span>
          </button>
          <button class="icon-btn logout-btn" onclick="fetch('/api/auth/logout.php',{method:'POST',credentials:'same-origin',headers:{'X-CSRF-Token':window.MATTRICS_AUTH&&window.MATTRICS_AUTH.csrfToken||''}}).then(()=>window.location.href='/login.php')" title="Log out" aria-label="Log out">
            <span>Log out</span>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h7"/><polyline points="17 8 21 12 17 16"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          </button>
        </div>
      </div>
    </div>
  </div>
  <div class="data-status-banner" id="dataStatusBanner" hidden></div>
</header>
