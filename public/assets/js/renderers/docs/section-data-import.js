(function () {
  const M = window.Mattrics;
  M.docsSections = M.docsSections || [];
  M.docsSections.push({
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

      ${M.docsTable([
        ["Operational source", "Strava activity data is imported into a sheet by Make.com."],
        ["Sync cursor", "The rolling cursor lives in UTC and is advanced from the newest returned activity timestamp."],
        ["Exposed API", "Apps Script converts the sheet into JSON for the dashboard."],
        ["Local snapshot", "The PHP data endpoint caches a sanitized snapshot at <code class=\"docs-code\">private/cache/training-data.json</code>."],
        ["Important caveat", "Backdated or manual activities can fall behind the cursor and may require a manual reset."],
      ])}
    `,
  });
}());
