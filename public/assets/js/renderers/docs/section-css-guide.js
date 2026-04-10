(function () {
  const M = window.Mattrics;
  M.docsSections = M.docsSections || [];
  M.docsSections.push({
    id: "docs-css-guide",
    label: "CSS guide",
    title: "Which stylesheet owns what",
    intro: "The styles are split by function so future changes stay scoped and predictable.",
    body: `
      ${M.docsTable([
        ["Layout", "<code class=\"docs-code\">layout.css</code> for shell, header, nav, and section scaffolding."],
        ["Buttons", "<code class=\"docs-code\">buttons.css</code> for nav buttons, filter pills, icon buttons, and shared hover/focus states."],
        ["Dashboard", "<code class=\"docs-code\">dashboard.css</code> for overview cards and recent-session content."],
        ["Fatigue", "<code class=\"docs-code\">fatigue.css</code> for the body figure, readiness tables, and fatigue colors."],
        ["Sessions", "<code class=\"docs-code\">sessions.css</code> for activity cards, filters, and timeline grouping."],
        ["Docs", "<code class=\"docs-code\">docs.css</code> for the documentation hub and in-view docs callouts."],
        ["Settings", "<code class=\"docs-code\">settings.css</code> for the profile form and passkey UI."],
      ])}
    `,
  });
}());
