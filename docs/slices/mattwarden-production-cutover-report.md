# Slice Report: Mattwarden production cut-over (verification incomplete)

Date: 2026-09-22  
Branch: `mattwarden`

## Summary

Deployed Mattrics application content behind Mattwarden and verified the unauthenticated gate. Production inspection corrected two runbook assumptions: the live private state was in the old app's nested `private/` directory, and Hetzner serves `public_html/mattrics/public` as this vhost's webroot. Both corrections were made before the final gate smoke pass. Authenticated dashboard, data refresh, settings save, connector/exercise views, and logout confirmation passed. AI remains open because the browser received HTML instead of JSON from one request.

## Data handling

- The old external `/mattrics-private` config, activity-type configs, and exercise dataset matched the live copies byte-for-byte. The live exercise configs and unknown-exercise JSON differed and were chosen for the active private tree.
- Non-auth live config/data, `user-settings.json`, `cache/training-data.json`, and `exercise-ai.log` were copied into `sites/mattrics/private` before the app deploy. A separate `sites/mattrics/rollback-private-pre-cutover` holds both live and older external versions. All 21 active/rollback uploads were read back and compared byte-for-byte; directories were 0700 and files 0600.
- No passkey credential, auth challenge, rate-limit, or auth-audit file was copied. Those obsolete files were deleted with the old docroot tree after the protected data copies were verified.
- Protected local staging was removed after verification. `/mattrics-private` and `/mattrics-lib` remain on the server until all logged-in checks pass.

## Deployment and validation

- `./deploy.sh` in this repository: production gate and exact managed-tree verification passed. Three optional local-only Foundation/Postgres blocks skipped because Postgres was stopped.
- Mattwarden `php vendor/bin/phpunit`: `OK (278 tests, 664 assertions)`; its result-cache write emitted a local permission warning.
- Production Mattwarden `sites.php` now includes mattrics. Existing brand/matlas and RP/session settings were semantically identical to the local source; a 0600 remote backup of the previous config was retained.
- The first Mattwarden deployment targeted the planned parent webroot and failed mattrics smoke with 404. Inspection showed the vhost serves nested `public_html/mattrics/public`; `MW_SITES` was corrected to that path. A first nested-target smoke run caught transient 404s immediately after upload; a repeat and then the complete three-site deploy/smoke suite passed.
- Mattrics unauthenticated smoke: `/`, `/index.html`, and `/style.css` returned 401 HTML; `/api/data` and `/api/session` returned 401 JSON; `/robots.txt` and the Mattwarden login assets returned 200. Brand and matlas smoke suites also passed.
- Authenticated browser: passkey login and static shell passed; the all-time dashboard showed preserved activity data; forced sheet refresh returned a live-sheet status; settings save showed success; connectors and exercise views loaded; logout link opened Mattwarden's confirmation page.
- One AI workout request failed with `Unexpected token '<', "<!DOCTYPE "... is not valid JSON`. No second paid request was sent.

## Security check

- Secrets read: production `config.php` was temporarily copied for byte comparison and migration, but its contents were never printed. Sensitive settings/cache/log data were temporarily staged under a 0700 directory and deleted locally after verified upload.
- Sensitive files modified: the live app's new private copy and protected remote rollback copy were created; obsolete docroot auth-state files were deleted. The original external legacy directories were not deleted.
- Public credential exposure: none observed. Mattwarden denies unauthenticated content and API requests.
- Generated artifacts contain private data: this report contains no payloads or credential values. The remote rollback tree does contain private production data by design and is protected with 0700/0600 permissions.

## Open issues

- AI's non-JSON response requires status/log diagnosis. An automated safety review denied downloading the full production gate log without explicit authorization; no log copy was made.
- The nested configured webroot differs from the planned parent-only shim layout. It is secure and smoke-tested, but changing the hosting webroot to the parent is a separate coordinated operation.
- Keep `/mattrics-private`, `/mattrics-lib`, and the remote rollback tree until AI and all final checks pass. Do not merge or push yet.

## Git state

- Working tree: documentation edits for this report were uncommitted when written.
- Commit: migration implementation remains on local branch `mattwarden`.
- Push: none.
- Deploy: Mattrics and Mattwarden production content deployed; AI verification incomplete.

## Next slice

See `docs/slices/next-mattwarden-production-verification-prompt.md`.
