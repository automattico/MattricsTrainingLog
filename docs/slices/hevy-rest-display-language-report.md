# Hevy REST display-language verification

Date 2026-11-25

## Summary

Closed [ST-HEVY-REST](../planning/stories/st-hevy-rest.md) and [TASK-HEVY-RENAME](../planning/tasks/task-hevy-rename.md) as verified on existing read-only display-language evidence. The earlier [backend preflight](hevy-rest-preflight-report.md#verification) established workout pagination, list-to-detail IDs and structured sets.

## Verification

- Paged `GET /v1/workouts`, `GET /v1/routines` and `GET /v1/exercise_templates`. All 129 distinct template IDs referenced by the sampled workouts and routines resolved to a current catalog entry.
- Joined historical exercise titles to catalog titles by template ID. Matching English and German movement and equipment terms identified 11 built-in templates with German workout titles and English catalog titles. For three of those templates, workout and template detail responses reproduced the differing titles and identical IDs.
- Repeating the list requests with `Accept-Language: en-US` and `Accept-Language: de-DE` returned the same exercise template IDs and titles on all three routes. The observed evidence is the bilingual title pair already present in REST, with the app language setting and a controlled rename still untested.

The existing display-language example meets the requested closure condition. The Hevy result is recorded on the [M0 access check](../planning/checks/check-m0-access.md), which still tracks separate hosting acceptance criteria.

## Files changed

- The story, task and M0 access check record the aggregate result. Implementation progress links this report and the next M0 handoff.

## Validation

- Read-only Hevy REST list and detail comparisons passed.
- Planning validation, fixture tests, and development and staging report builds passed.

## Security check

The probe read the Hevy key from the ignored local environment file and used it only for HTTPS GET requests to Hevy. The file was left untouched. Account responses were processed in memory, and the recorded output contains only aggregate counts and comparison results. The report and generated previews contain no credentials, raw payloads, exercise titles or IDs from the probe.

## Git state

The working tree already had local edits and untracked files. These documentation changes were left local, with no commit, push or deployment.

## Next slice

[Remaining M0 runtime verification](next-m0-runtime-after-hevy-prompt.md)
