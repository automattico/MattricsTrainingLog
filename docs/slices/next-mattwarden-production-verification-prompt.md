# Next slice: finish Mattwarden production verification

Superseded after the 2026-09-22 AI diagnosis. Use `next-mattwarden-ai-credential-verification-prompt.md` instead; the Anthropic key was rejected with HTTP 401 and the authorized temporary gate-log copy was deleted.

Read `AGENTS.md`, `docs/architecture-next.md`, `docs/codex-framework.md`, `docs/implementation-progress.md`, `docs/mattwarden-migration.md`, and the production cut-over report first.

Goal: identify and fix the production `/api/ai` non-JSON response, verify one successful AI workout request, and finish the remaining cut-over cleanup without losing rollback capability prematurely.

Constraints: do not print API keys, session cookies, private training payloads, or raw logs. A full production gate-log download was denied by automated safety review; obtain explicit approval before attempting it. Do not send repeated paid AI requests without a diagnostic reason. Keep the old external directories and the protected rollback copy until all product checks pass.

Then verify the final remote tree and logged-in dashboard, data, settings, connectors, exercises, AI, and logout. Only afterward remove the old `/mattrics-private` and `/mattrics-lib` paths; treat changing the Hetzner webroot to the parent as a separate coordinated operation. Update progress and cut-over docs, add a completion report and next prompt, run relevant tests, and report working-tree, commit, push, and deploy state. Merge/push only after explicit approval.
