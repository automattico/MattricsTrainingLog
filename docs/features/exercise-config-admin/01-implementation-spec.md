# Exercise Config Admin – Implementation Spec

## Goal
Provide a concrete, implementation-ready specification for:
- resolver logic
- normalization
- AI prompting
- API lifecycle
- validation
- recalculation integration

This document is optimized for Codex execution.

---

## Core Architecture Components

### 1. ExerciseConfigRepository
Responsible for:
- loading/saving configs
- loading/saving unknowns

Storage (MVP):
- private/data/exercise-configs.json
- private/data/exercise-unknowns.json

---

### 2. ExerciseConfigResolver

### Function
resolve(inputName, sourceType)

### Flow
1. normalize input
2. match canonicalName
3. match aliases
4. return config if found
5. else return null (unknown)

---

## Normalization Function

```
normalize(name):
- lowercase
- trim
- replace punctuation with space
- collapse spaces
- split camelCase
```

Example:
"CableLateralRaise" → "cable lateral raise"

---

## Unknown Detection

When resolver returns null:
- create/update UnknownExercise
- increment timesSeen
- store raw name

---

## AI Suggestion Flow

Triggered via:
POST /api/exercises/unknowns/{id}/suggest

### Backend Flow
1. load unknown
2. check config exists → abort if yes
3. check external dataset
4. if dataset match:
   - return external-dataset preview
   - wait for user save
5. build AI prompt
6. call OpenAI API
7. validate response
8. return AI preview
9. wait for user save
10. return

---

## AI Prompt

### System Prompt
You are a fitness classification assistant.
Return ONLY valid JSON.
Use allowed muscles only.
No explanations outside JSON.

### User Prompt Template

Classify this item:

sourceType: {{sourceType}}
rawName: {{rawName}}
normalizedName: {{normalizedName}}

Return JSON:
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

---

## Validation Rules

- valid JSON
- required fields present
- muscleWeights keys valid
- at least one muscle > 0
- fatigueMultiplier numeric
- setTypeHandling valid enum
- canonicalName non-empty

Reject invalid responses.

---

## Config Creation

Map saved AI output:

- source = ai_suggested

---

## Recalculation

After ANY change:
- run fatigue calculation from raw data
- return updated result

---

## API Summary

POST /api/exercises/unknowns/{id}/suggest
POST /api/exercises
POST /api/exercises/{id}/suggest
PATCH /api/exercises/{id}
DELETE /api/exercises/{id}

---

## Frontend Flow

On click ✨Generate suggestion:
- disable button
- call API
- refresh UI
- show error if failed

---

## Logging

Store:
- timestamp
- rawName
- normalizedName
- model
- result status

---

## Edge Cases

- duplicate normalization
- invalid AI output
- deleted exercise reappears
- dataset mismatch

---

## Done Criteria

- resolver replaces hardcoded mapping
- unknown detection works
- AI suggestion works
- validation enforced
- recalculation triggered
