(function () {
  const M = window.Mattrics;
  M.docsSections = M.docsSections || [];
  M.docsSections.push({
    id: "docs-local-development",
    label: "Local development",
    title: "Gate-compatible local development",
    intro: "The local server mirrors Mattwarden's static and flat-PHP dispatch contract without implementing application authentication.",
    body: `
      ${M.docsTable([
        ["Development server", "Run <code class=\"docs-code\">./scripts/dev-server.sh</code>. It stages a temporary site directory, loads the Mattwarden test stub, and starts PHP's built-in server."],
        ["Static serving", "The router serves only allowlisted files from <code class=\"docs-code\">public/</code>, rejects dotfiles, and applies the default gate CSP to HTML."],
        ["API dispatch", "Both <code class=\"docs-code\">/api/name</code> and <code class=\"docs-code\">/api/name.php</code> dispatch flat scripts; trailing segments become <code class=\"docs-code\">PATH_INFO</code>."],
        ["CSRF tests", "The stub uses one fixed 64-character token and a fixed origin so missing-token and foreign-origin behavior can be verified locally."],
      ])}
    `,
  });
}());
