# Garmin account export inventory

Date 2026-11-23

## Workout inventory

- Preserved a byte-identical copy of the original 131 MB ZIP in private local storage. The inventory read the archive and its nested ZIPs directly.
- Three JSON summary files contain 679 distinct Garmin activity IDs dated 2019–2026. The records include UTC and local start fields. Indoor, treadmill, strength, and other equipment-related activity types are represented.
- Three nested ZIPs contain 32,687 FIT files. FIT File ID metadata identifies 678 as Activity files. The other 32,009 have other FIT types. The summary index and workout-file totals differ by one. The import still needs to reconcile individual summaries to files. [Garmin FIT file types](https://developer.garmin.com/fit/file-types/)
- Compared SHA-256 digests of the unwrapped FIT contents with Strava. Found 596 byte-identical FIT Activity files with one copy in each export. A bounded FIT record inspection found a single session with a start and elapsed time in five representative pairs. Use these as candidates for the exact-match dry run. [Garmin FIT sessions](https://developer.garmin.com/fit/articles/cookbook/decoding_activity_files.html)

## Health Data coverage

- The wellness JSON contains 132 distinct timestamped body-weight readings. Dates span 2019–2026. Check the source unit before normalization and keep the profile snapshot separate from the history.
- Daily summaries contain resting heart rate on 2,569 dates and daily minimum and maximum heart rate on 2,573 dates across 2019–2026. Keep the daily resting field distinct from Garmin's resting trend field. Detailed heart-rate streams still need a separate decode and coverage check.
- Performance metrics contain 528 VO₂ max records across 409 dates. Some activity summaries also include VO₂ max. Resolve how to separate series and avoid duplicate readings during Health Data design.
- The JSON also contains sleep, steps, stress, Body Battery, respiration, hydration, and Fitness Age fields. These are archive coverage findings for the later Health Data milestone.

## Next work

Exercise the archive importer against synthetic nested FIT and Strava examples in local staging. Confirm per-session identity and reconcile index rows to files before the historical dry run. Keep wellness and monitoring files outside workout ingestion.

Only aggregate observations and source structure were recorded. The archives, names, identifiers, timestamps, readings, raw file hashes, and sample files remain private. The inventory made no database or hosted changes.
