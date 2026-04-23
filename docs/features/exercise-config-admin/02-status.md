# Exercise Config Admin – Status

## Overview
Tracks implementation progress across Codex slices.

---

## Slice Status

| Slice | Name | Status |
|------|------|--------|
| 1 | Persistence + Resolver | done |
| 2 | Unknown Detection + Warning | done |
| 3 | AI Suggestion Backend | done |
| 4 | UI + Review Queue | done |
| 5 | Merge + Delete + Polish | done |

---

## Acceptance Criteria

- [x] unknown exercises are never silently ignored
- [x] manual AI trigger works
- [x] AI suggestions can be applied to the editor and saved
- [x] unresolved items are visible in the review queue
- [x] recalculation always triggers
- [x] delete works and updates fatigue
- [x] dataset lookup works before AI
- [x] UI supports full workflow end-to-end

---

## Implemented Files
- `private/data/exercise-configs.json`
- `private/data/activity-type-configs.json`
- `public/api/exercise-config-repository.php`
- `public/api/exercises.php`
- `private/data/exercise-unknowns.json`
- `public/assets/js/core/exercise-config.js`
- `public/assets/js/core/constants.js`
- `public/assets/js/core/state.js`
- `public/assets/js/core/hevy-parser.js`
- `public/assets/js/core/fatigue-engine.js`
- `public/assets/js/renderers/orchestrator.js`
- `public/assets/js/renderers/fatigue-view.js`
- `public/assets/css/fatigue.css`
- `public/index.html`
- `docs/module-map.md`
- `docs/fatigue-model.md`
- `public/assets/js/renderers/docs/section-module-map.js`
- `public/assets/js/renderers/docs/section-fatigue-model.js`
- `public/tests/exercise-config-tests.js`
- `tests/exercise-config-tests.php`
- `tests/fixtures/run-exercises-endpoint.php`
- `public/api/exercise-config-ai.php`
- `tests/fixtures/run-exercise-suggest-endpoint.php`
- `private/data/exercise-dataset.json`
- `tests/fixtures/run-exercise-delete-endpoint.php`
- `tests/fixtures/run-exercise-merge-endpoint.php`
- `tests/fixtures/run-unknown-merge-endpoint.php`
- `public/assets/js/exercise-admin.js`
- `public/assets/css/exercise-admin.css`
- `tests/fixtures/run-exercise-patch-endpoint.php`

---

## Open Questions
- No unresolved questions after the final verification pass.

---

## Notes
- Resolver now reads both exercise and non-Hevy activity type mappings from private JSON seed data.
- Existing Hevy substring behavior is preserved through `matchTerms`.
- `getExerciseMuscleMapping()` remains as a compatibility wrapper while the new resolver becomes the source of truth.
- Unknown detection now scans both Hevy exercise names and non-Hevy activity types after data/config load, then syncs the live unresolved snapshot to `private/data/exercise-unknowns.json`.
- The fatigue view now exposes unresolved state for the current fatigue lookback window and renders a visible warning when recent mappings are incomplete.
- Current fatigue stimulus math and the generic `WeightTraining` fallback are unchanged in this slice.
- Slice 3 adds `POST /api/exercises/unknowns/{id}/suggest`, backed by OpenAI GPT-5.4 mini via the Responses API.
- AI suggestion responses are schema-validated and returned as saveable previews. The user must save before the config is persisted.
- Suggestion requests are logged to `private/exercise-ai.log` with timestamp, raw name, normalized name, `sourceType`, model, prompt version, result status, and `configCreated`.
- Invalid AI responses now return an error, preserve the unknown, and update `aiStatus` so the UI can surface the failure state.
- Slice 4 adds a new top-level `Exercises` view with a review queue, searchable exercise list, and an editor panel for exercise configs.
- The review queue surfaces unresolved exercises, with direct `✨ Generate suggestion` actions for unresolved exercise names.
- The API supports `PATCH /api/exercises/{id}` for edits, `POST /api/exercises` for unknown-to-config saves, merge endpoints, and delete.
- Saving from the exercise editor immediately updates the active config and refreshes the resolver.
- Unknown snapshots are pruned when a config edit resolves them, so review queue state and fatigue unresolved warnings stay in sync after alias or match-term updates.
- PHP and Node test coverage now includes the new patch flow, unknown pruning, and exercise-admin review/list derivation helpers.
- Slice 5 adds `POST /api/exercises/{id}/merge-alias`, `POST /api/exercises/unknowns/{id}/merge-alias`, and `DELETE /api/exercises/{id}` so aliases can be consolidated and redundant configs can be removed without leaving stale review state behind.
- Delete now refreshes the persisted unknown snapshot after the config is removed, so the fatigue map recalculates immediately and deleted exercises can reappear as unknowns when current data still references them.
- The exercise admin UI now exposes merge-target selection, the exact required destructive-action confirmation copy, clearer action copy, and better pending/error handling for save, merge, suggest, and delete flows.
- Slice 5 also adds optional external dataset lookup before OpenAI. Dataset hits return external-dataset previews and avoid unnecessary AI calls.
- Final verification confirms recalculation is fulfilled by persisted config mutation followed by config re-indexing and client-side re-render/re-scan of unresolved items, which keeps the fatigue-derived UI current without a separate server-side recomputation step.
- Final verification coverage now explicitly checks dataset-before-AI precedence, exact delete-confirmation copy, unresolved warning visibility, merge/delete flows, suggestion previews, and audit-log completeness for dataset, AI-success, and AI-failure flows.

---

## Next Slice
No further slices planned for this feature.
