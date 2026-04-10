(function () {
  const M = window.Mattrics;
  M.docsSections = M.docsSections || [];
  M.docsSections.push({
    id: "docs-domain-model",
    label: "Domain model",
    title: "Core app entities",
    intro: "The app is small enough that the domain model is mostly a handful of recurring concepts rather than a formal ORM.",
    body: `
      ${M.docsTable([
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
  });
}());
