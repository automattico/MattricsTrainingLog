# Mattrics planning backlog and reports

The Markdown records here track delivery, blockers, and verification for the [Mattrics implementation plan](../mattrics-implementation-plan.md). GitHub hosts code and pull requests. Use the [slice reports](../slices/) for detailed verification.

The [v1.0 milestone](milestones/ms-v1-0.md#production-readiness--25-november-2026) carries the current production readiness checklist and the active hosted verification handoff. The generated internal report shows that checklist under v1.0 in Releases.

## Build and preview

Run these commands from the repository root. The generator uses Ruby 2.6 or later with the standard Psych, JSON, and RubyGems libraries. Local HTTP preview uses Python 3.

```sh
ruby tools/planning-report/planning.rb validate
ruby tools/planning-report/test.rb
ruby tools/planning-report/planning.rb build development
ruby tools/planning-report/planning.rb build staging
```

Open `tools/planning-report/.output/staging/internal/index.html` for the private planning workspace. Use the **Story map**, **Releases**, **Features**, **Stories**, **Requirements**, and **Next work** tabs. Release headers show status and progress while collapsed. Links to a release, story, or task switch tabs and open the relevant cards.

For a local HTTP preview of the workspace:

```sh
python3 -m http.server 8765 --bind 127.0.0.1 --directory tools/planning-report/.output/staging/internal
```

Open the [planning workspace](http://127.0.0.1:8765/). The generator packages the existing Brandhub styles, Mattrics mark, fonts, and font licenses alongside the HTML and JSON. To refresh the brand tokens, copy `brand.css` from the local Brandhub checkout (`~/dev/brand`) to `tools/planning-report/brand.css`, then rebuild.

| Mode | Workflow |
| --- | --- |
| Development | Rebuild after editing and refresh the preview. Substitute `development` in the preview command. The test command builds synthetic fixtures in a temporary directory. |
| Staging | Build from the source being reviewed. Use the same inputs and `SOURCE_DATE_EPOCH` for repeatable output. Review story links, counts, open release checks, keyboard navigation, and narrow layouts. |
| Release | From a reviewed revision with clean planning records, tooling, and brand assets, run `ruby tools/planning-report/planning.rb build release` to build the private workspace with the source revision displayed. |

A successful rebuild replaces that mode's generated output. The workspace contains the full backlog and stays private. The generator ignores legacy `client` front matter.

## Add or update a ticket

Keep one Markdown file per record in the matching folder. Use stable uppercase IDs with the prefixes below and name new files after the lowercase ID. Leave room between positions when ordering records. The report sorts stories by milestone, activity column, and position within that cell. Activity folders and milestone records define the visible map order.

| Folder | ID prefix | Required front matter beyond `id` and `title` |
| --- | --- | --- |
| `activities/` | `ACT-` | `order`, unique among activities |
| `requirements/` | `REQ-` | — |
| `features/` | `FEAT-` | — |
| `milestones/` | `MS-` | `order`, unique among milestones |
| `stories/` | `ST-` | `feature`, `milestone`, `activity`, `order`, `scope`, `status`, `requirements` |
| `tasks/` | `TASK-` | `story`, `status` |
| `checks/` | `CHECK-` | `milestone`, `status` |

Use `required` or `optional` for story scope. Set `requirements` to a list of applicable requirement IDs, or use `[]` when none apply. Stories and tasks can use a `depends_on` list of story or task IDs and a separate `blocker` text field for manual blockers. Remove that field when the blocker clears. A planned story is ready when it has no manual blocker and every listed prerequisite is verified. Implemented stories have a separate verification queue.

Use `planned`, `in_progress`, `implemented`, or `verified` for status. Put context in the Markdown body. Add an `## Acceptance criteria` section with one `- ` bullet per criterion on each requirement, story, task, and release check. The internal report renders paragraphs, headings, flat lists, bold text, and inline code. Report progress counts verified required stories. Tasks and optional stories have their own visibility. A release needs at least one required story and one explicit release check, with all required stories and checks verified, to show as ready.

### Record evidence

After checking the acceptance criteria, add a dated outcome and reference under `## Evidence` before setting `status: verified`. Use one Markdown bullet per result.

```md
## Evidence

- 2026-11-19 — A downloaded Garmin activity ZIP contained a FIT file. — [Recorded preflight](../../mattrics-implementation-plan.md#what-we-know)
```

Use an existing repo-relative file in `docs/`, `tests/`, or `tools/`, or an HTTPS GitHub pull request or Actions run link. Relative paths start from the ticket's folder. The internal report prints local references as repository paths so its static copy stays portable. Record the checked outcome and any limitation. Keep credentials, account details, and raw health data out of the records and reports. Validate, rebuild, and confirm that the progress changed where expected.
