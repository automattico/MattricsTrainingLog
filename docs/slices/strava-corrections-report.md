# Strava title and sport corrections

A disposable Postgres test delivered a changed Strava title and sport for an existing linked recording. The authenticated dashboard returned both corrections on the original workout ID with the same distance, duration and workout count. The test used synthetic records and the existing archive import path. Live Make edit delivery remains unverified. Its current scenario searches by start date and skips known IDs, so edits to older workouts can be missed.

The owner wants indefinite history and continued Make coverage for Strava-only and Concept2 workouts. The standard API policy limits transient caching to seven days and restricts persistent storage. Permanent retention of the Make feed requires applicable permission. The archive import path remains available for user-supplied exports. [Strava API Policy §§5.5, 6.2 and 6.6](https://www.strava.com/legal/api_policy)

The local Postgres suite passed. No private workout samples or provider credentials entered the test. No hosted writes or deployment. [Next slice](next-strava-edit-delivery-prompt.md)
