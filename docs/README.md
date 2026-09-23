# Documentation index

Use the current-state documents below for development and operations. Files under `docs/slices/` and feature prompt directories are dated implementation records; they can explain why a decision was made, but they are not live runbooks.

## Current operations

| Document | Purpose |
|---|---|
| [`../README.md`](../README.md) | Repository overview and primary entry points |
| [`../DEPLOY.md`](../DEPLOY.md) | Mattrics validation and SFTP deployment contract |
| [`architecture.md`](architecture.md) | Deployed Mattwarden request, API, browser, and CSP architecture |
| [`local-development.md`](local-development.md) | Native Mattwarden-compatible development commands and test inventory |
| [`hetzner-private-deploy.md`](hetzner-private-deploy.md) | Current Hetzner layout and parent-webroot normalization procedure |
| [`mattwarden-migration.md`](mattwarden-migration.md) | Security outcome, API inventory, fixed cut-over order, and runbook status |
| [`anthropic-api-key.md`](anthropic-api-key.md) | Secret-safe Anthropic key creation, replacement, and verification |
| [`implementation-progress.md`](implementation-progress.md) | Current implementation state and next approved slice |

## Current code references

| Document | Purpose |
|---|---|
| [`module-map.md`](module-map.md) | Server and browser module dependencies |
| [`css-guide.md`](css-guide.md) | CSS ownership, tokens, and naming conventions |
| [`fatigue-model.md`](fatigue-model.md) | Fatigue algorithm, thresholds, and activity mappings |
| [`compatibility-api.md`](compatibility-api.md) | Compatibility API behavior and data shape |
| [`strava-sync-architecture.md`](strava-sync-architecture.md) | Strava/Make/Sheets ingestion and dashboard read path |
| [`vendor-body-map-assets.md`](vendor-body-map-assets.md) | Vendored body-map asset provenance and maintenance |

## Future architecture

| Document | Purpose |
|---|---|
| [`architecture-next.md`](architecture-next.md) | Approved future architecture direction |
| [`codex-framework.md`](codex-framework.md) | Required implementation-slice workflow |

## Historical records

- `docs/slices/` contains dated reports plus the few active handoff prompts named in `implementation-progress.md`. Statements about paths, pending work, or deployment status are accurate only for the date of each record.
- `docs/features/` contains feature specifications and implementation notes. Its current status files supplement—but do not override—the operating documents above.
- `docs/templates/` contains templates for future slice reports and prompts.

When documents conflict, use `AGENTS.md`, `README.md`, `DEPLOY.md`, this index, and the current architecture/runbooks in that order. Secrets never belong in documentation.
