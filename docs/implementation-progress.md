# Implementation progress

## Release and cutover safeguards · 2026-11-25

Commit-based staging, the shared Postgres TLS connection, the hosted migration runner, and checksum-guarded catalog promotion passed local checks. The real hosted rehearsal, approval-gated code deploy, Strava delivery proof, production catalog promotion and final parity are pending. [Release preparation report](slices/cutover-prep-implementation-report.md) · [Next slice](slices/next-cutover-prep-hosted-prompt.md)

## Review fixes and private planning workspace · 2026-11-25

The planning report is a single private workspace at its existing URL. It keeps section tabs, collapsible cards, and status-colored release bands.

Linked imports preserve the saved owner profile and effective bodyweight loads. Hevy refresh fetches the newest page on each run and resumes its rolling history scan from the last committed page. Background reloads preserve a valid Sessions filter. The review breakpoints live in the shared responsive stylesheet. [Review fixes report](slices/main-review-fixes-report.md) · [Hosted verification handoff](slices/next-main-review-fixes-prompt.md)

## Production release readiness · 2026-11-25

The current worksheet-backed service remains live. M0 has one open runtime story and v0.1 history is verified locally. The v1.0 sync and cutover checks are in progress. The application gate and disposable Postgres integration suite passed on the local working tree. Production code deploy, production database setup, catalog promotion, and switching dashboard reads each require explicit approval and evidence from the intended release revision.

The next slice is to enforce verified TLS on the actual Postgres application connection, then verify hosted Garmin FIT decoding and both providers' full app-open refresh. Remaining production work includes Make delivery for old edits and late arrivals, database preparation and recovery, catalog promotion, fatigue review, and observation with rollback available. [Readiness checklist](planning/milestones/ms-v1-0.md#production-readiness--25-november-2026) · [Planning refresh report](slices/planning-production-readiness-report.md) · [Next slice](slices/next-planning-production-readiness-prompt.md)

## v1.0 status · 2026-11-25

- Verified Hevy REST template identity across existing German workout labels and English catalog labels. Closed the Hevy REST story and display-change task on that evidence. [Display-language verification](slices/hevy-rest-display-language-report.md)

- Production Exercises is the sole routine catalog editor. The parity preview supports create, edit and merge for local rehearsal. The five remaining owner exercise decisions can wait for production Exercises while hosted verification proceeds. Carry the measured fatigue gap into cutover approval. The parity inbox dropped one setless entry and kept all scoped entries. All 38 configured exercises had recorded use in the available history. [Catalog ownership report](slices/v1-catalog-ownership-report.md)

- Verified the private Hetzner probe path and confirmed removal of the route and inputs. Hosted PHP 8.4.28, PDO Postgres, the Hevy read and the Garmin list and FIT download passed. The hosted Postgres retry returned `1` over `verify-full` TLS and rejected an incorrect hostname. Hosted FIT decoding and full app-open refresh timing remain open. The owner deferred scheduled cleanup for the future temporary Strava Postgres cache. [Hosted verification report](slices/v1-hosted-verification-report.md)

- The fresh October 28–November 24 Berlin comparison matches all four weeks on sessions, duration and distance. The isolate now has 31 verified Hevy template assignments, including 30 from the original backlog and one from the latest workout, and the Pádel label matches the Sheet. Five template decisions and fatigue parity remain open. The existing links, preferences, Keep separate decisions and all 322 linked sets passed preservation checks. Both release checks remain open. [Parity report](slices/v1-fresh-dashboard-parity-report.md)

In the preceding October 27–November 23 rerun, the 14 confirmed Hevy–Strava links brought weekly sessions, duration and distance into agreement with the full Sheet. That rerun identified the original 35 unmapped templates and the sport-label discrepancy. [Parity report](slices/v1-fresh-dashboard-parity-report.md)

## v1.0 status · 2026-11-24

- **Parity remains open.** The deployed dashboard serves the workbook’s ten-day view. All 17 rows in the full workbook tab during October 27–November 23 matched Strava identities and volume. The isolate found fourteen Hevy/Strava candidates, a sport mapping discrepancy and differing fatigue results. The active preview’s saved decisions cover other recording pairs. Starts, durations, titles, exercise order and every comparable weighted set corroborate the fourteen likely duplicates. The November 23 Garmin export, signed-in All Activities list and Personal API agree that the comparison window contains zero Garmin workouts. The May regression and paired restores passed. [Dashboard parity and restore](slices/v1-fresh-dashboard-parity-report.md)
- **Make handoff rehearsed locally.** Inspected the active five-hour flow and checkpoint. Disposable Postgres verified failure rollback, durable checkpoint commit, an older edit and late arrival, replay and retention. The live bridge and edit discovery remain open. [Make handoff](slices/v1-make-handoff-rehearsal-report.md)
- **Next verification.** Verify Strava late delivery and edits, review overlap candidates, and obtain approval for the scoped hosted probe. Use app-open Garmin and Hevy refresh. Complete catalog promotion under separate approval, keep Make and Sheets available through observation and leave both release checks open. [Hosted verification handoff](slices/next-v1-hosted-verification-prompt.md)


## v1.0 status · 2026-11-23

- **Verified locally.** Duplicate review, source selection, durable keep-separate decisions and undo work in the Sessions preview. The latest layout puts search and manual selection together and preserves the open comparison when filtering. Desktop and mobile Chrome checks, the repository gate and planning validation passed. [Comparison layout report](slices/workout-review-layout-report.md)
- **Recovery rehearsed.** A separate restore preserved all 596 existing exact links, review decisions, and dashboard results. A paired synthetic restore also preserved Hevy sets and an account-scoped mapping with the private JSON catalog. [Recovery report](slices/workout-review-recovery-report.md)
- **Next verification.** Compare the latest four complete weeks using a fresh Sheets-backed snapshot and an isolated Postgres restore brought to the same cutoff. Check session counts, duration, distance and fatigue after the matcher and stationary timer changes. The earlier May result is a historical regression based on a saved local cache. [Next slice](slices/next-workout-review-confidence-prompt.md)
- **Open for cutover.** Hosted web PHP, Postgres TLS and provider access still need evidence through the approved probe path. Verify on-open Garmin and Hevy refresh and delivery of older Strava edits through Make. Confirm the Make-to-Postgres path and cursor behavior. Concept2 direct access is deferred. Keep Make and Sheets available through the observation period and obtain separate approvals for production changes and legacy retirement. [v1.0 planning checklist](planning/milestones/ms-v1-0.md)

- **Required before v1.0 cutover.** Promote and verify the reconciled exercise and activity-type catalogs before switching dashboard reads. Forearms is deployed, and the staging catalog merge is verified. The production promotion remains open until an approved maintenance path is rehearsed and both live catalogs pass verification. [Cutover procedure](planning/milestones/ms-v1-0.md#required-production-catalog-promotion)

## Current architecture

The Mattwarden application and repository cleanup are deployed from `main` at cleanup commit `b97cab4`. The deploy-time production gate passed and the managed remote trees matched the explicit allowlist on 2026-09-23. The active Hetzner webroot is normalized to `public_html/mattrics`, which contains only Mattwarden's `.htaccess` and `index.php`; the temporary staging target, nested-shim rollback, `/mattrics-private`, and `/mattrics-lib` are absent. The workout-AI credential still returns Anthropic HTTP 401, but repair is explicitly deferred; the browser handles non-JSON upstream errors without a syntax error. Mattwarden owns authentication, sessions, cookies, CSRF generation, headers, static serving, PHP dispatch, and logout. The application is a static shell plus six flat authenticated endpoints.

## Slice history

Earlier Foundation/Postgres slices established canonical schema experiments, importers, compatibility reads/writes, exercise configuration, and connectors. The bundled Docker/Postgres runtime, schema files, and CLI preparation/import scripts were removed during repository cleanup. Compatibility modules that current API endpoints still import remain in `lib/`. Detailed reports remain in `docs/slices/` as historical records; paths and runtime claims in those reports describe the repository at the time and are not current operating instructions.

| Slice | State | Current note |
|---|---|---|
| Forearms fatigue | verified locally | One score across both arms, exercise prefill from each environment's catalog, and saved overrides including zero. The production snapshot, local catalog rehearsals, and staging integration passed. [Slice report](slices/forearms-fatigue-report.md) |
| Foundation Docker/Postgres runtime | removed | Docker, Compose, schema, migration, and CLI runtime scaffolding were retired; API compatibility modules remain |
| Runtime diagnostics | removed | Diagnostics are not public product endpoints |
| Live connectors | retained | Authenticated connector administration remains a product feature |
| Mattwarden migration | deployed, AI verification explicitly deferred | Production data and app/gate content deployed 2026-09-22; Anthropic rejected the configured key with 401 |
| Hetzner parent-webroot normalization | deployed, verified, and cleaned up | Active target is `public_html/mattrics`; staging, nested-shim rollback, and legacy external paths are absent |
| Real-gate integration check | validated locally | Passkey, static shell, session, guards, PATH_INFO, settings write/readback; synthetic upstream intentionally returned 502 |
| Hevy first-line recognition | deployed; authenticated verification pending | JavaScript and PHP detect standalone Hevy brand terms in the first non-empty description line, with canonical-read header skipping and regression tests |
| Detail workout visibility | deployed; authenticated verification pending | The detail modal removes its `is-hidden` class when parsed or canonical exercises exist, so the workout breakdown is visible |
| Pádel activity support | deployed; authenticated verification pending | Padel and its accented/tennis aliases share canonical display/filter metadata, card metrics, and an approved duration-scaled fatigue config |
| Activity type emojis | verified locally | Swim, Other, Breathwork, Mobility, Soccer, and Skate have dedicated icons in the shared display map. [Slice report](slices/activity-type-emojis-report.md) |
| Repository cleanup | deployed | Obsolete Docker/Foundation/Postgres scaffolding, historical prompts, dead code/CSS, and orphaned assets were removed; required compatibility modules and `CLAUDE.md` remain |
| Planning and reporting tooling | local preview | The private workspace tracks M0 through the [provisional v1.2 Health Data milestone](planning/milestones/ms-v1-2.md). [Planning backlog](planning/README.md) |
| M0 preflight and samples | archive review verified, runtime review open | Audited eight stories, nine tasks and two release checks. Archive samples meet the release criteria. Hosted runtime and real Hevy template identity checks remain open. [M0 audit](slices/m0-preflight-ticket-audit-report.md) |
| Local Postgres and synthetic imports | verified locally | Native loopback cluster, versioned workout schema, replay and exact source linking, transactional import, and the existing dashboard response with structured Hevy sets. [Slice report and commands](slices/local-postgres-synthetic-import-report.md) |
| Database environment setup | verified | Dev and staging migrations and synthetic suites passed. Staging restored into a separate database and matched the source rows. Read-only `SELECT 1` passed from the Mac with TLS hostname and certificate verification. [Slice report](slices/database-environments-report.md) |
| First direct Hevy workout | verified locally | One authorized REST workout reached the existing dashboard API through an isolated Postgres database. Exact replay and a private title and rep edit preserved source, canonical workout, and child IDs. [Slice report](slices/first-direct-workout-report.md) |
| Hevy exercise identity and review | verified locally | Same-title templates retain distinct account-scoped mappings and sets. A title edit preserves the mapping, and exercise review updates historical fatigue and reference loads. [Slice report](slices/hevy-exercise-identity-report.md) |
| v0.1 historical archive preview | verified locally | Both account exports resumed from committed checkpoints and replayed with zero writes. The dashboard served 1,474 canonical workouts, including 596 exact linked pairs. Twenty-four probable pairs remain for private review. The preview header shows Local Postgres workouts, a Local JSON catalog, and the last successful import. [Slice report](slices/historical-import-preview-report.md) |
| Exercise Manager merge layout | verified locally | Names and merging share equal-width cards that stack on narrow screens. The merge picker includes the full eligible catalog regardless of list search and filters. [Slice report](slices/exercise-manager-merge-layout-report.md) |
| Standalone Exercise Manager cards | verified locally | Matching names and merging use the same card style as the other editor sections, with equal columns on desktop and a vertical stack on phones. [Slice report](slices/exercise-manager-standalone-cards-report.md) |
| Compact Exercise Manager merge | verified locally | One full-width card groups Edit names and Merge in its header. The inline picker searches the eligible catalog and requires a target before showing the warning and enabling confirmation. [Slice report](slices/exercise-manager-compact-merge-report.md) |

## Current validation

The source contract, endpoint guards, static CSP, local router dispatch, deploy refusal cases, PHP suites, JavaScript suites, lints, and local browser behavior are validated by `scripts/prod-gate.sh` plus the commands recorded in `docs/slices/mattwarden-migration-report.md`. The full gate passed again during the 2026-09-23 deployment with no skipped database blocks, followed by successful managed-remote-tree verification. An isolated local run against the merged Mattwarden gate is recorded in `docs/slices/mattwarden-real-gate-validation-report.md`. Hevy header recognition has JavaScript, PHP import, and canonical-read regression coverage. Detail-modal visibility has focused DOM regression coverage for both workout breakdowns and ordinary notes. Pádel aliases, canonical filtering/display metadata, card metrics, approved activity configuration, and non-zero duration-based fatigue stimulus have focused JavaScript coverage.

On 2026-11-23 the app gate and 29 synthetic Postgres assertions passed, including Hevy template identity, account separation, review assignment, and recalculation against loaded dashboard data.

## Next slice

Use the [hosted cutover verification handoff](slices/next-cutover-prep-hosted-prompt.md). The private host path, web PHP, outbound provider access, and host-side Postgres TLS probe passed. The application builder also passed both controls in the disposable TLS fixture. The hosted application connection, FIT decode, and full app-open timing remain open.

The [Hevy REST sample](slices/hevy-rest-preflight-report.md) verified pagination, workout and exercise template IDs, and structured sets. Both exports are present. The [Garmin inventory](slices/garmin-archive-inventory-report.md) records workout and wellness coverage and exact FIT overlaps with the [Strava archive](slices/strava-archive-inventory-report.md).

The [v0.1 history report](slices/historical-import-preview-report.md) records archive and legacy-cache reconciliation, unresolved candidates, and the completed checks. Use the [private match review handoff](slices/next-historical-preview-review-prompt.md) for user-selected candidates.

Perform authenticated production verification of the newly deployed Hevy header recognition, workout-detail visibility, and Pádel behavior using the corresponding prompts below. Workout AI remains broken because Anthropic rejects the configured credential; repair is explicitly deferred and is not a deployment blocker.

Use `docs/slices/next-hevy-header-runtime-verification-prompt.md` to verify real imported descriptions and any needed re-import without exposing private workout data.

Use `docs/slices/next-detail-workout-visibility-production-prompt.md` to verify the workout breakdown in an authenticated production session; its commit/deploy steps are already complete.

Use `docs/slices/next-padel-production-verification-prompt.md` to verify canonical display, metrics, filtering, and fatigue behavior without changing the source pipeline; delivery is already complete.

## v1.0 recovery and transition review

Full local history and both dashboard catalogs were restored and reconciled. The first comparison found material volume differences in three of four weeks. The reviewed recovery rehearsal resolved those differences for that version. A Make-first transition is under discussion, Concept2 is deferred, and the direct refresh prototype remains disabled by default. Both release checks remain open. [Rehearsal report](slices/v1-runtime-recovery-report.md) · [Next slice](slices/next-v1-make-first-prompt.md)

Strava title and sport changes were verified together on one synthetic linked recording, preserving its workout ID, measurements and dashboard count. Live discovery of edits and indefinite retention permission remain open. [Slice report](slices/strava-corrections-report.md)

The Strava archive importer now recognizes `Football (Soccer)` as `Soccer`. Three local history workouts were corrected and verified through the dashboard API. [Soccer correction report](slices/soccer-archive-correction-report.md)

The new Make Postgres store has a seven-day expiry default and an independently saved Settings switch. Verified with synthetic data and authenticated settings calls. Production integration and idle cleanup scheduling remain pending. [Slice report](slices/make-retention-report.md)

## Workout matching and duplicate review

- [Reversible recording decisions](slices/workout-review-decisions-report.md)
- [Selected recording on the dashboard](slices/workout-review-dashboard-report.md)
- [Duplicate review in Sessions](slices/workout-review-sessions-report.md)
- [Workout review recovery and parity](slices/workout-review-recovery-report.md)

The later fresh comparison confirmed weekly session, duration, and distance parity after the matching and stationary duration work. Fatigue, hosted runtime, live edit delivery, observation and cutover approval remain open. [Fresh parity](slices/v1-fresh-dashboard-parity-report.md)

The Sessions comparison now leads with workout titles, highlights different measurements and separates the match decision from source selection. [Comparison report](slices/workout-review-comparison-report.md)

Sessions now saves a match and preferred source in one click. Saved comparisons and undo are available across reloads. [One-click review](slices/workout-review-one-click-report.md)

The duplicate review list now opens on larger discrepancies and supports search, saved comparisons, and already-linked workouts. [Priority filter report](slices/workout-review-priority-report.md)

The matcher now recognizes corroborated stationary Garmin–Strava workouts even when both recorded distances are zero. [Stationary matching report](slices/workout-review-stationary-report.md)

Duplicate review ranks match evidence ahead of measurement gaps, presents one entry per workout decision, and uses timer duration for stationary workouts. The prior dashboard parity result needs a refresh before production approval. [Confidence and duration report](slices/workout-review-confidence-report.md)

Sessions now separates the comparison list from review actions. Search and filters preserve the open comparison. [Comparison layout report](slices/workout-review-layout-report.md)
