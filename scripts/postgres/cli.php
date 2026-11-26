<?php
declare(strict_types=1);

require_once __DIR__ . '/runtime.php';

try {
    $config = local_postgres_config($argv[1]);
    $pdo = mattrics_foundation_connect($config['foundation_database_url']);
    $action = $argv[2] ?? '';
    $options = array_slice($argv, 3);
    if ($action === 'migrate' && $options === []) {
        $versions = local_postgres_migrate($pdo);
        echo $versions === [] ? "No pending migrations.\n" : 'Applied ' . implode(', ', $versions) . "\n";
    } elseif ($action === 'import' && ($options === [] || $options === ['--dry-run'])) {
        $dryRun = $options !== [];
        foreach (local_postgres_fixtures() as $batch) {
            $result = local_workout_import(
                $pdo, $config['foundation_user_key'], $batch['provider'], $batch['account'], $batch['records'], $dryRun
            );
            printf("%s%s: records=%d, canonical workouts touched=%d\n",
                $dryRun ? 'Dry run, ' : '', $batch['provider'], $result['records'], $result['workouts']);
        }
    } else {
        throw new RuntimeException('Expected migrate or import [--dry-run].');
    }
} catch (Throwable $failure) {
    $message = $failure instanceof PDOException ? 'Database operation failed (' . $failure->getCode() . ').' : $failure->getMessage();
    fwrite(STDERR, $message . "\n");
    exit(1);
}
