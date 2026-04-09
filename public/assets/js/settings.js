(function () {
  const M = window.Mattrics;

  const RPE_VALUES = [6, 7, 7.5, 8, 8.5, 9, 9.5, 10];
  const RPE_LABELS = {
    10:  "10 = absolute max effort, no reps left",
    9.5: "9.5 = Could have maybe done 1 rep",
    9:   "9 = Could have definitely done 1 more rep",
    8.5: "8.5 = Could have maybe done 2 more reps",
    8:   "8 = Could have definitely done 2 more reps",
    7.5: "7.5 = Could have maybe done 3 more reps",
    7:   "7 = Could have definitely done 3 more reps",
    6:   "6 = Could have done 4 or more reps",
  };

  const EXPERIENCE_OPTIONS = [
    { value: "Beginner",     label: "Beginner",     desc: "New to strength training or training inconsistently for less than 1 year" },
    { value: "Intermediate", label: "Intermediate", desc: "Training regularly for 1 to 3 years and familiar with basic programming and effort levels" },
    { value: "Advanced",     label: "Advanced",     desc: "Training consistently for more than 3 years with structured programming experience" },
  ];

  const SEX_OPTIONS = ["Male", "Female", "Prefer not to say"];

  // ── Utilities ────────────────────────────────────────────────────────────────

  function esc(str) {
    return String(str == null ? "" : str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function deriveAge(iso) {
    if (!iso) return null;
    const today = new Date();
    const dob = new Date(iso);
    if (isNaN(dob.getTime())) return null;
    let age = today.getFullYear() - dob.getFullYear();
    const m = today.getMonth() - dob.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
    return age;
  }

  M.deriveAge = deriveAge;

  function buildBirthdayIso(day, month, year) {
    if (!day || !month || !year) return null;
    const d = String(day).padStart(2, "0");
    const mo = String(month).padStart(2, "0");
    return `${year}-${mo}-${d}`;
  }

  function parseBirthdayIso(iso) {
    if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) return { day: "", month: "", year: "" };
    const [year, month, day] = iso.split("-");
    return { day: String(parseInt(day, 10)), month: String(parseInt(month, 10)), year };
  }

  // ── Client-side validation ────────────────────────────────────────────────────

  function validateSettings(data) {
    const errors = {};

    // bodyWeightKg
    const bw = data.bodyWeightKg;
    if (bw === "" || bw == null) {
      errors.bodyWeightKg = "Body weight is required.";
    } else {
      const v = parseFloat(bw);
      if (!isFinite(v)) {
        errors.bodyWeightKg = "Body weight must be a number.";
      } else if (v < 20 || v > 300) {
        errors.bodyWeightKg = "Must be between 20 and 300 kg.";
      } else if (Math.round(v * 10) / 10 !== Math.round(v * 1e9) / 1e9) {
        errors.bodyWeightKg = "At most 1 decimal place allowed.";
      }
    }

    // defaultRpe — pill UI always has a value, but validate just in case
    if (data.defaultRpe == null || !RPE_VALUES.includes(Number(data.defaultRpe))) {
      errors.defaultRpe = "Please select a default RPE.";
    }

    // birthday — optional
    if (data.birthday) {
      if (!/^\d{4}-\d{2}-\d{2}$/.test(data.birthday)) {
        errors.birthday = "Enter a valid date.";
      } else {
        const dob = new Date(data.birthday);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        if (isNaN(dob.getTime())) {
          errors.birthday = "Enter a valid date.";
        } else if (dob >= today) {
          errors.birthday = "Birthday must be a past date.";
        } else {
          const age = deriveAge(data.birthday);
          if (age < 8) errors.birthday = "Age must be at least 8 years.";
          else if (age > 130) errors.birthday = "Age must be 130 years or less.";
        }
      }
    }

    // heightCm — optional
    if (data.heightCm !== "" && data.heightCm != null) {
      const v = parseInt(data.heightCm, 10);
      if (!isFinite(v) || String(v) !== String(data.heightCm).trim()) {
        errors.heightCm = "Height must be a whole number.";
      } else if (v < 100 || v > 250) {
        errors.heightCm = "Must be between 100 and 250 cm.";
      }
    }

    // experienceLevel — required
    const expVals = EXPERIENCE_OPTIONS.map((o) => o.value);
    if (!data.experienceLevel || !expVals.includes(data.experienceLevel)) {
      errors.experienceLevel = "Please select your training experience.";
    }

    return errors;
  }

  M.validateSettings = validateSettings;

  // ── Tooltip helper ────────────────────────────────────────────────────────────

  function tooltip(text) {
    return `<span class="tooltip-wrap" tabindex="0" aria-label="${esc(text)}">
      <span class="tooltip-icon" aria-hidden="true">?</span>
      <span class="tooltip-text" role="tooltip">${esc(text)}</span>
    </span>`;
  }

  // ── HTML renderers ────────────────────────────────────────────────────────────

  function renderRpePicker(current) {
    const val = current != null ? Number(current) : 7.5;
    const btns = RPE_VALUES.map((v) =>
      `<button type="button" class="rpe-btn${v === val ? " active" : ""}" data-value="${v}" onclick="settingsRpeSelect(${v})">${v}</button>`
    ).join("");
    const currentLabel = RPE_LABELS[val] || "";
    return `
      <div class="rpe-picker" role="group" aria-label="Default RPE selector">${btns}</div>
      <div class="rpe-explanation" id="rpeExplanation">${esc(currentLabel)}</div>
      <input type="hidden" id="fieldDefaultRpe" value="${val}">
    `;
  }

  function renderBirthdayFields(iso, errors) {
    const { day, month, year } = parseBirthdayIso(iso);
    const age = deriveAge(iso);
    const ageHtml = age != null ? `<span class="settings-age-display">Age: ${age}</span>` : "";
    const errHtml = errors.birthday ? `<div class="settings-error">${esc(errors.birthday)}</div>` : "";
    return `
      <div class="settings-birthday-row">
        <div class="birthday-field">
          <label class="birthday-label" for="bdDay">Day</label>
          <input class="settings-input birthday-input" type="number" id="bdDay" min="1" max="31" placeholder="DD" value="${esc(day)}" oninput="settingsBirthdayChange()">
        </div>
        <div class="birthday-field">
          <label class="birthday-label" for="bdMonth">Month</label>
          <input class="settings-input birthday-input" type="number" id="bdMonth" min="1" max="12" placeholder="MM" value="${esc(month)}" oninput="settingsBirthdayChange()">
        </div>
        <div class="birthday-field">
          <label class="birthday-label" for="bdYear">Year</label>
          <input class="settings-input birthday-input birthday-input--year" type="number" id="bdYear" min="1900" max="2100" placeholder="YYYY" value="${esc(year)}" oninput="settingsBirthdayChange()">
        </div>
        <div class="birthday-age" id="birthdayAgeDisplay">${ageHtml}</div>
      </div>
      ${errHtml}
    `;
  }

  function renderForm(saved, errors) {
    saved = saved || {};
    errors = errors || {};

    const experienceOpts = EXPERIENCE_OPTIONS.map((o) =>
      `<option value="${esc(o.value)}"${saved.experienceLevel === o.value ? " selected" : ""}>${esc(o.label)} — ${esc(o.desc)}</option>`
    ).join("");

    const sexOpts = ["", ...SEX_OPTIONS].map((v) =>
      `<option value="${esc(v)}"${(saved.sex || "") === v ? " selected" : ""}>${v === "" ? "Prefer not to say" : esc(v)}</option>`
    ).join("");

    // Fix: blank option stays blank, actual "Prefer not to say" is its own option
    const sexOptsFixed = `<option value=""${!saved.sex ? " selected" : ""}></option>` +
      SEX_OPTIONS.map((v) =>
        `<option value="${esc(v)}"${saved.sex === v ? " selected" : ""}>${esc(v)}</option>`
      ).join("");

    return `
<div class="settings-shell">
  <div class="settings-kicker">Settings</div>
  <h1 class="settings-heading">Your Profile</h1>

  <div class="settings-groups-row">
  <section class="settings-group">
    <h2 class="settings-group-title">Profile</h2>

    <div class="settings-fields-row settings-fields-row--birthday">
      <div class="settings-field">
        <label class="settings-label" for="bdDay">
          Birthday
          <span class="settings-optional">recommended</span>
          ${tooltip("Age is calculated automatically from this date.")}
        </label>
        ${renderBirthdayFields(saved.birthday, errors)}
      </div>
    </div>

    <div class="settings-fields-row">
      <div class="settings-field">
        <label class="settings-label" for="fieldBodyWeightKg">
          Body weight
          <span class="settings-required">required</span>
          ${tooltip("Used for more accurate fatigue and training calculations.")}
        </label>
        <div class="settings-input-wrapper">
          <input class="settings-input settings-input--compact" type="number" id="fieldBodyWeightKg" min="20" max="300" step="0.1"
            placeholder="e.g. 80.0" value="${esc(saved.bodyWeightKg != null ? saved.bodyWeightKg : "")}">
          <span class="settings-unit">kg</span>
        </div>
        ${errors.bodyWeightKg ? `<div class="settings-error">${esc(errors.bodyWeightKg)}</div>` : ""}
      </div>

      <div class="settings-field">
        <label class="settings-label" for="fieldHeightCm">
          Body height
          <span class="settings-optional">optional</span>
          ${tooltip("Optional. May improve future calculations and analytics.")}
        </label>
        <div class="settings-input-wrapper">
          <input class="settings-input settings-input--compact" type="number" id="fieldHeightCm" min="100" max="250" step="1"
            placeholder="e.g. 178" value="${esc(saved.heightCm != null ? saved.heightCm : "")}">
          <span class="settings-unit">cm</span>
        </div>
        ${errors.heightCm ? `<div class="settings-error">${esc(errors.heightCm)}</div>` : ""}
      </div>

      <div class="settings-field">
        <label class="settings-label" for="fieldSex">
          Sex
          <span class="settings-optional">optional</span>
          ${tooltip("Optional. Can improve accuracy for some calculations.")}
        </label>
        <div class="settings-input-wrapper">
          <select class="settings-input settings-select settings-input--narrow" id="fieldSex">
            <option value=""${!saved.sex ? " selected" : ""}></option>
            ${SEX_OPTIONS.map((v) =>
              `<option value="${esc(v)}"${saved.sex === v ? " selected" : ""}>${esc(v)}</option>`
            ).join("")}
          </select>
        </div>
        ${errors.sex ? `<div class="settings-error">${esc(errors.sex)}</div>` : ""}
      </div>
    </div>
  </section>

  <section class="settings-group">
    <h2 class="settings-group-title">Training</h2>

    <div class="settings-field">
      <label class="settings-label" for="fieldExperienceLevel">
        Training experience
        <span class="settings-required">required</span>
        ${tooltip("Used to better personalize fatigue and training recommendations.")}
      </label>
      <select class="settings-input settings-select" id="fieldExperienceLevel">
        <option value=""${!saved.experienceLevel ? " selected" : ""}></option>
        ${experienceOpts}
      </select>
      ${errors.experienceLevel ? `<div class="settings-error">${esc(errors.experienceLevel)}</div>` : ""}
    </div>

    <div class="settings-field">
      <label class="settings-label">
        Default RPE for missing logs
        <span class="settings-required">required</span>
      </label>
      ${renderRpePicker(saved.defaultRpe)}
      ${errors.defaultRpe ? `<div class="settings-error">${esc(errors.defaultRpe)}</div>` : ""}
    </div>
  </section>
  </div>

  <div class="settings-actions">
    <button class="settings-save-btn" type="button" onclick="saveSettings()">Save settings</button>
    <div class="settings-feedback" id="settingsFeedback" hidden></div>
  </div>
</div>
    `;
  }

  // ── Public: render ────────────────────────────────────────────────────────────

  M.renderSettingsView = function renderSettingsView(errors) {
    const el = document.getElementById("settingsContent");
    if (!el) return;
    el.innerHTML = renderForm(M.state.userSettings, errors || {});
  };

  // ── Public: loadUserSettings ──────────────────────────────────────────────────

  M.loadUserSettings = async function loadUserSettings() {
    if (!M.DATA_URL) return; // file:// mode — skip, use hardcoded defaults
    try {
      const res = await fetch("api/settings.php", {
        credentials: "same-origin",
        headers: { "X-Mattrics-Token": window.MATTRICS_TOKEN || "" },
      });
      if (res.ok) {
        const json = await res.json();
        M.state.userSettings = json.settings || null;
      }
    } catch (_) {
      // graceful degradation — hardcoded defaults remain in effect
    }
  };

  // ── Public: saveSettings ──────────────────────────────────────────────────────

  M.saveSettings = async function saveSettings() {
    const day   = (document.getElementById("bdDay")   || {}).value || "";
    const month = (document.getElementById("bdMonth") || {}).value || "";
    const year  = (document.getElementById("bdYear")  || {}).value || "";
    const birthday = (day && month && year) ? buildBirthdayIso(day, month, year) : null;

    const heightRaw = (document.getElementById("fieldHeightCm") || {}).value;
    const bwRaw     = (document.getElementById("fieldBodyWeightKg") || {}).value;

    const data = {
      bodyWeightKg:    bwRaw !== "" ? parseFloat(bwRaw) : null,
      defaultRpe:      Number((document.getElementById("fieldDefaultRpe") || {}).value || 7.5),
      birthday:        birthday,
      sex:             (document.getElementById("fieldSex") || {}).value || null,
      heightCm:        heightRaw !== "" ? parseInt(heightRaw, 10) : null,
      experienceLevel: (document.getElementById("fieldExperienceLevel") || {}).value || null,
    };

    const errors = validateSettings(data);

    if (Object.keys(errors).length > 0) {
      // Re-render the form with errors shown
      const el = document.getElementById("settingsContent");
      if (el) {
        // Preserve current saved values but show new errors
        el.innerHTML = renderForm(Object.assign({}, M.state.userSettings || {}, data), errors);
      }
      return;
    }

    const feedback = document.getElementById("settingsFeedback");
    const saveBtn = document.querySelector(".settings-save-btn");

    if (saveBtn) saveBtn.disabled = true;

    try {
      const res = await fetch("api/settings.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-Mattrics-Token": window.MATTRICS_TOKEN || "",
        },
        credentials: "same-origin",
        body: JSON.stringify(data),
      });

      const json = await res.json();

      if (!res.ok) {
        // Server-side validation errors
        const serverErrors = json.errors || { _: "Save failed. Please try again." };
        const el = document.getElementById("settingsContent");
        if (el) el.innerHTML = renderForm(Object.assign({}, M.state.userSettings || {}, data), serverErrors);
        return;
      }

      M.state.userSettings = json.settings;

      if (feedback) {
        feedback.textContent = "Settings saved.";
        feedback.classList.remove("settings-feedback--error");
        feedback.classList.add("settings-feedback--ok");
        feedback.hidden = false;
        setTimeout(() => { feedback.hidden = true; }, 3000);
      }

      // Re-render data views so new bodyWeight/RPE take effect immediately
      M.renderAll();

    } catch (_) {
      if (feedback) {
        feedback.textContent = "Could not save. Check your connection.";
        feedback.classList.remove("settings-feedback--ok");
        feedback.classList.add("settings-feedback--error");
        feedback.hidden = false;
      }
    } finally {
      if (saveBtn) saveBtn.disabled = false;
    }
  };

  // ── Global handlers (called from inline onclick) ──────────────────────────────

  window.settingsRpeSelect = function settingsRpeSelect(val) {
    document.querySelectorAll(".rpe-btn").forEach((btn) => {
      btn.classList.toggle("active", Number(btn.dataset.value) === val);
    });
    const hidden = document.getElementById("fieldDefaultRpe");
    if (hidden) hidden.value = val;
    const explanation = document.getElementById("rpeExplanation");
    if (explanation) explanation.textContent = RPE_LABELS[val] || "";
  };

  window.settingsBirthdayChange = function settingsBirthdayChange() {
    const day   = (document.getElementById("bdDay")   || {}).value || "";
    const month = (document.getElementById("bdMonth") || {}).value || "";
    const year  = (document.getElementById("bdYear")  || {}).value || "";
    const iso = buildBirthdayIso(day, month, year);
    const age = deriveAge(iso);
    const display = document.getElementById("birthdayAgeDisplay");
    if (display) {
      display.innerHTML = age != null ? `<span class="settings-age-display">Age: ${age}</span>` : "";
    }
  };
}());
