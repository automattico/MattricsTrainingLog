/**
 * settings-tests.js
 * Pure-JS test suite — no framework required.
 * Run in browser: open public/tests/index.html
 * Run in Node:    node public/tests/settings-tests.js
 */

(function () {
  // ── Minimal test runner ───────────────────────────────────────────────────────

  let passed = 0;
  let failed = 0;
  const results = [];

  function assert(condition, message) {
    if (condition) {
      passed++;
      results.push({ ok: true, message });
    } else {
      failed++;
      results.push({ ok: false, message });
      if (typeof process !== "undefined") {
        console.error("  FAIL:", message);
      }
    }
  }

  function group(name, fn) {
    if (typeof process !== "undefined") console.log("\n" + name);
    fn();
  }

  // ── Inline copies of pure functions under test ────────────────────────────────
  // These are extracted from settings.js so they can run without a DOM or browser.

  function parseBirthdayDate(iso) {
    if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) return new Date(NaN);
    const [year, month, day] = iso.split("-").map((part) => parseInt(part, 10));
    return new Date(year, month - 1, day);
  }

  function toLocalIsoDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
  }

  function deriveAge(iso) {
    if (!iso) return null;
    const today = new Date();
    const dob = parseBirthdayDate(iso);
    if (isNaN(dob.getTime())) return null;
    let age = today.getFullYear() - dob.getFullYear();
    const m = today.getMonth() - dob.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
    return age;
  }

  function buildBirthdayIso(day, month, year) {
    if (!day || !month || !year) return null;
    const d = String(day).padStart(2, "0");
    const mo = String(month).padStart(2, "0");
    return `${year}-${mo}-${d}`;
  }

  const RPE_VALUES = [6, 7, 7.5, 8, 8.5, 9, 9.5, 10];

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

    // defaultRpe
    if (data.defaultRpe == null || !RPE_VALUES.includes(Number(data.defaultRpe))) {
      errors.defaultRpe = "Please select a default RPE.";
    }

    // birthday — optional
    if (data.birthday) {
      if (!/^\d{4}-\d{2}-\d{2}$/.test(data.birthday)) {
        errors.birthday = "Enter a valid date.";
      } else {
        const dob = parseBirthdayDate(data.birthday);
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

    // experienceLevel
    const expVals = ["Beginner", "Intermediate", "Advanced"];
    if (!data.experienceLevel || !expVals.includes(data.experienceLevel)) {
      errors.experienceLevel = "Please select your training experience.";
    }

    return errors;
  }

  // Base valid data for reuse in tests
  function validData(overrides) {
    return Object.assign({
      bodyWeightKg: 80,
      defaultRpe: 7.5,
      birthday: null,
      sex: null,
      heightCm: null,
      experienceLevel: "Intermediate",
    }, overrides);
  }

  // ── 1. bodyWeightKg validation ────────────────────────────────────────────────

  group("bodyWeightKg validation", function () {
    let e;

    e = validateSettings(validData({ bodyWeightKg: null }));
    assert(e.bodyWeightKg === "Body weight is required.", "null → required error");

    e = validateSettings(validData({ bodyWeightKg: "" }));
    assert(e.bodyWeightKg === "Body weight is required.", "empty string → required error");

    e = validateSettings(validData({ bodyWeightKg: 19 }));
    assert(!!e.bodyWeightKg, "19 kg → range error");

    e = validateSettings(validData({ bodyWeightKg: 301 }));
    assert(!!e.bodyWeightKg, "301 kg → range error");

    e = validateSettings(validData({ bodyWeightKg: 20 }));
    assert(!e.bodyWeightKg, "20 kg → valid");

    e = validateSettings(validData({ bodyWeightKg: 300 }));
    assert(!e.bodyWeightKg, "300 kg → valid");

    e = validateSettings(validData({ bodyWeightKg: 75.5 }));
    assert(!e.bodyWeightKg, "75.5 kg → valid (1 decimal)");

    e = validateSettings(validData({ bodyWeightKg: 80.12 }));
    assert(!!e.bodyWeightKg, "80.12 kg → error (2 decimals)");

    e = validateSettings(validData({ bodyWeightKg: "abc" }));
    assert(!!e.bodyWeightKg, "'abc' → not-a-number error");
  });

  // ── 2. defaultRpe fallback behaviour ─────────────────────────────────────────

  group("defaultRpe fallback", function () {
    // Simulate how hevy-parser.js resolves the effective defaultRpe
    function resolveRpe(userSettings) {
      const us = userSettings || {};
      const configDefault = 7.5; // matches updated constants.js
      return (us.defaultRpe != null ? us.defaultRpe : null) || configDefault;
    }

    assert(resolveRpe(null) === 7.5,                    "no settings → 7.5");
    assert(resolveRpe({}) === 7.5,                      "empty settings → 7.5");
    assert(resolveRpe({ defaultRpe: null }) === 7.5,    "null defaultRpe → 7.5");
    assert(resolveRpe({ defaultRpe: 8.5 }) === 8.5,     "explicit 8.5 → 8.5");
    assert(resolveRpe({ defaultRpe: 6 }) === 6,         "explicit 6 → 6");
    assert(resolveRpe({ defaultRpe: 10 }) === 10,       "explicit 10 → 10");
  });

  // ── 3. Age derivation from birthday ──────────────────────────────────────────

  group("age derivation", function () {
    const today = new Date();

    // Someone born exactly 30 years ago today
    const dob30 = new Date(today);
    dob30.setFullYear(dob30.getFullYear() - 30);
    const iso30 = toLocalIsoDate(dob30);
    assert(deriveAge(iso30) === 30, "exact 30th birthday → age 30");

    // Someone whose birthday is tomorrow (hasn't turned 30 yet)
    const dobAlmost30 = new Date(today);
    dobAlmost30.setFullYear(dobAlmost30.getFullYear() - 30);
    dobAlmost30.setDate(dobAlmost30.getDate() + 1); // tomorrow
    const isoAlmost30 = toLocalIsoDate(dobAlmost30);
    assert(deriveAge(isoAlmost30) === 29, "birthday tomorrow → still 29");

    // null / empty
    assert(deriveAge(null) === null, "null → null");
    assert(deriveAge("") === null, "empty → null");

    // Future date
    const future = new Date(today);
    future.setFullYear(future.getFullYear() + 1);
    const isoFuture = toLocalIsoDate(future);
    const futureAge = deriveAge(isoFuture);
    assert(futureAge !== null && futureAge < 0, "future date → negative age");
  });

  // ── 4. Birthday validation ────────────────────────────────────────────────────

  group("birthday validation", function () {
    let e;

    // Future date
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const isoFuture = toLocalIsoDate(tomorrow);
    e = validateSettings(validData({ birthday: isoFuture }));
    assert(!!e.birthday, "future birthday → error");

    // Too young (1 year old)
    const oneYearOld = new Date();
    oneYearOld.setFullYear(oneYearOld.getFullYear() - 1);
    e = validateSettings(validData({ birthday: toLocalIsoDate(oneYearOld) }));
    assert(!!e.birthday, "age 1 → too young error");

    // Too old (131 years)
    const tooOld = new Date();
    tooOld.setFullYear(tooOld.getFullYear() - 131);
    e = validateSettings(validData({ birthday: toLocalIsoDate(tooOld) }));
    assert(!!e.birthday, "age 131 → too old error");

    // Valid 30-year-old
    const valid30 = new Date();
    valid30.setFullYear(valid30.getFullYear() - 30);
    e = validateSettings(validData({ birthday: toLocalIsoDate(valid30) }));
    assert(!e.birthday, "age 30 → valid");

    // Wrong format
    e = validateSettings(validData({ birthday: "01/01/1990" }));
    assert(!!e.birthday, "DD/MM/YYYY format → error");

    // Empty string (optional — should be treated as null/absent)
    e = validateSettings(validData({ birthday: "" }));
    assert(!e.birthday, "empty birthday → no error (optional)");
  });

  // ── 5. Birthday ISO assembly ──────────────────────────────────────────────────

  group("birthday ISO assembly", function () {
    assert(buildBirthdayIso("5", "3", "1990") === "1990-03-05",   "day/month padding");
    assert(buildBirthdayIso("31", "12", "1999") === "1999-12-31", "no padding needed");
    assert(buildBirthdayIso("", "3", "1990") === null,            "missing day → null");
    assert(buildBirthdayIso("5", "", "1990") === null,            "missing month → null");
    assert(buildBirthdayIso("5", "3", "") === null,               "missing year → null");
  });

  // ── 6. heightCm validation ────────────────────────────────────────────────────

  group("heightCm validation", function () {
    let e;

    e = validateSettings(validData({ heightCm: null }));
    assert(!e.heightCm, "null → optional, no error");

    e = validateSettings(validData({ heightCm: "" }));
    assert(!e.heightCm, "empty string → optional, no error");

    e = validateSettings(validData({ heightCm: 99 }));
    assert(!!e.heightCm, "99 → below min");

    e = validateSettings(validData({ heightCm: 251 }));
    assert(!!e.heightCm, "251 → above max");

    e = validateSettings(validData({ heightCm: 178 }));
    assert(!e.heightCm, "178 → valid");

    e = validateSettings(validData({ heightCm: 100 }));
    assert(!e.heightCm, "100 → valid (min boundary)");

    e = validateSettings(validData({ heightCm: 250 }));
    assert(!e.heightCm, "250 → valid (max boundary)");
  });

  // ── Results ──────────────────────────────────────────────────────────────────

  const total = passed + failed;

  if (typeof process !== "undefined") {
    // Node
    console.log(`\nResults: ${passed}/${total} passed, ${failed} failed`);
    if (failed > 0) {
      results.filter((r) => !r.ok).forEach((r) => console.error("  FAIL:", r.message));
      process.exit(1);
    }
  }

  // Export for browser runner
  if (typeof window !== "undefined") {
    window.__settingsTestResults = { passed, failed, total, results };
  }
}());
