(function () {
  const M = window.Mattrics;
  M.docsSections = M.docsSections || [];
  M.docsSections.push({
    id: "docs-docker-local-dev",
    label: "Docker local dev",
    title: "Docker-first local development",
    intro: "Local development can now run inside Docker without changing the current app architecture. The container keeps the existing PHP app shape, while bind mounts preserve fast iteration and local runtime data.",
    body: `
      ${M.docsTable([
        ["Primary goal", "Work on the current app inside Docker today, without installing more runtime tooling on the Mac and without doing a major architecture rewrite yet."],
        ["Container entrypoint", "<code class=\"docs-code\">docker compose up --build</code> starts a single app container that serves <code class=\"docs-code\">public/</code> with PHP's built-in web server on <code class=\"docs-code\">http://localhost:8080</code>."],
        ["Image contents", "The Docker image includes PHP CLI, Node.js, npm, curl, git, and <code class=\"docs-code\">lftp</code> so existing tests and deploy-adjacent scripts can run inside the container."],
        ["Bind mounts", "The repo is mounted into <code class=\"docs-code\">/app</code>, so JS, CSS, PHP, and docs changes are reflected immediately without rebuilding the image for every edit."],
        ["Persistent local data", "<code class=\"docs-code\">private/</code> stays on the host via the bind mount. Existing passkeys, auth logs, settings, and cached training data survive container restarts."],
        ["Session storage", "PHP sessions live in a named Docker volume. Resetting that volume clears container session state without deleting host-mounted app data."],
        ["Runtime config", "<code class=\"docs-code\">MATTRICS_CONFIG=/app/private/config.php</code> keeps the current private config flow. Secrets stay local and outside the image build context."],
        ["Local auth behavior", "The container process injects <code class=\"docs-code\">MATTRICS_SITE_ORIGIN=http://localhost:8080</code> and <code class=\"docs-code\">MATTRICS_AUTH_REQUIRE_HTTPS=0</code> so passkeys continue to work on localhost in development."],
        ["Production safety", "Production deploy behavior is unchanged. The Docker local setup does not alter <code class=\"docs-code\">deploy.sh</code>, SFTP deployment, or the public/private deployment boundary."],
      ])}

      ${M.docsSubsection("Useful commands", `
        ${M.docsList([
          "<code class=\"docs-code\">docker compose up --build</code> to build and start the local app",
          "<code class=\"docs-code\">docker compose exec app php tests/auth-security-tests.php</code> to run the PHP auth test suite in the container",
          "<code class=\"docs-code\">docker compose exec app node public/tests/settings-tests.js</code> to run the JS settings tests in the container",
          "<code class=\"docs-code\">docker compose exec app ./scripts/predeploy-guard.sh --check</code> to run the existing validation flow in the container",
          "<code class=\"docs-code\">docker compose down</code> to stop the stack",
          "<code class=\"docs-code\">docker compose down -v</code> to stop the stack and clear the PHP session volume only",
        ])}
      `)}

      ${M.docsSubsection("Why this setup stays minimal", `
        <p class="docs-copy">
          This Docker pass deliberately avoids a larger rewrite. The app still serves the same PHP pages and API endpoints from
          <code class="docs-code">public/</code>, still reads the same private config and cache files, and still uses the same shell validation scripts.
          The only code-level change was a small config override layer so local container auth can use <code class="docs-code">localhost</code>
          without editing the real private config.
        </p>
      `)}

      ${M.docsSubsection("Current tradeoffs", `
        ${M.docsList([
          "Local Docker uses PHP's built-in server, so Apache <code class=\"docs-code\">.htaccess</code> rules are not mirrored in development.",
          "The first image build is slower because it installs both PHP- and Node-related tooling needed for current workflow parity.",
          "Writable app state is still file-based under <code class=\"docs-code\">private/</code>; Docker makes local setup cleaner, but it does not replace that storage model yet.",
        ])}
      `)}
    `,
  });
}());
