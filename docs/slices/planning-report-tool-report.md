# Slice report · Planning backlog and reports

Date 2026-11-19 · Branch `main`

## Delivered

- Seeded M0, v0.1, v1.0, and v1.1 with 9 requirements, 27 stories, 22 implementation tasks, and 7 release checks. Recorded the Garmin Personal API sample and Hevy app connector sample as verified within their original scope. The Hevy backend REST test remains planned.
- Added the independent planning generator, validator, fixture checks, and local development, staging, and release commands. The internal staging report shows 2 of 26 required stories verified. The client staging report includes selected outcomes across all four releases and shows 1 of 20 verified within its shared scope.
- Styled both reports with Brandhub 2026.09 from the local `brand` checkout at `dceff64`. The generated reports bundle the Mattrics mark, favicon, fonts, and license files.
- The [backlog guide](../planning/README.md) documents evidence, links, release checks, and local use. The [M0 preflight prompt](next-planning-preflight-prompt.md) hands off the next migration slice.

## Verification

- `ruby tools/planning-report/planning.rb validate` and `ruby tools/planning-report/test.rb` passed. The synthetic fixture checked invalid records, story verification transitions, dependency queues, an open release check after story verification, stale output removal, exclusion of private links and copy, and the client-only release archive.
- Development and staging builds passed. Both staging reports opened in local Chrome at 1366, 390, and 320 pixels. Checked story links and details, release counts and open checks, internal next work, keyboard skip navigation, and accessible progress labels. Audited both artifact trees and recalculated feature, milestone, and overall counts independently.
- Verified that both branded previews load the logo and all three typefaces locally at the same viewport widths. The fixture also verifies that the release archive bundles the allowlisted brand files.
- `./scripts/prod-gate.sh` and `git diff --check` passed.

## Security and release

Secrets and sensitive files were untouched. No live training data or provider credentials were used. Generated reports stayed in the ignored tooling output directory. The client artifact contains dedicated shared copy, projected references and statuses, and independently computed progress.

The release archive was verified with a synthetic fixture. The real preview is from the working tree. A release build requires the planning inputs, tooling, and brand assets to match a known Git revision.

Working tree contains local changes with the prior edits preserved. Commits, pushes, and deployments for this slice total zero. The next migration slice is [M0 archive inventory and private runtime preflight](next-planning-preflight-prompt.md).
