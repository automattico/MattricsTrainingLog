<div class="view active" id="view-dashboard">
  <section class="section-shell dashboard-shell">
    <div class="header-stats" id="dashboardOverview"></div>
  </section>
</div>

<div class="view" id="view-fatigue">
  <section class="section-shell fatigue-shell">
    <div id="fatigueOverview"></div>
    <div id="fatigueDocumentation"></div>
  </section>
</div>

<div class="view" id="view-sessions">
  <section class="section-shell">
    <div class="sessions-toolbar">
      <div class="filter-row" id="filterRow"></div>
      <div class="feed-display-tools">
        <div class="feed-mode-switch" aria-label="Session display mode">
          <button class="tl-sw-btn active" id="feedModeListBtn" onclick="setFeedMode('list',this)">List</button>
          <button class="tl-sw-btn" id="feedModeWeekBtn" onclick="setFeedMode('week',this)">Week</button>
          <button class="tl-sw-btn" id="feedModeMonthBtn" onclick="setFeedMode('month',this)">Month</button>
        </div>
      </div>
    </div>
    <div class="cards" id="cardList"></div>
  </section>
</div>

<div class="view" id="view-settings">
  <section class="section-shell settings-outer">
    <div id="settingsContent"></div>
  </section>
</div>

<div class="view" id="view-docs">
  <section class="section-shell docs-shell">
    <div id="docsContent"></div>
  </section>
</div>

<div class="view" id="view-ai">
  <section class="section-shell">
    <div class="ai-pane">
      <div class="section-kicker">Coach mode</div>
      <div class="ai-head">Today's <em>Workout</em></div>
      <div class="ai-desc" id="aiDesc"></div>
      <div class="api-note">
        For secure hosting, keep the Anthropic key on the server and call it through <code>api/ai.php</code>.
      </div>
      <div class="recent-preview" id="recentPreview">
        <div class="rp-label">Last 10 days</div>
        <div id="recentItems"></div>
      </div>
      <button class="gen-btn" onclick="generateWorkout()">Generate Workout</button>
      <div class="ai-thinking" id="aiThinking">Thinking...</div>
      <div class="ai-output" id="aiOutput">
        <div class="ai-output-label">Recommended session</div>
        <div class="ai-output-text" id="aiText"></div>
      </div>
    </div>
  </section>
</div>
