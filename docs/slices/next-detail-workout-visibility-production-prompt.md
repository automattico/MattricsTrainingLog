# Slice: Deploy and Verify Detail Workout Visibility

Read first:
- `AGENTS.md`
- `docs/architecture-next.md`
- `docs/codex-framework.md`
- `docs/implementation-progress.md`
- `docs/slices/detail-workout-visibility-report.md`

Goal: deliver the detail-modal visibility fix and confirm an authenticated production Hevy workout shows its exercise and set breakdown.

Context: workout blocks already populate the exercise and set counters, but `is-hidden { display: none !important; }` prevented the breakdown from appearing because the modal previously used inline `display: block`. The local fix toggles the class and has focused DOM coverage.

In scope:
- Commit only the detail visibility slice, excluding unrelated working-tree changes.
- Push and deploy only with explicit approval.
- In an authenticated production session, open a Hevy workout and verify exercise names and sets are visible; verify ordinary activity notes still show correctly.

Out of scope:
- Re-importing workouts or changing the Hevy parser, canonical child lookup, authentication, or production data.

Security constraints:
- Do not print or store session credentials, secrets, or raw private workout content.
- Keep Mattwarden as the sole authentication and session boundary.

Validation:
- `node tests/js/detail-tests.js`
- `./scripts/prod-gate.sh`
- Authenticated visual production check after an approved deployment.

Completion requirements:
- Update `docs/implementation-progress.md`.
- Update the slice report with final commit, push, and deploy state.
- Add the next slice prompt using `docs/templates/next-slice-prompt.md` if follow-up work remains.
