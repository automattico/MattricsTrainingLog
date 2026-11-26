<?php
declare(strict_types=1);

require_once __DIR__ . '/integration.php';
require_once dirname(__DIR__, 2) . '/scripts/postgres/archive-store.php';

try {
    $config = local_postgres_config($argv[1], 'mattrics_history');
    $pdo = mattrics_foundation_connect($config['foundation_database_url']);
    $rows = local_postgres_query($pdo, <<<'SQL'
SELECT a.id, a.activity_date, a.distance_km, a.duration_minutes
FROM mattrics.mattrics_activities a
JOIN mattrics.mattrics_users u ON u.id = a.user_id
WHERE u.external_key = :user_key
SQL, ['user_key' => $config['foundation_user_key']])->fetchAll();
    $data = with_test_router($config, static fn(string $address): array => api_response($address, '/api/data?includeChildren=1'));
    $apiIds = array_column($data['rows'], 'Activity ID raw');
    if ($data['meta']['workoutReview'] ?? false) {
        $review = with_test_router($config, static fn($host) => api_response($host, '/api/workout-review'));
        $expectedSources = local_postgres_query($pdo, "SELECT r.id FROM mattrics.mattrics_source_records r
            JOIN mattrics.mattrics_users u ON u.id=r.user_id WHERE u.external_key=:user_key",
            ['user_key' => $config['foundation_user_key']])->fetchAll(PDO::FETCH_COLUMN);
        $sources = array_merge([], ...array_column($review['groups'], 'ids'));
        sort($sources);
        sort($expectedSources);
        check($sources === $expectedSources, 'every stored recording belongs to exactly one dashboard group');
        $workouts = array_column($data['rows'], null, 'Activity ID raw');
        foreach ($review['groups'] as $group) {
            $selected = $review['sources'][$group['preferred']];
            $row = $workouts[$group['activity']] ?? null;
            if (!$row || $row['Distance (km)'] != $selected['km']
                || abs((float)$row['Duration (min)'] * 60 - (float)$selected['seconds']) > .00001) {
                throw new RuntimeException('Selected recording differs from dashboard.');
            }
        }
        check(count($workouts) === count($review['groups']) && count($workouts) === $data['count'],
            'dashboard counts, duration and distance agree with selected recordings');
        return;
    }
    $dbIds = array_column($rows, 'id');
    sort($apiIds);
    sort($dbIds);
    $report = local_archive_counts($pdo);
    $apiCount = count($apiIds);
    check($apiCount > 0 && $data['meta']['source'] === 'canonical' && $apiCount === $data['count']
        && $apiIds === $dbIds && count(array_unique($apiIds)) === $apiCount
        && $report['canonical_workouts'] === $apiCount
        && abs(array_sum(array_column($data['rows'], 'Distance (km)')) - array_sum(array_column($rows, 'distance_km'))) < 0.00001
        && abs(array_sum(array_column($data['rows'], 'Duration (min)')) - array_sum(array_column($rows, 'duration_minutes'))) < 0.00001,
        "local dashboard API matches all $apiCount stored workouts and canonical distance and duration totals");
} catch (Throwable $failure) {
    fwrite(STDERR, "Local history API verification failed.\n");
    exit(1);
}
