# Hetzner Private Deployment

This app is prepared for a private Hetzner Webhosting deployment with:

- `public/` as the only web-served directory
- `private/config.php` outside the docroot
- `public/api/data.php` as the browser-facing data endpoint
- `public/api/ai.php` as the browser-facing AI endpoint
- a shared secret between Hetzner and Google Apps Script
- password protection at the webspace level

## Recommended layout on Hetzner

Use a subdomain-specific folder such as:

- docroot: `~/public_html/mattrics/public`
- private config: `~/mattrics-private/config.php`

If your account layout does not let you create folders above `public_html`, the fallback is:

- docroot: `~/public_html/mattrics/public`
- private config: `~/public_html/mattrics/private/config.php`

The fallback still works, but it is weaker. If you must use it, add a deny-all `.htaccess` inside that private folder and never point any vhost at it.

The important rule is simple: `public/` is the docroot, and the real config file should live outside any web-served path whenever possible.

## Files to upload

Upload:

- everything inside [`public`](/Users/mwieland/dev/MattricsTrainingLog/public)
- one real secret config file copied from [`private/config.example.php`](/Users/mwieland/dev/MattricsTrainingLog/private/config.example.php)

Accepted config locations are:

- `~/mattrics-private/config.php`
- `~/public_html/mattrics/private/config.php`
- a path provided through the `MATTRICS_CONFIG` environment variable

Do not upload as public web files:

- [`public/config.js`](/Users/mwieland/dev/MattricsTrainingLog/public/config.example.js)
- the repo root as a docroot
- anything in `.git`
- local notes, exports, or backups

## `private/config.php`

Create it from [`private/config.example.php`](/Users/mwieland/dev/MattricsTrainingLog/private/config.example.php).

Required values:

- `sheet_url`
- `sheet_token`

Optional:

- `anthropic_api_key`
- `anthropic_model`

If `anthropic_api_key` is empty, the dashboard still works and the AI endpoint returns a disabled message.

## Password protection

Prefer Hetzner directory protection in the hosting panel for the subdomain docroot. That keeps the password file out of Git and out of the public tree.

If you manage auth manually, keep `.htpasswd` outside `public/` and add the auth directives to [`public/.htaccess`](/Users/mwieland/dev/MattricsTrainingLog/public/.htaccess).

## Hardening already included

[`public/.htaccess`](/Users/mwieland/dev/MattricsTrainingLog/public/.htaccess) already:

- redirects to HTTPS
- disables directory listing
- marks the site `noindex`
- adds basic browser hardening headers
- denies direct access to dotfiles

[`public/robots.txt`](/Users/mwieland/dev/MattricsTrainingLog/public/robots.txt) disallows crawlers.

[`public/api/.htaccess`](/Users/mwieland/dev/MattricsTrainingLog/public/api/.htaccess) blocks direct access to `bootstrap.php`.

## Google Apps Script

Set `MATTRICS_SHARED_SECRET` as a Script Property in Apps Script. The PHP proxy appends it server-side when requesting the sheet, so the browser never sees it.

The Apps Script deployment can still be set to `Anyone`, but without the correct `key` parameter it returns `Unauthorized`.
