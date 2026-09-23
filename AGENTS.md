# AGENTS.md

## Purpose

This repository hosts the Mattrics training dashboard: a Mattwarden-protected static shell with a flat PHP API.
`public/` is the static content tree, not a document root. Mattwarden serves it from outside the webroot.

Agents must preserve the deployment and security model defined here.

## Mattwarden Security Boundary

Authentication is Mattwarden's job. This repository contains no authentication code and must not grow any.

- Application code must not start or inspect sessions, read or write `$_SESSION`, set cookies, or send `Set-Cookie`.
- Every file in `api/` begins with `declare(strict_types=1); require_authenticated();`.
- Every state-changing method also calls `mattwarden_require_same_origin()` and `mattwarden_require_csrf()`.
- `MATTWARDEN_SITE_DIR` is the only application base path. Private state resolves to `MATTWARDEN_SITE_DIR . '/private'` with no fallback.
- Logout is `/__mattwarden/logout`. Passkey enrolment and management are not application features.
- Content deploys over SFTP to `sites/mattrics/public`, `sites/mattrics/api`, `sites/mattrics/lib`, and `sites/mattrics/private`; this repository never deploys into `public_html`.
- Deploy authentication is SSH-key-only with a pinned host key. Password variables are forbidden.

## Codex Slice Framework

Future architecture and migration work must read:

- `docs/architecture-next.md`
- `docs/codex-framework.md`
- `docs/implementation-progress.md`

Every implementation slice must update `docs/implementation-progress.md`, add a slice report, and create the next slice prompt. Existing static deploy rules remain authoritative unless a separately approved architecture decision replaces them. Secrets rules remain absolute.

---

## Feature-to-File Map

When you need to change something, open only these files:

| Task | JS files | CSS files |
|---|---|---|
| Add a new activity type | `core/constants.js` | `tokens.css` |
| Change fatigue scores / half-lives | `core/fatigue-engine.js` | — |
| Change fatigue tier labels / meanings | `core/fatigue-tiers.js` | — |
| Change fatigue body map UI | `renderers/fatigue-view.js` | `fatigue.css` |
| Change dashboard KPI cards | `renderers/dashboard.js` | `dashboard.css` |
| Change session cards (list view) | `feed.js` | `cards.css`, `sessions.css` |
| Change timeline / grouped view | `timeline.js` | `sessions.css` |
| Change detail modal | `detail.js` | `modal.css` |
| Change AI pane | `ai.js` | `ai.css` |
| Change connector administration | `connectors.js` | `connectors.css` |
| Change settings | `settings.js` | `settings.css` |
| Change exercise administration | `exercise-admin.js`, `core/exercise-config.js` | `exercise-admin.css` |
| Change in-app documentation | `renderers/docs.js`, `renderers/docs/*` | `docs.css` |
| Change date / time formatting | `core/formatters.js` | — |
| Change load / error screen | `renderers/loader.js` | `loading.css` |
| Change filter pills / nav / window switcher | `renderers/orchestrator.js` | `buttons.css`, `layout.css` |
| Add a new exercise muscle mapping | `core/hevy-parser.js` | — |
| Change metrics shown on activity cards | `core/metrics.js` | — |
| Change responsive breakpoints | — | `responsive.css` |
| Change design tokens (colors, spacing) | — | `tokens.css`, `brand.css` |
| Change data fetch / caching logic | `renderers/orchestrator.js` | — |
| Change state shape | `core/state.js` | — |

All JS paths are relative to `public/assets/js/`.
All CSS paths are relative to `public/assets/css/`.

---

## Script Load Order

`public/index.html` is the canonical script list. Its dependency order is:

1. Core: `constants.js`, `state.js`, `date-utils.js`, `formatters.js`, `filters.js`, `exercise-config.js`, `hevy-parser.js`, `fatigue-engine.js`, `fatigue-tiers.js`, `activity-analysis.js`, `metrics.js`.
2. Generated body-map asset: `body-map-team-buildr.js`.
3. Main renderers: `loader.js`, `orchestrator.js`, `dashboard.js`, `fatigue-view.js`.
4. In-app docs: `docs-helpers.js`, every `renderers/docs/section-*.js` in the HTML's listed order, then `docs.js`.
5. Views and event wiring: `feed.js`, `timeline.js`, `ai.js`, `detail.js`, `connectors.js`, `settings.js`, `exercise-admin.js`, then `app.js`.

Do not reorder by filename: later modules call globals defined by earlier ones. `app.js` bootstraps `/api/session` before loading training data.

---

## CSS File Map

`assets/css/main.css` is an `@import` index only — no rules live there.

| File | What it styles |
|---|---|
| `brand.css` | Upstream brand tokens (colors, fonts) — synced from brandhub |
| `tokens.css` | App-specific `:root` custom properties (surfaces, spacing, radius, activity/fatigue colors) |
| `base.css` | Reset, html/body, shared mono-font selector list |
| `loading.css` | Load screen, spinner, error box |
| `buttons.css` | All button variants and states (nav-btn, filter-pill, window-btn, gen-btn, etc.) |
| `layout.css` | App shell, site-header, nav, section-shell, view/grid scaffolding |
| `dashboard.css` | Overview KPI cards, donut chart, legend, recent sessions list |
| `fatigue.css` | Fatigue heatmap, body figure SVG, fatigue board/table/legend/tooltip |
| `sessions.css` | Activity cards (`.a-card`), timeline tiles (`.tl-*`), filter row, sessions toolbar |
| `modal.css` | Detail overlay/modal, metrics, hevy exercise breakdown |
| `ai.css` | AI pane, recent preview, output |
| `docs.css` | In-app documentation, including the fatigue model accordion |
| `exercise-admin.css` | Exercise and activity-type administration |
| `connectors.css` | Connector administration |
| `settings.css` | Settings view and forms |
| `responsive.css` | All `@media` breakpoint overrides (centralized) |

---

## State Shape

`Mattrics.state` (defined in `core/state.js`):

| Field | Type | Mutated by |
|---|---|---|
| `allData` | `Array` | `fetchData` (orchestrator.js) |
| `activityChildren`, `activityChildrenById` | `Array`, `Object` | canonical-data child indexing |
| `dataMeta` | `Object` | `fetchData` |
| `dashboardWindowDays` | `Number` | `setDashboardWindow` |
| `typeFilter` | `String` | `setFilter`, `fetchData` (resets to "All") |
| `feedMode` | `"list" \| "grouped"` | `setFeedMode` |
| `groupBy` | `"week" \| "month"` | `setFeedMode` |
| `recent` | `Array` | `renderAiPreview` |
| `currentFatigue` | `Object \| null` | `renderAiPreview` |
| `userSettings` | `Object \| null` | settings load/save |
| `exerciseConfigs`, `activityTypeConfigs`, `exerciseConfigMeta`, `exerciseConfigIndex` | catalogs and metadata | exercise-config load/save |
| `unknownExercises`, `unknownExerciseMeta` | review queue and metadata | unknown scan/sync |
| `connectors` | `Object` | connector administration |
| `exerciseAdmin` | `Object` | exercise editor/review UI |

---

## Data Flow

```
bootstrapSession() → in-memory CSRF token
  → fetchData()
      → GET /api/data and loadExerciseConfigs() in parallel
      → normalize + sort rows into state.allData
      → scanAndSyncUnknownExercises()
      → loadUserSettings()
  → renderAll()
      → renderContextBar() and renderDashboard()
      → renderFatigueView()
      → renderFilters()
      → renderFeed() → renderActivityCards() or renderTimeline()
      → renderAiPreview()
```

---

## Further Documentation

| Doc | What it covers |
|---|---|
| `docs/README.md` | Documentation index, authority, and current-versus-historical routing |
| `docs/fatigue-model.md` | Algorithm details: decay formula, half-lives, Hevy parsing, tier thresholds |
| `docs/module-map.md` | Dependency graph showing what each module exports and consumes |
| `docs/css-guide.md` | CSS class prefix → file mapping, custom property reference |
| `docs/architecture.md` | High-level system architecture and deploy model |
| `docs/strava-sync-architecture.md` | Upstream Strava → Make.com → Google Sheets pipeline |
| `docs/anthropic-api-key.md` | Safely replacing the workout AI provider key |

`docs/slices/` contains dated implementation records and old prompts. They document past states; use this file, `README.md`, `DEPLOY.md`, `docs/README.md`, and the current architecture/runbook for operating instructions.

---

## Communication Style

- Be concise, direct, and practical.
- Do not narrate obvious steps.
- Provide status summaries only after substantial work.

Status markers: ✅ done · ❌ failed · ❗ warning · 🚀 deployed · 🔒 security-sensitive

---

## Repository Architecture

```
public/   static shell and assets
api/      flat Mattwarden-dispatched endpoint scripts
lib/      non-addressable shared PHP modules
private/  config example and first-deploy JSON seeds
scripts/  local gate, deploy, imports, and validation
docs/     architecture and operational documentation
```

Rules:
- `public/`, `api/`, and `lib/` are mirrored to the matching `sites/mattrics/*` tree.
- Only `private/config.example.php` and the four JSON seeds are uploaded with `--only-missing`.
- `private/config.php`, runtime data, caches, logs, and raw imports are never uploaded or overwritten.
- Server-side entry points are allowed only as flat files in `api/`; shared code belongs in `lib/`.
- Secrets must never appear in `public/`, `api/`, `lib/`, Git, logs, or documentation.

---

## Security Rules

Never commit secrets, API keys, certificates, private keys, logs, or database files.
Secrets belong only in `.env.local` and `private/config.php`.
Treat `.env.local` and deploy credentials as 🔒 security-sensitive.

---

## Deployment

```
./deploy.sh
```

Flow: source guard → lint → PHP and JavaScript tests → allowlisted SFTP mirrors → remote-tree verification.

Agents must not deploy without explicit approval. The production data move documented in `docs/mattwarden-migration.md` must happen before the first application deploy.

---

## Working Tree Safety

Assume the working tree may be dirty. Agents must:
- never overwrite unrelated changes
- avoid destructive operations
- request confirmation before history rewrites

---

## Reviews

When reviewing code, prioritize:
1. bugs
2. regressions
3. deploy risk
4. missing validation
5. missing tests
