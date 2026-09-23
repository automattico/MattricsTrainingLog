# Next Slice: Pádel Production Delivery and Verification

Read first:
- AGENTS.md
- docs/architecture-next.md
- docs/codex-framework.md
- docs/implementation-progress.md

Goal:
Deliver the locally validated first-class Pádel activity support through an explicitly approved release, ensure the approved `Padel` activity-type config exists in the live private configuration store, and verify real `Padel` source rows end to end.

Context:
The source implementation recognizes `Padel`, `Pádel`, `Padel Tennis`, and `Pádel Tennis` as canonical `Padel`, displays `Pádel` with its own icon and colour, shows duration and average-heart-rate card metrics, and applies the approved duration-scaled `conditioning_hybrid` fatigue mapping. Local focused JavaScript tests and `./scripts/prod-gate.sh` passed in the implementation slice. Private JSON seeds deploy only when missing, so an existing runtime must receive the new activity-type record through the supported authenticated configuration workflow rather than by overwriting private data.

In scope:
- Confirm the release contains the Pádel source changes and approved activity-type seed.
- With explicit deploy approval, run the repository deployment flow.
- Use the authenticated activity-type configuration workflow to add the approved `Padel` record if the live private configuration store does not already contain it.
- Verify a real imported raw `Type: Padel` row displays as `Pádel`, filters under one canonical type, shows duration and average heart rate, and contributes non-zero fatigue.
- Record only non-sensitive status and aggregate verification evidence.

Out of scope:
- Changes to Make, Strava, Google Sheets, OAuth, imports, or source activity titles.
- A title-based `Workout` fallback.
- Adding `Paddle` as an alias or treating canoeing as Pádel.
- Authentication, session, cookie, passkey, or Mattwarden changes.

Security constraints:
- Do not read, print, commit, or expose secrets.
- Do not commit `.env.local`, `private/config.php`, API keys, tokens, tunnel credentials, raw health exports, or DB dumps.
- Do not overwrite existing private runtime configuration files during deployment.
- Do not include private activity payloads in logs or documentation.

Implementation requirements:
- Preserve the exact approved Pádel fatigue weights and metadata from `private/data/activity-type-configs.json`.
- Treat all four supported names as canonical `Padel`; keep `Paddle` unresolved.
- If the live config already exists, verify it rather than recreating it.
- Do not deploy until the user explicitly approves deployment in that slice.

Validation:
- `node tests/js/exercise-config-tests.js`
- `./scripts/prod-gate.sh`
- Authenticated production checks for display metadata, canonical filter grouping, card metrics, and non-zero fatigue using a real `Padel` activity.

Completion requirements:
- Update docs/implementation-progress.md.
- Add a slice report using docs/templates/slice-report.md.
- Add the next slice prompt using docs/templates/next-slice-prompt.md.
