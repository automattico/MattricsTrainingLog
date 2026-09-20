(function () {
  const M = window.Mattrics;
  M.docsSections = M.docsSections || [];
  M.docsSections.push({
    id: "docs-module-map",
    label: "Module map",
    title: "Repository and runtime layout",
    intro: "The Git layout maps directly to the three application directories below the Mattwarden site root.",
    body: `
      ${M.docsTree(`public/                 static HTML, CSS, JS, icons, fonts
api/                    flat authenticated endpoint scripts
  session.php           CSRF bootstrap and app version
  data.php              training data
  settings.php          athlete settings
  connectors.php        connector administration
  exercises.php         exercise configuration and PATH_INFO routes
  ai.php                server-side Anthropic proxy
lib/                    shared non-addressable PHP modules
private/                config example and initial JSON seeds
tests/                  PHP, router, and browser-independent JS tests
scripts/dev-server.sh   local gate-compatible server`) }
      ${M.docsTable([
        ["Remote public", "<code class=\"docs-code\">sites/mattrics/public</code>"],
        ["Remote API", "<code class=\"docs-code\">sites/mattrics/api</code>"],
        ["Remote library", "<code class=\"docs-code\">sites/mattrics/lib</code>"],
        ["Remote private", "<code class=\"docs-code\">sites/mattrics/private</code>"],
      ])}
    `,
  });
}());
