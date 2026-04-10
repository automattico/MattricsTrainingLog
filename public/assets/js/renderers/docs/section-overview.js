(function () {
  const M = window.Mattrics;
  M.docsSections = M.docsSections || [];
  M.docsSections.push({
    id: "docs-overview",
    label: "Overview",
    title: "What this app is",
    intro: "Mattrics Training Log is a private training dashboard with a static frontend, a small PHP API layer, and a Google Sheets-backed data pipeline.",
    body: `
      <div class="docs-flow">
        <div class="docs-flow-step">Strava</div>
        <div class="docs-flow-arrow">→</div>
        <div class="docs-flow-step">Make.com</div>
        <div class="docs-flow-arrow">→</div>
        <div class="docs-flow-step">Google Sheets</div>
        <div class="docs-flow-arrow">→</div>
        <div class="docs-flow-step">PHP API</div>
        <div class="docs-flow-arrow">→</div>
        <div class="docs-flow-step">Browser app</div>
        <div class="docs-flow-arrow">↔</div>
        <div class="docs-flow-step">Anthropic API</div>
      </div>

      <div class="docs-lead-grid">
        ${M.docsCards([
          { title: "Frontend app", text: "Vanilla JavaScript and CSS render the dashboard, fatigue map, sessions, AI pane, settings, and docs hub inside a single authenticated app shell." },
          { title: "Small PHP API", text: "PHP serves data, settings, AI proxying, and auth endpoints while keeping private config and credentials outside the public web root." },
          { title: "Sheets + Apps Script", text: "The dashboard reads a sanitized JSON snapshot that originates in Google Sheets and is exposed through Apps Script." },
          { title: "Passkeys", text: "WebAuthn passkeys protect the app and all state-changing endpoints." },
        ])}
      </div>
    `,
  });
}());
