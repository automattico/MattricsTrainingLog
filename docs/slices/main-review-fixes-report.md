# Main review fixes and private planning workspace

25 November 2026 · main

## Changes

- Locked the linked-import owner row without modifying the saved profile and used its timezone to date workouts.
- Stored parsed effective weight for new Hevy sets and recovered it from recorded load for older affected sets. Original provider values remain in the source payload.
- Added a versioned migration for Hevy’s next history page. Each refresh reads page one and a bounded history segment with one page of overlap. The continuation advances with the import transaction and resets at the end of the scan. Older edits on large histories appear as their pages are revisited. [Hevy API workout pagination](https://api.hevyapp.com/docs/)
- Preserved a valid Sessions filter during background reloads and moved the two workout-review breakpoints into `responsive.css`.
- Kept the planning workspace at its existing location and retired the client preview and export. Older ticket front matter remains readable.

## Verification

The repository gate, disposable Postgres suite, planning fixtures, and development and staging builds passed. The database suite covered the existing profile and timezone, old and new set rows through the dashboard API, paging past 1,000 workouts, and continuation rollback. JavaScript coverage checked a filter selected mid-request, a disappearing filter, and foreground reload behavior.

## Security and Git

Verification used synthetic data in a disposable local database. The working tree contains existing local changes. No commit, push, or deployment took place.

## Next slice

[Verify the hosted refresh](next-main-review-fixes-prompt.md)
