<?php
declare(strict_types=1);

require_once __DIR__ . '/api/bootstrap-auth.php';

mattrics_auth_session_start();
if (mattrics_auth_requires_https() && !mattrics_is_https_request()) {
    header('Location: https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/recovery.php'));
    exit;
}

if (!empty($_SESSION['mattrics_authed']) && !mattrics_session_is_timed_out()) {
    header('Location: /?view=settings');
    exit;
}

$authPageTitle = 'Recover Access';
$authPageBody  = <<<'HTML'
<h1 class="auth-heading">Recover access</h1>
<p class="auth-desc">Use one of your saved recovery codes. The code works once, then you can register a replacement passkey.</p>
<div class="auth-error" id="authError"></div>
<input id="recoveryCode" class="auth-input" type="text" autocomplete="one-time-code" placeholder="XXXXX-XXXXX" maxlength="32" style="width:100%;box-sizing:border-box;margin:0 0 1rem;padding:0.85rem 1rem;border-radius:8px;border:1px solid rgba(255,255,255,0.18);background:rgba(255,255,255,0.06);color:inherit;font:inherit;text-transform:uppercase">
<button class="auth-btn" id="recoverBtn" onclick="recoverAccess()">Continue</button>
<p class="auth-note">Only use recovery on a trusted device. After this step, register a new passkey immediately.</p>
<script>
function showError(msg) {
  const el = document.getElementById('authError');
  el.textContent = msg;
  el.classList.add('visible');
}
async function recoverAccess() {
  const btn = document.getElementById('recoverBtn');
  const input = document.getElementById('recoveryCode');
  btn.disabled = true;
  document.getElementById('authError').classList.remove('visible');
  try {
    const res = await fetch('/api/auth/recovery.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'verify', code: input.value.trim() }),
    });
    const result = await res.json();
    if (!res.ok) throw new Error(result.error || 'Recovery failed.');
    window.location.href = result.redirect || '/register.php?recovery=1';
  } catch (err) {
    showError(err.message);
    btn.disabled = false;
  }
}
document.getElementById('recoveryCode').addEventListener('keydown', function (event) {
  if (event.key === 'Enter') recoverAccess();
});
</script>
HTML;

require __DIR__ . '/auth-page.php';
