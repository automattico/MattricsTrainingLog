<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/scripts/postgres/archive-review.php';
require_once dirname(__DIR__, 2) . '/scripts/postgres/archive-legacy.php';

function run_archive_history(PDO $pdo, array $config): void
{
    $encoded = shell_exec('python3 ' . escapeshellarg(__DIR__ . '/archive-fixtures.py') . ' --manifest');
    $fixtures = json_decode($encoded ?? '', true, 512, JSON_THROW_ON_ERROR);
    $config['foundation_user_key'] = 'synthetic-history';
    $key = $config['foundation_user_key'];
    $garmin = local_archive_import($pdo, $key, $fixtures['garmin']);
    $part = local_archive_import($pdo, $key, $fixtures['strava'], 1);
    $firstChunk = local_archive_counts($pdo);
    check($garmin['checkpoint'] === 5 && $part === ['resumed_at' => 0, 'checkpoint' => 100, 'total' => 105, 'batches' => 1]
        && $firstChunk['source_records'] === 105, 'archive import stops after a committed batch with an atomic checkpoint');

    $resumed = local_archive_import($pdo, $key, $fixtures['strava']);
    $report = local_archive_counts($pdo);
    check($resumed === ['resumed_at' => 100, 'checkpoint' => 105, 'total' => 105, 'batches' => 1]
        && $report['source_records'] === 110 && $report['canonical_workouts'] === 108 && $report['exact_links'] === 2
        && $report['review_pairs'] === ['probable' => 1, 'ambiguous' => 2] && $report['review_workouts'] === 5,
        'archive resume links exact FIT legs and queues probable and ambiguous candidates separately');
    $legs = local_postgres_query($pdo, <<<'SQL'
SELECT count(DISTINCT l.activity_id)
FROM mattrics.mattrics_activity_source_links l
JOIN mattrics.mattrics_source_records r ON r.id = l.source_record_id
WHERE l.link_kind = 'exact_fit' AND r.external_id IN ('1:1', '1:2')
SQL)->fetchColumn();
    check((int) $legs === 2, 'identical multisport files share corresponding legs and preserve two canonical leg IDs');

    $before = snapshot($pdo, true);
    $again = local_archive_import($pdo, $key, $fixtures['strava']);
    check($again['resumed_at'] === 105 && $again['batches'] === 0 && $before === snapshot($pdo, true),
        'byte-identical archive replay writes no workout, source record or import batch');

    $repack = $fixtures['strava'];
    $repack['archive_sha256'] = str_repeat('f', 64);
    $idSql = <<<'SQL'
SELECT jsonb_agg(to_jsonb(t) ORDER BY t.id) FROM (
    SELECT r.id, r.external_id, l.activity_id, l.link_kind FROM mattrics.mattrics_source_records r
    JOIN mattrics.mattrics_activity_source_links l ON l.source_record_id = r.id
    WHERE jsonb_exists(r.raw_payload, 'archive_evidence')
) t
SQL;
    $beforeIds = $pdo->query($idSql)->fetchColumn();
    $repacked = local_archive_import($pdo, $key, $repack);
    check($repacked['batches'] === 2 && $beforeIds === $pdo->query($idSql)->fetchColumn()
        && $report === local_archive_counts($pdo), 'a repacked archive exercises source upsert and retains canonical and review counts');

    [$data, $catalog] = with_test_router($config, static fn(string $address): array => [
        api_response($address, '/api/data'),
        api_response($address, '/api/exercises'),
    ]);
    check($catalog['meta']['source'] === 'linked-workout-json' && $catalog['meta']['warning'] === null,
        'linked-workout preview identifies its intentional JSON catalog separately from legacy fallback');
    $rows = $data['rows'];
    $exact = array_filter($rows, static fn(array $row): bool => $row['Name'] === 'Edited archive label');
    check($data['meta']['source'] === 'canonical' && $data['count'] === 108
        && count(array_unique(array_column($rows, 'Activity ID raw'))) === 108
        && count($exact) === 2 && array_sum(array_column($exact, 'Distance (km)')) === 5.0
        && in_array('Manual only', array_column($rows, 'Name'), true)
        && array_sum(array_column($report['year_sport'], 'count')) === 108,
        'dashboard reads one row per canonical workout with measured totals and metadata-only coverage');
    check(postgres_dashboard_analysis('archive-fatigue.js', array_values($exact)) === true,
        'each linked workout contributes once to dashboard fatigue');


    $queue = local_archive_review_queue($pdo);
    $ambiguous = current(array_filter($queue, static fn(array $row): bool => $row['reason'] === 'ambiguous'));
    $probable = current(array_filter($queue, static fn(array $row): bool => $row['reason'] === 'probable'));
    $exact = local_archive_review_exact_pairs($pdo)[0];
    $decide = static function (string $action, array $pair) use ($pdo): void {
        local_archive_decide($pdo, $action, $pair['garmin_record_id'], $pair['strava_record_id']);
    };
    $decide('keep_separate', $ambiguous);
    $decide('link', $probable);
    $decide('split', $exact);
    $decisionReport = local_archive_counts($pdo);
    check($decisionReport['exact_links'] === 1 && $decisionReport['manual_links'] === 1
        && $decisionReport['canonical_workouts'] === 108 && $decisionReport['source_records'] === 110
        && $decisionReport['review_pairs'] === ['ambiguous' => 1],
        'manual link, exact split and keep-separate update counts without removing sources');
    $repack['archive_sha256'] = str_repeat('e', 64);
    local_archive_import($pdo, $key, $repack);
    check(local_archive_counts($pdo) === $decisionReport, 'manual review decisions survive source upsert on replay');
    $decide('undo', $exact);
    $decide('undo', $probable);
    $decide('undo', $ambiguous);
    check($beforeIds === $pdo->query($idSql)->fetchColumn() && $report === local_archive_counts($pdo),
        'undo restores the original canonical IDs, counts and unresolved queue');
    $legacy = local_archive_legacy_compare($pdo, [
        ['Activity ID raw' => '1', 'Date' => '2020-01-02T12:00:00Z'],
        ['Activity ID raw' => '4', 'Date' => '2020-01-02T16:10:00Z'],
        ['Activity ID raw' => '99999999999999999999999'],
    ]);
    check($legacy['legacy_matches'] === 2 && $legacy['matched_multisession'] === 1
        && $legacy['matched_metadata_only'] === 1 && $legacy['legacy_unmatched'] === 1
        && $legacy['matched_starts_within_60_seconds'] === 2,
        'local legacy comparison uses exact Strava identity for multisession and metadata-only records');

    // Later deliveries can correct both display fields on an already linked recording.
    $prior = current(array_filter($rows, static fn(array $row): bool => $row['Name'] === 'Edited archive label'));
    $target = local_postgres_query($pdo, "SELECT r.external_id FROM mattrics.mattrics_source_records r
        JOIN mattrics.mattrics_activity_source_links l ON l.source_record_id = r.id
        JOIN mattrics.mattrics_data_sources d ON d.id = r.data_source_id
        WHERE l.activity_id = :id AND d.source_kind = 'strava'", ['id' => $prior['Activity ID raw']])->fetchColumn();
    $edit = current(array_filter($fixtures['strava']['records'], static fn(array $r): bool => $r['id'] === $target));
    $edit['title'] = 'Corrected afternoon workout';
    $edit['sport'] = 'Padel';
    local_workout_import($pdo, $key, 'strava', 'historical-archive', [$edit]);
    $updated = with_test_router($config, static fn(string $address): array => api_response($address, '/api/data'));
    $activity = array_column($updated['rows'], null, 'Activity ID raw')[$prior['Activity ID raw']];
    check($updated['count'] === $data['count'] && $activity['Name'] === $edit['title']
        && $activity['Type'] === $edit['sport'] && $activity['Distance (km)'] === $prior['Distance (km)']
        && $activity['Duration (min)'] === $prior['Duration (min)'],
        'late Strava title and sport corrections reach the same dashboard workout and preserve recorded metrics');

}
