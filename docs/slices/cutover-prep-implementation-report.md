# Release and cutover safeguards

25 November 2026 · release candidate

## Delivered locally

- SFTP staging reads the selected commit and compares file hashes and inventory before connecting. Code deployment reruns the application gate from that revision. The current upstream release staged successfully as a rollback bundle.
- The dashboard, writes, and hosted migration runner share a verified Postgres connection builder. A disposable TLS server confirmed that the actual application builder connects with the right hostname and rejects the wrong hostname.
- Catalog reads share a lock. Writes and promotions take the exclusive lock. Provider calls release it and verify the target again before publishing the result. A disposable promotion rejected a stale checksum and an invalid candidate, captured the original pair, published the reviewed files, and restored the original bytes.
- The disposable database suite covered migrations 001–007, historical import, linked corrections, provider retry and Make receiver checkpoints. The synthetic staging backup and separate restore matched row contents.

## Production work awaiting approval

Verify the candidate over Mattwarden with hosted PostgreSQL, FIT decoding and real Garmin and Hevy request budgets. The current Make-to-Sheets route remains the live path. Synthetic receiver tests do not demonstrate the Strava-to-dashboard bridge. Prove the approved bridge, promote the reconciled catalogs, repeat production-range parity, resolve or accept the remaining gap and rehearse routing rollback before approving the read switch.

## Security and Git

The local rehearsals used synthetic data and a temporary certificate. No live configuration, provider credential, or raw archive was read by the tests. No push, deploy or hosted database write took place.

[Next slice](next-cutover-prep-hosted-prompt.md)
