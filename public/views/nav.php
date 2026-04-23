<nav class="nav" aria-label="Views">
  <button class="nav-btn active" onclick="showView('dashboard',this)">Dashboard</button>
  <button class="nav-btn" onclick="showView('fatigue',this)">Muscle Fatigue Map</button>
  <button class="nav-btn" onclick="showView('sessions',this)">Sessions</button>
  <button class="nav-btn" onclick="showView('exercises',this)">Exercises</button>
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

<!-- Hamburger (drawer style only) -->
<button class="nav-hamburger" onclick="toggleDrawer()" aria-label="Open menu" aria-expanded="false" id="navHamburger">
  <span></span><span></span><span></span>
</button>

<!-- Drawer overlay + panel (drawer style only) -->
<div class="nav-drawer-overlay" onclick="toggleDrawer()"></div>
<div class="nav-drawer" id="navDrawer" role="dialog" aria-label="Navigation">
  <button class="nav-drawer-btn active" onclick="showView('dashboard',this);toggleDrawer()">Dashboard</button>
  <button class="nav-drawer-btn" onclick="showView('fatigue',this);toggleDrawer()">Muscle Fatigue Map</button>
  <button class="nav-drawer-btn" onclick="showView('sessions',this);toggleDrawer()">Sessions</button>
  <button class="nav-drawer-btn" onclick="showView('exercises',this);toggleDrawer()">Exercises</button>
  <button class="nav-drawer-btn nav-drawer-btn--ai" onclick="showView('ai',this);toggleDrawer()">
    <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true">
      <path d="M8 1.5l1.15 3.35L12.5 6l-3.35 1.15L8 10.5 6.85 7.15 3.5 6l3.35-1.15L8 1.5Z" fill="currentColor"/>
      <path d="M12.75 9.5l.68 1.98 1.97.68-1.97.68-.68 1.98-.68-1.98-1.97-.68 1.97-.68.68-1.98Z" fill="currentColor"/>
    </svg>
    AI Workout
  </button>
  <button class="nav-drawer-btn" onclick="showView('docs',this);toggleDrawer()">Docs</button>
  <button class="nav-drawer-btn" onclick="showView('settings',this);toggleDrawer()">Settings</button>
</div>

<!-- Bottom tab bar (bottom style only) -->
<nav class="nav-bottom" aria-label="Views">
  <button class="nav-bottom-btn active" onclick="showView('dashboard',this)">
    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
      <rect x="2" y="2" width="7" height="7" rx="1.5" fill="currentColor" opacity=".7"/>
      <rect x="11" y="2" width="7" height="7" rx="1.5" fill="currentColor" opacity=".7"/>
      <rect x="2" y="11" width="7" height="7" rx="1.5" fill="currentColor" opacity=".7"/>
      <rect x="11" y="11" width="7" height="7" rx="1.5" fill="currentColor" opacity=".7"/>
    </svg>
    <span>Dashboard</span>
  </button>
  <button class="nav-bottom-btn" onclick="showView('fatigue',this)">
    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
      <ellipse cx="10" cy="5.5" rx="3" ry="3.5" fill="currentColor" opacity=".7"/>
      <path d="M4 18c0-3.314 2.686-6 6-6s6 2.686 6 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" opacity=".7"/>
      <path d="M10 12v-2.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" opacity=".7"/>
      <path d="M7.5 14l-2-1.5M12.5 14l2-1.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" opacity=".7"/>
    </svg>
    <span>Fatigue</span>
  </button>
  <button class="nav-bottom-btn" onclick="showView('sessions',this)">
    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
      <rect x="2" y="4" width="16" height="2" rx="1" fill="currentColor" opacity=".7"/>
      <rect x="2" y="9" width="16" height="2" rx="1" fill="currentColor" opacity=".7"/>
      <rect x="2" y="14" width="10" height="2" rx="1" fill="currentColor" opacity=".7"/>
    </svg>
    <span>Sessions</span>
  </button>
  <button class="nav-bottom-btn" onclick="showView('exercises',this)">
    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
      <path d="M5 4.5h10a1.5 1.5 0 0 1 1.5 1.5v8A1.5 1.5 0 0 1 15 15.5H5A1.5 1.5 0 0 1 3.5 14V6A1.5 1.5 0 0 1 5 4.5Z" stroke="currentColor" stroke-width="1.5" opacity=".7"/>
      <path d="M6.5 8h7M6.5 11h4.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" opacity=".7"/>
      <circle cx="14.5" cy="13.5" r="1.2" fill="currentColor" opacity=".7"/>
    </svg>
    <span>Exercises</span>
  </button>
  <button class="nav-bottom-btn nav-bottom-btn--ai" onclick="showView('ai',this)">
    <svg width="20" height="20" viewBox="0 0 16 16" fill="none" aria-hidden="true">
      <path d="M8 1.5l1.15 3.35L12.5 6l-3.35 1.15L8 10.5 6.85 7.15 3.5 6l3.35-1.15L8 1.5Z" fill="currentColor"/>
      <path d="M12.75 9.5l.68 1.98 1.97.68-1.97.68-.68 1.98-.68-1.98-1.97-.68 1.97-.68.68-1.98Z" fill="currentColor"/>
    </svg>
    <span>AI</span>
  </button>
  <button class="nav-bottom-btn" onclick="showView('docs',this)">
    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
      <rect x="4" y="2" width="12" height="16" rx="2" stroke="currentColor" stroke-width="1.6" opacity=".7"/>
      <path d="M7 7h6M7 10h6M7 13h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" opacity=".7"/>
    </svg>
    <span>Docs</span>
  </button>
  <button class="nav-bottom-btn" onclick="showView('settings',this)">
    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
      <circle cx="10" cy="10" r="2.5" stroke="currentColor" stroke-width="1.6" opacity=".7"/>
      <path d="M10 2v2M10 16v2M2 10h2M16 10h2M4.22 4.22l1.42 1.42M14.36 14.36l1.42 1.42M4.22 15.78l1.42-1.42M14.36 5.64l1.42-1.42" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" opacity=".7"/>
    </svg>
    <span>Settings</span>
  </button>
</nav>
