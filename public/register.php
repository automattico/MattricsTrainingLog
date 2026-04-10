<?php
declare(strict_types=1);

require_once __DIR__ . '/api/bootstrap-auth.php';

mattrics_auth_session_start();
if (mattrics_auth_requires_https() && !mattrics_is_https_request()) {
    header('Location: https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/register.php'));
    exit;
}

$store = mattrics_load_credentials();
$isRecovery = isset($_GET['recovery']) && $_GET['recovery'] === '1' && mattrics_recovery_session_is_valid();

// If a store exists and user is NOT authenticated, they must log in first
if ($store !== null && empty($_SESSION['mattrics_authed']) && !$isRecovery) {
    header('Location: /login.php');
    exit;
}

$isAdding    = $store !== null && !$isRecovery;
$passkeyName = htmlspecialchars(trim($_GET['name'] ?? ''), ENT_QUOTES, 'UTF-8');
if ($passkeyName === '') $passkeyName = 'Default';

// Define these BEFORE the heredoc so PHP can interpolate them
$_passkeyNameJson = json_encode($passkeyName);
$_isAddingJson    = $isAdding ? 'true' : 'false';

$heading = $isAdding ? 'Add a passkey' : 'First-time setup';
$heading = $isRecovery ? 'Register a replacement passkey' : $heading;
$desc    = $isRecovery
    ? 'Your recovery code was accepted. Register a new passkey now to restore normal access.'
    : ($isAdding
    ? 'Register a new passkey for this device or password manager.'
    : 'Register a passkey for this device. You\'ll use it every time you sign in.');
$note    = $isRecovery
    ? 'After registration, sign in with the new passkey. The recovery code you used cannot be used again.'
    : ($isAdding
    ? 'To remove a passkey, use the Settings page inside the app.'
    : 'Manage passkeys and recovery codes from Settings after signing in. Store the recovery codes shown after setup in a safe place.');

$authPageTitle = $isAdding ? 'Add Passkey' : 'Register Passkey';
$authPageBody  = <<<HTML
<h1 class="auth-heading">{$heading}</h1>
<p class="auth-desc">{$desc}</p>
<div class="auth-error" id="authError"></div>
<div class="auth-error" id="recoveryCodesBox" style="text-align:left"></div>
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
function showRecoveryCodes(codes) {
  const box = document.getElementById('recoveryCodesBox');
  if (!codes || !codes.length || !box) return false;
  box.innerHTML = '<strong>Save these recovery codes now. They will not be shown again.</strong><br><br>' +
    codes.map(code => '<code style="display:block;margin:4px 0">' + code + '</code>').join('') +
    '<br><button class="auth-btn" id="savedRecoveryCodesBtn" type="button">I saved these codes</button>';
  box.classList.add('visible');
  document.getElementById('savedRecoveryCodesBtn').onclick = function () {
    window.location.href = IS_ADDING ? '/?view=settings' : '/login.php';
  };
  document.getElementById('registerBtn').style.display = 'none';
  return true;
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

    if (showRecoveryCodes(result.recoveryCodes)) return;
    window.location.href = IS_ADDING ? '/?view=settings' : '/login.php';
  } catch (err) {
    showError(err.name === 'NotAllowedError' ? 'Passkey registration was cancelled or timed out.' : err.message);
    btn.disabled = false;
  }
}
</script>
HTML;

require __DIR__ . '/auth-page.php';
