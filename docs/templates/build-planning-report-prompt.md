# Build prompt for the Mattrics planning and reporting tool

Work in the MattricsTrainingLog repository at `/Users/mwieland/dev/MattricsTrainingLog`. Build the complete planning and reporting tool described below. Begin by reading `AGENTS.md`, `docs/architecture-next.md`, `docs/codex-framework.md`, `docs/implementation-progress.md`, and `docs/mattrics-implementation-plan.md`. Preserve existing working tree edits. The implementation plan is the source for delivery scope and initial tickets. GitHub stays the home of code and pull requests.

## Outcome

I want a story map that I can read in a browser, a short set of testable requirements, and a release report I can share with a client. Codex and I should both be able to tell what has been delivered, what was verified, what is missing, and what is ready to work on next. Updating a ticket in the repository should update every report view on the next build.

## Source records

- Put the planning records under `docs/planning/` as Markdown with YAML front matter and documented, stable IDs. Use requirements, features or epics, stories, optional tasks, and milestones. Use a widely supported YAML parser with a safe loading mode. Keep the required metadata small. Put the narrative, acceptance criteria, and verification evidence in Markdown.
- Give stories a feature, a milestone, a position on the story map, a status, applicable requirement IDs, and dependencies where needed. Provide an explicit field for required versus optional scope. Tasks can link to a parent story and can carry their own status and dependency information. Require explicit inclusion and dedicated shareable copy for client-facing records.
- Use `planned`, `in_progress`, `implemented`, and `verified` for status. Track manual blockers separately and derive dependency blockers from the current records. Require verifiable evidence before a story or release check earns a checkmark. A ticket can reference a test result, a PR, or a slice report. Keep the evidence in a form that does not include credentials, raw health data, or private account details.
- Create a concise requirements list and seed the actual map, epics, stories, and release gates from the implementation plan. Seed all releases at story level and give M0 and v0.1 implementation-ready acceptance criteria and tasks. Cover Postgres, full Garmin and Strava archive imports, cross-source deduplication and reimports, manual review, Hevy exercise identity, scheduled Garmin/Hevy/Concept2 sync, backup and restore, cutover, and the 10-day workout brief. Start with M0, v0.1, v1.0, and v1.1 as mapped in the plan. Record prior Garmin and Hevy preflight evidence accurately, including the difference between the Hevy app connector and the backend REST API. Leave unverified work in its actual state.

## Report

- Build a read-only static site with a small generator in its own tooling directory. Store generated files outside `public/` and outside existing deployment inputs. Generate HTML and optional JSON from the same records. The site should work from a local preview and be portable as a static artifact.
- **Story map.** Use user activities as columns and delivery slices as horizontal bands. Use the documented order and link cards to readable detail with status, feature, target release, and acceptance criteria.
- **Features and releases.** Show status and progress per feature and milestone, with counts such as “8 of 12 required stories verified.” Compute progress from required stories in that scope. Keep optional work visible internally and label it separately. A milestone is ready only when its required stories and explicit release checks are verified. Show an empty scope as “No required stories” and exclude tasks from the story denominator.
- **Requirements and next work.** Show each requirement and its linked stories, identify requirements with no coverage or incomplete evidence, and show the next ready stories. List blockers and their prerequisites. Use a deterministic order based on milestone and story map order. A ready story is planned, has no manual blocker, and has its dependencies verified. Display implemented stories awaiting verification separately so they do not disappear from the work queue.
- **Client report.** Generate a separate artifact from an allowlist of client-facing records and approved fields. Include the story map, feature and release summaries, and progress for that visible scope. Recalculate counts from client-visible records and label the scope. Exclude internal notes, task details, private links, and internal IDs unless explicitly designated shareable. Leave excluded data out of the generated HTML, JSON, and assets. Give both reports accessible labels, readable layouts on desktop and mobile, and status text alongside visual indicators.

## Environments and workflow

| Mode | Expected use |
| --- | --- |
| Development | Local watch or rebuild and preview. Use synthetic fixtures to exercise progress, blockers, and sanitization. |
| Staging | Reproducible preview build for review. Check internal and client outputs, links, counts, accessibility, and client exclusions before sharing. Use an access-controlled preview if hosted. |
| Production/report release | Build a reviewed static artifact from a known revision. Keep the internal output private and package the client output separately for sharing. Include the source revision or build date for context. |

These are report modes and artifact boundaries. Support them locally with documented commands. Hosted URLs and any publishing require a separate request. The reporting tool is independent of the training database and runs without provider keys or live health data. Preserve the Mattwarden security and application deployment contracts.

Provide documented commands to validate planning records, build internal and client reports, and preview them locally. Use the smallest build setup that fits the existing repo. Document how to add a ticket, link a requirement, record evidence, regenerate the report, and prepare the client artifact. Link the usage instructions from the docs index. Leave the planning backlog as the authoritative progress record for the mapped work, using existing slice reports for detailed evidence.

## Acceptance checks

1. Unknown IDs, duplicate IDs, dangling links, invalid statuses, dependency cycles, and missing verified evidence fail validation with actionable file-level messages.
2. A fixture demonstrates planned, blocked, implemented, and verified work. A progress bar and checkmarks update correctly when a required story moves from implemented to verified. A release with all stories verified and an unmet release check still shows the open check.
3. A full rebuild produces current reports and removes stale pages or data from earlier output. Excluded internal fixture content is absent from the complete client artifact, including generated data and links.
4. The real Mattrics backlog renders in both views. Open both outputs locally and check the story map, progress indicators, next-work view, and a narrow viewport.
5. Run the relevant checks and summarize the artifacts, commands, and known limits. Complete this as tooling and documentation work. Product implementation, imports, provider changes, commits, pushes, and deployments are outside this prompt.
