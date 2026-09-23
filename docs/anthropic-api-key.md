# Workout AI: Anthropic API key replacement

Mattrics sends workout requests server-side through `api/ai.php`. The Anthropic secret is read only from `MATTWARDEN_SITE_DIR . '/private/config.php'` as `anthropic_api_key`; it must never appear in `public/`, a browser request, Git, chat, or deployment logs. `./deploy.sh` deliberately does **not** upload or overwrite `config.php`.

An existing Anthropic secret cannot be revealed again by the Console or API. It can only be recovered from a password manager or another secure copy where it was saved when created. If no valid saved copy exists, create a replacement key.

## Create or replace a key

1. Sign in to the [Claude Console](https://platform.claude.com/) for the organization that pays for Mattrics API use. Open **Settings → API keys → Create key**. Give it a recognizable name such as `mattrics-production` and choose an appropriate expiration. For this unattended site, a service-account key is appropriate if the organization supports service accounts; otherwise use a personal key that you can rotate. Scope it to the intended single workspace so Mattrics does not need an additional workspace-ID header.
   A paid Claude chat subscription does not itself include Console/API access; check the Console organization's [API billing](https://support.claude.com/en/articles/9876003-i-have-a-paid-claude-subscription-pro-max-team-or-enterprise-plans-why-do-i-have-to-pay-separately-to-use-the-claude-api-and-console) separately.
2. Copy the new secret into a secure password manager when the Console presents it. If you previously saved a key in a password manager, you may check that copy locally, but the current Mattrics key is already rejected with 401. If an existing secret is lost, expired, disabled, or rejected, create a replacement rather than trying to recover it from the Admin API: Anthropic's key-management read endpoint never returns secret values. Check the old key's status/expiration in the Console, but do not paste either key into a task.
3. Before installing it, validate the new key with a no-generation `GET /v1/models` request. The following zsh commands prompt without echoing the secret, keep it out of shell history and curl's argument list, and print only the HTTP status:

   ```sh
   read -s "mattrics_anthropic_key?Anthropic key: "; echo
   printf 'header = "x-api-key: %s"\nheader = "anthropic-version: 2023-06-01"\n' "$mattrics_anthropic_key" \
     | curl --silent --show-error --output /dev/null --write-out '%{http_code}\n' \
       --config - https://api.anthropic.com/v1/models
   unset mattrics_anthropic_key
   ```

   Expect `200`. A `401` means the key is not usable; check its active, expiry, and workspace state in the Console rather than sending paid generation requests.
4. Using FileZilla's existing SSH-key connection, edit the protected production file `/usr/home/mwiela/sites/mattrics/private/config.php`. Replace only the quoted value assigned to `anthropic_api_key`; preserve every other entry and do not edit `config.example.php`. Confirm the private directory remains mode 0700 and `config.php` remains mode 0600. Avoid leaving a downloaded copy in Downloads or another unprotected folder.
5. Run one authenticated workout request in Mattrics. If authentication succeeds but the workout fails, diagnose that distinct response before changing anything else.
6. Once production works, disable or delete the rejected old key in the Console and keep the new key's expiration/rotation reminder with the password-manager entry.

The current 2026-09-22 production key and the ignored local key both returned HTTP 401. The cause (expired, disabled, wrong workspace, or otherwise invalid) has not been distinguished. The browser error-handling fix is deployed. On 2026-09-23 the operator explicitly skipped replacement and successful-workout verification for migration completion; use this guide if the feature is re-enabled later.

Sources: [Anthropic authentication and key lifecycle](https://platform.claude.com/docs/en/manage-claude/authentication), [API key metadata does not expose the secret](https://platform.claude.com/docs/en/api/http/beta/organization/api_keys), [Models API](https://platform.claude.com/docs/en/api/models).
