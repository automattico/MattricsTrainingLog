(function () {
  const M = window.Mattrics;
  M.docsSections = M.docsSections || [];
  M.docsSections.push({
    id: "docs-feature-map",
    label: "Feature map",
    title: "What each app area does",
    intro: "This is the product surface area the docs should explain and preserve.",
    body: `
      ${M.docsCards([
        { title: "Dashboard overview", text: "Summarizes training volume, recent activity, and recovery state." },
        { title: "Muscle fatigue map", text: "Shows the body map, readiness table, and fatigue tiers derived from recent load." },
        { title: "Sessions list and timeline", text: "Switches between flat activity cards and grouped timeline views with type filters." },
        { title: "AI Workout coach mode", text: "Generates a recommended session from recent activity and current fatigue state." },
        { title: "Settings profile and passkeys", text: "Edits the user profile and manages passkeys/recovery codes." },
        { title: "Documentation hub", text: "Central place for architecture, data flow, model notes, and operational boundaries." },
      ])}
    `,
  });
}());
