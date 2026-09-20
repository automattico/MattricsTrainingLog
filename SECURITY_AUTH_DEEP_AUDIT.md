# Executive assessment

**Assessment: B — fundamentally sound WebAuthn core, but significant fixes are required before reuse.** The present implementation is **not an adequate private-site gate** for other sites. The most serious defect is a production-callable development bypass keyed to the untrusted `Host` header. Whether an Internet request can exploit it on the current Hetzner vhost depends on Apache/CDN routing that was not tested. Independently, `index.html`, all static assets, and test pages are directly reachable without PHP authentication. A missing or mislocated credential file opens first-user registration to anyone who can reach the site.

The positive core is real: the app creates 32-byte random WebAuthn challenges; binds them to a PHP session, purpose, expiry, expected origin and RP ID; verifies credential public-key signatures, RP ID hash, user presence and user verification; and regenerates the PHP session ID after successful assertion. These checks do not compensate for the bypass and deployment boundaries above.

**Scope and limits.** This is a source audit of the present working tree on 2026-09-16, including uncommitted files. I did not send requests to production, execute an auth ceremony, change implementation/dependencies/configuration, or deploy. Current local private files exist, but their contents and all secrets are omitted here. The production document-root, vhost selection, session PHP settings, Cloudflare configuration, and remote file permissions were not independently verified. “Reachable” below assumes `public/` is the Apache document root, PHP is enabled, and committed `.htaccess` is honored.

# Architecture and trust boundaries

`public/` is the intended document root. `public/index.php` protects the app HTML; `public/api/bootstrap.php::mattrics_require_auth()` protects normal data and mutation APIs. Pre-login `public/api/auth/{challenge,register,verify,recovery}.php` use `bootstrap-auth.php`. `private/` holds configuration, public credential keys/counters, hashed recovery codes, challenge/rate-limit JSON and logs. `lib/WebAuthn/` is copied beside the document root. Browser passkeys hold the private signing keys; the server stores only public keys and metadata. `deploy.sh` SFTP-mirrors `public/`, uploads config to private paths, and mirrors `lib/` separately.

The crucial boundaries are (1) Apache route to PHP versus direct static file service, (2) request headers versus canonical site configuration, (3) anonymous versus authenticated/recovery sessions, (4) atomic ownership of credential/challenge/rate state, and (5) `public/` versus private storage. A successful login assertion crosses the session boundary at `public/api/auth/verify.php:80-85`.

# Findings

## F1 — Host-controlled development authentication bypass

- **Severity:** CRITICAL (conditional production exploitability).
- **Affected code/file:** `public/api/bootstrap-auth.php:37-57`; calls at `public/api/bootstrap.php:291-305` and `public/index.php:9`.
- **Attack scenario:** An Internet client reaches the Mattrics PHP vhost with `Host: localhost` or `Host: 127.0.0.1`. `mattrics_current_host()` reads `HTTP_HOST`, not `REMOTE_ADDR`; `mattrics_dev_bypass_auth()` sets `mattrics_authed=true`. The same session then reads protected APIs or the app without a passkey. Case-insensitive `LOCALHOST` and `Host: localhost:443` also work after normalization; the literal `::1` matches only if PHP receives that exact unbracketed value. `localhost.`, numeric variants such as `2130706433`, and bracketed `[::1]` do not match this function. `X-Forwarded-For` and `X-Real-IP` are irrelevant; the code never checks them. `X-Forwarded-Host`/`Forwarded` alone do not trigger it. An absolute-form request could matter if Apache maps its authority into `HTTP_HOST` and still selects this vhost.
- **Actual exploitability:** The bypass is unconditional in PHP and requires no passkey. Remote exploit depends on whether Hetzner or a direct-to-origin path routes a mismatched Host to this application. A correctly isolated name-based vhost or edge Host allowlist could block it; repository code does not. Cloudflare in front is insufficient if the origin is directly reachable and accepts the Host. No live routing test was performed.
- **Recommended remediation:** Remove the automatic bypass from deployable code. If local development needs one, enable it only with a non-production server-side flag **and** a verified loopback `REMOTE_ADDR`, and ensure it cannot be enabled by request headers. Add a canonical Host allowlist at Apache and PHP; test the origin directly.

## F2 — Static document-root files bypass the PHP gate

- **Severity:** HIGH for a “completely private” site.
- **Affected code/file:** `public/.htaccess:1-24`, `public/index.html`, `public/tests/*`, `public/assets/**`, `public/views/*`.
- **Attack scenario:** Direct `GET /index.html`, `/tests/index.html`, any CSS/JS/icon, or a PHP view fragment does not run `index.php` or `mattrics_require_auth()`. `.htaccess` redirects HTTP and denies dotfiles/directory listings, but has no auth rule or rewrite-to-gate for these files.
- **Actual exploitability:** Confirmed from routing rules for any served copy. Static files reviewed are primarily UI/code rather than training data; `config.js` is a special local sensitive candidate. The result violates full privacy even if `/` and APIs require auth. A separately configured Hetzner directory password could provide another gate, but none is configured in this repository.
- **Recommended remediation:** Put site-wide authorization at the web-server/proxy layer before static file serving, or serve private files through an authenticated handler outside the public tree. Allow only explicit login/bootstrap assets anonymously. Remove the legacy `index.html` and public test files from production payload.

## F3 — Open bootstrap on absent or invalid credential state

- **Severity:** HIGH.
- **Affected code/file:** `public/api/bootstrap-auth.php:307-376`, `public/api/auth/challenge.php:17-25`, `public/api/auth/register.php:19-25,72-96`, `public/login.php:22-27`.
- **Attack scenario:** If `passkey-credential.json` is absent, malformed JSON, or resolves in a different private directory, `mattrics_load_credentials()` returns `null`. Any visitor can request a registration challenge and enroll their own key; first registration also receives recovery codes. A fresh publicly reachable install is therefore first-come, first-served. Concurrent first registrations can overwrite each other because the store has no compare-and-create lock.
- **Actual exploitability:** Not available to an anonymous attacker while a valid store is reliably found. It becomes immediately exploitable during initial public deployment, state loss/corruption, or private-root mismatch. Local state currently exists; production state was not checked.
- **Recommended remediation:** Fail closed when credential state is missing after an explicit installation marker; use an out-of-band, one-time bootstrap capability and operator-only setup window. Make initial create atomic, verify private-root paths before serving requests, and back up the credential store.

## F4 — Expired auth flags can authorize passkey enrollment

- **Severity:** HIGH.
- **Affected code/file:** `public/api/auth/challenge.php:20-25`, `public/api/auth/register.php:21-25`, `public/register.php:14-20` versus timeout enforcement in `public/api/bootstrap.php:291-305`.
- **Attack scenario:** The registration endpoints accept any session containing `mattrics_authed=true`; they do not call `mattrics_session_is_timed_out()`. A still-present expired PHP session ID can request and complete new-key enrollment after the configured idle/absolute timeout. The same stale flag also permits a delete challenge, although the final delete route checks timeout.
- **Actual exploitability:** Requires possession/fixation of an old authenticated session cookie; arbitrary unauthenticated requests cannot create one except through F1. PHP garbage collection and browser cookie expiry may remove the session, but neither is a reliable authorization check. The configured defaults and local override are both 30 days (`public/api/config-defaults.php:7-8`), a long exposure window for a private-site gate.
- **Recommended remediation:** Centralize an `is_authenticated_now()` check and use it at **every** privilege transition, including challenge issue and registration finish. Consider recent WebAuthn reauthentication for adding credentials and rotating recovery codes; shorten default sessions.

## F5 — Request-derived origin and untrusted HTTPS forwarding header

- **Severity:** MEDIUM.
- **Affected code/file:** `public/api/bootstrap-auth.php:30-35,183-220`, `public/api/config-defaults.php:10-11`; redirect in `public/.htaccess:5-7`.
- **Attack scenario:** Without a configured `site_origin`, the expected WebAuthn origin is derived from attacker-controllable `HTTP_HOST` plus HTTPS detection. A client can send `X-Forwarded-Proto: https` to make PHP treat plain HTTP as HTTPS and set a Secure cookie. This can also suppress PHP-side HTTPS enforcement when Apache redirect is absent or bypassed. The Apache rule uses Apache's own `%{HTTPS}`, so that header does **not** defeat the committed HTTP redirect if it is active.
- **Actual exploitability:** Cannot forge an existing passkey signature or bypass exact challenge origin by itself. It widens host confusion and weakens transport assumptions; direct-to-Hetzner HTTP behavior and Cloudflare header sanitization were not verified. Local `private/config.php` does not define an HTTPS `site_origin`; the deployed config may differ.
- **Recommended remediation:** Pin the exact production origin and RP ID in server-side config, reject unexpected Host values, and trust `X-Forwarded-Proto` only from known proxy IPs after the proxy overwrites it. Enforce HTTPS at the origin/edge with tested direct-origin behavior.

## F6 — File-backed rate, recovery and credential state lacks transactional updates

- **Severity:** MEDIUM.
- **Affected code/file:** `public/api/bootstrap-auth.php:282-300,383-393,426-462,507-574,705-740`; `public/api/auth/recovery.php:42-55`.
- **Attack scenario:** `LOCK_EX` covers only writing a temporary file, then `rename()` replaces the whole JSON file. Separate requests can both read old state and write conflicting versions. Distributed recovery attempts can lose rate-limit increments; two sessions can redeem one recovery code before either marks it used; separate registrations/deletions can lose each other's changes. `verify.php` and `register.php` ignore the boolean from `mattrics_consume_challenge()`.
- **Actual exploitability:** Concurrent requests from distinct sessions are not serialized by PHP's per-session file lock. This is a demonstrable design race, not proof of a successful production attack. A replayed assertion still needs a legitimate signed response; the race is more material to recovery-code one-time semantics and throttling. It cannot make a random unknown code valid.
- **Recommended remediation:** Use a database transaction or one exclusive lock covering read/check/update/commit for each shared state transition. Require successful atomic challenge consumption before committing session/credential changes. Use durable atomic rate limiting.

## F7 — Vendored WebAuthn accepts invalid client-data and flag combinations

- **Severity:** MEDIUM (defense in depth; no passkey-free login found from these gaps).
- **Affected code/file:** `lib/WebAuthn/src/WebAuthn.php:332-420,437-523,623-642`; `lib/WebAuthn/src/Attestation/Format/None.php:27-29`; `lib/WebAuthn/src/Attestation/AuthenticatorData.php:~244-267`.
- **Attack scenario:** The library's RP suffix regex lacks a dot-boundary; it ignores `crossOrigin`/`topOrigin` and `tokenBinding`; it accepts `fmt:none` with nonempty `attStmt`; it reads BE/BS flags but does not reject BS=1/BE=0. The app's own exact origin comparison (`bootstrap-auth.php:256-264,586-605`) **does block** the malformed-suffix and other unexpected-origin case in normal WebAuthn flows. The other malformed fields are not rejected by the app.
- **Actual exploitability:** A forged `clientDataJSON` invalidates the WebAuthn signature unless the attacker owns the signing key or is enrolling during open bootstrap/recovery. Cross-origin WebAuthn in a browser also depends on browser permissions and page embedding. Thus these are genuine validation defects, but not evidence of remote takeover under the stated passkey-free attacker. The `none` attestation weakness matters primarily to a registration already authorized by another path.
- **Recommended remediation:** Use a maintained library/fork with these checks and keep application-level exact-origin validation. Set a deliberate attestation policy; ordinary personal-site passkeys do not need device-provenance claims. Retest with malformed signed fixtures.

## F8 — Recovery does not rotate session ID; one-time use is race-prone

- **Severity:** MEDIUM.
- **Affected code/file:** `public/api/auth/recovery.php:42-55`, `public/api/bootstrap-auth.php:677-683,705-740`.
- **Attack scenario:** A valid recovery code sets a 10-minute `mattrics_recovery_verified` flag in the existing session without `session_regenerate_id()`. If an attacker had fixed or acquired that session ID, they could use the recovery privilege to enroll a passkey. Concurrent redemption may succeed twice as described in F6.
- **Actual exploitability:** The assumed attacker lacks recovery codes, so this is not a standalone remote bypass. `session.use_strict_mode` and `session.use_only_cookies` are not set here; host PHP defaults determine fixation resistance. Successful recovery does **not** set normal application auth, which is correct.
- **Recommended remediation:** Regenerate ID and clear unrelated flags at recovery transition; enable strict cookie-only session IDs; make code consumption atomic; bind recovery privilege to a single enrollment challenge and revoke it on success/expiry.

## F9 — Production payload and deployment path are not fail-closed

- **Severity:** MEDIUM.
- **Affected code/file:** `deploy.sh:34-63`, `scripts/predeploy-guard.sh:21-78`, `public/config.js`, `public/tests/*`.
- **Attack scenario:** The mirror publishes **everything** under `public/` except `config.js`, `.htpasswd`, `.well-known`; this includes static `index.html`, test pages, and any future backup/log accidentally placed there. There is no allowlist or `public/` sensitive-file inventory check. `SFTP_REMOTE_PRIVATE_DIR` and `SFTP_REMOTE_LIB_DIR` are not verified to be outside the document root. Config is copied to two private paths, while runtime path discovery selects the first match; a layout change could select an empty state root and reopen F3. `--delete` generally removes stale ordinary public files, but excluded `.well-known` and `.htpasswd` remain, and an interrupted deploy can leave mixed state.
- **Actual exploitability:** Depends on remote path configuration and contents. The script explicitly deletes and excludes `public/config.js`, so a **successful standard deploy** should remove that file remotely. Its local presence is still unsafe if a different uploader or docroot serves this checkout. The predeploy grep examines tracked text only and is not a proof against secrets in untracked public files.
- **Recommended remediation:** Validate canonical remote paths and resolved runtime root before deployment, fail if private/lib paths are under docroot, deploy an explicit public allowlist, reject backups/tests/config/logs, and verify remote response codes for representative protected paths after deployment. Keep runtime auth state at one stable location.

## F10 — Diagnostic endpoints have a Host-based or mode-based public gate

- **Severity:** MEDIUM.
- **Affected code/file:** `public/api/runtime-status.php:10-13`, `public/api/import-diagnostics.php:8-11`, `public/api/foundation-diagnostics.php:9-14`.
- **Attack scenario:** These endpoints omit `mattrics_require_auth()`. They return diagnostic JSON whenever runtime mode equals `foundation`, or `mattrics_is_local_host()` sees a spoofable localhost Host. Metadata can include runtime state, user key, connector/import status and counts.
- **Actual exploitability:** On normal non-foundation production Host they return 404. With foundation mode publicly deployed, they are reachable without passkey even on the canonical Host. With a routed localhost Host they are reachable under F1's condition. The exact data present in production was not inspected.
- **Recommended remediation:** Require authentication and an explicit server-side diagnostics enable flag; never use Host to establish local-only access. Remove development diagnostics from the production document root.

## F11 — Session and response policy leaves avoidable exposure

- **Severity:** LOW.
- **Affected code/file:** `public/api/bootstrap-auth.php:9-27,648-674`, `public/api/auth/register.php:98-104`, `public/api/auth/challenge.php`, `public/api/auth/verify.php`.
- **Attack scenario:** Default idle and absolute sessions are 30 days. Auth endpoints that return challenges or first-setup recovery codes do not set `Cache-Control: no-store` (normal API helper does). PHP `session.save_path`, GC policy, strict mode and cross-site cookie defaults beyond those set here are host configuration, not verified. Auth remains active in other concurrent sessions after credential deletion or recovery; there is no server-side all-session revocation.
- **Actual exploitability:** Requires browser/cache/session exposure, server misconfiguration, or an already authenticated session. Cookies are explicitly `Secure` when HTTPS is required, `HttpOnly`, `SameSite=Strict`, path `/`; login calls `session_regenerate_id(true)`. Logout clears data/cookie and destroys the current session, with a CSRF token check.
- **Recommended remediation:** Set strict session mode; use short idle/absolute limits and explicit server storage permissions; send no-store on all auth responses; offer all-session revocation after credential/recovery events; consider a session generation value tied to credential-state changes.

## F12 — Registration and assertion metadata is incompletely validated

- **Severity:** LOW.
- **Affected code/file:** `public/api/auth/register.php:40-90`, `public/api/auth/verify.php:45-72`, `public/api/auth/passkeys.php:83-112`.
- **Attack scenario:** Registration ignores request `id/rawId` and stores the attested credential ID without checking uniqueness or non-emptiness. `excludeCredentials` is browser guidance only. Login chooses a public key by matching `body.id` but does not inspect a supplied `userHandle`; UI does not send one. A malicious authenticated or recovery actor can attempt duplicate IDs and ambiguous credential selection.
- **Actual exploitability:** In the single-user store, an existing credential ID maps to an existing public key and signature verification still blocks the stipulated attacker. The missing checks are data-integrity and future multi-user reuse risks, not a demonstrated passkey-free bypass. Credential IDs and public keys are not secrets.
- **Recommended remediation:** Enforce unique, nonempty credential IDs atomically; compare asserted `userHandle` to the stored owner whenever present; store explicit owner association for each credential; keep the single-user invariant documented and tested.

## F13 — Authenticated AI POST lacks CSRF protection

- **Severity:** LOW.
- **Affected code/file:** `public/api/ai.php:4-18`.
- **Attack scenario:** The endpoint requires a session and POST but does not call `mattrics_require_csrf()`, then sends a paid upstream AI request. A same-site sibling origin with attacker-controlled JavaScript could issue a credentialed simple POST containing JSON as `text/plain`; the server parses JSON regardless of Content-Type. The attacker need not read the response to spend quota. `SameSite=Strict` ordinarily blocks cross-site cookies, but not same-site sibling origins.
- **Actual exploitability:** Conditional on a malicious same-site origin or another way to send a credentialed request from the user's browser. It does not provide application-data read access and is not a passkey-free login.
- **Recommended remediation:** Require the existing CSRF token on AI POST and reject unexpected Content-Type/Origin for state-changing or costly requests.

## F14 — Private-file permissions are permissive locally

- **Severity:** MEDIUM if shared-host filesystem isolation is weak; otherwise LOW.
- **Affected code/file:** `private/` and generated files via `public/api/bootstrap-auth.php:282-300,383-393,407-418`; `deploy.sh:54-57`.
- **Attack scenario:** The local `private/` directory is mode `0755`; local `config.php`, credential state, challenge/rate JSON and audit log are mode `0644`. A different local Unix user with traverse access could read them. SFTP `put` and PHP's umask determine remote modes; the script does not assert restrictive permissions after upload/create.
- **Actual exploitability:** No claim about Hetzner cross-account isolation or remote modes is possible from this checkout. Exposure of configuration or recovery-code hashes requires filesystem access or a docroot error; public credential keys alone are not signing secrets.
- **Recommended remediation:** Use an account-private directory and restrictive umask/modes (`0700` directory, `0600` sensitive files), verify remote ownership/modes, and never place the directory under a served root.

# WebAuthn implementation review

| Check | Actual implementation | Assessment |
|---|---|
| Challenge entropy | `ByteBuffer::randomBuffer(32)` calls PHP `random_bytes()` on supported PHP; 256 bits. | Good. |
| Storage, expiry, purpose | SHA-256 hash in `private/auth-challenges.json`, session hash, origin, RP ID, purpose, default 300-second TTL. | Good design; shared-file atomicity caveat F6. |
| Single use/replay | Consumed flag after verification; consumed challenges rejected. | Normally effective with PHP file-session locking; consume result ignored and JSON update not transactional. |
| Ceremony type | Library requires `webauthn.create`/`webauthn.get`. | Good. |
| Exact expected origin | App compares signed `clientDataJSON.origin` with challenge-record origin using `hash_equals`. | Good if origin is pinned; request-derived fallback is F5. |
| RP ID and RP hash | App validates configured RP ID is origin host or a dot-boundary parent; library checks SHA-256 RP hash. | Good in app; library's own suffix check is defective but app catches it. |
| Credential ownership | Login finds `body.id` in the one-user store and verifies with its public key. | Sufficient for current one-user identity; missing userHandle and uniqueness checks F12. |
| Signature, UP, UV | `processGet` verifies signature over authenticator data plus SHA-256 client data, and requires both flags; registration requires both flags. | Good. |
| Signature counter | Stored per credential; nonzero counter must increase. Zero/zero accepted for passkeys that do not count. | Standards-consistent signal, not reliable clone prevention for synced keys; lost-update race F6. |
| Backup flags | Library parses BE/BS; app does not store or compare them, BS without BE accepted. | Standards gap; no standalone authentication bypass shown. |
| Cross-origin | `crossOrigin` and `topOrigin` are not checked. | Reject cross-origin ceremonies unless explicitly designed and topOrigin allowlisted. |
| Attestation | `none`, `packed`, `apple` enabled. `none` returns true without validating empty statement; `failIfRootMismatch=false`, and no CA roots added. | Attestation is not a device trust guarantee here. Ordinary personal-site auth does not require it, but parser must reject malformed input. |
| Token binding | Ignored. | Legacy token binding is reserved in WebAuthn L3; accepting a fabricated `present` value is a conformance gap with little modern practical impact. |

The [W3C WebAuthn Level 3 specification](https://www.w3.org/TR/2026/REC-webauthn-3-20260825/) requires origin validation and explains `crossOrigin`/`topOrigin` and the reserved token-binding field. Its discussion of backup flags and counters is more nuanced than treating a counter as an absolute anti-cloning guarantee. The same origin and RP ID checks must remain application policy even if the library changes.

# Dependency/library assessment

The repository **does use `lbuchs/WebAuthn`**: the `lbuchs\WebAuthn\WebAuthn` class is loaded directly from `lib/WebAuthn/src/WebAuthn.php`. Fourteen source files are committed under `lib/WebAuthn/`; there is no Composer manifest/lock for the application and no vendored package manifest/tag/SHA in that directory. `git log` shows the library files were added in repository commit `2384ad56848b861df35e91d842339bd578e99d42` on 2026-04-10 and have no current working-tree edits. That is the **application import commit, not an upstream library commit**. The exact upstream version/commit and whether the initial import contained local modifications are **not determinable from this repository**. Code and API resemble upstream 2.2.0; do not label it exact 2.2.0 without a byte-for-byte upstream comparison. The latest Packagist release shown is [v2.2.0 (2024-07-04)](https://packagist.org/packages/lbuchs/WebAuthn), with no listed advisory; absence of an advisory does not mean the code is hardened.

As of this audit, upstream [PR #123](https://github.com/lbuchs/WebAuthn/pull/123) (RP suffix boundary), [#125](https://github.com/lbuchs/WebAuthn/pull/125) (cross-origin), [#127](https://github.com/lbuchs/WebAuthn/pull/127) (empty `none` attestation statement), [#129](https://github.com/lbuchs/WebAuthn/pull/129) (BS/BE flags), and [#131](https://github.com/lbuchs/WebAuthn/pull/131) (token binding) are open. The installed code lacks all five proposed checks. The [Report URI security fork](https://github.com/report-uri/passkeys-php) applies these fixes and removes complex certificate attestation formats it does not need; that is a concrete reduction in parser and trust-anchor surface. Its fork is still newer/smaller and does not solve this app's Host bypass, bootstrap, file-state races or static gating.

The [Webauthn Framework](https://github.com/web-auth/webauthn-framework) has an explicit supported-version/security-release policy and richer ceremony validation, but adds integration complexity and has had its own [exact-origin advisory, fixed in 5.2.4](https://github.com/web-auth/webauthn-framework/security/advisories/GHSA-f7pm-6hr8-7ggm). A current supported version could materially improve maintained validation and testing if the application can support its PHP/dependency requirements. [ShipMonk Passkeys](https://github.com/shipmonk-rnd/passkeys) is another focused option with exact origins, default cross-origin rejection, single-use ceremony storage interface and integration test fixtures, but requires PHP 8.4+, which must be checked against Hetzner. Migration should be chosen by a version-pinned integration test suite, not project size. OpenSSL, PHP session handling, Apache, and the shared-host runtime are also security-sensitive dependencies; their actual deployed versions/settings were not available here. No upgrade was performed.

# Access-control surface

**Classification assumes no separately configured hosting-panel Basic Auth.** “Public intentionally” means safe to fetch as a login/presentation resource **only if it contains no private data**, not that every file is needed on the final site. The complete file inventory is appended below; directory routes follow their `DirectoryIndex` and `Options -Indexes` rules.

- **AUTHENTICATED:** `/` through `index.php` and `/index.php`; `/api/{ai,data,connectors,exercises,settings}.php`; `/api/auth/passkeys.php`; the rewritten `/api/exercises` and `/api/exercises/*` route. Mutation APIs generally check `X-CSRF-Token`; the login assertion itself is authorized by challenge and signature.
- **PUBLIC INTENTIONALLY:** `/login.php`, `/register.php` (redirects after setup), `/recovery.php`, `/api/auth/{challenge,verify,register,recovery}.php` (conditionally allow registration/recovery), `/api/auth/logout.php` (requires session CSRF token but not auth), `brand.css`, icons, manifest and robots.
- **SENSITIVE BUT ACCESSIBLE:** `/index.html` (legacy static app shell), app-only `/assets/js/**` and `/assets/css/**`, `/tests/*`, `/views/*.php` and `/auth-page.php` fragments, `/api/{config-defaults,exercise-config-ai,exercise-config-repository,foundation-diagnostics,foundation-read,foundation-write}.php` helpers that execute with little/no output, and `/api/{runtime-status,import-diagnostics}.php` in foundation mode or spoofable local Host. `/config.js` exists locally; a successful standard deploy deletes/excludes it, but any directly served local/alternate copy is public and must be treated as potentially sensitive. PHP helpers are still direct entry points despite no current secret output.
- **NOT REACHABLE under assumed Apache config:** `/api/bootstrap.php` and `/api/bootstrap-auth.php` are denied by `public/api/.htaccess`; `.htaccess` and `.DS_Store` basenames are denied by `public/.htaccess`. `private/**`, `lib/**`, `.env.local`, repository docs/scripts/tests, and Docker files are outside the intended docroot. If the docroot is the repository root or remote private/lib paths are wrong, this classification collapses.

`Options -Indexes` blocks listings, not named files. `Options -MultiViews` is not set; Apache content negotiation or `PATH_INFO` could produce additional aliases, but the same underlying PHP entry points and access checks should apply if PHP and `.htaccess` are configured correctly. Encoded paths, alternate names, case behavior, rewrite inheritance, `.htaccess` override permissions, and PHP source serving require live Apache configuration tests. If PHP handling fails, all `.php` source, including auth/config-loading code, can be downloaded; the repository cannot prove host PHP configuration. `RewriteRule` in `api/.htaccess` routes `/api/exercises/...` to `exercises.php/$1`; `exercises.php` checks auth at top.

## Complete `public/` file inventory

These are all 110 files observed in the current `public/` tree. Classification is by direct request to that exact filename under the assumed Apache setup; login/API behavior remains conditional as explained above. `config.js` is present locally but excluded from standard deploy. App-only JS/CSS is classed as accessible surface even where it does not contain private data.

| URL path | Classification |
|---|---|
| `/.DS_Store` | NOT REACHABLE |
| `/.htaccess` | NOT REACHABLE |
| `/api/.htaccess` | NOT REACHABLE |
| `/api/ai.php` | AUTHENTICATED |
| `/api/auth/challenge.php` | PUBLIC INTENTIONALLY |
| `/api/auth/logout.php` | PUBLIC INTENTIONALLY |
| `/api/auth/passkeys.php` | AUTHENTICATED |
| `/api/auth/recovery.php` | PUBLIC INTENTIONALLY |
| `/api/auth/register.php` | PUBLIC INTENTIONALLY |
| `/api/auth/verify.php` | PUBLIC INTENTIONALLY |
| `/api/bootstrap-auth.php` | NOT REACHABLE |
| `/api/bootstrap.php` | NOT REACHABLE |
| `/api/config-defaults.php` | SENSITIVE BUT ACCESSIBLE |
| `/api/connectors.php` | AUTHENTICATED |
| `/api/data.php` | AUTHENTICATED |
| `/api/exercise-config-ai.php` | SENSITIVE BUT ACCESSIBLE |
| `/api/exercise-config-repository.php` | SENSITIVE BUT ACCESSIBLE |
| `/api/exercises.php` | AUTHENTICATED |
| `/api/foundation-diagnostics.php` | SENSITIVE BUT ACCESSIBLE |
| `/api/foundation-read.php` | SENSITIVE BUT ACCESSIBLE |
| `/api/foundation-write.php` | SENSITIVE BUT ACCESSIBLE |
| `/api/import-diagnostics.php` | SENSITIVE BUT ACCESSIBLE |
| `/api/runtime-status.php` | SENSITIVE BUT ACCESSIBLE |
| `/api/settings.php` | AUTHENTICATED |
| `/assets/.DS_Store` | NOT REACHABLE |
| `/assets/css/ai.css` | SENSITIVE BUT ACCESSIBLE |
| `/assets/css/base.css` | SENSITIVE BUT ACCESSIBLE |
| `/assets/css/brand.css` | PUBLIC INTENTIONALLY |
| `/assets/css/buttons.css` | SENSITIVE BUT ACCESSIBLE |
| `/assets/css/connectors.css` | SENSITIVE BUT ACCESSIBLE |
| `/assets/css/dashboard.css` | SENSITIVE BUT ACCESSIBLE |
| `/assets/css/docs.css` | SENSITIVE BUT ACCESSIBLE |
| `/assets/css/exercise-admin.css` | SENSITIVE BUT ACCESSIBLE |
| `/assets/css/fatigue.css` | SENSITIVE BUT ACCESSIBLE |
| `/assets/css/layout.css` | SENSITIVE BUT ACCESSIBLE |
| `/assets/css/loading.css` | SENSITIVE BUT ACCESSIBLE |
| `/assets/css/main.css` | SENSITIVE BUT ACCESSIBLE |
| `/assets/css/modal.css` | SENSITIVE BUT ACCESSIBLE |
| `/assets/css/responsive.css` | SENSITIVE BUT ACCESSIBLE |
| `/assets/css/sessions.css` | SENSITIVE BUT ACCESSIBLE |
| `/assets/css/settings.css` | SENSITIVE BUT ACCESSIBLE |
| `/assets/css/tokens.css` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/.DS_Store` | NOT REACHABLE |
| `/assets/js/ai.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/app.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/body-map-team-buildr.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/connectors.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/core/activity-analysis.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/core/constants.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/core/date-utils.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/core/exercise-config.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/core/fatigue-engine.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/core/fatigue-tiers.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/core/filters.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/core/formatters.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/core/hevy-parser.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/core/metrics.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/core/state.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/detail.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/exercise-admin.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/feed.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/passkeys.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/renderers/dashboard.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/renderers/docs/section-ai-model.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/renderers/docs/section-auth-passkeys.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/renderers/docs/section-body-map.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/renderers/docs/section-css-guide.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/renderers/docs/section-data-import.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/renderers/docs/section-deployment.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/renderers/docs/section-docker-local-dev.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/renderers/docs/section-domain-model.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/renderers/docs/section-fatigue-model.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/renderers/docs/section-feature-map.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/renderers/docs/section-module-map.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/renderers/docs/section-overview.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/renderers/docs/section-tech-stack.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/renderers/docs-helpers.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/renderers/docs.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/renderers/fatigue-view.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/renderers/loader.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/renderers/orchestrator.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/settings.js` | SENSITIVE BUT ACCESSIBLE |
| `/assets/js/timeline.js` | SENSITIVE BUT ACCESSIBLE |
| `/auth-page.php` | SENSITIVE BUT ACCESSIBLE |
| `/config.example.js` | SENSITIVE BUT ACCESSIBLE |
| `/config.js` | SENSITIVE BUT ACCESSIBLE |
| `/icons/apple-touch-icon.png` | PUBLIC INTENTIONALLY |
| `/icons/favicon-16x16.png` | PUBLIC INTENTIONALLY |
| `/icons/favicon-32x32.png` | PUBLIC INTENTIONALLY |
| `/icons/favicon-48x48.png` | PUBLIC INTENTIONALLY |
| `/icons/favicon.svg` | PUBLIC INTENTIONALLY |
| `/icons/icon-192.png` | PUBLIC INTENTIONALLY |
| `/icons/icon-512.png` | PUBLIC INTENTIONALLY |
| `/index.html` | SENSITIVE BUT ACCESSIBLE |
| `/index.php` | AUTHENTICATED |
| `/login.php` | PUBLIC INTENTIONALLY |
| `/recovery.php` | PUBLIC INTENTIONALLY |
| `/register.php` | PUBLIC INTENTIONALLY |
| `/robots.txt` | PUBLIC INTENTIONALLY |
| `/site.webmanifest` | PUBLIC INTENTIONALLY |
| `/tests/exercise-config-tests.js` | SENSITIVE BUT ACCESSIBLE |
| `/tests/index.html` | SENSITIVE BUT ACCESSIBLE |
| `/tests/settings-tests.js` | SENSITIVE BUT ACCESSIBLE |
| `/views/detail-modal.php` | SENSITIVE BUT ACCESSIBLE |
| `/views/head.php` | SENSITIVE BUT ACCESSIBLE |
| `/views/header.php` | SENSITIVE BUT ACCESSIBLE |
| `/views/load-screen.php` | SENSITIVE BUT ACCESSIBLE |
| `/views/main-views.php` | SENSITIVE BUT ACCESSIBLE |
| `/views/nav.php` | SENSITIVE BUT ACCESSIBLE |
| `/views/scripts.php` | SENSITIVE BUT ACCESSIBLE |

# Session and recovery review

Session name is `mattrics_sess`; cookie path `/`, `HttpOnly`, `SameSite=Strict`, and `Secure` whenever HTTPS is seen or required. Login calls `session_regenerate_id(true)`. Protected normal API and `index.php` enforce idle/absolute timeout and touch last-seen. Logout requires POST plus CSRF token, clears the session and expires the cookie. The application does not configure PHP strict session IDs, storage path/permissions, or reliable cleanup, and it has no concurrent-session revocation. Registration checks only the raw auth flag (F4). Recovery does not rotate ID (F8).

Recovery generates eight codes from two independent random 5-character base64url prefixes, uppercased and hyphenated. Case folding reduces nominal 60 bits to about **53 bits Shannon entropy per code** (at least 50 bits min-entropy); online guessing remains impractical if throttling is real. Codes are `password_hash()` values, verified with `password_verify()`, and marked used. The nominal limit is 5 IP and 5 session attempts per hour, but the JSON limiter is not atomic (F6); an attacker can also use distributed IPs. A successful code grants only a 10-minute recovery enrollment state; it does not set `mattrics_authed`. Successful passkey registration clears that state. Recovery code rotation requires normal app auth plus CSRF. A lost final passkey cannot be deleted through the normal passkeys endpoint.

# Deployment/origin review

`deploy.sh` runs gates, mirrors `public/` with deletion, removes `config.js`, uploads `private/config.php` outside the intended root, mirrors initial private data as only-missing, mirrors `lib/` separately, then runs smoke tests. It does not upload the local credential/challenge/rate/audit files, so a clean new target has **no credential store** and is open to F3 until enrollment. Existing remote auth state is intended to persist. Remote path variables are not checked for separation from docroot; both an explicit private dir and a sibling private dir get config copies. There is no remote check for `/index.html`, `/tests/`, `/private/`, `/lib/`, Host-spoof behavior, direct-origin HTTP, or protected static files. `.htaccess` is part of the public mirror but its application and inheritance are host settings. The checked-in `public/.htaccess` does **not** enable Basic Auth; its comment merely recommends Hetzner panel protection.

`X-Forwarded-Proto` is accepted from any source by PHP. Behind Cloudflare, only a trusted edge should be able to set that header, and the Hetzner origin must reject direct client paths or perform its own HTTPS/Host checks. A Cloudflare-only restriction is bypassable if the origin is publicly routable. Direct-Hetzner requests need explicit canonical-host handling. The response redirect itself interpolates `HTTP_HOST`; a hostile Host can influence redirect destinations when Apache routes it here. HSTS is sent on responses but cannot substitute for valid origin routing.

# Suitability for reuse

- **`brand.mwieland.com` (static HTML/CSS/assets):** This PHP-session scheme cannot protect files served directly by Apache. Every HTML, stylesheet, image, font, JS file, and alternate URL must pass one server/edge authorization check. A static build may remain static on disk, but access must be enforced **before** Apache serves it. Anonymous login resources can be a small separate allowlist.
- **`matlas.mwieland.com` (Vite/React SPA plus `projects.yaml`):** Protect `index.html`, hashed JS/CSS chunks, source maps, `projects.yaml`, media, public-directory copies, fallback SPA rewrites, and any API. Protecting only `/` or hiding navigation leaves `projects.yaml` and bundles fetchable by URL. Build outputs and backup files need an explicit inventory and denied default. A central auth service with per-site session validation or an edge/auth gateway is preferable to copying Mattrics' single-user JSON state and passkey bootstrap to each site.

WebAuthn RP IDs and origins need deliberate design for separate subdomains. A parent `mwieland.com` RP ID may technically cover subdomains, but each exact origin must be allowlisted and the credential/session sharing model must be designed; accidental acceptance of a sibling origin is unsafe. A dedicated auth origin with a vetted handoff to each site is more maintainable than broad cookie domains or header-derived origins.

# Recommended target architecture

1. One canonical authentication service with a pinned HTTPS origin/RP ID, maintained WebAuthn verifier, atomic credential/challenge/recovery store, explicit bootstrap procedure, short server-side sessions and recovery-only capability.
2. A web-server or trusted edge gate for **all** private paths, including static files. Default deny; allow only minimal login assets and the auth endpoints. At Hetzner shared hosting, verify whether panel-level directory protection or Apache auth/rewrite hooks can cover static content and PHP uniformly. If they cannot, use a reverse proxy/hosting arrangement that can.
3. Lock origin reachability: canonical Host rejection, direct-origin HTTPS, trusted proxy header policy, Cloudflare-to-origin controls if Cloudflare is used, and regression probes that hit both CDN and origin with wrong Host/headers.
4. Keep secrets and state outside every served root, with one explicit path and restrictive filesystem ownership/mode; pin package versions and record provenance.

# Prioritized remediation plan

1. **Before any reuse:** remove F1 development bypass; verify wrong-Host requests against the actual origin. Prevent direct static access (F2) or avoid claiming the sites are private.
2. Close public bootstrap after installation and make missing/corrupt state fail closed (F3). Verify one stable remote state path and back it up.
3. Apply timeout and recent-auth checks at all enrollment/recovery/rotation transitions (F4); shorten sessions, enable strict session IDs, rotate ID on recovery.
4. Pin origin/RP ID and trust proxy headers only from known proxies (F5). Validate Cloudflare and direct-Hetzner paths.
5. Replace whole-file state races with transactions/locks (F6), enforce credential ID uniqueness/userHandle, and add adversarial integration tests for replay, concurrency, malformed WebAuthn data and wrong Host.
6. Choose a maintained verifier or vetted fork after PHP compatibility review; retain exact application origin checks and tests. Verify static-route inventory and deployment smoke probes before expanding to brand/matlas.

**Final assessment: B — Fundamentally sound but requires significant fixes first.** This is not currently safe to reuse as a complete gate for private websites. No finding here proves passkey-free authentication on a correctly routed, initialized canonical production vhost **except** the Host-based development bypass if that vhost accepts a localhost Host. The static-file bypass is unconditional for directly served static paths under the assumed document root.
