# Recognize stationary workout duplicates

The exact-boundary Garmin–Strava rule now accepts two recorded zero distances for workouts and weight training when the sport and title agree and positive timer times agree within one second. The existing ambiguity and review-decision checks still apply. Provider moving-time calculations can differ for stationary sessions, so moving time is excluded from this identity check.

The disposable Postgres suite verified an automatic link with differing moving times and a Keep separate override. The private local preview confirmed the reported pair is now one workout with Strava in use.

The comparison keeps the reported zero moving time visible. When a workout or weight-training recording has zero distance and a positive timer, a zero moving time contributes no warning or priority score. A local classifier check confirmed that timer and outdoor running discrepancies still qualify.

[Next slice](next-workout-review-stationary-prompt.md)
