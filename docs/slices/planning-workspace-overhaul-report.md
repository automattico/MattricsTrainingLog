# Planning workspace overhaul

25 November 2026 · main

## Summary

Combined the internal report and approved client preview in one workspace. Shared tabs cover the story map, releases, features, and stories. Internal also has requirements and next work. Milestone bands, release cards, features, requirements, and story details can expand or collapse. Status text and color are consistent across the map and release cards. Links open the destination tab and expand its enclosing cards.

## Files changed

- Planning report renderer, build assets, tab interaction, stylesheet, and fixture checks under `tools/planning-report/`.
- The planning README and implementation progress.

## Validation

- Planning validation, report fixtures, development and staging builds, JavaScript syntax, and the repository production gate passed.
- Exercised tab routing, keyboard activation, and deep-link expansion in a local DOM fixture. The in-app browser rejected direct file navigation, so visual browser verification was unavailable.

## Security

The workspace embeds the projected client view alongside internal content. The independently packaged client report contains only approved data and shared code. No secrets, private workout data, or deployment resources were used.

## Git state

The existing working tree was dirty. The changes are local and uncommitted. No push or deployment.

## Next slice

[Verify hosted refresh and update the planning workspace](next-hosted-refresh-workspace-prompt.md)
