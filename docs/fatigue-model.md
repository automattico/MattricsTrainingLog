# Fatigue Model

The muscle fatigue system uses a **decay-based load accumulation model**. Activities add local per-muscle stimulus and may also add a separate systemic fatigue stimulus. Both channels decay over time. Final muscle readiness is mostly local fatigue, with a modest systemic penalty.

**Implemented in:** `public/assets/js/core/fatigue-engine.js`
**Configuration:** `public/assets/js/core/constants.js` → `MUSCLE_FATIGUE_CONFIG`
**Tier classification:** `public/assets/js/core/fatigue-tiers.js`

---

## Fatigue Score (0–100)

Looks back **10 days** from now. Each activity contributes local per-muscle stimulus. Remaining local load after exponential decay is summed, divided by a normalization load, and then adjusted by a small systemic penalty.

```
localScore       = rawLocalLoad / normalizationLoad × 100
systemicPenalty  = systemicScore × 0.25
finalMuscleScore = clamp(localScore + systemicPenalty, 0, 100)
rawLocalLoad     = Σ ( localStimulus × 0.5^(hoursAgo / adjustedHalfLife) )
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
| 72 h | Chest, upper back |
| 60 h | Deltoids, trapezius, calves |
| 48 h | Triceps, biceps, abs, obliques, gluteal, quadriceps, hamstrings |

---

## Recovery Thresholds

The model now keeps two separate thresholds:
- `trainableThreshold`: used for the board's `When` ETA
- `freshThreshold`: used for "fully fresh" timing

```
trainableThreshold = normalizationLoad × 0.50
freshThreshold     = normalizationLoad × 0.25
```

| Setting | Value |
|---|---|
| Trainable threshold ratio | 0.50 — used for the readiness ETA |
| Fresh threshold ratio | 0.25 — aligns with the Fresh tier boundary |

---

## Fatigue Tiers

| Tier | Score range | Body map state | Readiness bucket |
|---|---|---|---|
| No recent load | n/a (rawLocalLoad = 0 and no systemic penalty) | `none` | Train today |
| Fresh | 0–24% | `fresh` | Train today |
| Recovering | 25–49% | `recovering` | Train today / soon |
| Fatigued | 50–74% | `fatigued` | Needs more recovery |
| Highly fatigued | 75–100% | `high` | Needs more recovery |

---

## Normalization Loads

The per-muscle "fully loaded" reference values (used to scale rawLoad to a 0–100 score):

| Muscle | Normalization load |
|---|---|
| Chest | 3.50 |
| Upper back | 3.50 |
| Gluteal | 7.50 |
| Quadriceps | 5.50 |
| Deltoids | 2.80 |
| Hamstrings | 4.00 |
| Lower back | 2.50 |
| Abs | 5.00 |
| Trapezius | 2.30 |
| Triceps | 2.50 |
| Biceps | 2.50 |
| Calves | 1.55 |
| Adductors | 3.20 |
| Obliques | 3.50 |

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
- Exercises with `fatigueImpact: "none"` are resolved but excluded from local and systemic fatigue. They may have empty or all-zero `muscleWeights`.
- Multiple muscles may be Primary in the same exercise.
- Legacy decimal weights are mapped on load with these thresholds:
  `0 or missing → unchecked`, `>0 to <=0.14 → Stabilizer`, `>0.14 to <=0.32 → Minor`,
  `>0.32 to <=0.55 → Secondary`, `>0.55 to <=0.82 → Strong secondary`, `>0.82 → Primary`.
- If an older exercise has positive weights but none cross the Primary threshold, the highest positive muscle is promoted to Primary in the editor so every existing exercise remains editable without manual cleanup.
- All current exercises are represented in the new model after that load-time mapping.

Preview and scoring:
- The editor shows a live front/back body-map preview that updates as weights change.
- Saved numeric weights affect local fatigue as per-muscle multipliers on the local share of parsed set stimulus.
- Total stimulus comes from parsed set load or fallback duration/set-count logic, then fatigue archetype shares split it into local and systemic channels.
- `fatigueImpact: "none"` is intended for stretching, mobility, Yin Yoga, or other recovery work that should clear unknown warnings without adding fake fatigue.

---

## Hevy Workout Parsing

Triggered when the first non-empty line of `activity.Description` contains a standalone Hevy brand term (case-insensitive), including `Hevy`, `HevyApp`, `Hevy App`, or `hevyapp.com`. The entire identifying line is removed before exercises and sets are parsed. A Hevy mention on a later line does not trigger parsing.

When canonical mode is active and the current activity has canonical child rows, the fatigue engine does **not** treat the rebuilt display strings as the source of truth. It consumes canonical `activityChildren[].exercises[].sets[]` fields first:

- `parsedKind: "parsed"` uses typed `weightKg`, `reps`, and `effortFactor`
- `parsedKind: "time"` uses typed `durationMinutes` and `distanceKm`
- `parsedKind: "unknown"` or missing typed numeric fields mark the block as ambiguous and fall back to the existing duration-plus-set-count heuristic

The rebuilt set text remains for UI display and detail views only. Description parsing stays in place strictly for legacy rows or canonical activities that do not have child rows.

### Set Load Formula

```
baseLoad       = weight(kg) × reps
effortFactor   = 0.5 + RPE/10     (default RPE = 7.5 unless user fallback is configured)
relativeFactor = clamp(weightKg / personalReferenceLoadKg, 0.55, 1.35)
setStimulus    = (baseLoad / 1500) × effortFactor × relativeFactor
```

RPE → effort factor: 6→1.10 · 7→1.20 · 8→1.30 · 9→1.40 · 10→1.50

`personalReferenceLoadKg` uses same-exercise history, then same-family history, then a bodyweight and experience heuristic. Experience uses the existing settings only: Beginner, Intermediate, Advanced. Missing or invalid experience falls back to neutral scale `1.00`.

Parsed set stimulus is split by internal fatigue archetype:

| Archetype | Local | Systemic |
|---|---:|---:|
| isolation | 0.90 | 0.10 |
| machine_compound | 0.80 | 0.20 |
| freeweight_compound | 0.70 | 0.30 |
| hinge_squat | 0.60 | 0.40 |
| conditioning_hybrid | 0.55 | 0.45 |

Time-based sets (e.g. "3 min", "45 sec") are skipped by default. Exercises with `setTypeHandling: "time_duration"` use the logged set minutes as a light duration-scaled stimulus, which is useful for cycling/spinning warmups and cooldowns.

Canonical child-row precedence:

- direct Hevy-import activities and bootstrapped legacy Hevy activities both flow through the same canonical child-row structure once selected by the compatibility API
- direct Hevy rows still win over matching legacy snapshot rows before the fatigue engine sees them
- if the selected activity has canonical child rows, those typed child rows beat any conflicting or stale `Description` text on the activity
- if no child rows are available, the existing description parser remains the fallback

### Exercise Pattern Matching

Exercise name is normalized and resolved from `api/exercises.php`, using canonical names, aliases, then legacy substring `matchTerms`. The seed data preserves the previous matching groups:

| Pattern keywords | Primary muscle |
|---|---|
| bench / push-up / pushup / chest fly / pec deck / dip | Chest |
| incline press / incline bench / chest press | Chest (incline angle) |
| row / face pull | Upper back |
| pulldown / pull-up / pullup / chin-up / chinup | Upper back (width) |
| deadlift / rdl / romanian deadlift | Hamstrings + glutes |
| shoulder press / overhead press / arnold press | Deltoids |
| lateral raise / single arm lateral raise | Deltoids isolation |
| external shoulder rotation / cable external rotation | Light rotator cuff / delt support |
| curl / hammer | Biceps |
| triceps / pushdown / skull crusher | Triceps |
| plank / crunch / twist / dead bug / hollow / sit up / leg raise / russian twist | Abs |
| hip thrust / glute bridge | Gluteal |
| calf raise | Calves |
| squat / lunge / step up / stepup / leg press / split squat | Quadriceps |

**To add a new exercise:** edit `private/data/exercise-configs.json`. The resolver loaded from `api/exercises.php` now owns canonical names, aliases, and legacy substring `matchTerms`.

**Unmatched exercises** contribute zero muscle-specific stimulus. If *no* exercise in the session matches, the whole session falls back to the generic `WeightTraining` type mapping.

**Configured no-fatigue exercises** are different from unknowns: they resolve normally, clear warning state, and intentionally add zero fatigue.

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
| Padel / Pádel / Padel Tennis / Pádel Tennis | Quads, calves, obliques, hamstrings, glutes, adductors, deltoids, abs, triceps, upper back, biceps, trapezius, lower back | sport |
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
