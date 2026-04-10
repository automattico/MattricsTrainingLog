<?php
declare(strict_types=1);

require_once __DIR__ . '/api/bootstrap-auth.php';

mattrics_auth_session_start();

// Already authenticated — go straight to app
if (!empty($_SESSION['mattrics_authed'])) {
    header('Location: /');
    exit;
}

// No passkey registered yet — go to setup
$credPath = mattrics_private_root() . '/passkey-credential.json';
if (!is_file($credPath)) {
    header('Location: /register.php');
    exit;
}

$authPageTitle = 'Sign In';
$authPageBody  = <<<'HTML'
<h1 class="auth-heading">Sign in</h1>
<p class="auth-desc">Use your passkey (Touch ID, Face ID, or security key) to sign in.</p>
<div class="auth-error" id="authError"></div>
<button class="auth-btn" id="signInBtn" onclick="signIn()">Sign in with passkey</button>
<script>
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
async function signIn() {
  const btn = document.getElementById('signInBtn');
  btn.disabled = true;
  document.getElementById('authError').classList.remove('visible');
  try {
    const challengeRes = await fetch('/api/auth/challenge.php?action=login', { credentials: 'same-origin' });
    if (!challengeRes.ok) throw new Error('Failed to get challenge.');
    const json = await challengeRes.json();
    const opts = json.publicKey;

    // Convert base64url fields to ArrayBuffer
    opts.challenge = base64urlToBuffer(opts.challenge);
    if (opts.allowCredentials) {
      opts.allowCredentials = opts.allowCredentials.map(c => ({
        ...c,
        id: base64urlToBuffer(c.id),
      }));
    }

    const assertion = await navigator.credentials.get({ publicKey: opts });

    const body = {
      id:                bufferToBase64url(assertion.rawId),
      clientDataJSON:    bufferToBase64url(assertion.response.clientDataJSON),
      authenticatorData: bufferToBase64url(assertion.response.authenticatorData),
      signature:         bufferToBase64url(assertion.response.signature),
    };

    const verifyRes = await fetch('/api/auth/verify.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    const result = await verifyRes.json();
    if (!verifyRes.ok) throw new Error(result.error || 'Verification failed.');

    window.location.href = '/';
  } catch (err) {
    showError(err.name === 'NotAllowedError' ? 'Passkey sign-in was cancelled or timed out.' : err.message);
    btn.disabled = false;
  }
}
</script>
HTML;

require __DIR__ . '/auth-page.php';
