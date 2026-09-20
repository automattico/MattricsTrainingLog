<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/foundation-import.php';

$options = getopt('', [
    'input::',
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
$privateRoot = (string) ($options['private-root'] ?? dirname(__DIR__) . '/private');
$input = (string) ($options['input'] ?? 'import-garmin/Activities.csv');
$dryRun = array_key_exists('dry-run', $options);

try {
    $pdo = mattrics_foundation_connect($databaseUrl);
    $summary = mattrics_foundation_run_garmin_import($pdo, [
        'input' => $input,
        'userKey' => $userKey,
        'displayName' => $displayName,
        'timezone' => $timezone,
        'privateRoot' => $privateRoot,
        'dryRun' => $dryRun,
    ]);
} catch (Throwable $throwable) {
    fwrite(STDERR, 'Garmin import failed: ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}

$counts = $summary['counts'];
echo 'Garmin import ' . ($dryRun ? 'dry run ' : '') . 'completed.' . PHP_EOL;
echo 'User: ' . $summary['user']['externalKey'] . ' (' . $summary['user']['timezone'] . ')' . PHP_EOL;
echo 'Input: ' . (string) ($summary['input']['relativePath'] ?? 'import-garmin/Activities.csv') . PHP_EOL;
echo 'Activities: ' . $counts['activities'] . PHP_EOL;
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
