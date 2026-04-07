# Module Map

Dependency graph for `public/assets/js/`. Each module lists what it **exports** (attaches to `window.Mattrics`) and what it **consumes** from earlier modules.

All files use the IIFE + `window.Mattrics` global pattern. Load order in `index.html` must match this graph top-to-bottom.

---

## core/constants.js
**Exports:** `DATA_URL`, `AI_PROXY_URL`, `SHEET_URL`, `SHEET_TOKEN`, `API_KEY`, `AI_ENABLED`, `TYPES`, `MUSCLE_REGIONS`, `MUSCLE_FATIGUE_CONFIG`, `MUSCLE_FATIGUE_BODY_MAP` (placeholder)
**Consumes:** `window.MATTRICS_CONFIG` (optional runtime config)

## core/state.js
**Exports:** `state` (`allData`, `dataMeta`, `windowDays`, `typeFilter`, `feedMode`, `groupBy`, `recent`, `currentFatigue`)
**Consumes:** nothing

## core/date-utils.js
**Exports:** `normalizeDateValue`, `parseDate`, `startOfDay`, `toIsoDate`, `shiftDate`, `diffDays`, `weekStart`, `formatWeekRange`
**Consumes:** nothing (self-contained arithmetic)

## core/formatters.js
**Exports:** `tc`, `canonicalType`, `esc`, `escAttr`, `fmt`, `fmtDate`, `fmtShort`, `fmtDateTime`, `formatContextRange`, `getActivityId`
**Consumes:** `parseDate`, `toIsoDate`, `shiftDate` (date-utils); `TYPES` (constants)

## core/filters.js
**Exports:** `getWindowRange`, `getRollingPeriod`, `applyTypeFilter`, `getWindowedData`, `getFixedRecentActivities`
**Consumes:** `state`, `startOfDay`, `toIsoDate`, `diffDays`, `parseDate` (date-utils); `canonicalType` (formatters); `MUSCLE_FATIGUE_CONFIG` (constants)

## core/hevy-parser.js
**Exports:** `getExerciseMuscleMapping`, `parseHevySetLine`, `parseHevyDescription`
**Consumes:** `MUSCLE_FATIGUE_CONFIG` (constants)

## core/fatigue-engine.js
**Exports:** `getActivityMuscleStimulus`, `getMuscleLoadAnalysis`, `getMuscleFatigueAnalysis`
**Consumes:** `MUSCLE_REGIONS`, `MUSCLE_FATIGUE_CONFIG` (constants); `parseDate`, `startOfDay`, `toIsoDate` (date-utils); `parseHevyDescription`, `parseHevySetLine`, `getExerciseMuscleMapping` (hevy-parser); `getFixedRecentActivities` (filters); `getMuscleFatigueTier`, `getRecoveryLabel`, `getRelativeDayLabel` (fatigue-tiers — loaded after)

> **Note:** `fatigue-tiers.js` loads after `fatigue-engine.js` but the tier functions are only called at runtime (not at parse time), so the load order works.

## core/fatigue-tiers.js
**Exports:** `getMuscleFatigueTier`, `getFatigueVisualState`, `getFatigueDisplayTier`, `getFatigueTierMeaning`, `getRecoveryLabel`, `getRelativeDayLabel`
**Consumes:** `diffDays`, `parseDate` (date-utils); `getFatigueDisplayTier` (self-reference within same file)

## core/activity-analysis.js
**Exports:** `getActivityMix`, `getActiveDayStats`, `getOverviewMetrics`
**Consumes:** `state` (state); `canonicalType`, `tc` (formatters); `getActiveDayStats`, `getActivityMix` (self); `getMuscleFatigueAnalysis` (fatigue-engine)

## core/metrics.js
**Exports:** `cardMetrics`, `detailFacts`
**Consumes:** `fmt` (formatters)

---

## body-map-team-buildr.js
**Exports:** `MUSCLE_FATIGUE_BODY_MAP` (SVG path config for front/back body figures)
**Consumes:** nothing (static data, overwrites the placeholder set in constants.js)

---

## renderers/loader.js
**Exports:** `showLoading`, `showError`, `showApp`, `renderDataStatus`
**Consumes:** `state.dataMeta`, `fmtDateTime` (formatters)

## renderers/orchestrator.js
**Exports:** `fetchData`, `renderAll`, `setWindow`, `setFilter`, `setFeedMode`, `showView`, `renderContextBar`, `renderFilters`
**Consumes:** `showLoading`, `showError`, `showApp`, `renderDataStatus` (loader); `state` (state); `normalizeDateValue` (date-utils); `getWindowRange`, `getWindowedData`, `applyTypeFilter` (filters); `formatContextRange`, `canonicalType`, `tc`, `escAttr` (formatters); `renderDashboard`, `renderFatigueView`, `renderFeed`, `renderAiPreview` (called at runtime, loaded later)

## renderers/dashboard.js
**Exports:** `renderDashboard`
**Consumes:** `getOverviewMetrics` (activity-analysis); `tc`, `esc`, `escAttr`, `fmt`, `fmtDate`, `getActivityId`, `cardMetrics` (formatters/metrics); `state` (state)

## renderers/fatigue-view.js
**Exports:** `renderFatigueView`, `renderFatigueBodyFigure`, `renderFatigueLegendPanel`, `renderFatigueReadinessTables`, `getFatigueReadinessBucket`, `getFatigueReadinessToken`, `getFatiguePercentLabel`, `getMuscleContextLabel`, `bindFatigueHoverCard`
**Consumes:** `MUSCLE_FATIGUE_BODY_MAP` (body-map); `getFatigueVisualState`, `getFatigueDisplayTier`, `getFatigueTierMeaning` (fatigue-tiers); `escAttr` (formatters); `getOverviewMetrics` (activity-analysis)

---

## feed.js
**Exports:** `renderActivityCards`, `renderFeed`
**Consumes:** `state`, `tc`, `esc`, `escAttr`, `fmtDate`, `fmt`, `getActivityId`, `cardMetrics`, `applyTypeFilter` (various core modules)

## timeline.js
**Exports:** `renderTimeline`
**Consumes:** `state`, `getRollingPeriod`, `applyTypeFilter`, `tc`, `fmtShort`, `fmt`, `getActivityId`, `weekStart`

## ai.js
**Exports:** `renderAiPreview`, `generateWorkout`
**Consumes:** `state`, `getFixedRecentActivities`, `getMuscleFatigueAnalysis`, `fmtDate`, `fmt`, `AI_PROXY_URL`, `API_KEY`, `AI_ENABLED`

## detail.js
**Exports:** `openDetail`, `closeDetail`
**Consumes:** `tc`, `esc`, `escAttr`, `fmtDate`, `fmtDateTime`, `fmt`, `detailFacts`, `cardMetrics`, `parseHevyDescription`

## app.js
**Exports:** exposes `setWindow`, `setFilter`, `setFeedMode`, `showView`, `openDetail`, `closeDetail`, `fetchData`, `generateWorkout` to `window` globals
**Consumes:** all of the above
**Side effects:** calls `fetchData()` on load; sets up `[data-activity-id]` click delegation; ESC key closes detail modal
