# Codex Framework

Use Codex in small implementation slices. Each slice should leave Mattrics coherent, secure, and closer to the architecture in `docs/architecture-next.md`.

## Start Every Slice

Before changing files, Codex must:

1. Read `AGENTS.md`.
2. Read `docs/architecture-next.md`.
3. Read `docs/codex-framework.md`.
4. Read `docs/implementation-progress.md`.
5. Inspect `git status --short`.
6. Identify unrelated dirty files and avoid touching them.
7. Confirm the slice goal, scope, security constraints, and validation commands.

## Slice Rules

- Implement one coherent vertical slice at a time.
- Preserve existing behavior unless the slice explicitly changes it.
- Avoid unrelated refactors.
- Do not rewrite the UI framework unless a later architecture decision explicitly authorizes it.
- Do not expose secrets or private health data.
- Keep runtime/private data outside `public/`.
- Add or update tests when behavior changes.
- Prefer compatibility layers during migration so the deployed app remains usable.
- Keep `MattricsTrainingLog` as the main repo and use `MattricsNext` only as reference.

## End Every Slice

Before finishing a slice, Codex must:

1. Run relevant validation for the changed files.
2. Run `git status --short`.
3. Update `docs/implementation-progress.md`.
4. Add a slice report using `docs/templates/slice-report.md`.
5. Create the next slice prompt using `docs/templates/next-slice-prompt.md`.
6. Report changed files, validation results, git state, commit state, push state, and deploy state.

## Preferred Implementation Order

1. Framework docs.
2. Architecture decision docs.
3. Docker/Postgres foundation.
4. Canonical schema.
5. Legacy import.
6. Compatibility API.
7. Read-only UI switch-over.
8. Hevy ingestion.
9. Fatigue from canonical data.
10. Exercise config DB migration.
11. Hevy live connector.
12. Garmin Connect live connector.
13. Duplicate resolution and metadata precedence.
14. Private server/Cloudflare Tunnel deployment.
15. Concept2 erg import.

## Slice Quality Bar

A slice is complete only when:

- the app remains in a usable state
- the change is scoped to the slice goal
- relevant validation passes or failures are documented
- no secrets are exposed
- progress docs are updated
- the next slice prompt is ready to paste into a new Codex thread

## Security Checklist

Every slice report must state:

- whether secrets were read or modified
- whether sensitive files were touched
- whether raw/private health data was handled
- whether public responses expose credential values
- whether generated artifacts contain private data

If a slice requires credentials, store them only in server-side private config or encrypted credential storage. UI and logs may show status only.
