# AGENTS.md

## Purpose

This repository hosts a static website template with optional small APIs.
The deployable web root is `public/`.

Agents must preserve the deployment and security model defined here.

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

`index.html` loads scripts in this order. **Order matters** — later files call functions defined by earlier ones.

1. `core/constants.js` — namespace init, config URLs, `TYPES`, `MUSCLE_REGIONS`, `MUSCLE_FATIGUE_CONFIG`
2. `core/state.js` — `Mattrics.state` initial shape
3. `core/date-utils.js` — `normalizeDateValue`, `parseDate`, `startOfDay`, `toIsoDate`, `shiftDate`, `diffDays`, `weekStart`, `formatWeekRange`
4. `core/formatters.js` — `fmt`, `fmtDate`, `fmtShort`, `fmtDateTime`, `formatContextRange`, `tc`, `canonicalType`, `esc`, `escAttr`, `getActivityId`
5. `core/filters.js` — `getWindowRange`, `getRollingPeriod`, `applyTypeFilter`, `getWindowedData`, `getFixedRecentActivities`
6. `core/hevy-parser.js` — `getExerciseMuscleMapping`, `parseHevySetLine`, `parseHevyDescription`
7. `core/fatigue-engine.js` — `getActivityMuscleStimulus`, `getMuscleLoadAnalysis`, `getMuscleFatigueAnalysis`
8. `core/fatigue-tiers.js` — `getMuscleFatigueTier`, `getFatigueVisualState`, `getFatigueDisplayTier`, `getFatigueTierMeaning`, `getRecoveryLabel`, `getRelativeDayLabel`
9. `core/activity-analysis.js` — `getActivityMix`, `getActiveDayStats`, `getOverviewMetrics`
10. `core/metrics.js` — `cardMetrics`, `detailFacts`
11. `body-map-team-buildr.js` — `MUSCLE_FATIGUE_BODY_MAP` SVG config (generated asset)
12. `renderers/loader.js` — `showLoading`, `showError`, `showApp`, `renderDataStatus`
13. `renderers/orchestrator.js` — `fetchData`, `renderAll`, `setWindow`, `setFilter`, `setFeedMode`, `showView`, `renderContextBar`, `renderFilters`
14. `renderers/dashboard.js` — `renderDashboard`
15. `renderers/fatigue-view.js` — `renderFatigueView`, `renderFatigueBodyFigure`, `renderFatigueLegendPanel`, `renderFatigueReadinessTables`, `bindFatigueHoverCard`, fatigue readiness helpers
16. `feed.js` — `renderActivityCards`, `renderFeed`
17. `timeline.js` — `renderTimeline`
18. `ai.js` — `renderAiPreview`, `generateWorkout`
19. `detail.js` — `openDetail`, `closeDetail`
20. `app.js` — event wiring, `fetchData()` bootstrap call

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
| `fatigue-doc.css` | In-page fatigue model developer documentation accordion |
| `responsive.css` | All `@media` breakpoint overrides (centralized) |

---

## State Shape

`Mattrics.state` (defined in `core/state.js`):

| Field | Type | Mutated by |
|---|---|---|
| `allData` | `Array` | `fetchData` (orchestrator.js) |
| `dataMeta` | `Object` | `fetchData` |
| `windowDays` | `Number` | `setWindow` |
| `typeFilter` | `String` | `setFilter`, `setWindow` (resets to "All") |
| `feedMode` | `"list" \| "grouped"` | `setFeedMode` |
| `groupBy` | `"week" \| "month"` | `setFeedMode` |
| `recent` | `Array` | unused (reserved) |
| `currentFatigue` | `Object \| null` | unused (reserved) |

---

## Data Flow

```
fetchData()
  → normalize + sort rows into state.allData
  → renderAll()
      → renderContextBar()
      → renderDashboard()     uses getOverviewMetrics()
      → renderFatigueView()   uses getMuscleFatigueAnalysis()
      → renderFilters()
      → renderFeed()          → renderActivityCards() or renderTimeline()
      → renderAiPreview()
```

---

## Further Documentation

| Doc | What it covers |
|---|---|
| `docs/fatigue-model.md` | Algorithm details: decay formula, half-lives, Hevy parsing, tier thresholds |
| `docs/module-map.md` | Dependency graph showing what each module exports and consumes |
| `docs/css-guide.md` | CSS class prefix → file mapping, custom property reference |
| `docs/architecture.md` | High-level system architecture and deploy model |
| `docs/strava-sync-architecture.md` | Upstream Strava → Make.com → Google Sheets pipeline |

---

## Communication Style

- Be concise, direct, and practical.
- Do not narrate obvious steps.
- Provide status summaries only after substantial work.

Status markers: ✅ done · ❌ failed · ❗ warning · 🚀 deployed · 🔒 security-sensitive

---

## Repository Architecture

```
public/   deployable web root
private/  runtime config + cache (never deployed)
scripts/  deploy pipeline and validation tools
docs/     architecture and operational documentation
```

Rules:
- Only `public/` is deployed.
- `private/` must never be deployed.
- Server-side code is allowed **only in `public/api/`**.
- Secrets must never appear in `public/`.

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

Flow: prod-gate → predeploy guard → deploy public/ → smoke tests

Agents must run validation before deploy, deploy only `public/`, and avoid exposing secrets in logs.

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
