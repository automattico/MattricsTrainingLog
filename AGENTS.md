# AGENTS.md

## Purpose

This repository hosts a static website template with optional small APIs.
The deployable web root is `public/`.

Agents must preserve the deployment and security model defined here.

---

## Communication Style

- Be concise, direct, and practical.
- Do not narrate obvious steps.
- Provide status summaries only after substantial work.

Status markers:

- ✅ done / verified
- ❌ failed / missing
- ❗ warning / user attention needed
- 🚀 deployed
- 🔒 security-sensitive

When a status summary is used, include:

- git working tree state
- commit / push state
- deploy state

---

## Repository Architecture

The repository structure must remain:

public/   deployable web root  
private/  runtime or server configuration (never deployed)  
scripts/  deploy pipeline and validation tools  
docs/     architecture and operational documentation

Rules:

- Only `public/` is deployed.
- `private/` must never be deployed.
- Server-side code is allowed **only in `public/api/`**.
- Secrets must never appear in `public/`.

---

## Security Rules

Never commit:

- secrets
- API keys
- certificates
- private keys
- logs
- database files

Secrets belong only in:

.env.local

Agents must treat `.env.local` and deploy credentials as 🔒 security-sensitive.

---

## Deployment Model

Deployment uses:

./deploy.sh

Required flow:

prod-gate  
↓  
predeploy guard  
↓  
deploy public/  
↓  
smoke tests

Agents must:

- run validation before deploy
- deploy only `public/`
- avoid exposing secrets in logs

---

## Working Tree Safety

Assume the working tree may be dirty.

Agents must:

- never overwrite unrelated changes
- avoid destructive operations
- request confirmation before history rewrites

---

## Reviews

When reviewing code, prioritize:

1. bugs
2. regressions
3. deploy risk
4. missing validation
5. missing tests
