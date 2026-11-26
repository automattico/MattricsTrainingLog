---
id: "ST-HOST-PATH"
title: "Select a private Hetzner probe path"
feature: "FEAT-PREFLIGHT"
milestone: "MS-M0"
activity: "ACT-OPERATE"
order: 10
status: "verified"
scope: "required"
requirements: ["REQ-PREFLIGHT"]
---

The current Level 1 plan supports a temporary authenticated Mattwarden web probe via pinned SFTP. Interactive SSH and bundled cron are unavailable.

## Preflight results

- On 2026-11-23, konsoleH showed a public key registered under the main FTP account. Port 222 responded with the existing trusted host key, and a pinned, key-only shell test was denied before any command ran.
- The user confirmed the account is legacy Webhosting Level 1. Hetzner lists interactive SSH and included cron jobs as unavailable at this level. [Legacy plan comparison](https://docs.hetzner.com/de/managed/webhosting/old-webhosting-packages/)
- The user provisioned the hosted PostgreSQL database in konsoleH. The Mac passed a read-only TLS connection with hostname and certificate verification. The private runner for hosted PHP and scheduling checks remains open. [Database environment validation](../../slices/database-environments-report.md#validation)
- The local rehearsal identified an on-demand alternative. Selecting it would require a decision on the M0 scheduling requirement and a private route for checking the hosted PHP runtime. [Runtime rehearsal](../../slices/v1-runtime-recovery-report.md#runtime)

## Acceptance criteria

- Identify a permitted private mechanism for probing CLI and web PHP without creating a public diagnostic endpoint.
- Record the supported route or the remaining access blocker, then choose where to run hosting checks.

## Evidence

- 2026-11-25 — With explicit owner approval, pinned SFTP uploaded one read-only endpoint and two private inputs. The signed-in Firefox session invoked it through Mattwarden. Pinned SFTP confirmed all three files were removed, and the authenticated route returned `Not found.` on a fresh request. — [Hosted access and removal](../../slices/v1-hosted-verification-report.md#host-access)
