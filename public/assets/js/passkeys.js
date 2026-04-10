(function () {
  'use strict';

  const M = window.Mattrics;

  // ── State ──────────────────────────────────────────────────────────────────
  let _passkeys = []; // [{internalId, name, registeredAt}]
  let _recovery = null;

  // ── Utilities ──────────────────────────────────────────────────────────────
  function esc(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function fmtDate(value) {
    if (!value) return '';
    const raw = String(value).trim();
    const isoMatch = raw.match(/^(\d{4}-\d{2}-\d{2})(?:[T ](\d{2}:\d{2}))?/);
    if (isoMatch) {
      return isoMatch[2] ? `${isoMatch[1]} ${isoMatch[2]}` : isoMatch[1];
    }

    const d = new Date(raw);
    if (isNaN(d.getTime())) return '';
    const yyyy = String(d.getUTCFullYear()).padStart(4, '0');
    const mm = String(d.getUTCMonth() + 1).padStart(2, '0');
    const dd = String(d.getUTCDate()).padStart(2, '0');
    const hh = String(d.getUTCHours()).padStart(2, '0');
    const min = String(d.getUTCMinutes()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd} ${hh}:${min}`;
  }

  // ── API ────────────────────────────────────────────────────────────────────
  async function apiPasskeys(method, body) {
    const opts = {
      method,
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': (window.MATTRICS_AUTH && window.MATTRICS_AUTH.csrfToken) || '',
      },
    };
    if (body) opts.body = JSON.stringify(body);
    const res  = await fetch('api/auth/passkeys.php', opts);
    const json = await res.json();
    if (!res.ok) throw new Error(json.error || 'Request failed.');
    return json;
  }

  // ── Render ─────────────────────────────────────────────────────────────────
  function renderRecoveryBlock(recovery, recoveryCodes) {
    const status = recovery && recovery.configured
      ? `${recovery.activeCount || 0} unused recovery codes available.`
      : 'Recovery codes are not configured yet.';
    const codesHtml = recoveryCodes && recoveryCodes.length
      ? `<div class="passkey-confirm-panel" style="margin-top:var(--space-3)">
          <span class="passkey-confirm-msg">Save these recovery codes now. They will not be shown again.</span>
          <div style="margin-top:var(--space-2)">
            ${recoveryCodes.map((code) => `<code style="display:block;margin:4px 0">${esc(code)}</code>`).join('')}
          </div>
        </div>`
      : '';

    return `
      <div class="passkey-recovery" style="margin-top:var(--space-4)">
        <div class="passkey-date">${esc(status)}</div>
        <p style="color:var(--muted);font-size:0.88rem;margin:var(--space-2) 0">Recovery codes are the lockout safety net if every passkey is lost. Regenerating codes invalidates older unused codes.</p>
        <button class="passkey-add-btn" onclick="passkeyRegenerateRecoveryCodes()">Generate new recovery codes</button>
        ${codesHtml}
      </div>
    `;
  }

  function renderPasskeysSection(passkeys, errorMsg, recovery, recoveryCodes) {
    const isLast = passkeys.length <= 1;

    const rows = passkeys.map((pk) => `
      <div class="passkey-row" data-id="${esc(pk.internalId)}">
        <div class="passkey-row-info">
          <div class="passkey-name-row">
            <span class="passkey-name">${esc(pk.name)}</span>
            <button class="passkey-icon-btn passkey-rename-btn"
              onclick="passkeyStartRename('${esc(pk.internalId)}')"
              title="Rename" aria-label="Rename passkey ${esc(pk.name)}">
              <svg width="13" height="13" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M11.5 1.5a2.121 2.121 0 0 1 3 3L5 14H2v-3L11.5 1.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
              </svg>
            </button>
          </div>
          <span class="passkey-date">Created ${esc(fmtDate(pk.created_at || pk.registeredAt))}${pk.last_used_at ? ` · Last used ${esc(fmtDate(pk.last_used_at))}` : ''}</span>
        </div>
        <button
          class="passkey-icon-btn passkey-delete-btn${isLast ? ' passkey-delete-btn--disabled' : ''}"
          ${isLast ? 'disabled title="Add another passkey before deleting this one"' : `onclick="passkeyDelete('${esc(pk.internalId)}')" title="Delete"`}
          aria-label="Delete passkey ${esc(pk.name)}"
        >
          <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <path d="M2 4h12M5 4V2h6v2M6 7v5M10 7v5M3 4l1 9a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1l1-9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
      </div>
    `).join('');

    const errHtml = errorMsg
      ? `<div class="settings-error" style="margin-bottom:var(--space-3)">${esc(errorMsg)}</div>`
      : '';

    return `
<section class="settings-group" id="passkeysGroup">
  <h2 class="settings-group-title">Passkeys</h2>
  ${errHtml}
  <div class="passkey-list" id="passkeyList">
    ${rows || '<p style="color:var(--muted);font-size:0.88rem;margin:0">No passkeys found.</p>'}
  </div>
  <div style="margin-top:var(--space-4)">
    <button class="passkey-add-btn" onclick="passkeyAdd()">
      <svg width="13" height="13" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 2v12M2 8h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      Add passkey
    </button>
  </div>
  ${renderRecoveryBlock(recovery, recoveryCodes)}
</section>`;
  }

  // ── Public: loadAndRenderPasskeys ──────────────────────────────────────────
  M.loadAndRenderPasskeys = async function loadAndRenderPasskeys() {
    const container = document.getElementById('passkeysSection');
    if (!container) return;
    try {
      const json = await apiPasskeys('GET');
      _passkeys = json.passkeys || [];
      _recovery = json.recovery || null;
      if (json.csrfToken && window.MATTRICS_AUTH) window.MATTRICS_AUTH.csrfToken = json.csrfToken;
      container.innerHTML = renderPasskeysSection(_passkeys, null, _recovery, null);
    } catch (err) {
      container.innerHTML = renderPasskeysSection([], err.message, null, null);
    }
  };

  window.passkeyRegenerateRecoveryCodes = async function passkeyRegenerateRecoveryCodes() {
    if (!confirm('Generate new recovery codes? Older unused codes will stop working.')) return;
    try {
      const json = await apiPasskeys('POST', { action: 'regenerate_recovery' });
      _recovery = json.recovery || _recovery;
      const container = document.getElementById('passkeysSection');
      if (container) container.innerHTML = renderPasskeysSection(_passkeys, null, _recovery, json.recoveryCodes || []);
    } catch (err) {
      alert(err.message);
    }
  };

  // ── Rename ─────────────────────────────────────────────────────────────────
  window.passkeyStartRename = function passkeyStartRename(internalId) {
    const pk = _passkeys.find((p) => p.internalId === internalId);
    if (!pk) return;

    const row = document.querySelector(`.passkey-row[data-id="${CSS.escape(internalId)}"]`);
    if (!row) return;

    const nameRow = row.querySelector('.passkey-name-row');
    if (!nameRow) return;

    // Replace the entire name-row with an inline edit row
    const editRow = document.createElement('div');
    editRow.className = 'passkey-edit-row';

    const input = document.createElement('input');
    input.type        = 'text';
    input.className   = 'passkey-rename-input';
    input.value       = pk.name;
    input.maxLength   = 64;

    // ✓ confirm button
    const confirmBtn = document.createElement('button');
    confirmBtn.className   = 'passkey-icon-btn passkey-confirm-btn';
    confirmBtn.title       = 'Save';
    confirmBtn.setAttribute('aria-label', 'Save name');
    confirmBtn.innerHTML   = `<svg width="13" height="13" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M2 8l4.5 4.5L14 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>`;

    // ✕ cancel button
    const cancelBtn = document.createElement('button');
    cancelBtn.className  = 'passkey-icon-btn passkey-cancel-btn';
    cancelBtn.title      = 'Cancel';
    cancelBtn.setAttribute('aria-label', 'Cancel rename');
    cancelBtn.innerHTML  = `<svg width="11" height="11" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M2 2l12 12M14 2L2 14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>`;

    editRow.append(input, confirmBtn, cancelBtn);
    nameRow.replaceWith(editRow);
    input.focus();
    input.select();

    confirmBtn.onclick = async () => {
      const newName = input.value.trim();
      if (!newName) { input.focus(); return; }
      confirmBtn.disabled = true;
      try {
        await apiPasskeys('POST', { action: 'rename', id: internalId, name: newName });
        pk.name = newName;
        await M.loadAndRenderPasskeys();
      } catch (err) {
        confirmBtn.disabled = false;
        alert(err.message);
      }
    };

    cancelBtn.onclick = () => M.loadAndRenderPasskeys();

    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter')  { e.preventDefault(); confirmBtn.click(); }
      if (e.key === 'Escape') cancelBtn.click();
    });
  };

  // ── Delete (with passkey re-auth) ──────────────────────────────────────────
  window.passkeyDelete = async function passkeyDelete(internalId) {
    const pk = _passkeys.find((p) => p.internalId === internalId);
    if (!pk) return;

    // Show inline confirmation panel on the row
    const row = document.querySelector(`.passkey-row[data-id="${CSS.escape(internalId)}"]`);
    if (!row) return;

    // Avoid opening twice
    if (row.querySelector('.passkey-confirm-panel')) return;

    const panel = document.createElement('div');
    panel.className = 'passkey-confirm-panel';
    panel.innerHTML = `
      <span class="passkey-confirm-msg">Authenticate with your passkey to confirm deletion.</span>
      <div class="passkey-confirm-actions">
        <button class="settings-save-btn passkey-confirm-delete-btn" style="background:rgba(255,145,102,0.15);border-color:rgba(255,145,102,0.4);color:rgba(255,145,102,0.9);padding:7px 14px;font-size:0.83rem">
          Authenticate &amp; delete
        </button>
        <button class="passkey-add-btn passkey-confirm-cancel-btn" style="padding:7px 12px;font-size:0.83rem">Cancel</button>
      </div>
    `;
    row.after(panel);

    panel.querySelector('.passkey-confirm-cancel-btn').onclick = () => {
      panel.remove();
    };

    panel.querySelector('.passkey-confirm-delete-btn').onclick = async () => {
      const deleteBtn = panel.querySelector('.passkey-confirm-delete-btn');
      deleteBtn.disabled = true;
      deleteBtn.textContent = 'Waiting for passkey…';

      try {
        // 1. Get a fresh challenge
        const challengeRes = await fetch('/api/auth/challenge.php?action=login&purpose=delete', { credentials: 'same-origin' });
        if (!challengeRes.ok) throw new Error('Failed to get challenge.');
        const json = await challengeRes.json();
        const opts = json.publicKey;

        opts.challenge = base64urlToBuffer(opts.challenge);
        if (opts.allowCredentials) {
          opts.allowCredentials = opts.allowCredentials.map((c) => ({
            ...c, id: base64urlToBuffer(c.id),
          }));
        }

        // 2. Prompt for passkey
        const assertion = await navigator.credentials.get({ publicKey: opts });

        // 3. Send assertion + delete request
        await apiPasskeys('POST', {
          action: 'delete',
          id:     internalId,
          assertion: {
            id:                bufferToBase64url(assertion.rawId),
            clientDataJSON:    bufferToBase64url(assertion.response.clientDataJSON),
            authenticatorData: bufferToBase64url(assertion.response.authenticatorData),
            signature:         bufferToBase64url(assertion.response.signature),
          },
        });

        await M.loadAndRenderPasskeys();

      } catch (err) {
        deleteBtn.disabled = false;
        deleteBtn.textContent = 'Authenticate & delete';
        const msg = panel.querySelector('.passkey-confirm-msg');
        msg.textContent = err.name === 'NotAllowedError'
          ? 'Authentication cancelled. Try again.'
          : err.message;
        msg.style.color = '#ff8d70';
      }
    };
  };

  // ── WebAuthn helpers (needed for delete re-auth) ───────────────────────────
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

  // ── Add — inline form ──────────────────────────────────────────────────────
  window.passkeyAdd = function passkeyAdd() {
    // If the inline form is already open, focus it instead of opening a second one
    const existing = document.getElementById('passkeyAddForm');
    if (existing) { existing.querySelector('input').focus(); return; }

    const addBtn = document.querySelector('#passkeysGroup .passkey-add-btn');
    if (!addBtn) return;

    const form = document.createElement('div');
    form.id        = 'passkeyAddForm';
    form.className = 'passkey-add-form';

    const input = document.createElement('input');
    input.type        = 'text';
    input.className   = 'passkey-add-input';
    input.placeholder = 'e.g. iCloud Keychain, Bitwarden, Android…';
    input.maxLength   = 64;
    input.value       = '';

    const goBtn = document.createElement('button');
    goBtn.className   = 'settings-save-btn passkey-add-submit-btn';
    goBtn.style.cssText = 'white-space:nowrap;padding:8px 16px;font-size:0.875rem';
    goBtn.textContent = 'Register passkey';

    const cancelBtn = document.createElement('button');
    cancelBtn.className   = 'passkey-add-btn';
    cancelBtn.style.cssText = 'padding:8px 14px';
    cancelBtn.textContent = 'Cancel';

    form.append(input, goBtn, cancelBtn);
    addBtn.replaceWith(form);
    input.focus();

    goBtn.onclick = () => {
      const name = input.value.trim() || 'New Device';
      window.location.href = '/register.php?name=' + encodeURIComponent(name);
    };

    cancelBtn.onclick = () => M.loadAndRenderPasskeys();

    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') { e.preventDefault(); goBtn.click(); }
      if (e.key === 'Escape') cancelBtn.click();
    });
  };

}());
