(function () {
  const M = window.Mattrics;
  M.docsSections = M.docsSections || [];
  M.docsSections.push({
    id: "docs-deployment",
    label: "Deployment / security",
    title: "Public versus private boundaries",
    intro: "The deployment model is intentionally strict: only public assets go to the web root, and secrets remain private. Security layers are enforced at the server, session, and credential level.",
    body: `
      ${M.docsTable([
        ["Deployable root", "<code class=\"docs-code\">public/</code> only. The web root serves HTML, CSS, and JavaScript. API endpoints under <code class=\"docs-code\">public/api/</code> are the only server-side entry points."],
        ["Private runtime", "<code class=\"docs-code\">private/</code> is outside the web root. It holds the config, app secrets, cached data, passkey credentials, and session state. Never deploy this directory."],
        ["Config isolation", "<code class=\"docs-code\">private/config.php</code> contains database credentials, API keys, and deployment-specific settings. It is gitignored and loaded server-side only."],
        ["API credentials", "The Anthropic API key is read from config and never exposed to the browser. All AI requests are proxied through <code class=\"docs-code\">public/api/ai.php</code>."],
        ["Data cache", "<code class=\"docs-code\">private/cache/training-data.json</code> holds the latest snapshot from Google Sheets. Stale cache is served if the upstream API is unreachable."],
        ["HTTPS enforcement", "Production must enforce HTTPS. Mixed-content warnings will occur if http resources are requested over HTTPS connections."],
        ["Session cookies", "The <code class=\"docs-code\">mattrics_sess</code> cookie uses flags: HttpOnly (no JS access), Secure (HTTPS only), SameSite=Strict (same-site requests only)."],
        ["CSRF tokens", "Every state-changing request (POST, PUT, DELETE) validates a CSRF token. The token is unique per session and regenerated on login."],
        ["Input validation", "All user input is validated server-side. API endpoints sanitize and type-check parameters before processing."],
        ["XSS prevention", "Template rendering escapes HTML entities. Passkey challenges and stored data are never directly inserted into the DOM as code."],
        ["SQL injection defense", "Credentials and data are stored in JSON files, not a SQL database, eliminating SQL injection risk. File access is properly constrained."],
        ["Deployment checklist", "Before deploy: run security tests, verify HTTPS is enabled, check that <code class=\"docs-code\">private/</code> is not accessible, validate API keys are in config not hardcoded, smoke test passkey login."],
      ])}

      ${M.docsSubsection("File and directory permissions", `
        <p class="docs-copy">
          The <code class="docs-code">private/</code> directory should have restrictive permissions (no public read). Web server process must have write access to
          <code class="docs-code">private/cache/</code> and <code class="docs-code">private/storage/</code>. Configuration files should be readable by the server process only.
        </p>
      `)}

      ${M.docsSubsection("Content Security Policy (CSP)", `
        <p class="docs-copy">
          The app does not emit inline scripts. All JavaScript is loaded from same-origin. Consider setting strict CSP headers to prevent inline script injection
          and restrict external script sources. Example: <code class="docs-code">script-src 'self'</code>.
        </p>
      `)}

      ${M.docsSubsection("Rate limiting", `
        <p class="docs-copy">
          The AI endpoint should implement rate limiting to prevent abuse. Consider limiting requests per session or per time window. Failed authentication attempts
          should be logged and monitored for brute-force patterns.
        </p>
      `)}

      ${M.docsSubsection("Logging and monitoring", `
        <p class="docs-copy">
          Authentication failures, API errors, and state mutations should be logged server-side with timestamp and user context. Logs should not contain sensitive
          data like recovery codes or plaintext credentials. Logs should be protected and reviewed regularly.
        </p>
      `)}

      ${M.docsSubsection("Dependency management", `
        <p class="docs-copy">
          The frontend has zero npm dependencies at runtime. This eliminates supply-chain risk and makes the codebase easier to audit. The backend uses only
          built-in PHP functionality and the Google Sheets API. Keep PHP version current and monitor security advisories.
        </p>
      `)}

      ${M.docsSubsection("Database and backup security", `
        <p class="docs-copy">
          Passkey credentials should be backed up securely. Recovery codes should be stored separately from credentials. Consider encrypting sensitive data at rest.
          Backups should be encrypted and stored off-site. Restore procedures should be tested regularly.
        </p>
      `)}
    `,
  });
}());
