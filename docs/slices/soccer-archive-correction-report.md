# Soccer archive correction

On 2026-11-24, the Strava archive importer gained a mapping from `Football (Soccer)` to `Soccer`. A synthetic archive fixture checks both the mapped type and the retained source label.

One transaction corrected the normalized sport in three local history source records and their three canonical workout rows. It verified that every other field matched its original value before committing. The authenticated local dashboard API returned all three workouts as `Soccer`.

The source label remains in the import evidence. The local update used verified Strava sport labels and preserved workout IDs and measurements. The verification output contained aggregate results only.

The working tree contains this slice and pre-existing changes. No commit, push, or deploy was performed.

[Next slice prompt](next-soccer-archive-correction-prompt.md)
