<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || $argc !== 6) {
    fwrite(STDERR, "Usage: php scripts/catalog-promotion.php SITE_ROOT CANDIDATE_DIR NEW_BACKUP_DIR EXERCISES_SHA256 ACTIVITY_TYPES_SHA256\n");
    exit(1);
}
[, $root, $candidate, $backup, $exerciseHash, $activityHash] = $argv;
$site = realpath($root);
if ($site === false || !is_dir($site . '/private/data')) {
    fwrite(STDERR, "Expected a site root with an existing private catalog.\n");
    exit(1);
}
define('MATTWARDEN_SITE_DIR', $site);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/catalog-promotion.php';

try {
    $result = mattrics_promote_catalogs($candidate, $backup, [
        'exercise-configs.json' => $exerciseHash,
        'activity-type-configs.json' => $activityHash,
    ]);
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n";
} catch (Throwable $failure) {
    fwrite(STDERR, $failure->getMessage() . "\n");
    exit(1);
}
