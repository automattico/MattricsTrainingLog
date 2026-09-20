(function () {
  const M = window.Mattrics;

  function esc(value) {
    const input = String(value == null ? "" : value);
    return typeof M.esc === "function" ? M.esc(input) : input
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function escAttr(value) {
    return typeof M.escAttr === "function" ? M.escAttr(String(value == null ? "" : value)) : esc(value);
  }

  function getConnectorState() {
    if (!M.state.connectors || typeof M.state.connectors !== "object") {
      M.state.connectors = {
        loading: false,
        loaded: false,
        error: "",
        feedback: null,
        pendingAction: "",
        draft: {
          enabled: false,
          apiKey: "",
        },
        data: null,
      };
    }
    return M.state.connectors;
  }

  function connectorEndpoint() {
    return String(M.CONNECTORS_URL || "");
  }

  function formatDateTime(value) {
    if (!value) return "Never";
    return typeof M.fmtDateTime === "function" ? M.fmtDateTime(value) : String(value);
  }

  function syncSummary(connector) {
    const sync = connector && connector.sync ? connector.sync : {};
    return [
      ["Status", connector && connector.connectionStatus ? connector.connectionStatus : "paused"],
      ["Last sync", formatDateTime(sync.lastSucceededAt)],
      ["Last test", formatDateTime(connector && connector.test ? connector.test.lastSucceededAt : "")],
      ["Sync mode", connector && connector.syncStrategy ? connector.syncStrategy : "Not run yet"],
      ["Cursor", connector && connector.cursorStartedAt ? formatDateTime(connector.cursorStartedAt) : "Not set"],
      ["Window start", connector && connector.lastSyncWindowStartedAt ? formatDateTime(connector.lastSyncWindowStartedAt) : "Not set"],
      ["Recent refetch", `${connector && connector.lastFetchedWorkoutCount ? connector.lastFetchedWorkoutCount : 0} workouts across ${connector && connector.lastFetchedPageCount ? connector.lastFetchedPageCount : 0} pages`],
    ];
  }

  function renderDetailList(items) {
    return `<dl class="connectors-detail-list">
      ${items.map(([label, value]) => `<div class="connectors-detail-row"><dt>${esc(label)}</dt><dd>${esc(value)}</dd></div>`).join("")}
    </dl>`;
  }

  function renderFeedback(feedback) {
    if (!feedback || !feedback.message) return "";
    return `<div class="connectors-feedback connectors-feedback--${escAttr(feedback.kind === "error" ? "error" : "ok")}">${esc(feedback.message)}</div>`;
  }

  function renderHevyCard(state, connectors) {
    const hevy = connectors && connectors.hevy ? connectors.hevy : null;
    const pending = state.pendingAction;
    const canInteract = pending === "";
    const statusLabel = hevy && hevy.connectionStatus ? hevy.connectionStatus : "paused";

    return `<section class="connectors-card">
      <div class="connectors-card-head">
        <div>
          <div class="connectors-kicker">Live Connector</div>
          <h2 class="connectors-title">Hevy</h2>
        </div>
        <span class="connectors-badge connectors-badge--${escAttr(statusLabel)}">${esc(statusLabel)}</span>
      </div>
      <p class="connectors-copy">Manage the local Hevy API key without hand-editing private runtime files. The stored key never comes back to the browser.</p>
      <label class="connectors-switch-row">
        <span>Enabled</span>
        <input type="checkbox" data-connectors-enabled ${state.draft.enabled ? "checked" : ""} ${canInteract ? "" : "disabled"}>
      </label>
      <label class="settings-label" for="connectorHevyApiKey">API key</label>
      <input
        class="settings-input connectors-input"
        id="connectorHevyApiKey"
        type="password"
        autocomplete="off"
        placeholder="${hevy && hevy.hasCredential ? "Stored privately. Leave blank to keep it." : "Paste Hevy API key"}"
        value="${escAttr(state.draft.apiKey || "")}"
        data-connectors-api-key
        ${canInteract ? "" : "disabled"}>
      <div class="connectors-field-note">${hevy && hevy.hasCredential ? "A key is already stored privately." : "No key stored yet."}</div>
      <div class="connectors-actions">
        <button type="button" class="settings-save-btn" data-connectors-save ${canInteract ? "" : "disabled"}>Save</button>
        <button type="button" class="secondary-btn exercise-admin-secondary-btn" data-connectors-test ${canInteract ? "" : "disabled"}>Test connection</button>
        <button type="button" class="secondary-btn exercise-admin-secondary-btn connectors-danger-btn" data-connectors-clear ${canInteract ? "" : "disabled"}>Clear</button>
      </div>
      ${renderDetailList(syncSummary(hevy))}
    </section>`;
  }

  function renderGarminCard(connectors) {
    const garmin = connectors && connectors.garmin ? connectors.garmin : null;
    const statusLabel = garmin && garmin.connectionStatus ? garmin.connectionStatus : "paused";

    return `<section class="connectors-card connectors-card--muted">
      <div class="connectors-card-head">
        <div>
          <div class="connectors-kicker">Groundwork</div>
          <h2 class="connectors-title">Garmin</h2>
        </div>
        <span class="connectors-badge connectors-badge--${escAttr(statusLabel)}">${esc(statusLabel)}</span>
      </div>
      <p class="connectors-copy">Garmin live fetching is still deferred in this slice. This card stays read-only so the reserved source and diagnostics status remain visible.</p>
      ${renderDetailList([
        ["Auth type", garmin && garmin.authType ? garmin.authType : "deferred"],
        ["Has credential", garmin && garmin.hasCredential ? "Yes" : "No"],
        ["Last sync", formatDateTime(garmin && garmin.lastSyncSucceededAt)],
        ["Last error", formatDateTime(garmin && garmin.lastErrorAt)],
      ])}
    </section>`;
  }

  function renderUnavailable(message) {
    return `<section class="connectors-card connectors-card--muted">
      <div class="connectors-card-head">
        <div>
          <div class="connectors-kicker">Connectors</div>
          <h2 class="connectors-title">Unavailable</h2>
        </div>
      </div>
      <p class="connectors-copy">${esc(message)}</p>
    </section>`;
  }

  function applyConnectorResponse(json) {
    const state = getConnectorState();
    if (!json || !json.connectors || typeof json.connectors !== "object") return;
    state.data = json.connectors;
    state.loaded = true;
    state.loading = false;
    state.error = "";
    state.draft.enabled = Boolean(json.connectors.hevy && json.connectors.hevy.enabled);
    state.draft.apiKey = "";
  }

  M.loadConnectors = async function loadConnectors() {
    const state = getConnectorState();
    const url = connectorEndpoint();
    if (!url || state.loading) return;

    state.loading = true;
    state.error = "";
    M.renderConnectorsView();

    try {
      const response = await M.apiFetch(url, {
        credentials: "same-origin",
      });
      const json = await response.json();
      if (!response.ok) {
        throw new Error((json && json.error) || `Could not load connectors. HTTP ${response.status}`);
      }
      applyConnectorResponse(json);
    } catch (error) {
      state.loading = false;
      state.loaded = false;
      state.data = null;
      const message = String(error && error.message ? error.message : error);
      state.error = /Not found\./.test(message)
        ? "Connector admin is available only in the local foundation runtime."
        : message;
    }

    M.renderConnectorsView();
  };

  M.renderConnectorsView = function renderConnectorsView() {
    const mount = document.getElementById("connectorsContent");
    if (!mount) return;

    const state = getConnectorState();
    if (!state.loaded && !state.loading && !state.error && connectorEndpoint()) {
      M.loadConnectors();
    }

    const body = state.error
      ? renderUnavailable(state.error)
      : state.loading && !state.data
        ? renderUnavailable("Loading local connector status...")
        : !state.data
          ? renderUnavailable("Connector data is not available yet.")
          : `${renderHevyCard(state, state.data)}${renderGarminCard(state.data)}`;

    mount.innerHTML = `<div class="connectors-shell">
      <div class="connectors-head">
        <div class="connectors-kicker">Operator</div>
        <h1 class="connectors-heading">Connectors</h1>
        <p class="connectors-intro">Local-only admin for live connector credentials and incremental sync state.</p>
      </div>
      ${renderFeedback(state.feedback)}
      <div class="connectors-grid">${body}</div>
    </div>`;
  };

  async function submitAction(action, extraBody) {
    const state = getConnectorState();
    const url = connectorEndpoint();
    if (!url || state.pendingAction) return;

    state.pendingAction = action;
    state.feedback = null;
    M.renderConnectorsView();

    try {
      const response = await M.apiFetch(url, {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(Object.assign({
          connector: "hevy",
          action,
        }, extraBody || {})),
      });
      const json = await response.json();
      if (json && json.connectors) {
        applyConnectorResponse(json);
      }
      if (!response.ok) {
        throw new Error((json && json.error) || `Request failed. HTTP ${response.status}`);
      }
      state.feedback = {
        kind: "ok",
        message: action === "save"
          ? "Hevy connector saved."
          : action === "clear"
            ? "Hevy connector cleared."
            : "Hevy connection test succeeded.",
      };
    } catch (error) {
      state.feedback = {
        kind: "error",
        message: String(error && error.message ? error.message : error),
      };
    } finally {
      state.pendingAction = "";
      M.renderConnectorsView();
    }
  }

  document.addEventListener("input", (event) => {
    const field = event.target.closest("[data-connectors-api-key]");
    if (!field) return;
    const state = getConnectorState();
    state.draft.apiKey = String(field.value || "");
  });

  document.addEventListener("change", (event) => {
    const field = event.target.closest("[data-connectors-enabled]");
    if (!field) return;
    const state = getConnectorState();
    state.draft.enabled = Boolean(field.checked);
  });

  document.addEventListener("click", (event) => {
    const saveButton = event.target.closest("[data-connectors-save]");
    if (saveButton) {
      const state = getConnectorState();
      submitAction("save", {
        enabled: state.draft.enabled,
        apiKey: state.draft.apiKey,
      });
      return;
    }

    const testButton = event.target.closest("[data-connectors-test]");
    if (testButton) {
      submitAction("test");
      return;
    }

    const clearButton = event.target.closest("[data-connectors-clear]");
    if (clearButton) {
      submitAction("clear");
    }
  });
}());
