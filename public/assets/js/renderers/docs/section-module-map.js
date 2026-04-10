(function () {
  const M = window.Mattrics;
  M.docsSections = M.docsSections || [];
  M.docsSections.push({
    id: "docs-module-map",
    label: "Module map / path keys",
    title: "Where the important code lives",
    intro: "The app is dependency-ordered, so the docs should mirror the actual load order and the files that define each concept.",
    body: `
      ${M.docsSubsection("Directory structure", `
        ${M.docsTree(
`public/
├── index.php               Auth entry point — guards, session setup, require_once calls only
├── views/                  PHP partials — one file per shell region
│   ├── head.php            <head> block: meta, fonts, CSS links, MATTRICS_AUTH config
│   ├── load-screen.php     Loading spinner shown before data arrives
│   ├── header.php          Site header: brand, range switcher, logout, refresh
│   ├── nav.php             Main nav bar: Dashboard · Fatigue · Sessions · AI · Settings
│   ├── main-views.php      All five .view containers (client-side router swaps these)
│   ├── detail-modal.php    Session detail overlay modal
│   └── scripts.php         All <script> tags in load order
├── assets/
│   ├── css/
│   │   ├── main.css        Import index — no rules, just @import statements
│   │   ├── tokens.css      Design tokens: colors, radii, spacing, fatigue vars
│   │   ├── base.css        Resets and global element defaults
│   │   ├── layout.css      Shell, header, nav, section scaffolding
│   │   ├── buttons.css     All button variants and shared interaction states
│   │   ├── dashboard.css   Overview cards and recent-session content
│   │   ├── fatigue.css     Body figure, readiness tables, fatigue colors
│   │   ├── sessions.css    Activity cards, filters, timeline grouping
│   │   ├── docs.css        Documentation hub styles
│   │   ├── settings.css    Profile form and passkey UI
│   │   ├── ai.css          AI coach pane
│   │   ├── modal.css       Detail overlay modal
│   │   ├── loading.css     Load screen and spinner
│   │   └── responsive.css  Breakpoint overrides
│   └── js/
│       ├── core/           Data model — loaded first, no rendering
│       │   ├── constants.js     Activity types, muscle regions, fatigue config
│       │   ├── state.js         Global M.state shape
│       │   ├── fatigue-engine.js Decayed load + score computation
│       │   ├── fatigue-tiers.js  Score → readiness tier labels
│       │   ├── hevy-parser.js   Hevy workout set parsing
│       │   ├── activity-analysis.js Grouping and filtering
│       │   ├── metrics.js       Overview metric calculations
│       │   ├── formatters.js    Date, time, number formatting
│       │   ├── date-utils.js    Date arithmetic helpers
│       │   └── filters.js       Activity type filtering
│       ├── renderers/      View rendering — loaded after core
│       │   ├── loader.js        Loading states: spinner, error, show app
│       │   ├── orchestrator.js  Master router + data pipeline; lazily loads docs.js
│       │   ├── dashboard.js     Dashboard overview cards
│       │   ├── fatigue-view.js  Body map, readiness tables, fatigue legend
│       │   ├── docs-helpers.js  Shared HTML builders: docsTable, docsCards, docsMermaid…
│       │   ├── docs.js          Docs renderer shell — reads M.docsSections, calls mermaid.run()
│       │   └── docs/            One file per documentation section
│       │       ├── section-overview.js
│       │       ├── section-tech-stack.js
│       │       ├── section-feature-map.js
│       │       ├── section-data-import.js
│       │       ├── section-domain-model.js
│       │       ├── section-auth-passkeys.js
│       │       ├── section-ai-model.js
│       │       ├── section-fatigue-model.js
│       │       ├── section-module-map.js   ← this file
│       │       ├── section-body-map.js
│       │       ├── section-css-guide.js
│       │       └── section-deployment.js
│       ├── body-map-team-buildr.js  Vendored SVG paths + slugToKey mapping
│       ├── feed.js          Flat session list and activity cards
│       ├── timeline.js      Week/month grouped timeline views
│       ├── ai.js            AI coach pane + SSE streaming
│       ├── detail.js        Session detail modal
│       ├── settings.js      Profile form render + save
│       ├── passkeys.js      Passkey registration, deletion, recovery codes
│       └── app.js           Wires globals onto window, bootstraps app
└── api/
    ├── bootstrap.php        Session + config bootstrap (unauthenticated)
    ├── bootstrap-auth.php   Auth-required bootstrap
    ├── data.php             Fetches + caches Google Sheets JSON
    ├── ai.php               Anthropic API proxy (SSE streaming)
    ├── settings.php         Read/write user profile settings
    └── auth/
        ├── challenge.php    Issues WebAuthn assertion/creation challenges
        ├── verify.php       Verifies login assertion, sets session
        ├── register.php     Registers new passkey credential
        ├── passkeys.php     Lists/deletes passkeys
        ├── logout.php       Clears session, redirects to login
        └── recovery.php     Recovery code redemption`
        )}
      `)}

      ${M.docsSubsection("Adding a new docs section", `
        ${M.docsList([
          "Create <code class=\"docs-code\">public/assets/js/renderers/docs/section-your-topic.js</code>",
          "Copy the IIFE pattern: <code class=\"docs-code\">M.docsSections = M.docsSections || []; M.docsSections.push({...})</code>",
          "Add a <code class=\"docs-code\">&lt;script&gt;</code> tag in <code class=\"docs-code\">views/scripts.php</code> in the desired sidebar order (after other section files, before feed.js)",
        ])}
      `)}

      ${M.docsSubsection("Path keys — body map slugs", `
        <p class="docs-copy">
          The body map SVG uses region slugs on path elements (e.g. <code class="docs-code">upper-back</code>).
          The <code class="docs-code">slugToKey</code> map in <code class="docs-code">body-map-team-buildr.js</code>
          resolves these back to the canonical muscle keys used by the fatigue engine
          (e.g. <code class="docs-code">upper-back</code> → <code class="docs-code">upperBack</code>).
        </p>
      `)}
    `,
  });
}());
