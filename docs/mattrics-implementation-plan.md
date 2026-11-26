# Mattrics implementation plan

**Updated 25 November 2026 · Design and rollout guide**

Evolve the personal Mattrics Training Log in place. Keep the current dashboard, exercise editor, fatigue model, and Mattwarden access. Move training history to Postgres, including as many years as the full Garmin and Strava account exports contain. Refresh new workouts directly from Hevy and Garmin Connect. Keep Make as the Strava connection during the transition. Direct Concept2 Logbook access is deferred. Use the resulting history to review training and prepare the next workout.

The current app is the implementation target. MattricsNext and the older Foundation reports are references. This plan records the proposed migration; the current production architecture and deploy instructions continue to describe the live system until cutover.

After the workout roadmap, plan a dedicated Health Data section for Garmin readings, manual body measurements, and progress charts.

## What we know

| Area | Evidence and remaining check |
| --- | --- |
| Garmin Connect | The full archive was inventoried and imported locally. The Personal API supplied FIT data that decoded locally. Hosted token, list, and FIT download passed. Hosted decoding and app-open refresh remain open. |
| Hevy | Production web PHP read a valid REST page. Local authorized reads verified pagination, sets, and stable template IDs across observed display languages. Full app-open refresh and edit recovery remain open on the host. |
| Concept2 | Direct access remains deferred. Keep the existing path through Strava during v1.0. |
| Hetzner | Pinned SFTP, Mattwarden-dispatched web PHP, PDO Postgres, and host-side certificate and hostname validation passed. Shell execution is unavailable on the current plan. Enforce TLS in the actual application connection and verify full request timing. |
| Legacy pipeline | Make currently delivers Strava activities to Sheets, and Mattrics reads the most recent ten days via Apps Script. The active flow uses a start-time lookback and inserts new IDs. Live delivery of older edits and backdated activities still needs proof. |

## Target architecture

`Account archives and provider APIs → source records and import runs → linked Mattrics activities → dashboard and coach brief`

- Keep the PHP API and vanilla JavaScript UI. Preserve the existing dashboard response shape for migration parity, then add bounded date ranges and pagination where years of history make full loads impractical. Preserve “all time” totals without returning every raw activity or track to the browser.
- Use a canonical activity ID independent of provider. Store source identities, original timestamps and units, normalized values, file fingerprints, and import provenance. Keep multiple source records linked to the same activity. Store strength sessions, exercises and sets separately from endurance details and planned routines.
- Store import and sync runs with counts, cursors, and actionable errors. Advance cursors only after durable writes. Upsert on provider, account and provider record ID where available. Serialize overlapping runs per source and use a bounded overlap or provider change feed so late edits and retries converge.
- For v1.0, refresh Garmin and Hevy on authenticated open or reload and keep the existing Make schedule for Strava. Choose an approved private runner for migration and recovery. Keep secrets and archives outside the webroot and out of Git, deployment mirrors, and logs. Mattwarden continues to protect browser reads and writes.
- Review the historical schema and importer code for reuse. Bring back only the migrations and CLI tooling needed for this app. The current database URL switch alone cannot provision a usable database.

## Full historical backfill

1. **Request both official account archives.** Start the requests early and keep the originals in private storage. Inventory them locally before scheduling an import. The target is every recoverable recorded workout across the available years. Wellness streams, social data, and attachments are outside the initial workout import. [Garmin full export instructions](https://support.garmin.com/en-SG/?faq=W1TvTPW8JZ6LfJSfK512Q8), [Strava bulk export instructions](https://support.strava.com/en-us/articles/15401919-how-do-i-export-my-strava-data)
2. **Inventory the Garmin archive.** Find the activity FIT files, including nested ZIPs, and inspect any companion metadata that links files to Garmin activities. Parse each FIT session with a decoder validated against Garmin’s activity format. Keep lap, sample or multisport details private and attach them where the activity model can represent them. Separate device-recorded sessions from activities imported into Garmin from another provider. [Garmin FIT activity guide](https://developer.garmin.com/fit/articles/cookbook/decoding_activity_files.html)
3. **Inventory the Strava archive.** Read its activity index and link each row to a referenced original file where available. Inspect real exports to verify filenames, field formats, time zones and available file types. Support FIT first and add TCX or GPX when the archive contains them. Keep metadata-only activities such as manual entries or rows with missing files. Preserve titles, sport labels, descriptions and source IDs when present. Treat Strava as the authoritative source for the user's Strava edits and independently recorded Strava-only sessions, with explicit provenance.
4. **Dry-run and import in restartable batches.** Validate archive paths and decompressed size, cap nested archive depth, and report corrupt or unsupported records by reason. Keep timestamps in UTC plus the original value and timezone evidence. Flag uncertain offsets for review. Normalize duration and distance units, distinguishing elapsed, moving and timer time. Report counts and date coverage by year and sport, including unmatched files and rows. Keep a checkpoint per batch and make reruns safe after interruption. Use content hashes for archive and file replay detection.
5. **Reconcile the legacy Sheet against the merged history.** Use it to check recent coverage and find metadata overrides or genuinely missing records. Establish the source of unmatched rows before migrating them into permanent history or AI inputs. Keep original account exports distinct from API data copied via Make. Use direct Hevy history to recover set detail where available, since an old Strava description may be text-only.

## Deduplication and field selection

Apply matching across the archives, live connectors, and repeated imports. Link records without erasing the originals or overwriting manual decisions.

| Match evidence | Rule |
| --- | --- |
| Same provider account and external activity ID | Upsert the existing source record. Link API and archive records when they carry the same verified ID. |
| Same original FIT content | Link matching sessions using the decoded file hash and session boundary or index. A matching multisport file does not make all of its legs the same workout. |
| Same underlying workout with changed metadata or re-encoded files | Generate candidates using nearby starts and compatible sports, accounting for known time offsets. Compare duration, distance where meaningful, and optional track or lap evidence. Auto-link only unique, strong matches. Titles and filenames provide supporting evidence. |
| Several plausible matches, coarse timestamps, or different recording boundaries | Put them in review. Keep separate warmups, cooldowns, back-to-back workouts, multisport legs, and independently recorded sessions unless the evidence supports a link. |
| Manual link, split, or keep-separate decision | Persist the decision and supporting evidence. Match reruns must honor it. An undo can restore separate activities from their source records. |

Use native recordings for measured fields where possible. Hevy supplies lifting sets and reps, a device-native Garmin recording supplies time, route or heart rate, and Concept2 supplies erg-specific results. Preserve the user's chosen title and sport as Mattrics overrides. During backfill, offer differing Strava export labels as candidates for those overrides. Track the selected source per field. A workout linked from several sources contributes once to counts, volume and fatigue.

For Hevy, key mappings by template ID and account where appropriate. Hevy exercise titles, German and English aliases, and old names remain display or legacy matching data. An unmapped template goes into review. Mapping it later can repair historical analysis from the preserved records.

## Delivery slices

| Slice | Deliverable and acceptance |
| --- | --- |
| 0 · Preflight and samples | Request both full archives. Inventory representative records locally, including an older activity, an indoor/manual session, and an overlap. Confirm Hevy REST access and choose a private Hetzner probe path. Test CLI and web PHP, database TLS with `SELECT 1`, cron behavior, and outbound provider calls before relying on that runtime. |
| 1 · Postgres and first direct import | Versioned migrations, a private local Postgres setup, import tracking, and one real Hevy session through the current dashboard response. Verify preserved template IDs, repeat sync, an edited workout updating in place, and visible unresolved mappings. If Hevy REST access is delayed, use the already verified Garmin API read to exercise the same persistence path. |
| 2 · Historical archives and review | Import both full archives into an isolated database, initially in dry-run mode. Test one identical-file overlap, one probable match with changed metadata, one manual-only activity, and cases that must remain separate. Verify restart, safe rerun, and manual split persistence. Publish year-by-year and sport-by-sport counts, oldest/newest dates, linked pairs, unmatched rows, and rejects. Check known old workouts and reconcile activity totals after deduplication. |
| 3 · App-open refresh and Make delivery | Verify independent Garmin and Hevy refresh on authenticated app open or reload. Rehearse a Make-to-Postgres branch while keeping the Sheet. Reuse the FIT importer for Garmin API downloads. Test cursor recovery, token expiry, overlapping archive and live records, and temporary upstream failures. Expose last success and sync status in Mattrics. |
| 4 · Side-by-side and cutover | Compare several weeks against the existing dashboard, including edits, late arrivals and duplicate recordings. Verify historic coverage, recent workout and fatigue results, and a restore to a separate database. Complete the [required catalog promotion](planning/milestones/ms-v1-0.md#required-production-catalog-promotion) before switching dashboard reads to Postgres. Keep the old read path available for rollback during observation. Keep Make for approved Strava coverage. Obtain separate sign-off before retiring a legacy component. |
| 5 · Workout review and planning | Add a copyable **Last 10 days** brief with session facts, relevant lift history, fatigue estimates, and data gaps. Use only fields whose provider terms and privacy settings permit the chosen AI use. Keep reviewed routine save in the official Hevy connector initially. Consider a scoped read-only Mattrics connector after the brief works reliably. |
| 6 · Health Data | Design a private Health Data section, verify source coverage, then implement Garmin health imports, dated manual measurements, time-filtered charts, BMI, and the hip/waist ratio. Track extra circumference sites as optional follow-up. |

Each implementation slice should follow the repository's Codex framework, update implementation progress, and record validation and the next slice. Use synthetic fixtures in Git. Keep actual archives and account-specific validation outputs private.

## Later scope · Health Data

- **Garmin readings.** Target body weight, VO₂ max, resting heart rate, and other heart-rate readings selected during design. Confirm coverage for the account and device through the authorized API and archive before choosing how each metric is imported. [Garmin Personal API reference](https://developer.garmin.com/gc-developer-program/personal/swagger-docs)
- **Manual measurements.** Make body weight easy to log on its own. Support hip, waist, neck, and high waist at belt height as distinct measurements. Consider adding thigh and biceps circumference later. Keep the source of each reading visible when manual and Garmin weight entries coexist.
- **Charts and calculations.** Build a Health Data section with time-range filters, units, and visible data gaps. Calculate BMI from recorded body weight and the profile height. Confirm the requested hip/waist ratio direction and how to pair hip and standard-waist readings during design. [CDC BMI formula](https://www.cdc.gov/bmi/about/)

The [Health Data milestone](planning/milestones/ms-v1-2.md) is provisionally v1.2. Its design story captures the measurement workflow, Garmin availability, source handling, and calculation rules before implementation begins.

## Requirements, story map, and progress reporting

Keep the requirements and backlog in version-controlled Markdown under `docs/planning/`. A small, read-only HTML report generated from those files will show the story map, feature and release progress, requirements coverage, and the next ready work. GitHub continues to host the code and PRs. Tickets in another system are optional references.

- Give requirements, features or epics, stories, tasks, and milestones stable IDs. Keep the requirements list short and testable. Link each story to the relevant requirements, its feature, and its target milestone. Separate standalone implementation tasks only when they need independent tracking.
- Use story map columns for user activities and rows for release slices. The roadmap groups **M0** preflight (slice 0), **v0.1** historical preview (slices 1–2), **v1.0** direct sync and cutover (slices 3–4), **v1.1** workout planning (slice 5), and provisional **v1.2** Health Data (slice 6). Milestones describe outcomes and release checks.
- Track planned, in progress, implemented, and verified separately. Record evidence before marking a story verified. Count verified required stories against the required stories in scope. Verify milestone checks separately from closing stories. Show missing coverage, blockers, and dependencies in the internal view.
- Update the affected Markdown records and record validation with each implementation slice. Regenerate the report as part of that update. Treat generated HTML and JSON as derived output.

Use **local development** for editing and building the report. Use a **staging preview** to review the generated layout, links, and counts. Keep the private workspace and its generated artifacts outside the Training Log application deploy tree.

Keep **training app environments** separate from the reporting build. Develop on a local database with synthetic fixtures. Validate imports and restore procedures against an isolated staging database and only the private data authorized for that test. Reserve production for the live database, credentials, schedules, and cutover. The [database environments runbook](database-environments.md) covers the synthetic staging rehearsal and read-only production TLS verification.

Use the [planning backlog and report guide](planning/README.md) to validate and rebuild the current private workspace.

## Transition decisions

- Keep Make as the Strava connection and rehearse a separate Postgres branch before switching dashboard reads. Keep the Sheet route and its cursor through observation. Enable the isolated short-lived cache after permission and delivery verification. Keep Strava API-derived rows out of the permanent archive and AI brief. A new paid Strava subscription or developer connector is unnecessary for the proposed path. [Strava API Policy, §§5.3, 6.2 and 6.6](https://www.strava.com/legal/api_policy)
- Use Garmin Connect Personal API for automatic Garmin reads. Treat USB, manual FIT or Gadgetbridge as alternate ways to feed the same importer if bypassing Garmin Connect becomes important. [Garmin Personal API](https://developer.garmin.com/gc-developer-program/personal/swagger-docs), [Gadgetbridge FIT export](https://gadgetbridge.org/internals/automations/auto-export/)
- Keep fitness recommendations separate from the core data migration. Start with the current fatigue model and make missing sets, unmapped exercises and stale sources visible in the brief. [Hevy REST API access](https://help.hevyapp.com/hc/en-us/articles/44834585028375-How-to-Access-and-Use-the-Hevy-API), [Hevy assistant connector](https://www.hevyapp.com/hevy-mcp/), [Concept2 Logbook API](https://log.concept2.com/developers/documentation/)

## Next release step

Finish verified application TLS and hosted Garmin FIT decoding and Garmin and Hevy refresh on an approved isolated path. The [v1.0 readiness checklist](planning/milestones/ms-v1-0.md#production-readiness--25-november-2026) tracks the remaining code deployment and production data cutover. [Next slice](slices/next-planning-production-readiness-prompt.md)
