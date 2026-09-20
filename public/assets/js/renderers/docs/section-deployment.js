(function () {
  const M = window.Mattrics;
  M.docsSections = M.docsSections || [];
  M.docsSections.push({
    id: "docs-deployment",
    label: "Deployment / security",
    title: "Mattwarden is the security boundary",
    intro: "Mattrics deploys application content outside every document root; Mattwarden authenticates and dispatches every request.",
    body: `
      ${M.docsTable([
        ["Static shell", "<code class=\"docs-code\">public/</code> deploys to <code class=\"docs-code\">sites/mattrics/public</code> and is served by the gate."],
        ["PHP API", "Flat scripts in <code class=\"docs-code\">api/</code> deploy to <code class=\"docs-code\">sites/mattrics/api</code>. Every endpoint calls <code class=\"docs-code\">require_authenticated()</code>."],
        ["Mutation guards", "POST, PUT, PATCH, and DELETE operations require both Mattwarden same-origin and CSRF checks."],
        ["Shared code", "Non-addressable PHP modules deploy from <code class=\"docs-code\">lib/</code> to <code class=\"docs-code\">sites/mattrics/lib</code>."],
        ["Private runtime", "Config, cached data, settings, connector state, and JSON data live under <code class=\"docs-code\">sites/mattrics/private</code>."],
        ["Document root", "Only Mattwarden's shim and Apache rules belong in <code class=\"docs-code\">public_html/mattrics</code>. This repository never deploys there."],
        ["Transport", "Deployment uses SFTP with SSH key authentication and a pinned host key. Password authentication is rejected."],
        ["CSP", "The shell uses external same-origin scripts and styles only. Dynamic visual values are applied through CSSOM after rendering."],
      ])}
      <div class="docs-note">Account access, gate sessions, cookies, security headers, and logout are Mattwarden responsibilities and are intentionally absent from this codebase.</div>
    `,
  });
}());
