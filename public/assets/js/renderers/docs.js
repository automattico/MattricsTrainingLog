(function () {
  const M = window.Mattrics;

  function esc(value) {
    return M.esc ? M.esc(value) : String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function table(rows) {
    return `<table class="docs-table">
      <tbody>
        ${rows.map((row) => `<tr>
          <th scope="row">${esc(row[0])}</th>
          <td>${row[1]}</td>
        </tr>`).join("")}
      </tbody>
    </table>`;
  }

  function cards(items) {
    return `<div class="docs-card-grid">
      ${items.map((item) => `<article class="docs-card">
        <div class="docs-card-title">${esc(item.title)}</div>
        <div class="docs-card-text">${item.text}</div>
      </article>`).join("")}
    </div>`;
  }

  function list(items) {
    return `<ul class="docs-list">
      ${items.map((item) => `<li>${item}</li>`).join("")}
    </ul>`;
  }

  function subsection(title, body) {
    return `<section class="docs-subsection">
      <h3 class="docs-subsection-title">${esc(title)}</h3>
      ${body}
    </section>`;
  }

  function sectionButton(item, activeId) {
    const isActive = item.id === activeId;
    return `<button class="docs-nav-btn${isActive ? " active" : ""}" type="button" onclick="Mattrics.showDocsSection('${esc(item.id)}')">
      <span class="docs-nav-label">${esc(item.label)}</span>
    </button>`;
  }

  const sections = [
    {
      id: "docs-overview",
      label: "Overview",
      title: "What this app is",
      intro: "Mattrics Training Log is a private training dashboard with a static frontend, a small PHP API layer, and a Google Sheets-backed data pipeline.",
      body: `
        <div class="docs-lead-grid">
          ${cards([
            { title: "Frontend app", text: "Vanilla JavaScript and CSS render the dashboard, fatigue map, sessions, AI pane, settings, and docs hub inside a single authenticated app shell." },
            { title: "Small PHP API", text: "PHP serves data, settings, AI proxying, and auth endpoints while keeping private config and credentials outside the public web root." },
            { title: "Sheets + Apps Script", text: "The dashboard reads a sanitized JSON snapshot that originates in Google Sheets and is exposed through Apps Script." },
            { title: "Passkeys", text: "WebAuthn passkeys protect the app and all state-changing endpoints." },
          ])}
        </div>
      `,
    },
    {
      id: "docs-tech-stack",
      label: "Tech stack",
      title: "The moving parts",
      intro: "The app is intentionally lightweight: browser-rendered UI, PHP for server-side boundaries, and external tooling only where it adds value.",
      body: `
        ${table([
          ["Frontend", "Vanilla JavaScript modules under <code class=\"docs-code\">public/assets/js/</code> and CSS under <code class=\"docs-code\">public/assets/css/</code>."],
          ["App shell", "A single HTML shell swaps views with a client-side router and uses URL query state for deep links."],
          ["API layer", "<code class=\"docs-code\">public/api/</code> contains the data, auth, settings, and AI proxy endpoints."],
          ["Data source", "Google Sheets is the operational source of truth, exposed through Google Apps Script."],
          ["Sync tooling", "Make.com pulls Strava activity data into the sheet on a rolling UTC cursor."],
          ["Auth", "WebAuthn passkeys + PHP sessions + CSRF-protected state changes."],
          ["AI", "Anthropic requests are proxied server-side so the API key never appears in public assets."],
        ])}
      `,
    },
    {
      id: "docs-feature-map",
      label: "Feature map",
      title: "What each app area does",
      intro: "This is the product surface area the docs should explain and preserve.",
      body: `
        ${cards([
          { title: "Dashboard overview", text: "Summarizes training volume, recent activity, and recovery state." },
          { title: "Muscle fatigue map", text: "Shows the body map, readiness table, and fatigue tiers derived from recent load." },
          { title: "Sessions list and timeline", text: "Switches between flat activity cards and grouped timeline views with type filters." },
          { title: "AI Workout coach mode", text: "Generates a recommended session from recent activity and current fatigue state." },
          { title: "Settings profile and passkeys", text: "Edits the user profile and manages passkeys/recovery codes." },
          { title: "Documentation hub", text: "Central place for architecture, data flow, model notes, and operational boundaries." },
        ])}
      `,
    },
    {
      id: "docs-data-import",
      label: "Data import",
      title: "How activity data gets into the app",
      intro: "The import path is deliberately explicit so sync issues are easy to reason about.",
      body: `
        <div class="docs-flow">
          <div class="docs-flow-step">Strava</div>
          <div class="docs-flow-arrow">→</div>
          <div class="docs-flow-step">Make.com</div>
          <div class="docs-flow-arrow">→</div>
          <div class="docs-flow-step">Google Sheets</div>
          <div class="docs-flow-arrow">→</div>
          <div class="docs-flow-step">Apps Script JSON</div>
          <div class="docs-flow-arrow">→</div>
          <div class="docs-flow-step">private cache</div>
          <div class="docs-flow-arrow">→</div>
          <div class="docs-flow-step">Dashboard</div>
        </div>
        ${table([
          ["Operational source", "Strava activity data is imported into a sheet by Make.com."],
          ["Sync cursor", "The rolling cursor lives in UTC and is advanced from the newest returned activity timestamp."],
          ["Exposed API", "Apps Script converts the sheet into JSON for the dashboard."],
          ["Local snapshot", "The PHP data endpoint can serve a cached sanitized snapshot from <code class=\"docs-code\">private/cache/training-data.json</code>."],
          ["Important caveat", "Backdated or manual activities can fall behind the cursor and may require a manual reset."],
        ])}
      `,
    },
    {
      id: "docs-domain-model",
      label: "Domain model",
      title: "Core app entities",
      intro: "The app is small enough that the domain model is mostly a handful of recurring concepts rather than a formal ORM.",
      body: `
        ${table([
          ["Activity", "One imported row from the sheet, normalized in <code class=\"docs-code\">state.allData</code> and rendered into dashboards, sessions, and fatigue analysis."],
          ["Activity type", "Canonical movement category such as Run, Rowing, WeightTraining, or Yoga."],
          ["Muscle region", "A tracked body area in <code class=\"docs-code\">MUSCLE_REGIONS</code> with a key, label, color, and body-map placement."],
          ["Fatigue snapshot", "The computed readiness state for each muscle region over the current lookback window."],
          ["User settings", "Profile inputs used for personalization such as body weight, height, sex, birthday, and experience level."],
          ["Passkey credential", "A stored WebAuthn credential plus metadata and recovery-code records."],
          ["Sync metadata", "Source, staleness, and timestamp information attached to imported data."],
        ])}
        <div class="docs-note">
          At a high level: activities feed the fatigue engine, the fatigue engine produces muscle-region snapshots, and the UI layers read those snapshots to render the dashboard, fatigue map, and AI prompt context.
        </div>
      `,
    },
    {
      id: "docs-auth-passkeys",
      label: "Auth / passkeys",
      title: "How authentication is structured",
      intro: "Passkeys are the primary gate for the app and for mutating API calls.",
      body: `
        ${table([
          ["Session name", "<code class=\"docs-code\">mattrics_sess</code>."],
          ["Auth flag", "<code class=\"docs-code\">$_SESSION['mattrics_authed']</code>."],
          ["Register", "Uses a WebAuthn creation challenge and stores the credential in private storage."],
          ["Login", "Uses a WebAuthn assertion challenge and regenerates the session on success."],
          ["Recovery", "One-time hashed recovery codes unlock passkey replacement."],
          ["Storage boundary", "Credentials and challenges live in <code class=\"docs-code\">private/</code>, not public assets."],
        ])}
      `,
    },
    {
      id: "docs-fatigue-model",
      label: "Fatigue model",
      title: "Decay-based load accumulation",
      intro: "Each activity contributes muscle-specific stimulus, which decays over time and is normalized into a 0–100 fatigue score.",
      body: `
        ${subsection("Purpose", `
          <p class="docs-copy">
            The fatigue model estimates how much useful training stress still remains in each muscle region. It is not
            trying to diagnose soreness or injury. It answers a narrower product question: given the recent activity log,
            which muscles are likely ready now, which are still recovering, and which should probably wait.
          </p>
          <p class="docs-copy">
            The model works in three stages: turn each activity into per-muscle stimulus, decay older stimulus over time,
            then normalize the remaining load against a calibrated per-muscle reference.
          </p>
        `)}

        ${subsection("Core formula", `
          <div class="docs-formula">rawLoad = sum(stimulus * 0.5^(hoursAgo / halfLife))
score   = min(100, rawLoad / normalizationLoad * 100)</div>
          ${table([
            ["Stimulus", "The muscle-specific load contributed by one activity before decay."],
            ["hoursAgo", "Elapsed hours from the activity timestamp to now."],
            ["halfLife", "Per-muscle recovery half-life in hours. After one half-life, 50% of that activity's stimulus remains."],
            ["rawLoad", "All remaining decayed stimulus for one muscle across the lookback window."],
            ["normalizationLoad", "The per-muscle reference for a fully loaded 100% score."],
            ["score", "The displayed fatigue score, capped at 100."],
          ])}
        `)}

        ${subsection("Model parameters", `
          ${table([
            ["Lookback window", "<code class=\"docs-code\">10</code> days rolling from now."],
            ["Estimated bodyweight", "<code class=\"docs-code\">75 kg</code> fallback when bodyweight is needed and no profile value is available."],
            ["Bodyweight load factor", "<code class=\"docs-code\">0.4</code>, used to estimate bodyweight exercise load when no external weight is logged."],
            ["Default RPE", "<code class=\"docs-code\">7.5</code> when a Hevy set has no usable RPE value."],
            ["Small threshold ratio", "<code class=\"docs-code\">0.02</code>, used to ignore tiny residual loads."],
            ["Recovery threshold ratio", "<code class=\"docs-code\">0.25</code>, aligned with the Fresh tier boundary."],
            ["Strength load divisor", "<code class=\"docs-code\">1500</code>, converting set volume into model-scale stimulus."],
          ])}
        `)}

        ${subsection("Decay and half-lives", `
          <p class="docs-copy">
            Fatigue fades exponentially. A muscle with a 72 hour half-life keeps half of an activity's stimulus after
            72 hours, one quarter after 144 hours, and one eighth after 216 hours. This makes recent sessions matter most
            while still allowing heavy sessions to influence the map for several days.
          </p>
          <div class="docs-formula">remaining = stimulus * 0.5^(hoursElapsed / halfLife)</div>
          ${table([
            ["72 h", "Chest, upper back, lower back, quadriceps, hamstrings, gluteal, adductors."],
            ["60 h", "Deltoids, trapezius, calves."],
            ["48 h", "Triceps, biceps, abs, obliques."],
          ])}
          <p class="docs-copy">
            Activity timestamps are normalized to noon on the activity date. That keeps sheet date handling simple, but
            it also means the model does not use sub-day precision from the original activity source.
          </p>
        `)}

        ${subsection("Normalization loads", `
          <p class="docs-copy">
            Different muscles tolerate and accumulate load differently, so each muscle has its own 100% reference. A raw
            load of <code class=\"docs-code\">1.2</code> means different things for upper back than for obliques.
          </p>
          ${table([
            ["Upper back", "2.45"],
            ["Chest", "2.40"],
            ["Gluteal", "2.30"],
            ["Quadriceps", "2.25"],
            ["Deltoids", "2.20"],
            ["Hamstrings", "2.05"],
            ["Trapezius", "1.90"],
            ["Lower back", "1.85"],
            ["Abs", "1.70"],
            ["Triceps", "1.65"],
            ["Biceps", "1.55"],
            ["Calves", "1.55"],
            ["Adductors", "1.50"],
            ["Obliques", "1.45"],
          ])}
        `)}

        ${subsection("Recovery threshold", `
          <p class="docs-copy">
            Recovery is considered complete when the decayed raw load falls to or below 25% of that muscle's normalization
            load. Above that threshold, the model estimates how many hours remain until the muscle crosses back into Fresh.
          </p>
          <div class="docs-formula">threshold     = normalizationLoad * 0.25
recoveryHours = halfLife * log2(rawLoad / threshold)</div>
          ${table([
            ["Below threshold", "Fresh, train now."],
            ["Above threshold", "Recovery hours are calculated and used to group the muscle into tomorrow or later."],
            ["No raw load", "Shown as no recent load, but still grouped as train today."],
          ])}
        `)}

        ${subsection("Fatigue tiers and readiness", `
          ${table([
            ["No recent load", "Raw load is zero or effectively zero. Body map state: <code class=\"docs-code\">none</code>. Readiness: train today."],
            ["Fresh", "0-24%. Body map state: <code class=\"docs-code\">fresh</code>. Readiness: train today."],
            ["Recovering", "25-49%. Body map state: <code class=\"docs-code\">recovering</code>. Readiness: train tomorrow if recovery is within about 24 hours."],
            ["Fatigued", "50-74%. Body map state: <code class=\"docs-code\">fatigued</code>. Readiness: needs more recovery."],
            ["Highly fatigued", "75-100%. Body map state: <code class=\"docs-code\">high</code>. Readiness: needs more recovery."],
          ])}
          <p class="docs-copy">
            The table columns in the Muscle Fatigue Map are sorted from soonest ready to latest ready, then by fatigue score
            and configured muscle order. This makes the view practical for choosing the next session rather than just
            inspecting raw percentages.
          </p>
        `)}

        ${subsection("Hevy workout parsing", `
          <p class="docs-copy">
            If an activity description starts with <code class=\"docs-code\">Logged with Hevy</code>, the model parses the
            workout into exercises and sets. Each exercise name is lowercased and matched by substring against known
            pattern groups. First match wins.
          </p>
          <div class="docs-formula">load         = weightKg * reps * effortFactor
effortFactor = 0.5 + RPE / 10
bodyweight   = estimatedBodyweightKg * 0.4
scaledLoad   = load / 1500</div>
          ${table([
            ["RPE 6", "Effort factor 1.10."],
            ["RPE 7", "Effort factor 1.20."],
            ["RPE 8", "Effort factor 1.30."],
            ["RPE 9", "Effort factor 1.40."],
            ["RPE 10", "Effort factor 1.50."],
            ["Time sets", "Time-based sets such as 3 min or 45 sec are skipped because they do not provide load in this model."],
          ])}
        `)}

        ${subsection("Exercise pattern groups", `
          ${table([
            ["bench / push-up / pushup / chest fly / pec deck / dip", "Chest primary."],
            ["incline press / incline bench / chest press", "Chest with incline emphasis."],
            ["row / face pull", "Upper back primary."],
            ["pulldown / pull-up / pullup / chin-up / chinup", "Upper back width."],
            ["deadlift / rdl / romanian deadlift", "Hamstrings and gluteal."],
            ["shoulder press / overhead press / arnold press / lateral raise / front raise / rear delt", "Deltoids primary."],
            ["curl / hammer", "Biceps primary."],
            ["triceps / pushdown / skull crusher", "Triceps primary."],
            ["plank / crunch / twist / dead bug / hollow / sit up / leg raise / russian twist", "Abs and core."],
            ["hip thrust / glute bridge", "Gluteal primary."],
            ["calf raise", "Calves primary."],
            ["squat / lunge / step up / stepup / leg press / split squat", "Quadriceps primary."],
          ])}
          <p class="docs-copy">
            To model a new exercise, add a substring pattern to the right group in
            <code class=\"docs-code\">getExerciseMuscleMapping()</code> inside <code class=\"docs-code\">core/hevy-parser.js</code>.
          </p>
        `)}

        ${subsection("Non-Hevy activity types", `
          <p class="docs-copy">
            If the activity is not a parsed Hevy workout, the model uses the activity <code class=\"docs-code\">Type</code>
            field and applies hardcoded muscle weights. Duration scales the stimulus, with caps so very long sessions do
            not grow without bound.
          </p>
          <div class="docs-formula">sportFactor    = clamp(durationMin / 60, 0.70, 1.45)
strengthFactor = clamp(durationMin / 45, 0.85, 1.60)
stimulus       = baseWeights * factor</div>
          ${table([
            ["Run", "Quads, hamstrings, calves, gluteal."],
            ["Hike", "Quads, hamstrings, calves, gluteal, heavier than run."],
            ["Ride", "Quads dominant, plus gluteal and hamstrings."],
            ["Canoeing / Canoe", "Upper back, deltoids, trapezius, biceps, abs."],
            ["WaterSport", "Similar to canoeing, slightly less intense."],
            ["Rowing", "Full body: upper/lower back, quads, hamstrings, biceps."],
            ["Surfing", "Upper back and deltoids for paddling; abs and obliques for pop-up work."],
            ["Yoga", "Abs, obliques, and light stabilizers."],
            ["Walk", "Light quads, hamstrings, calves."],
            ["WeightTraining / Workout", "Generic light full-body fallback."],
            ["Unknown type", "Zero stimulus."],
          ])}
        `)}

        ${subsection("Fallbacks and edge cases", `
          ${list([
            "An unmatched Hevy exercise contributes zero muscle-specific stimulus.",
            "If no exercise in a Hevy session matches any pattern, the entire session falls back to the generic WeightTraining mapping.",
            "Very small residual loads are filtered out by the small threshold so ancient tiny fatigue does not clutter the map.",
            "Scores are capped at 100 even when raw load exceeds the normalization reference.",
            "The model is deterministic from the imported activity rows plus user settings; it does not learn or adjust automatically.",
          ])}
        `)}
      `,
    },
    {
      id: "docs-module-map",
      label: "Module map / path keys",
      title: "Where the important code lives",
      intro: "The app is dependency-ordered, so the docs should mirror the actual load order and the files that define each concept.",
      body: `
        ${table([
          ["Core constants", "<code class=\"docs-code\">core/constants.js</code> defines the canonical types, muscle regions, and fatigue config."],
          ["State shape", "<code class=\"docs-code\">core/state.js</code> defines the global app state."],
          ["Fatigue engine", "<code class=\"docs-code\">core/fatigue-engine.js</code> computes muscle load and fatigue."],
          ["Fatigue tiers", "<code class=\"docs-code\">core/fatigue-tiers.js</code> maps scores to readiness language."],
          ["Body-map paths", "<code class=\"docs-code\">body-map-team-buildr.js</code> supplies the SVG path data and <code class=\"docs-code\">slugToKey</code> path-key mapping."],
          ["Render flow", "<code class=\"docs-code\">renderers/orchestrator.js</code> handles view switching and data refresh."],
          ["Path keys", "The body map uses region slugs on the SVG paths, then resolves them back to muscle keys for fatigue rendering."],
        ])}
      `,
    },
    {
      id: "docs-body-map",
      label: "Body map assets",
      title: "Where the body figure comes from",
      intro: "The body map is vendored SVG path data, not a runtime dependency on the upstream React Native package.",
      body: `
        ${table([
          ["Source", "<code class=\"docs-code\">@teambuildr/react-native-body-highlighter</code> v3.0.7."],
          ["What is shipped", "Only the male front/back outline and the fatigue-relevant muscle path groups."],
          ["Where it lives", "<code class=\"docs-code\">public/assets/js/body-map-team-buildr.js</code>."],
          ["Why it is vendored", "So the browser can render inline SVG without bundling the upstream React Native runtime."],
        ])}
      `,
    },
    {
      id: "docs-css-guide",
      label: "CSS guide",
      title: "Which stylesheet owns what",
      intro: "The styles are split by function so future changes stay scoped and predictable.",
      body: `
        ${table([
          ["Layout", "<code class=\"docs-code\">layout.css</code> for shell, header, nav, and section scaffolding."],
          ["Buttons", "<code class=\"docs-code\">buttons.css</code> for nav buttons, filter pills, icon buttons, and shared hover/focus states."],
          ["Dashboard", "<code class=\"docs-code\">dashboard.css</code> for overview cards and recent-session content."],
          ["Fatigue", "<code class=\"docs-code\">fatigue.css</code> for the body figure, readiness tables, and fatigue colors."],
          ["Sessions", "<code class=\"docs-code\">sessions.css</code> for activity cards, filters, and timeline grouping."],
          ["Docs", "<code class=\"docs-code\">docs.css</code> for the documentation hub and in-view docs callouts."],
          ["Settings", "<code class=\"docs-code\">settings.css</code> for the profile form and passkey UI."],
        ])}
      `,
    },
    {
      id: "docs-deployment",
      label: "Deployment / security",
      title: "Public versus private boundaries",
      intro: "The deployment model is intentionally strict: only public assets go to the web root, and secrets remain private.",
      body: `
        ${table([
          ["Deployable root", "<code class=\"docs-code\">public/</code> only."],
          ["Private runtime", "<code class=\"docs-code\">private/</code> holds config, caches, passkeys, and auth state."],
          ["Config", "<code class=\"docs-code\">private/config.php</code> stays off the public web root."],
          ["AI", "The Anthropic key stays server-side and is accessed through <code class=\"docs-code\">public/api/ai.php</code>."],
          ["Validation", "The deploy flow is expected to run validation and smoke tests before pushing live."],
          ["Auth policy", "HTTPS, HttpOnly cookies, SameSite Strict, and CSRF-protected state changes."],
        ])}
      `,
    },
  ];

  function getActiveSectionId(id) {
    if (sections.some((section) => section.id === id)) return id;
    const hash = window.location.hash ? window.location.hash.slice(1) : "";
    if (sections.some((section) => section.id === hash)) return hash;
    return "docs-overview";
  }

  function renderSection(section) {
    return `<section class="docs-section docs-active-panel" id="${esc(section.id)}">
      <div class="docs-section-head">
        <h2 class="docs-section-title">${esc(section.title)}</h2>
        <p class="docs-section-intro">${esc(section.intro)}</p>
      </div>
      <div class="docs-section-body">${section.body}</div>
    </section>`;
  }

  M.showDocsSection = function showDocsSection(id) {
    M.renderDocsView(id);
  };

  M.renderDocsView = function renderDocsView(id) {
    const mount = document.getElementById("docsContent");
    if (!mount) return;
    const activeId = getActiveSectionId(id);
    const activeSection = sections.find((section) => section.id === activeId) || sections[0];

    if (window.history && window.history.replaceState) {
      try {
        const url = new URL(window.location.href);
        url.hash = activeId;
        window.history.replaceState({}, "", url.toString());
      } catch {
        // Hash persistence is helpful but not required.
      }
    }

    mount.innerHTML = `
      <div class="docs-page">
        <header class="docs-hero">
          <h1 class="docs-title">The whole app, documented</h1>
          <p class="docs-intro">
            This hub collects the app architecture, data flow, domain model, implementation notes, and operational
            boundaries in one place.
          </p>
        </header>
        <div class="docs-layout">
          <nav class="docs-sidebar" aria-label="Documentation sections">
            ${sections.map((section) => sectionButton(section, activeId)).join("")}
          </nav>
          <div class="docs-section-list">
            ${renderSection(activeSection)}
          </div>
        </div>
      </div>
    `;
  };
}());
