# Slice: Finalize repository cleanup

Read first:
- `AGENTS.md`
- `docs/README.md`
- `docs/implementation-progress.md`
- `docs/slices/repository-cleanup-report.md`

Goal: review and commit the approved repository cleanup while preserving unrelated changes and the Mattwarden deployment/security model.

Context: obsolete assets, dead code/CSS, Docker/Foundation/Postgres runtime scaffolding, completed prompts, and database-only integration checks were removed. Current production compatibility modules remain because API endpoints import them. `CLAUDE.md` remains, and `.claude/launch.json` now starts `scripts/dev-server.sh`. Workout AI is still broken because Anthropic rejects the configured credential; repair is deferred.

In scope:
- Review the exact cleanup diff and distinguish it from pre-existing documentation/product work.
- Confirm removed files have no current references outside dated historical reports.
- Run `git diff --check`, the Markdown/local-asset reference audits, and `./scripts/prod-gate.sh`.
- Commit only the intended cleanup after resolving overlap with the existing documentation slice.

Out of scope:
- Deploying, pushing, merging, replacing the Anthropic credential, changing private runtime data, or removing the production compatibility libraries.

Completion requirements:
- Report validation results and exact remaining working-tree state.
- Report commit, push, and deploy state.
- Update `docs/implementation-progress.md` if branch state changes.
