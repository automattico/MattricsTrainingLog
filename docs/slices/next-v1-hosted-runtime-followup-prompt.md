# Finish hosted refresh verification

Read the [verification report](v1-hosted-verification-report.md) and [original handoff](next-v1-hosted-verification-prompt.md). The Mac and hosted Postgres checks passed, including the hosted wrong-hostname control. The temporary Mattwarden endpoint and both private inputs were removed.

Continue runtime verification while the five exercise decisions wait for production Exercises. Keep the measured fatigue difference visible in the cutover review.

- Verify hosted Garmin FIT decoding and full Garmin and Hevy refresh when opening or reloading the authenticated dashboard. Exercise cold start, paging, timeout and retry behavior on the approved hosted path.
- Require equivalent `verify-full` TLS in the production application connection before switching dashboard reads. Update [ST-HOST-RUNTIME](../planning/stories/st-host-runtime.md) and the sync release check with the results.
- Keep the current Strava-to-Sheets scenario. The owner deferred scheduled cleanup. Revisit retention and idle expiry with any later decision to enable the temporary Strava Postgres cache.
