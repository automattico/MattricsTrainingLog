# Fatigue Model

The muscle fatigue system uses a **decay-based load accumulation model**. Every activity adds a per-muscle stimulus. That stimulus fades exponentially over time based on each muscle's half-life. The fatigue score represents how much accumulated load remains relative to a calibrated "fully loaded" reference.

**Implemented in:** `public/assets/js/core/fatigue-engine.js`
**Configuration:** `public/assets/js/core/constants.js` → `MUSCLE_FATIGUE_CONFIG`
**Tier classification:** `public/assets/js/core/fatigue-tiers.js`

---

## Fatigue Score (0–100)

Looks back **10 days** from now. Each activity contributes a per-muscle stimulus. Remaining load after exponential decay is summed, then divided by a normalization load to produce a 0–100 score.

```
score    = rawLoad / normalizationLoad × 100
rawLoad  = Σ ( stimulus × 0.5^(hoursAgo / halfLife) )
```

| Parameter | Value |
|---|---|
| Lookback window | 10 days rolling |
| Score cap | 100 |
| Activity timestamp | Normalised to noon on the activity date |

---

## Half-Lives Per Muscle

After one half-life, 50% of the stimulus remains. After two, 25%.

| Half-life | Muscles |
|---|---|
| 72 h | Chest, upper back, lower back, quadriceps, hamstrings, gluteal, adductors |
| 60 h | Deltoids, trapezius, calves |
| 48 h | Triceps, biceps, abs, obliques |

---

## Recovery Threshold

Recovery is considered complete when `rawLoad ≤ threshold`. Below that, the muscle is **Fresh**.

```
threshold     = normalizationLoad × 0.25
recoveryHours = halfLife × log₂(rawLoad / threshold)
                (only calculated when rawLoad > threshold)
```

| Setting | Value |
|---|---|
| Threshold ratio | 0.25 — aligns with Fresh tier boundary |

---

## Fatigue Tiers

| Tier | Score range | Body map state | Readiness bucket |
|---|---|---|---|
| No recent load | n/a (rawLoad = 0) | `none` | Train today |
| Fresh | 0–24% | `fresh` | Train today |
| Recovering | 25–49% | `recovering` | Train tomorrow |
| Fatigued | 50–74% | `fatigued` | Needs more recovery |
| Highly fatigued | 75–100% | `high` | Needs more recovery |

---

## Normalization Loads

The per-muscle "fully loaded" reference values (used to scale rawLoad to a 0–100 score):

| Muscle | Normalization load |
|---|---|
| Upper back | 2.45 |
| Chest | 2.40 |
| Gluteal | 2.30 |
| Quadriceps | 2.25 |
| Deltoids | 2.20 |
| Hamstrings | 2.05 |
| Lower back | 1.85 |
| Abs | 1.70 |
| Trapezius | 1.90 |
| Triceps | 1.65 |
| Biceps | 1.55 |
| Calves | 1.55 |
| Adductors | 1.50 |
| Obliques | 1.45 |

---

## Exercise-Admin Muscle Editing

The exercise admin UI no longer asks for free-form decimals. It uses five semantic buckets that still save as numeric multipliers, not percentages, so the fatigue engine keeps working on numbers while the editor stays readable.

| UI category | Saved multiplier | Meaning |
|---|---|---|
| Not involved | 0.00 | No meaningful fatigue contribution |
| Stabilizer | 0.08 | Supports movement, mainly for stability |
| Minor | 0.20 | Contributes slightly, not limiting |
| Secondary | 0.45 | Clearly involved but not a main driver |
| Strong secondary | 0.65 | Major assisting role, close to primary |
| Primary | 1.00 | Main muscle driving the movement |

Why categories replace raw decimals:
- They make review faster than comparing arbitrary decimals.
- They keep the admin UI consistent across exercises and activity types.
- They avoid false precision in a model that is really about relative load contribution.

Editing rules:
- At least one involved muscle must be marked Primary.
- Multiple muscles may be Primary in the same exercise.
- Legacy decimal weights are mapped on load with these thresholds:
  `0 or missing → unchecked`, `>0 to <=0.14 → Stabilizer`, `>0.14 to <=0.32 → Minor`,
  `>0.32 to <=0.55 → Secondary`, `>0.55 to <=0.82 → Strong secondary`, `>0.82 → Primary`.
- If an older exercise has positive weights but none cross the Primary threshold, the highest positive muscle is promoted to Primary in the editor so every existing exercise remains editable without manual cleanup.
- All current exercises are represented in the new model after that load-time mapping.

Preview and scoring:
- The editor shows a live front/back body-map preview that updates as weights change.
- Saved numeric weights affect fatigue as per-muscle multipliers on the same scaled set load.
- Final per-muscle stimulus is `scaledSetLoad × fatigueMultiplier × muscleWeight`.

---

## Hevy Workout Parsing

Triggered when `activity.Description` starts with `"Logged with Hevy"`.

### Set Load Formula

```
load         = weight(kg) × reps × effortFactor
effortFactor = 0.5 + RPE/10     (default RPE = 7 → effortFactor = 1.20)
bodyweight   = 75 kg × 0.4      (when no weight is logged)
scaledLoad   = load / 1500      (unit divisor)
```

RPE → effort factor: 6→1.10 · 7→1.20 · 8→1.30 · 9→1.40 · 10→1.50

Time-based sets (e.g. "3 min", "45 sec") are skipped — no load calculated.

### Exercise Pattern Matching

Exercise name is normalized and resolved from `api/exercises.php`, using canonical names, aliases, then legacy substring `matchTerms`. The seed data preserves the previous matching groups:

| Pattern keywords | Primary muscle |
|---|---|
| bench / push-up / pushup / chest fly / pec deck / dip | Chest |
| incline press / incline bench / chest press | Chest (incline angle) |
| row / face pull | Upper back |
| pulldown / pull-up / pullup / chin-up / chinup | Upper back (width) |
| deadlift / rdl / romanian deadlift | Hamstrings + glutes |
| shoulder press / overhead press / arnold press / lateral raise / front raise / rear delt | Deltoids |
| curl / hammer | Biceps |
| triceps / pushdown / skull crusher | Triceps |
| plank / crunch / twist / dead bug / hollow / sit up / leg raise / russian twist | Abs |
| hip thrust / glute bridge | Gluteal |
| calf raise | Calves |
| squat / lunge / step up / stepup / leg press / split squat | Quadriceps |

**To add a new exercise:** edit `private/data/exercise-configs.json`. The resolver loaded from `api/exercises.php` now owns canonical names, aliases, and legacy substring `matchTerms`.

**Unmatched exercises** contribute zero muscle-specific stimulus. If *no* exercise in the session matches, the whole session falls back to the generic `WeightTraining` type mapping.

Unknown Hevy exercises and unknown non-Hevy activity types are also persisted to `private/data/exercise-unknowns.json` via `api/exercises.php` so they are never silently ignored.

### Unresolved Warning Signal

The fatigue view now adds an unresolved warning block whenever the fixed fatigue lookback window contains exercises or activity types that the resolver could not classify.

Warning payload:

```json
{
  "unresolved": {
    "hasUnresolved": true,
    "count": 2,
    "items": [
      {
        "id": "exercise:mystery curl",
        "sourceType": "exercise",
        "normalizedName": "mystery curl",
        "rawNames": ["Mystery Curl", "mystery-curl"],
        "timesSeen": 2
      }
    ],
    "warningText": "Some recent exercises or activity types are unresolved, so the fatigue map may be incomplete: Mystery Curl, ..."
  }
}
```

This warning is computed from the same fixed recent window used by the fatigue map, so older unresolved items do not keep the current readiness view in a warning state.

---

## Activity Type Mappings (Non-Hevy)

If not a Hevy workout, the activity `Type` field resolves through `private/data/activity-type-configs.json`. Duration scales the configured stimulus:

```
sportFactor    = clamp(min / 60,  0.70, 1.45)   caps at 87 min+
strengthFactor = clamp(min / 45,  0.85, 1.60)   caps at 72 min+
stimulus       = baseWeights × factor
```

| Type | Primary muscles | Factor |
|---|---|---|
| Run | Quads, hamstrings, calves, glutes | sport |
| Hike | Quads, hamstrings, calves, glutes (heavier than run) | sport |
| Ride | Quads dominant, glutes, hamstrings | sport |
| Canoeing / Canoe | Upper back, deltoids, trapezius, biceps, abs | sport |
| WaterSport | Same as canoeing, slightly less intense | sport |
| Rowing | Full body — upper/lower back, quads, hamstrings, biceps | sport |
| Surfing | Upper back + deltoids (paddle), abs + obliques (pop-up) | sport |
| Yoga | Abs, obliques, light stabilisers | sport |
| Walk | Light quads, hamstrings, calves | sport |
| WeightTraining / Workout | Generic full-body fallback (all muscles, light) | strength |
| Unrecognised type | Zero stimulus + unresolved warning/persistence | — |
