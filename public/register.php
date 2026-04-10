<?php
declare(strict_types=1);

require_once __DIR__ . '/api/bootstrap-auth.php';

mattrics_auth_session_start();

$store = mattrics_load_credentials();

// If a store exists and user is NOT authenticated, they must log in first
if ($store !== null && empty($_SESSION['mattrics_authed'])) {
    header('Location: /login.php');
    exit;
}

$isAdding    = $store !== null;
$passkeyName = htmlspecialchars(trim($_GET['name'] ?? ''), ENT_QUOTES, 'UTF-8');
if ($passkeyName === '') $passkeyName = 'Default';

// Define these BEFORE the heredoc so PHP can interpolate them
$_passkeyNameJson = json_encode($passkeyName);
$_isAddingJson    = $isAdding ? 'true' : 'false';

$heading = $isAdding ? 'Add a passkey' : 'First-time setup';
$desc    = $isAdding
    ? 'Register a new passkey for this device or password manager.'
    : 'Register a passkey for this device. You\'ll use it every time you sign in.';
$note    = $isAdding
    ? 'To remove a passkey, use the Settings page inside the app.'
    : 'Manage your passkeys from the Settings page after signing in. To re-register, delete <code>private/passkey-credential.json</code> on the server.';

$authPageTitle = $isAdding ? 'Add Passkey' : 'Register Passkey';
$authPageBody  = <<<HTML
<h1 class="auth-heading">{$heading}</h1>
<p class="auth-desc">{$desc}</p>
<div class="auth-error" id="authError"></div>
<button class="auth-btn" id="registerBtn" onclick="registerPasskey()">Register passkey</button>
<p class="auth-note">{$note}</p>
<script>
var PASSKEY_NAME = {$_passkeyNameJson};
var IS_ADDING    = {$_isAddingJson};

function bufferToBase64url(buffer) {
  const bytes = new Uint8Array(buffer);
  let str = '';
  for (const b of bytes) str += String.fromCharCode(b);
  return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}
function base64urlToBuffer(b64url) {
  const b64 = b64url.replace(/-/g, '+').replace(/_/g, '/');
  const bin = atob(b64);
  const buf = new Uint8Array(bin.length);
  for (let i = 0; i < bin.length; i++) buf[i] = bin.charCodeAt(i);
  return buf.buffer;
}
function showError(msg) {
  const el = document.getElementById('authError');
  el.textContent = msg;
  el.classList.add('visible');
}
async function registerPasskey() {
  const btn = document.getElementById('registerBtn');
  btn.disabled = true;
  document.getElementById('authError').classList.remove('visible');
  try {
    const challengeRes = await fetch('/api/auth/challenge.php?action=register', { credentials: 'same-origin' });
    if (!challengeRes.ok) {
      const err = await challengeRes.json();
      throw new Error(err.error || 'Failed to get challenge.');
    }
    const json = await challengeRes.json();
    const opts = json.publicKey;

    opts.challenge = base64urlToBuffer(opts.challenge);
    opts.user.id   = base64urlToBuffer(opts.user.id);
    if (opts.excludeCredentials) {
      opts.excludeCredentials = opts.excludeCredentials.map(c => ({
        ...c,
        id: base64urlToBuffer(c.id),
      }));
    }

    const credential = await navigator.credentials.create({ publicKey: opts });

    const body = {
      id:                bufferToBase64url(credential.rawId),
      clientDataJSON:    bufferToBase64url(credential.response.clientDataJSON),
      attestationObject: bufferToBase64url(credential.response.attestationObject),
      name:              PASSKEY_NAME,
    };

    const regRes = await fetch('/api/auth/register.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    const result = await regRes.json();
    if (!regRes.ok) throw new Error(result.error || 'Registration failed.');

    window.location.href = IS_ADDING ? '/?view=settings' : '/login.php';
  } catch (err) {
    showError(err.name === 'NotAllowedError' ? 'Passkey registration was cancelled or timed out.' : err.message);
    btn.disabled = false;
  }
}
</script>
HTML;

require __DIR__ . '/auth-page.php';
