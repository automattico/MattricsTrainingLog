# Slice: Verify replacement Anthropic key and finish Mattwarden cut-over

Read first:
- `AGENTS.md`
- `docs/architecture-next.md`
- `docs/codex-framework.md`
- `docs/implementation-progress.md`
- `docs/mattwarden-migration.md`
- `docs/slices/mattwarden-ai-diagnosis-report.md`

Goal: verify a valid replacement Anthropic key, complete one authenticated production AI workout check, then finish authorized legacy cleanup without losing rollback capability prematurely.

Context: the production Mattrics/Mattwarden cut-over and most logged-in checks passed. The gate log showed Anthropic HTTP 401 for AI; the ignored local key also returned 401 from the no-generation models endpoint. The browser's non-JSON error handling was fixed and deployed. The current key must be replaced in `/usr/home/mwiela/sites/mattrics/private/config.php`; never put it in Git, logs, or a task message.

In scope:
- After the operator replaces the private key, validate it using a no-generation Anthropic models request without printing the key or response body.
- Perform one authenticated AI workout check, then confirm dashboard, data, settings, connectors, exercises, and logout still work.
- Once all checks pass, identify and remove only the exact legacy `/mattrics-private` and `/mattrics-lib` directories with a recoverable backup plan. Keep the protected rollback tree until the operator approves its retention/deletion policy.
- Update progress/runbook docs, add a slice report and next prompt, run relevant tests, and report git, push, and deploy state.

Out of scope:
- Reworking the working nested vhost webroot without a separate Hetzner control-panel decision.
- Introducing application-owned authentication or exposing the key in browser code.
- Merging or pushing without explicit approval.

Security constraints:
- Never print, commit, or paste API keys, session cookies, raw logs, or private training payloads.
- Keep all application paths rooted at `MATTWARDEN_SITE_DIR`; keep Mattwarden as the only authentication/session boundary.
- Do not make repeated paid AI requests without a diagnostic reason.

Validation:
- `./scripts/prod-gate.sh` and relevant endpoint/browser checks.
- Mattwarden's unauthenticated smoke suite if its deployment changes.
- Exact remote-tree verification after any application deploy.

Completion requirements:
- Update `docs/implementation-progress.md`.
- Add a slice report using `docs/templates/slice-report.md`.
- Add the next slice prompt using `docs/templates/next-slice-prompt.md`.
