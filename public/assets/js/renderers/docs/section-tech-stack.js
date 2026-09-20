(function () {
  const M = window.Mattrics;
  M.docsSections = M.docsSections || [];
  M.docsSections.push({
    id: "docs-tech-stack",
    label: "Tech stack",
    title: "The moving parts",
    intro: "The app is intentionally lightweight: browser-rendered UI, PHP for server-side boundaries, and external tooling only where it adds value.",
    body: `
      ${M.docsTable([
        ["Frontend", "Vanilla JavaScript modules under <code class=\"docs-code\">public/assets/js/</code> and CSS under <code class=\"docs-code\">public/assets/css/</code>. No bundler, no npm dependencies at runtime."],
        ["App shell", "A static HTML shell (<code class=\"docs-code\">public/index.html</code>) swaps views with a client-side router. Mattwarden serves it only after authentication."],
        ["API layer", "Flat scripts under <code class=\"docs-code\">api/</code> expose the product API. Every script rechecks Mattwarden authentication; mutations also require same-origin and CSRF validation."],
        ["Data source", "Google Sheets is the operational source of truth. Apps Script exposes a live JSON snapshot via webhook URL. The PHP data endpoint caches this into <code class=\"docs-code\">private/cache/training-data.json</code>."],
        ["Sync tooling", "Make.com automation pulls Strava activities every rolling UTC window and writes to the Google Sheet. The sync cursor is maintained in a sheet cell and advanced on each successful import."],
        ["Gate", "Mattwarden owns authentication, sessions, cookies, CSRF token generation, security headers, static serving, API dispatch, and logout."],
        ["AI", "Anthropic requests are proxied server-side via <code class=\"docs-code\">/api/ai</code>. The browser never receives the API key."],
        ["Security", "Application state and config live under <code class=\"docs-code\">MATTWARDEN_SITE_DIR/private</code>, outside every document root."],
      ])}
    `,
  });
}());
