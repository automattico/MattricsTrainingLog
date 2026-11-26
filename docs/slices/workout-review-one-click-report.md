# One-click duplicate review

Each comparison column starts with one title, one original activity link when available, and its merge button in the table header. Differences use an inline warning marker and row highlight. Partial data uses a separate inline information marker. The existing review API saves the merge and preferred source in one decision. Linked workouts show their current source with an **In use** label. The measurement table highlights differences and explains missing data.

**Review later** moves the comparison under **Saved for later**. Manually selected comparisons can also be saved and reopened after reload. The API supplies the latest decision's pair so Undo can reopen it. The review panel advances after a successful decision and refreshes the current comparison when a decision is stale.

The isolated Chrome preview verified both Garmin and Strava merges, Keep separate, Review later across reload, Undo, keyboard focus, source buttons at mobile width, a missing Garmin link and a three-recording preference change. The disposable Postgres suite verified manual deferral, undo recovery and stale-version rejection. Repository and planning gates passed.

[Next slice](next-workout-review-one-click-owner-prompt.md)
