# Pre-deploy security fixes

26 November 2026 · branch `codex/mattrics-cutover-prep`

## Changes

- The dashboard reads data with GET. The Refresh button uses a same-origin POST with Mattwarden's CSRF token. The old force-refresh query is rejected, and the POST refuses redirects.
- Garmin provider work shares a 20-second deadline across token, listing, download, and FIT decoding. HTTP timeouts shrink with the available time. A stalled decoder is stopped and reaped. A timeout leaves the last successful checkpoint intact.

## Verification

- The production gate exercised cached reads, the old GET refresh, rejected POSTs, and a protected POST against a synthetic Sheet response. Rejected requests left the private snapshot unchanged.
- The disposable Postgres suite verified normal FIT decoding, termination of a stalled decoder, the shrinking request budget, and unchanged committed Garmin sync state after timeout.
- The remaining hosted check is full Garmin refresh using the final code. The earlier bounded hosted probe only demonstrated one successful download and decode.

## Git and deployment

The release branch holds the local changes. No push, application deployment, database write to Hetzner, or live setting change occurred. The tests handled only synthetic inputs and used no secrets.

[Next slice](next-predeploy-security-fixes-prompt.md)
