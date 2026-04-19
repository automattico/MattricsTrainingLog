(function () {
  const M = window.Mattrics;
  M.docsSections = M.docsSections || [];
  M.docsSections.push({
    id: "docs-auth-passkeys",
    label: "Auth / passkeys",
    title: "How authentication is structured",
    intro: "Passkeys are the primary gate for the app and for mutating API calls. The system combines WebAuthn (FIDO2) credentials with PHP sessions and recovery codes.",
    body: `
      ${M.docsSubsection("Login flow", `
        <div class="docs-flow docs-flow--vertical">
          <div class="docs-flow-step">Browser requests assertion challenge</div>
          <div class="docs-flow-arrow">↓</div>
          <div class="docs-flow-step">PHP returns challenge</div>
          <div class="docs-flow-arrow">↓</div>
          <div class="docs-flow-step">Device signs with passkey</div>
          <div class="docs-flow-arrow">↓</div>
          <div class="docs-flow-step">Browser POSTs signed assertion to verify.php</div>
          <div class="docs-flow-arrow">↓</div>
          <div class="docs-flow-step">PHP verifies signature + counter</div>
          <div class="docs-flow-arrow">↓</div>
          <div class="docs-flow-step">Session cookie set → redirect to app</div>
        </div>
      `)}

      ${M.docsSubsection("Registration flow", `
        <div class="docs-flow docs-flow--vertical">
          <div class="docs-flow-step">Browser requests creation challenge</div>
          <div class="docs-flow-arrow">↓</div>
          <div class="docs-flow-step">Device generates keypair + attestation</div>
          <div class="docs-flow-arrow">↓</div>
          <div class="docs-flow-step">Browser POSTs public key to register.php</div>
          <div class="docs-flow-arrow">↓</div>
          <div class="docs-flow-step">PHP verifies attestation + stores credential</div>
          <div class="docs-flow-arrow">↓</div>
          <div class="docs-flow-step">Recovery codes generated and shown once</div>
        </div>
      `)}

      ${M.docsSubsection("Session and credential details", `
        ${M.docsTable([
          ["Session name", "<code class=\"docs-code\">mattrics_sess</code>. Started on first page load and validated on every request that requires auth."],
          ["Auth flag", "<code class=\"docs-code\">$_SESSION['mattrics_authed']</code>. Set to true after a successful WebAuthn assertion."],
          ["Credential storage", "WebAuthn credentials stored in <code class=\"docs-code\">private/storage/passkeys.json</code> with public key, counter, transports, and creation timestamp."],
          ["Challenge lifecycle", "Ephemeral — created per request, validated within the same session context, discarded after 10 minutes."],
          ["Recovery codes", "One-time hashed codes generated during passkey setup. Stored hashes in <code class=\"docs-code\">private/storage/recovery-codes.json</code>; marked spent after use."],
          ["Validation boundary", "All endpoints under <code class=\"docs-code\">public/api/</code> that mutate state check <code class=\"docs-code\">$_SESSION['mattrics_authed']</code>."],
          ["Session lifetime", "Cookie persists for 30 days. Sessions remain valid for up to 30 days without requiring another login, unless you log out explicitly. Configurable via <code class=\"docs-code\">session_idle_seconds</code> and <code class=\"docs-code\">session_absolute_seconds</code> in <code class=\"docs-code\">private/config.php</code>."],
        ])}
      `)}

      ${M.docsSubsection("Counter validation and cloning detection", `
        <p class="docs-copy">
          Each passkey maintains a counter incremented on every assertion. On login, the server verifies that the returned counter is greater than the stored
          counter. If the counter goes backwards or stays the same across multiple assertions, it indicates the credential may have been cloned and the login is rejected.
        </p>
      `)}

      ${M.docsSubsection("CSRF protection", `
        <p class="docs-copy">
          Form submissions and settings mutations are protected with CSRF tokens. The token is generated per session and validated before processing. API calls
          that mutate state via POST require the token to be sent in the request body or as a header.
        </p>
      `)}

      ${M.docsSubsection("Local development bypass", `
        <p class="docs-copy">
          During local development, when the app is served from <code class="docs-code">localhost</code> or <code class="docs-code">127.0.0.1</code>, the passkey authentication is bypassed for convenience. The session is automatically marked as authenticated on first request, allowing seamless app access without a passkey prompt.
        </p>
        <p class="docs-copy">
          <strong>How it works:</strong> The <code class="docs-code">mattrics_dev_bypass_auth()</code> function in <code class="docs-code">bootstrap-auth.php</code> checks if the request originates from a loopback address (localhost, 127.0.0.1, or ::1). If so, it sets <code class="docs-code">$_SESSION['mattrics_authed'] = true</code> when no authenticated session exists. This is a pure development convenience and has <strong>no effect on production</strong>.
        </p>
        <p class="docs-copy">
          <strong>Security:</strong> The bypass is automatically disabled on any non-loopback host, and a real authenticated session (from an actual passkey login) is never overwritten. Start your local server with <code class="docs-code">php -S localhost:8001 -t public</code> to enable the bypass.
        </p>
      `)}
    `,
  });
}());
