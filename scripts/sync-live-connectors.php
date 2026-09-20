<?php
declare(strict_types=1);

define('MATTWARDEN_SITE_DIR', dirname(__DIR__));
require_once MATTWARDEN_SITE_DIR . '/lib/bootstrap.php';
require_once MATTWARDEN_SITE_DIR . '/lib/foundation-import.php';

$options = getopt('', [
    'database-url::',
    'user-key::',
    'display-name::',
    'timezone::',
    'private-root::',
    'dry-run',
]);

$databaseUrl = (string) ($options['database-url'] ?? getenv('DATABASE_URL') ?: MATTRICS_FOUNDATION_DEFAULT_DATABASE_URL);
$userKey = (string) ($options['user-key'] ?? 'legacy-local-user');
$displayName = (string) ($options['display-name'] ?? 'Legacy Local User');
$timezone = (string) ($options['timezone'] ?? 'Europe/Berlin');
$privateRoot = (string) ($options['private-root'] ?? MATTWARDEN_SITE_DIR . '/private');
$dryRun = array_key_exists('dry-run', $options);

try {
    $pdo = mattrics_foundation_connect($databaseUrl);
    $summary = mattrics_foundation_run_live_connector_sync($pdo, [
        'userKey' => $userKey,
        'displayName' => $displayName,
        'timezone' => $timezone,
        'privateRoot' => $privateRoot,
        'dryRun' => $dryRun,
    ]);
} catch (Throwable $throwable) {
    fwrite(STDERR, 'Live connector sync failed: ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}

$hevyBatch = $summary['batches']['hevyLive'] ?? ['status' => 'skipped', 'importedRowCount' => 0, 'appliedActivityCount' => 0];
echo 'Live connector sync ' . ($dryRun ? 'dry run ' : '') . 'completed.' . PHP_EOL;
echo 'User: ' . $summary['user']['externalKey'] . ' (' . $summary['user']['timezone'] . ')' . PHP_EOL;
echo 'Hevy live status: ' . (string) ($summary['connectors']['hevy']['connectionStatus'] ?? 'paused') . PHP_EOL;
echo 'Garmin live status: ' . (string) ($summary['connectors']['garmin']['connectionStatus'] ?? 'paused') . PHP_EOL;
echo 'Hevy live batch: ' . (string) ($hevyBatch['status'] ?? 'skipped') . PHP_EOL;
echo 'Hevy live strategy: ' . (string) ($hevyBatch['strategy'] ?? 'n/a') . PHP_EOL;
echo 'Hevy overlap days: ' . (int) ($hevyBatch['overlapDays'] ?? 0) . PHP_EOL;
echo 'Hevy cursor: ' . (string) ($hevyBatch['cursorStartedAt'] ?? 'n/a') . PHP_EOL;
echo 'Hevy sync window start: ' . (string) ($hevyBatch['lastSyncWindowStartedAt'] ?? 'n/a') . PHP_EOL;
echo 'Hevy live imported workouts: ' . (int) ($hevyBatch['importedRowCount'] ?? 0) . PHP_EOL;
echo 'Hevy live applied activities: ' . (int) ($hevyBatch['appliedActivityCount'] ?? 0) . PHP_EOL;
echo 'Hevy live fetched pages: ' . (int) ($hevyBatch['fetchedPageCount'] ?? 0) . PHP_EOL;
echo 'Hevy live fetched workouts: ' . (int) ($hevyBatch['fetchedWorkoutCount'] ?? 0) . PHP_EOL;
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
