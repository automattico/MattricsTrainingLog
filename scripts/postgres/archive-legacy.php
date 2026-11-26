<?php
declare(strict_types=1);

require_once __DIR__ . '/archive-store.php';

function local_archive_legacy_compare(PDO $pdo, array $rows): array
{
    $records = $pdo->query(<<<'SQL'
SELECT r.raw_payload->'archive_evidence'->>'source_activity_id' AS source_id,
       min(r.started_at) AS started_at, min(r.raw_payload->'archive_evidence'->>'format') AS format,
       count(DISTINCT l.activity_id) AS workouts
FROM mattrics.mattrics_source_records r
JOIN mattrics.mattrics_data_sources ds ON ds.id = r.data_source_id AND ds.source_kind = 'strava'
JOIN mattrics.mattrics_activity_source_links l ON l.source_record_id = r.id
WHERE jsonb_exists(r.raw_payload, 'archive_evidence')
GROUP BY 1
SQL)->fetchAll();
    $byId = array_column($records, null, 'source_id');
    $count = [
        'legacy_rows' => count($rows), 'legacy_matches' => 0, 'legacy_unmatched' => 0,
        'legacy_invalid_or_duplicate_ids' => 0, 'matched_metadata_only' => 0,
        'matched_multisession' => 0, 'matched_starts_within_60_seconds' => 0,
        'matched_starts_differ' => 0, 'matched_starts_uncomparable' => 0,
    ];
    $ids = array_count_values(array_map(static fn(array $row): string => trim((string) ($row['Activity ID raw'] ?? '')), $rows));
    $count['matched_calendar_day'] = 0;
    $count['matched_different_calendar_day'] = 0;
    $count['matched_legacy_at_local_midnight'] = 0;
    $count['matched_consistent_with_local_wall_time_in_utc'] = 0;
    $timezone = new DateTimeZone('Europe/Berlin');
    $seen = [];
    foreach ($rows as $row) {
        $id = trim((string) ($row['Activity ID raw'] ?? ''));
        if (!ctype_digit($id) || $ids[$id] !== 1) {
            $count['legacy_invalid_or_duplicate_ids']++;
            continue;
        }
        if (!isset($byId[$id])) {
            $count['legacy_unmatched']++;
            continue;
        }
        $count['legacy_matches']++;
        $seen[$id] = true;
        $match = $byId[$id];
        if ($match['format'] === 'metadata') {
            $count['matched_metadata_only']++;
        }
        if ((int) $match['workouts'] > 1) {
            $count['matched_multisession']++;
        }
        try {
            $time = local_workout_timestamp($row['Date'] ?? null)->setTimezone($timezone);
            $start = (new DateTimeImmutable($match['started_at']))->setTimezone($timezone);
            $count[$time->format('Y-m-d') === $start->format('Y-m-d') ? 'matched_calendar_day' : 'matched_different_calendar_day']++;
            $delta = $time->getTimestamp() - $start->getTimestamp();
            if (abs($delta - $start->getOffset()) <= 60 && abs($delta) > 60) {
                $count['matched_consistent_with_local_wall_time_in_utc']++;
            }
            if ($time->format('H:i:s') === '00:00:00') {
                $count['matched_legacy_at_local_midnight']++;
            } else {
                $count[abs($delta) <= 60
                    ? 'matched_starts_within_60_seconds' : 'matched_starts_differ']++;
            }
        } catch (Exception $error) {
            $count['matched_starts_uncomparable']++;
        }
    }
    $count['strava_ids_outside_legacy_snapshot'] = count($byId) - count($seen);
    return $count;
}
