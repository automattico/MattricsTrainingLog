# Planning report and production readiness

25 November 2026 · main

## Summary

Reconciled the roadmap, v1.0 milestone, runtime and cutover checks, and planning tickets with the hosted probe, local recovery, Make delivery rehearsal, and fresh parity comparison. Added explicit tasks for production application TLS, hosted refresh verification, and preparing and restoring the production-bound database. The v1.0 milestone distinguishes deploying code from switching the live dashboard to Postgres.

## Validation

- The application production gate and disposable local Postgres integration suite passed on the working tree. The local deployment environment file passed a syntax-only check with diagnostics suppressed.
- The planning validator, report fixture suite, and development and staging builds passed. Both staging audiences were checked for generated milestone status, open release checks, and client projection.

## Security

The review used the repository and existing redacted evidence. It made no hosted changes and accessed no raw private workout records. The deploy environment syntax check emitted no values. Generated planning reports remain in the ignored tooling output.

## Decisions and remaining work

The hosted TLS probe closes the connectivity question. Actual application TLS and the full hosted refresh remain open. The four-week comparison establishes volume parity and leaves fatigue for explicit cutover review. The five mapping decisions can wait for production Exercises. The code release still needs an approved revision, release gate reruns, and authenticated smoke evidence. The read switch additionally needs production-bound history and recovery, verified Strava delivery, catalog promotion, and approval. Retain Sheets for rollback through observation.

## Git and deployment

The working tree was already dirty on main. The intended application changes, migrations, and planning work are local. The cached tracking ref was two commits ahead of local main, and a read-only lookup found a newer branch tip on GitHub. No branch update, commit, push, or deployment was performed.

## Next slice

[Verify hosted application TLS and full Garmin and Hevy refresh](next-planning-production-readiness-prompt.md)
