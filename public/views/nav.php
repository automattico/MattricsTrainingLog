<nav class="nav" aria-label="Views">
  <button class="nav-btn active" onclick="showView('dashboard',this)">Dashboard</button>
  <button class="nav-btn" onclick="showView('fatigue',this)">Muscle Fatigue Map</button>
  <button class="nav-btn" onclick="showView('sessions',this)">Sessions</button>
  <button class="nav-btn nav-btn--ai ai-top-btn" onclick="showView('ai', this)" title="Open AI workout" aria-label="Open AI workout">
    <svg class="nav-btn-icon" width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true">
      <path d="M8 1.5l1.15 3.35L12.5 6l-3.35 1.15L8 10.5 6.85 7.15 3.5 6l3.35-1.15L8 1.5Z" fill="currentColor"/>
      <path d="M12.75 9.5l.68 1.98 1.97.68-1.97.68-.68 1.98-.68-1.98-1.97-.68 1.97-.68.68-1.98Z" fill="currentColor"/>
    </svg>
    <span>AI Workout</span>
  </button>
  <button class="nav-btn nav-btn--docs" onclick="showView('docs',this)">Docs</button>
  <button class="nav-btn nav-btn--settings" onclick="showView('settings',this)">Settings</button>
</nav>
