# Fatigue Model V2, Phase 1

## Purpose

This document defines a practical, user-friendly Phase 1 upgrade for the fatigue model.

The goal is to improve model quality in the places where the current version is weakest, without turning the system into a science project and without asking users to track lots of extra inputs.

This is explicitly **not** a full redesign. It is a focused upgrade that should make the model more believable and more useful in real-world use.

---

## Product goals

The Phase 1 model should answer these three questions better than the current model:

1. **How hard was this session for this person?**
2. **How much of that fatigue is local muscle fatigue versus whole-body/systemic fatigue?**
3. **How long will it likely take until a muscle is meaningfully ready again?**

The system should remain easy to use.

### User-input constraints

Do **not** require users to configure lots of extra things.

Assume:
- many users do **not** track RPE consistently
- experience level already exists
- a personal fallback RPE already exists
- if neither is available, use the general fallback RPE of `7.5`

The design should prefer:
- good defaults
- automatic inference
- conservative heuristics
- limited, explainable config

---

## Current model limitations this phase is meant to solve

### 1. Absolute load is too dominant
The current model uses weight, reps, and effort, but it still leans too hard on absolute load.

That means:
- `10 kg` can be maximal for one person and trivial for another
- strong users are often over-penalized just because they move bigger numbers
- weaker or newer users can be under-modeled even when the session was hard for them

### 2. Local muscle fatigue is doing too much work
The current model mainly distributes fatigue across muscles.

That misses an important distinction:
- some sessions are locally fatiguing
- some sessions are also globally exhausting

Examples:
- heavy squats
- heavy hinges
- full-body compounds
- brutal rows or conditioning-like sessions

These create a kind of whole-body fatigue that should not be represented only through muscle weights.

### 3. Recovery is too static
Base half-lives by muscle are acceptable as a starting point, but the current approach is too rigid.

A light quad session and a brutal quad session should not recover at exactly the same speed.

---

## Phase 1 scope

Implement exactly these three upgrades:

1. **Relative strength stimulus**
2. **Separate systemic fatigue channel**
3. **Severity-adjusted recovery**

Do **not** expand this phase into broader personalization, adaptive learning, or large UI changes.

Those can come later.

---

# Conceptual model

## High-level pipeline

For each activity or set:

`activity/set -> relative stimulus -> split into local + systemic -> distribute local across muscles -> decay both channels -> readiness output`

This is the core conceptual change.

The model should no longer behave as if all fatigue is purely muscle-local.

---

# 1. Relative strength stimulus

## Goal

Estimate how fatiguing a strength set was **for this user**, not just in absolute kilograms.

## Design principle

Do not throw away the existing load formula.

Instead, keep it and add a **relative intensity adjustment** on top.

That makes the upgrade safer and easier to ship.

## Per-set formula

```text
baseLoad = effectiveLoadKg × reps

effortFactor = 0.5 + effectiveRPE / 10

relativeFactor = clamp(
  effectiveLoadKg / personalReferenceLoadKg(exercise, repRange),
  0.55,
  1.35
)

setStimulus = (baseLoad / strengthLoadDivisor) × effortFactor × relativeFactor
```

## Meaning of each term

### `effectiveLoadKg`
The working load used for the set.

Use the existing logic where possible.

For bodyweight-based exercises, continue using the existing fallback logic for now, but allow this area to improve later.

### `effectiveRPE`
Use this fallback chain:

1. actual set/session RPE if available
2. user-specific fallback RPE if configured
3. general fallback RPE = `7.5`

This keeps the model robust even when RPE is missing.

### `strengthLoadDivisor`
Keep the existing divisor concept unless inspection of the current code shows a compelling reason to rename or slightly retune it.

Do not use this phase to rework normalization across the entire model.

### `relativeFactor`
This is the key upgrade.

It adjusts the stimulus based on whether the current load is light, normal, or heavy **for this person**.

It should be conservative, not explosive.

The clamp is important.

Recommended starting clamp:
- minimum: `0.55`
- maximum: `1.35`

This prevents weird history or missing data from causing massive distortions.

---

## How to calculate `personalReferenceLoadKg(exercise, repRange)`

The model should use a fallback chain.

Do **not** ask the user to enter this manually.

### Fallback chain

#### Tier 1: exercise-specific history
If the user has enough history for the same exercise and a similar rep range:
- use a rolling median working weight
- only consider working sets, not obvious warmups if that distinction already exists or can be inferred safely

#### Tier 2: exercise-family history
If exercise-specific history is too sparse:
- use a reference from similar exercises in the same family

Examples:
- flat bench and incline bench -> horizontal press family
- pulldown and pull-up variants -> vertical pull family
- squat variants -> squat family
- RDL and hip hinge variants -> hinge family

#### Tier 3: bodyweight + experience heuristic
If there is not enough history:
- use a heuristic based on exercise family, bodyweight, and experience level

Recommended formula:

```text
strengthScaleByExperience = {
  beginner: 0.85,
  novice: 1.00,
  intermediate: 1.15,
  advanced: 1.30
}

bodyweightAdjustment = clamp(userBodyweightKg / 75, 0.80, 1.25)

personalReferenceLoadKg = baseExerciseReferenceKg × strengthScaleByExperience × bodyweightAdjustment
```

### Notes
- `baseExerciseReferenceKg` should come from exercise-family defaults, not user input
- keep these defaults in config, not scattered in code
- this is a heuristic, not a physics model
- the point is to improve the model materially without requiring a profile setup wizard

---

## Why this is a safe improvement

This does **not** replace absolute load.

It adds a bounded personalization layer on top of the existing logic.

That makes it:
- understandable
- controllable
- less likely to break everything at once

---

# 2. Separate systemic fatigue channel

## Goal

Model the difference between:
- local fatigue in specific muscles
- global fatigue from demanding sessions

This replaces the need for a vague global tuning knob like `fatigueMultiplier`.

## Core idea

Each exercise or set produces two outputs:
- `localStimulus`
- `systemicStimulus`

## Formula

```text
localStimulus = setStimulus × localShare
systemicStimulus = setStimulus × systemicShare
```

Where:

```text
localShare + systemicShare = 1
```

## Implementation principle

Do **not** ask users to set this.

Infer it from an internal **exercise family / fatigue archetype**.

---

## Recommended exercise fatigue archetypes

Use a small internal classification layer.

This should be admin/config-driven, not user-facing.

Recommended starting archetypes:
- `isolation`
- `machine_compound`
- `freeweight_compound`
- `hinge_squat`
- `conditioning_hybrid`

## Recommended default shares

```text
isolation:           local 0.90, systemic 0.10
machine_compound:    local 0.80, systemic 0.20
freeweight_compound: local 0.70, systemic 0.30
hinge_squat:         local 0.60, systemic 0.40
conditioning_hybrid: local 0.55, systemic 0.45
```

These are deliberately conservative.

They should improve realism without making the model unstable.

## Examples

### Lateral raise
- archetype: `isolation`
- most fatigue stays local
- small systemic effect

### Machine chest press
- archetype: `machine_compound`
- mostly local, some systemic

### Barbell row
- archetype: `freeweight_compound`
- meaningful local fatigue plus real systemic cost

### Heavy squat or deadlift variation
- archetype: `hinge_squat`
- large local fatigue plus strong systemic impact

---

## How local stimulus is used

The existing muscle involvement weights already exist and are already implemented.

Do **not** redesign them in this phase.

Local stimulus should continue through the existing muscle distribution logic:

```text
finalLocalStimulusForMuscle = localStimulus × muscleWeight
```

## How systemic stimulus is used

Systemic stimulus should feed into a separate whole-body fatigue pool.

This pool should:
- accumulate over time
- decay over time
- influence readiness output as a secondary modifier

It should **not** replace local muscle fatigue.

---

# 3. Severity-adjusted recovery

## Goal

Keep the existing base half-life concept, but make hard sessions last longer than light sessions.

## Design principle

Do not destroy the current model shape.

Keep:
- per-muscle base half-lives
- exponential decay

Add:
- a modest severity adjustment

## Formula for local fatigue

For each muscle:

```text
muscleSeverity = rawLocalLoad / normalizationLoad

adjustedHalfLife = baseHalfLife × clamp(
  0.85 + 0.35 × muscleSeverity,
  0.85,
  1.35
)

remainingLocal = stimulus × 0.5^(hoursElapsed / adjustedHalfLife)
```

## Meaning

- light loads recover a bit faster than base
- hard loads recover a bit slower than base
- the adjustment remains bounded

This keeps the system intuitive and avoids huge swings.

---

## Formula for systemic fatigue

Use a dedicated systemic half-life.

Recommended starting value:
- `systemicBaseHalfLife = 24h`

Then apply a similar bounded severity adjustment:

```text
systemicSeverity = rawSystemicLoad / systemicNormalizationLoad

adjustedSystemicHalfLife = systemicBaseHalfLife × clamp(
  0.90 + 0.40 × systemicSeverity,
  0.90,
  1.50
)

remainingSystemic = systemicStimulus × 0.5^(hoursElapsed / adjustedSystemicHalfLife)
```

## Why systemic half-life is shorter

Systemic fatigue should usually clear faster than deep local muscular fatigue, but not instantly.

A separate half-life makes that difference explicit and explainable.

---

# Readiness output

## Design principle

Local muscle fatigue remains the main signal.

Systemic fatigue acts as a secondary penalty, not the dominant driver.

## Recommended formula

```text
finalMuscleScore = clamp(localMuscleScore + systemicPenalty, 0, 100)

systemicPenalty = systemicScore × 0.25
```

## Meaning

This says:
- muscle readiness is mostly about local fatigue
- but if the whole organism is still tired, readiness should be somewhat reduced

This is intentionally modest.

Do not let systemic fatigue overwhelm the whole model in Phase 1.

---

# Defaults to actually ship

These should be the starting defaults unless inspection of the existing code/data strongly suggests a nearby adjustment.

## Effort fallback

```text
effectiveRPE = actual RPE if present
             else user fallback RPE if present
             else 7.5
```

## Relative factor clamp

```text
min = 0.55
max = 1.35
```

## Exercise archetype shares

```text
isolation:           local 0.90 / systemic 0.10
machine_compound:    local 0.80 / systemic 0.20
freeweight_compound: local 0.70 / systemic 0.30
hinge_squat:         local 0.60 / systemic 0.40
conditioning_hybrid: local 0.55 / systemic 0.45
```

## Systemic defaults

```text
systemicBaseHalfLife = 24h
systemicPenaltyWeight = 0.25
```

## Severity adjustment clamps

### Local
```text
0.85 to 1.35
```

### Systemic
```text
0.90 to 1.50
```

These values are intentionally conservative and should improve realism without creating chaos.

---

# What not to do in Phase 1

Do **not** do these things in this phase:

- do not add more user profile setup burden
- do not ask users to configure per-exercise fatigue knobs
- do not revive or replace `fatigueMultiplier` with another vague global override
- do not redesign muscle involvement editing
- do not rebuild normalization across the whole model yet
- do not attempt a biomechanics simulator
- do not expose lots of advanced config in the main UI

This phase should feel like a major quality improvement with minimal user friction.

---

# Implementation path

This is the recommended engineering sequence.

## Step 1: inspect current code and integration points

Identify:
- where the current strength set stimulus is calculated
- where exercise configs live
- whether exercise family or archetype tags already exist
- where muscle distribution currently happens
- where decay and readiness scoring currently happen
- how non-Hevy activities currently enter the pipeline

Do not start coding until this is clear.

---

## Step 2: add exercise fatigue archetype classification

Add a compact internal classification layer for exercises.

This should be config-driven if possible.

Examples:
- lateral raise -> `isolation`
- chest press machine -> `machine_compound`
- barbell bench / row -> `freeweight_compound`
- squat / deadlift / RDL -> `hinge_squat`
- hybrid circuit-like movements if relevant -> `conditioning_hybrid`

This classification is **not** a user-facing setting.

---

## Step 3: implement `personalReferenceLoadKg(exercise, repRange)`

Create one helper that resolves reference load through the fallback chain:

1. exercise history
2. family history
3. experience + bodyweight heuristic

Keep this logic centralized.

Do not duplicate it across modules.

---

## Step 4: upgrade strength stimulus calculation

Replace the current pure base stimulus logic with:

```text
setStimulus = (baseLoad / strengthLoadDivisor) × effortFactor × relativeFactor
```

Do this only for strength-style activities/sets.

Do not try to force this formula onto all non-strength activities.

---

## Step 5: split strength stimulus into local and systemic

Using archetype defaults:

```text
localStimulus = setStimulus × localShare
systemicStimulus = setStimulus × systemicShare
```

Then:
- feed local stimulus into existing muscle distribution
- feed systemic stimulus into a new systemic fatigue accumulator

---

## Step 6: implement systemic fatigue pool

Add a parallel fatigue accumulator for systemic fatigue.

This should:
- accumulate from all relevant activities
- decay with its own half-life
- produce a `systemicScore`
- remain clearly separated from muscle-local fatigue internally

---

## Step 7: add severity-adjusted decay

Keep current base half-lives.

Modify them with the bounded severity formula.

Apply this:
- per muscle for local fatigue
- once for the systemic pool

---

## Step 8: combine local and systemic into final readiness

Use the modest systemic penalty approach:

```text
finalMuscleScore = clamp(localMuscleScore + systemicPenalty, 0, 100)
```

Do not let systemic become dominant in Phase 1.

---

## Step 9: UI output

Keep the UI simple.

Recommended output changes:
- keep the existing muscle fatigue map as the main view
- optionally add a small systemic fatigue indicator such as:
  - Fresh
  - Moderate
  - High

Do not add a lot of explanation or new control surfaces in Phase 1.

---

# Suggested config additions

The exact code shape depends on the repo, but conceptually the model likely needs:

## Global config

```text
DEFAULT_RPE = 7.5
RELATIVE_FACTOR_MIN = 0.55
RELATIVE_FACTOR_MAX = 1.35
SYSTEMIC_BASE_HALF_LIFE_HOURS = 24
SYSTEMIC_PENALTY_WEIGHT = 0.25
LOCAL_HALF_LIFE_SCALE_MIN = 0.85
LOCAL_HALF_LIFE_SCALE_MAX = 1.35
SYSTEMIC_HALF_LIFE_SCALE_MIN = 0.90
SYSTEMIC_HALF_LIFE_SCALE_MAX = 1.50
```

## Experience-level strength scale

```text
beginner = 0.85
novice = 1.00
intermediate = 1.15
advanced = 1.30
```

## Exercise archetype defaults

```text
isolation           = { localShare: 0.90, systemicShare: 0.10 }
machine_compound    = { localShare: 0.80, systemicShare: 0.20 }
freeweight_compound = { localShare: 0.70, systemicShare: 0.30 }
hinge_squat         = { localShare: 0.60, systemicShare: 0.40 }
conditioning_hybrid = { localShare: 0.55, systemicShare: 0.45 }
```

## Exercise-family reference defaults

These should exist in one config location and be inspectable/editable by admins if needed.

Do **not** hardcode these all over the codebase.

---

# Validation and safety principles

## 1. Keep everything bounded
Every new modifier should have clamps.

This is non-negotiable.

## 2. Prefer conservative under-adjustment over dramatic correction
A subtle but stable improvement is better than a theoretically clever but erratic system.

## 3. Preserve explainability
Every core value should be explainable in plain language.

If a parameter cannot be explained clearly, it probably should not exist.

## 4. Preserve low user burden
Do not improve the model by making the user do a lot more admin.

That would make the product worse.

---

# Acceptance criteria

The Phase 1 model is done when all of the following are true:

1. Strength stimulus is adjusted by a bounded personal-relative factor
2. The system uses a clear fallback chain for personal reference load
3. The model adds a separate systemic fatigue channel
4. Systemic fatigue is driven by exercise archetype defaults, not user micromanagement
5. Existing muscle involvement weights still work for local stimulus distribution
6. Recovery speed now depends modestly on session severity
7. Final readiness remains mostly driven by local fatigue, with systemic as a secondary penalty
8. The model still works when RPE is missing
9. The system does not require new setup burden for normal users
10. The implementation is explainable and maintainable
11. `fatigueMultiplier` is not needed and should not be reintroduced in disguised form

---

# Deliverables expected from Codex

At the end of implementation, provide:

1. a short summary of what changed
2. the exact files changed
3. any config or migration notes
4. any assumptions made
5. a short note on what should be Phase 2, but do not implement it here

---

# Final instruction to Codex

Implement this as a focused Phase 1 model upgrade.

Do not drift into unrelated refactors.
Do not overcomplicate the UI.
Do not ask for extra user input unless absolutely necessary.
Make the smallest coherent set of changes that meaningfully improves model quality while keeping the system stable and understandable.
