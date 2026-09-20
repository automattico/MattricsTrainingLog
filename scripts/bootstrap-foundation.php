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
    $summary = mattrics_foundation_run_import($pdo, [
        'userKey' => $userKey,
        'displayName' => $displayName,
        'timezone' => $timezone,
        'privateRoot' => $privateRoot,
        'dryRun' => $dryRun,
    ]);
} catch (Throwable $throwable) {
    fwrite(STDERR, 'Foundation bootstrap failed: ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}

$counts = $summary['counts'];
echo 'Foundation bootstrap ' . ($dryRun ? 'dry run ' : '') . 'completed.' . PHP_EOL;
echo 'User: ' . $summary['user']['externalKey'] . ' (' . $summary['user']['timezone'] . ')' . PHP_EOL;
echo 'Private root: ' . $summary['privateRoot'] . PHP_EOL;
echo 'Exercises: ' . $counts['exercises'] . ' | exercise aliases: ' . $counts['exerciseAliases'] . PHP_EOL;
echo 'Activity types: ' . $counts['activityTypes'] . ' | activity type aliases: ' . $counts['activityTypeAliases'] . PHP_EOL;
echo 'Activities: ' . $counts['activities'] . PHP_EOL;
echo 'Hevy activities: ' . $counts['hevyActivities'] . PHP_EOL;
echo 'Hevy exercise blocks: ' . $counts['hevyExerciseBlocks']
    . ' (resolved ' . $counts['resolvedHevyExerciseBlocks']
    . ', unresolved ' . $counts['unresolvedHevyExerciseBlocks'] . ')' . PHP_EOL;
echo 'Sets: parsed ' . $counts['parsedSets']
    . ', time ' . $counts['timeSets']
    . ', unknown ' . $counts['unknownSets'] . PHP_EOL;

if (($summary['unresolvedExercises'] ?? []) !== []) {
    echo 'Unresolved exercise names: ' . implode(', ', $summary['unresolvedExercises']) . PHP_EOL;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
