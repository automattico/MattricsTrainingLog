# Hevy REST preflight

Date 2026-11-23

## Verification

- The hosted Mattrics connector reported a successful Hevy connection test while sync was paused.
- A local read-only REST probe checked pages 1 and 2 with a page size of two. Pagination metadata was present and the pages returned four distinct workout IDs.
- All 30 exercise entries had an `exercise_template_id`. All 96 set entries were structured and included an index and a type.
- The workout ID and ordered exercise template IDs matched between the list and detail endpoints for one sampled workout.

The read-only sample did not exercise an exercise rename or a language change. The sample task is verified. The broader Hevy preflight story remains in progress for those checks.

## Data handling

The probe read `HEVY_API_KEY` from the local ignored environment file and used it only for GET requests to the official Hevy API. Only aggregate results were retained. No keys, raw workout payloads, titles, or IDs were recorded. The probe left sync and application runtime untouched.

## Next work

Continue the [M0 preflight handoff](next-planning-preflight-prompt.md). Compare template identity across existing title or language variants if available, then record any remaining limits. The Garmin and Strava archive requests are still pending download.
