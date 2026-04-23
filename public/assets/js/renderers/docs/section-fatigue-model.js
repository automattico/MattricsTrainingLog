(function () {
  const M = window.Mattrics;
  M.docsSections = M.docsSections || [];
  M.docsSections.push({
    id: "docs-fatigue-model",
    label: "Fatigue model",
    title: "Decay-based load accumulation",
    intro: "Each activity contributes muscle-specific stimulus, which decays over time and is normalized into a 0–100 fatigue score.",
    body: `
      ${M.docsSubsection("Calculation pipeline", `
        <div class="docs-flow">
          <div class="docs-flow-step">Activity log</div>
          <div class="docs-flow-arrow">→</div>
          <div class="docs-flow-step">Raw stimulus per muscle</div>
          <div class="docs-flow-arrow">→</div>
          <div class="docs-flow-step">Exponential decay</div>
          <div class="docs-flow-arrow">→</div>
          <div class="docs-flow-step">0–100 score</div>
          <div class="docs-flow-arrow">→</div>
          <div class="docs-flow-step">Tier + recovery hours</div>
        </div>
      `)}

      ${M.docsSubsection("Core formula", `
        <div class="docs-formula">rawLoad = Σ( stimulus × 0.5^(hoursAgo / halfLife) )
score   = min(100, rawLoad / normalizationLoad × 100)</div>
        ${M.docsTable([
          ["stimulus", "The muscle-specific load contributed by one activity before decay."],
          ["hoursAgo", "Elapsed hours from the activity timestamp to now."],
          ["halfLife", "Per-muscle recovery half-life in hours. After one half-life, 50% of stimulus remains."],
          ["rawLoad", "All remaining decayed stimulus for one muscle across the lookback window."],
          ["normalizationLoad", "The per-muscle reference for a fully loaded 100% score."],
          ["score", "The displayed fatigue score, capped at 100."],
        ])}
      `)}

      ${M.docsSubsection("Fatigue tiers and readiness", `
        ${M.docsTiers([
          { color: "var(--fatigue-color-none)",       name: "No recent load",   range: "—",        desc: "Raw load is zero or negligible",          action: "Train today" },
          { color: "var(--fatigue-color-fresh)",      name: "Fresh",            range: "0 – 24 %",  desc: "Low accumulated stimulus",               action: "Train today" },
          { color: "var(--fatigue-color-recovering)", name: "Recovering",       range: "25 – 49 %", desc: "Still within half-life window",           action: "Train tomorrow" },
          { color: "var(--fatigue-color-fatigued)",   name: "Fatigued",         range: "50 – 74 %", desc: "Significant stimulus remaining",          action: "Needs recovery" },
          { color: "var(--fatigue-color-high)",       name: "Highly fatigued",  range: "75 – 100 %",desc: "Near or at normalization ceiling",        action: "Rest" },
        ])}
        <p class="docs-copy" style="margin-top:10px">
          The Muscle Fatigue Map table sorts columns from soonest ready to latest ready, making it practical for
          choosing the next session rather than just inspecting raw percentages.
        </p>
      `)}

      ${M.docsSubsection("Decay and half-lives", `
        <div class="docs-formula">remaining = stimulus × 0.5^(hoursElapsed / halfLife)</div>
        ${M.docsTable([
          ["72 h", "Chest, upper back, lower back, quadriceps, hamstrings, gluteal, adductors."],
          ["60 h", "Deltoids, trapezius, calves."],
          ["48 h", "Triceps, biceps, abs, obliques."],
        ])}
        <p class="docs-copy">
          Activity timestamps are normalized to noon on the activity date for simple sheet date handling —
          the model does not use sub-day precision.
        </p>
      `)}

      ${M.docsSubsection("Model parameters", `
        ${M.docsTable([
          ["Lookback window", "<code class=\"docs-code\">10</code> days rolling from now."],
          ["Estimated bodyweight", "<code class=\"docs-code\">75 kg</code> fallback when no profile value is available."],
          ["Bodyweight load factor", "<code class=\"docs-code\">0.4</code> — used to estimate bodyweight exercise load."],
          ["Default RPE", "<code class=\"docs-code\">7.5</code> when a Hevy set has no usable RPE value."],
          ["Small threshold ratio", "<code class=\"docs-code\">0.02</code> — ignores tiny residual loads."],
          ["Recovery threshold ratio", "<code class=\"docs-code\">0.25</code> — aligned with the Fresh tier boundary."],
          ["Strength load divisor", "<code class=\"docs-code\">1500</code> — converts set volume into model-scale stimulus."],
        ])}
      `)}

      ${M.docsSubsection("Exercise-admin muscle editing", `
        <p class="docs-copy">
          The exercise admin UI uses five semantic buckets instead of free-form decimals. The saved values are still
          numeric multipliers, not percentages, so the fatigue engine stays numeric while the editor becomes easier
          to review.
        </p>
        ${M.docsTable([
          ["Not involved (0.00)", "No meaningful fatigue contribution."],
          ["Stabilizer (0.08)", "Supports movement, mainly for stability."],
          ["Minor (0.20)", "Contributes slightly, not limiting."],
          ["Secondary (0.45)", "Clearly involved but not a main driver."],
          ["Strong secondary (0.65)", "Major assisting role, close to primary."],
          ["Primary (1.00)", "Main muscle driving the movement."],
        ])}
        <p class="docs-copy">
          Categories replace raw decimals because they are faster to scan, keep the editor consistent across exercises
          and activity types, and avoid false precision in a relative-load model.
        </p>
        ${M.docsTable([
          ["Primary rule", "At least one involved muscle must be Primary."],
          ["Multiple Primary", "Compound exercises may mark more than one muscle as Primary."],
          ["Legacy load", "Old decimal weights are interpreted with threshold mapping on load."],
          ["Migration", "If a legacy record has no threshold-derived Primary, the editor promotes the highest positive muscle so every existing exercise stays editable."],
        ])}
        <p class="docs-copy">
          The editor renders a live front/back body-map preview while you edit, so the distribution is visible before
          you save. Saved weights affect fatigue as per-muscle multipliers on the same scaled set load:
          <code class="docs-code">scaledSetLoad × fatigueMultiplier × muscleWeight</code>.
        </p>
        <p class="docs-copy">
          Load-time thresholds:
          <code class="docs-code">0 or missing → unchecked</code>,
          <code class="docs-code">&gt;0 to ≤0.14 → Stabilizer</code>,
          <code class="docs-code">&gt;0.14 to ≤0.32 → Minor</code>,
          <code class="docs-code">&gt;0.32 to ≤0.55 → Secondary</code>,
          <code class="docs-code">&gt;0.55 to ≤0.82 → Strong secondary</code>,
          <code class="docs-code">&gt;0.82 → Primary</code>.
        </p>
      `)}

      ${M.docsSubsection("Normalization loads", `
        <p class="docs-copy">
          Each muscle has its own 100% reference. A raw load of <code class="docs-code">1.2</code> means
          different things for upper back than for obliques.
        </p>
        ${M.docsTable([
          ["Upper back", "2.45"], ["Chest", "2.40"], ["Gluteal", "2.30"], ["Quadriceps", "2.25"],
          ["Deltoids", "2.20"], ["Hamstrings", "2.05"], ["Trapezius", "1.90"], ["Lower back", "1.85"],
          ["Abs", "1.70"], ["Triceps", "1.65"], ["Biceps", "1.55"], ["Calves", "1.55"],
          ["Adductors", "1.50"], ["Obliques", "1.45"],
        ])}
      `)}

      ${M.docsSubsection("Recovery threshold", `
        <div class="docs-formula">threshold     = normalizationLoad × 0.25
recoveryHours = halfLife × log₂(rawLoad / threshold)</div>
        ${M.docsTable([
          ["Below threshold", "Fresh — train now."],
          ["Above threshold", "Recovery hours calculated and used to group muscle into tomorrow or later."],
          ["No raw load", "No recent load — grouped as train today."],
        ])}
      `)}

      ${M.docsSubsection("Hevy workout parsing", `
        <p class="docs-copy">
          If an activity description starts with <code class="docs-code">Logged with Hevy</code>, the model parses
          exercises and sets. Resolver matching now comes from <code class="docs-code">api/exercises.php</code>, which
          indexes canonical names, aliases, and legacy substring <code class="docs-code">matchTerms</code>.
        </p>
        <div class="docs-formula">load         = weightKg × reps × effortFactor
effortFactor = 0.5 + RPE / 10
bodyweight   = estimatedBodyweightKg × 0.4
scaledLoad   = load / 1500</div>
        ${M.docsTable([
          ["RPE 6–10", "Effort factor 1.10 → 1.50 (steps of 0.10 per RPE point)."],
          ["Time sets", "Skipped — no load data in this model."],
          ["Unmatched exercise", "Zero muscle-specific stimulus; session falls back to WeightTraining mapping if no exercises match."],
        ])}
      `)}

      ${M.docsSubsection("Exercise pattern groups", `
        ${M.docsTable([
          ["bench / push-up / pushup / chest fly / pec deck / dip", "Chest primary."],
          ["incline press / incline bench / chest press", "Chest with incline emphasis."],
          ["row / face pull", "Upper back primary."],
          ["pulldown / pull-up / pullup / chin-up / chinup", "Upper back width."],
          ["deadlift / rdl / romanian deadlift", "Hamstrings and gluteal."],
          ["shoulder press / overhead press / arnold press / lateral raise / front raise / rear delt", "Deltoids primary."],
          ["curl / hammer", "Biceps primary."],
          ["triceps / pushdown / skull crusher", "Triceps primary."],
          ["plank / crunch / twist / dead bug / hollow / sit up / leg raise / russian twist", "Abs and core."],
          ["hip thrust / glute bridge", "Gluteal primary."],
          ["calf raise", "Calves primary."],
          ["squat / lunge / step up / stepup / leg press / split squat", "Quadriceps primary."],
        ])}
        <p class="docs-copy">
          Exercise mapping now lives in <code class="docs-code">private/data/exercise-configs.json</code>.
          The resolver loaded from <code class="docs-code">api/exercises.php</code> uses canonical names, aliases, and legacy substring
          <code class="docs-code">matchTerms</code> to preserve the previous matching behavior.
        </p>
        <p class="docs-copy">
          Unknown Hevy exercises and unknown non-Hevy activity types are persisted to
          <code class="docs-code">private/data/exercise-unknowns.json</code> so they are never silently ignored.
        </p>
      `)}

      ${M.docsSubsection("Non-Hevy activity types", `
        <div class="docs-formula">sportFactor    = clamp(durationMin / 60, 0.70, 1.45)
strengthFactor = clamp(durationMin / 45, 0.85, 1.60)
stimulus       = baseWeights × factor</div>
        ${M.docsTable([
          ["Run", "Quads, hamstrings, calves, gluteal."],
          ["Hike", "Quads, hamstrings, calves, gluteal — heavier than run."],
          ["Ride", "Quads dominant, plus gluteal and hamstrings."],
          ["Canoeing / Canoe", "Upper back, deltoids, trapezius, biceps, abs."],
          ["WaterSport", "Similar to canoeing, slightly less intense."],
          ["Rowing", "Full body: upper/lower back, quads, hamstrings, biceps."],
          ["Surfing", "Upper back and deltoids for paddling; abs and obliques for pop-up."],
          ["Yoga", "Abs, obliques, and light stabilizers."],
          ["Walk", "Light quads, hamstrings, calves."],
          ["WeightTraining / Workout", "Generic light full-body fallback."],
          ["Unknown type", "Zero stimulus + unresolved warning/persistence."],
        ])}
      `)}

      ${M.docsSubsection("Unresolved warning signal", `
        <p class="docs-copy">
          The fatigue view computes unresolved items from the same fixed recent window as the body map. If any recent
          exercises or activity types are unresolved, the UI shows a warning that the map may be incomplete.
        </p>
        ${M.docsTable([
          ["Scope", "Same 10-day fatigue lookback window as the map and readiness tables."],
          ["Payload", "<code class=\"docs-code\">hasUnresolved</code>, <code class=\"docs-code\">count</code>, <code class=\"docs-code\">items[]</code>, <code class=\"docs-code\">warningText</code>."],
          ["Purpose", "Make missing mappings visible without changing current fatigue load math or fallback behavior."],
        ])}
      `)}
    `,
  });
}());
