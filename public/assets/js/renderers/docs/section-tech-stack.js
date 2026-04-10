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
        ["App shell", "A single HTML shell (<code class=\"docs-code\">public/index.php</code>) swaps views with a client-side router and uses URL hash state for deep linking. Session validation happens server-side on page load."],
        ["API layer", "<code class=\"docs-code\">public/api/</code> contains stateless endpoints for data fetch, auth, settings mutations, and AI proxy. All endpoints require valid session + passkey assertion or CSRF token."],
        ["Data source", "Google Sheets is the operational source of truth. Apps Script exposes a live JSON snapshot via webhook URL. The PHP data endpoint caches this into <code class=\"docs-code\">private/cache/training-data.json</code>."],
        ["Sync tooling", "Make.com automation pulls Strava activities every rolling UTC window and writes to the Google Sheet. The sync cursor is maintained in a sheet cell and advanced on each successful import."],
        ["Auth", "WebAuthn passkeys are the primary auth mechanism. PHP sessions store the auth state. State-changing endpoints require CSRF tokens for form submissions or session validation for API calls."],
        ["AI", "Anthropic API requests are proxied server-side via <code class=\"docs-code\">public/api/ai.php</code>. The frontend sends a prompt and context, the backend manages the API key and streams responses."],
        ["Security", "HTTPS enforced in production. Session cookies use HttpOnly, Secure, and SameSite=Strict flags. Credentials and config live outside the public web root."],
      ])}
    `,
  });
}());
