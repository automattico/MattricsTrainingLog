# Exercise Config Admin Feature

## Summary
Replace hardcoded exercise and activity mappings with a persistent, self-extending configuration system.

Unknown exercises or activity types are detected, can be enriched via AI on demand, and are immediately usable once a valid config exists. All changes trigger recalculation so the fatigue map is always up to date.

---

## Core Principles
- no silent failures
- immediate usability over perfect classification
- manual control over AI usage
- single-user workflow
- recalculation on every change

---

## Scope

### Included
- persistent exercise + activity config system
- resolver replacing hardcoded mappings
- unknown detection (Hevy + non-Hevy)
- manual AI suggestion trigger (✨Generate suggestion)
- optional external dataset lookup
- saved configs used immediately
- review queue + edit UI
- alias management
- delete functionality
- automatic fatigue recalculation
- AI request logging

### Not included
- multi-user roles
- approval workflows between users
- changes to fatigue model logic

---

## System Architecture

### Current
exercise → substring mapping → fatigue engine

### Target
exercise/activity → resolver → config → fatigue engine

Resolver becomes the single source of truth.

---

## Data Model

### ExerciseConfig
- id
- canonicalName
- normalizedName
- source: manual | ai_suggested | external_dataset | merged
- aliases[]
- muscleWeights
- fatigueMultiplier
- bodyweightEligible
- setTypeHandling
- lastUpdatedAt
- lastUpdatedType: ai | manual

### UnknownExercise
- id
- normalizedName
- rawNames[]
- timesSeen
- firstSeenAt
- lastSeenAt
- aiStatus

---

## Matching Logic

### Normalization
- lowercase
- trim whitespace
- collapse spaces
- normalize punctuation
- split camelCase into words

### Resolution Order
1. canonical match
2. alias match
3. external dataset lookup
4. AI suggestion (manual trigger only)
5. unresolved

---

## Unknown Handling Flow

On detection:
- normalize name
- persist/update unknown
- show in review queue

Important:
- AI is NOT triggered automatically
- user must explicitly trigger suggestion

---

## AI Integration

### Trigger
Manual only via button:

✨Generate suggestion

### Flow
- user clicks button
- backend builds prompt
- OpenAI GPT-5.4 mini called
- response validated
- suggestion returned as a preview
- user saves the preview to create or update the active config
- fatigue recalculated

### Cost
~$0.02–0.20/month → negligible

### Output Schema
```json
{
  "canonicalName": string,
  "aliases": string[],
  "muscleWeights": { [muscle]: number },
  "fatigueMultiplier": number,
  "bodyweightEligible": boolean,
  "setTypeHandling": "weight_reps" | "bodyweight_reps" | "time_based_ignore",
  "confidence": number,
  "shortReason": string
}
```

---

## External Dataset

Lookup before AI:

config → dataset → AI

If match found:
- return an external-dataset preview
- user saves the preview to create an active config
- recalc immediately

Dataset is assistive, not authoritative.

---

## UI

### Review Queue
Shows:
- unknown exercises

Actions:
- review
- edit
- merge as alias
- ✨Generate suggestion
- delete

---

### Editor
Editable fields:
- canonical name
- aliases
- muscle weights
- fatigueMultiplier

---

### Tooltips (Required)
Explain:
- muscleWeights (relative distribution)
- scaled load meaning (how stimulus accumulates)
- fatigueMultiplier (intensity adjustment)
- setTypeHandling (how load is interpreted)

---

## Deletion

Every exercise must be deletable.

Confirmation dialog:
"Are you sure you want to permanently delete this exercise? Once deleted it cannot be recovered!"

### Effects
- removed from config
- fatigue recalculated
- may reappear as unknown if data still exists

---

## Fatigue Integration

- saved configs are used immediately
- unresolved exercises trigger warning
- no silent gaps allowed

---

## Recalculation

Triggered on:
- save after AI suggestion
- edit
- merge
- delete

Behavior:
- recompute from raw data
- no stale derived state

---

## API

- GET /api/exercises
- POST /api/exercises
- PATCH /api/exercises/{id}
- DELETE /api/exercises/{id}
- POST /api/exercises/{id}/suggest
- POST /api/exercises/{id}/merge-alias
- POST /api/exercises/unknowns/{id}/suggest
- POST /api/exercises/unknowns/{id}/merge-alias

---

## AI Logging

Store:
- timestamp
- raw name
- normalized name
- sourceType
- model
- prompt version
- result status
- configCreated

---

## Acceptance Criteria

- unknown exercises are never silently ignored
- manual AI trigger works
- AI preview can be saved into an active config
- unresolved review queue is visible
- recalculation always triggers
- delete works and updates fatigue
- dataset lookup works before AI
- UI supports full workflow end-to-end

---

## End State

- self-extending mapping system
- no hidden errors
- minimal manual effort
- always accurate fatigue map
