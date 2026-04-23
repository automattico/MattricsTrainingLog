# Final Verification & Autonomous Fix Prompt

## Instructions
You are performing a final end-to-end verification pass for the **Exercise Config Admin** feature.

This is **not** a new feature slice. It is a full-system review, test, and autonomous fix pass.

Read these files first and use them as the source of truth:
- `docs/features/exercise-config-admin/00-feature-spec.md`
- `docs/features/exercise-config-admin/01-implementation-spec.md`
- `docs/features/exercise-config-admin/02-status.md`

## Goal
Validate the entire feature against:
- feature requirements
- implementation spec
- acceptance criteria
- intended slice outcomes

Then fix any gaps autonomously, as long as they are within the defined feature scope.

Do not ask unnecessary questions. Make grounded decisions and document them.

---

## What to verify

### 1. Architecture and data flow
Check that the implementation actually matches the intended design:
- persistent config store exists and is used
- resolver is the source of truth
- hardcoded mappings are no longer the active source of truth
- Hevy exercises and non-Hevy activity types both flow through the unified config system
- unknown detection works
- external dataset lookup, if implemented, happens before AI
- AI is manual-trigger only
- active draft configs are applied immediately
- recalculation happens after every relevant config mutation

### 2. Feature behavior
Verify the full behavior end-to-end:
- unknown items are persisted
- unknown items appear in the review queue
- `✨Generate suggestion` exists and works
- valid AI response creates an active draft config
- `reviewNeeded` is visible
- edit/save works
- review/approve works
- merge-as-alias works
- delete works
- delete confirmation dialog uses the required exact copy:
  - `Are you sure you want to permanently delete this exercise? Once deleted it cannot be recovered!`
- unresolved warning appears when no valid config exists
- active AI draft vs unresolved state is distinguished clearly enough
- fatigue recalculates after:
  - AI suggestion creation
  - edit
  - review/approve
  - merge
  - delete

### 3. Validation and safety
Check:
- normalization is robust enough
- camelCase splitting works
- aliases are handled safely
- AI response validation is enforced
- invalid AI output does not break the app
- duplicate config creation is handled safely
- deletion can cause reappearance as unknown later if source data still contains it
- AI logging exists and captures the required fields

### 4. UX requirements
Check:
- review queue UX is clear
- parameter tooltips/help exist for important fields
- meaning of weights / scaled load / fatigueMultiplier / setTypeHandling is explained
- loading/error states are good enough
- button labels are correct
- important states are visible without digging

### 5. Acceptance criteria
Review every acceptance criterion in `02-status.md` and verify whether it is truly:
- done
- partially done
- not done

Do not mark things done unless they really work.

---

## Required work style

### Autonomous fixing
If you find gaps, inconsistencies, missing validation, missing recalc triggers, broken UI flow, or incomplete docs:
- fix them directly
- keep changes within scope
- prefer simple, robust solutions
- avoid overengineering

### Testing expectation
Run or perform whatever checks are practical in this repo, for example:
- existing automated tests
- targeted manual verification steps
- endpoint verification
- UI checks where feasible
- smoke tests of the most important flows

If full automated coverage is not realistic, still do a serious best-effort verification and document what was checked manually.

---

## Output requirements

### 1. Implement fixes
Apply all necessary code/doc changes.

### 2. Update status doc
Update:
- slice status if appropriate
- acceptance criteria checkboxes
- implemented files
- open questions
- notes
- overall completion state

### 3. Final report
At the end, provide:
- summary of what was verified
- summary of what was fixed
- remaining risks or limitations
- whether the feature is now implementation-complete relative to the spec

### 4. Be strict
Do not give a superficial “looks good”.
Act like a final QA + implementation hardening pass.

---

## Important constraints
- do not start unrelated work
- do not redesign the feature
- do not introduce new scope unless strictly required to satisfy the existing spec
- prefer correctness and completeness over elegance

---

## Final success condition
The feature should be genuinely usable end-to-end and the status doc should accurately reflect reality.
