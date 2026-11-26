# Reversible recording decisions

Migration 006 adds a per-user review log and remembers unique automatic Garmin–Strava pairs. Source records, original links and exercises stay in place. The review log records membership separately from the preferred recording. Undo recalculates the workout using current provider values. A saved keep-separate decision blocks indirect links through a third recording.

Disposable Postgres tests covered splitting an automatic pair, undo, concurrent-tab stale versions, a third provider joining an existing workout, and stable workout IDs. Ambiguous candidates remained separate. A run on the same route the following day generated no suggestion.

[Next slice](next-workout-review-dashboard-prompt.md)
