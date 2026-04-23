# Slice 3 – AI Suggestion Backend

## Goal
Implement AI suggestion endpoint.

## Read:
- docs/features/exercise-config-admin/00-feature-spec.md
- docs/features/exercise-config-admin/01-implementation-spec.md
- docs/features/exercise-config-admin/02-status.md


## Tasks
- implement POST /api/exercises/unknowns/{id}/suggest
- integrate OpenAI GPT-5.4 mini
- implement prompt builder
- validate response
- create draft config
- trigger recalculation
- log request

## Definition of Done
- AI suggestion creates config
- invalid responses handled
- recalculation works

## End of Slice
- update status doc
- recommend next slice
