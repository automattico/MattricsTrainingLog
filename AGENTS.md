# AGENTS.md

## Communication Style

- In final status updates, use simple visual status markers so progress is easy to scan.
- Prefer these markers consistently:
  - ✅ done / verified
  - ❌ not done / failed / missing
  - ❗ important warning / user should pay attention
  - 🚀 deployed
  - 🔒 security-sensitive
- After significant work, include a short status summary that explicitly covers:
  - Git working tree state
  - commit/push state
  - deploy state
- Do not include a status summary after every message or routine answer. Reserve it for substantial implementation, debugging, verification, deploy, or git workflow progress.
- Keep status summaries concise and easy to scan.
- When something is still pending, say so explicitly rather than implying it is complete.
