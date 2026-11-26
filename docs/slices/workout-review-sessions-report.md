# Duplicate review in Sessions

Open **Sessions → Review duplicates** on a migrated linked-workout database. Choose a suggestion or select two recordings manually. Compare recording times, titles, sport, distance and available durations. Provider links open original activities. Select a preferred recording and inspect the preview before saving. Already-linked workouts support source changes. Undo reverses the most recent active review decision for that user.

The flat API requires Mattwarden authentication and checks origin and CSRF on writes. The disposable API test rejected a stale decision with HTTP 409. A Chrome rehearsal against the authenticated local router opened Sessions, compared three sources, selected recordings manually, saved a preference and undid it. The comparison fit the mobile viewport with horizontal scrolling inside the table.

[Next slice](next-workout-review-recovery-prompt.md)
