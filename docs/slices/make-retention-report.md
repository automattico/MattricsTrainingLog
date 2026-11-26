# Make retention switch

The new Postgres destination defaults to 168 hours from the original fetch of a Make record. Replaying a delivery keeps that deadline. A newer delivery for the same owner and Strava activity ID updates the saved payload in place. A previously purged activity can enter again through a fresh upstream fetch.

After migration 005, enable the Settings control with `make_postgres_enabled` set to boolean true in the private site configuration. Use the same foundation database and user key as the Make destination. Under **Settings → Make workout storage**, clearing **Delete Make workouts after seven days** preserves the remaining rows until expiry is re-enabled. Re-enabling it asks for confirmation and immediately deletes overdue records. The control applies to the isolated Make table. The Sheet, prior backups, private catalog and archive/direct workout tables have separate lifecycles.

## Make integration for a future approved cutover

- Deliver each workout using a parameterized call to `mattrics.receive_make_workout(owner_key, source_id, fetched_at, payload)`. Supply the original UTC upstream fetch time on retries. Advance the Make cursor after a durable write.
- Read from `mattrics.current_make_workouts` for automatic expiry at the deadline. Schedule `SELECT mattrics.prune_make_workouts($1)` for each owner independent of new arrivals (including empty Make runs). Physical removal is on the next cleanup, delivery or applicable switch change. Provision and verify the cleanup cadence before enabling the production feed, including an idle-feed check. Exclude transient payloads from long-lived backups.
- Verify applicable retention permission for the actual source. The setting changes database behavior. The Strava API policy describes a seven-day transient cache. [Strava API Policy](https://www.strava.com/legal/api_policy)

## Evidence

The disposable Postgres suite exercised default expiry, late edits, stale replay, overdue rows while the switch is off, switching it back on, and owner isolation. The authenticated Settings API test persisted the switch independently of profile settings. The repository gate passed. All payloads were synthetic. Existing dirty work was preserved. No hosted migration, schedule, commit, push or deployment took place.

[Next slice](next-make-retention-integration-prompt.md)
