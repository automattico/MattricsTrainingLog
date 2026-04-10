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
          ["Session timeout", "Explicit logout clears <code class=\"docs-code\">mattrics_authed</code> and regenerates the session ID."],
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
    `,
  });
}());
