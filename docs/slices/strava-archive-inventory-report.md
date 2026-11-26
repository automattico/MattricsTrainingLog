# Strava archive inventory

Date 2026-11-23

## Inventory

- The original ZIP is in private local storage. The inventory read the archive directory and `activities.csv` directly, with bounded reads of activity file headers and timestamps.
- The index contains 1,389 activities across 2019–2026. Every row has a distinct activity ID. All 876 file references resolved, and another 513 rows have no file reference. Preserve those rows as metadata-only activities. The oldest year has 81 rows, including 72 with activity files.
- The archive contains 780 FIT, 89 GPX, and 7 TCX activity files. Gzip wraps 773 FIT files and all 7 TCX files. A representative file from each format had the expected signature.

## Import implications

- The index dates use English month names and 12-hour time without a time-zone suffix. Interpreting them as UTC matched timestamps from all 96 GPX and TCX files to within one minute. Confirm the same behavior against FIT and carry the uncertainty for rows without recordings.
- The index repeats `Distance` and `Elapsed Time` headings. Address each column by position. The two positive distance values were about 1,000 times apart in 710 rows, nearly the same in 27 rows, and had another ratio in 3 rows. Retain both source values and validate units before normalizing.

## Next work

The [Garmin comparison](garmin-archive-inventory-report.md#workout-inventory) found byte-identical FIT Activity files in both exports, including representative single-session pairs. Use synthetic fixtures that cover all three formats, gzip wrapping, metadata-only rows, and ambiguous distance units when preparing the isolated dry run.
