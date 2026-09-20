<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/foundation-read.php';
require_once __DIR__ . '/foundation-diagnostics.php';
require_once dirname(__DIR__, 2) . '/scripts/lib/foundation-connectors.php';
require_once dirname(__DIR__, 2) . '/scripts/lib/foundation-migrations.php';

if (!mattrics_foundation_local_diagnostics_allowed()) {
    mattrics_send_json(['error' => 'Not found.'], 404);
}

$payload = [
    'ok' => true,
    'runtimeMode' => mattrics_foundation_runtime_mode(),
    'localOnly' => true,
    'canonicalModeEnabled' => mattrics_foundation_read_enabled(),
    'foundationUserKey' => mattrics_foundation_read_user_key(),
    'databaseReachable' => false,
    'foundationUserFound' => false,
    'canonicalReadStatus' => 'disabled',
    'migrationStatus' => mattrics_foundation_read_enabled() ? 'unreachable' : 'disabled',
    'availableMigrationCount' => count(mattrics_foundation_list_migration_files()),
    'appliedMigrationCount' => 0,
    'pendingMigrationCount' => 0,
    'lastSuccessfulSyncAt' => null,
    'lastSuccessfulImportBatchKind' => null,
    'lastSuccessfulImportBatchFinishedAt' => null,
    'connectors' => mattrics_foundation_safe_connector_public_payload(),
];

if (!$payload['canonicalModeEnabled']) {
    mattrics_send_json($payload);
}

try {
    $pdo = mattrics_foundation_read_connect();
    $payload['databaseReachable'] = true;

    $applied = mattrics_foundation_read_applied_migrations_if_available($pdo);
    $payload['appliedMigrationCount'] = count($applied);
    $payload['pendingMigrationCount'] = max(0, $payload['availableMigrationCount'] - $payload['appliedMigrationCount']);
    $payload['migrationStatus'] = $payload['pendingMigrationCount'] > 0 ? 'pending' : 'up_to_date';

    $context = mattrics_foundation_read_context();
    $payload['foundationUserFound'] = true;
    $payload['canonicalReadStatus'] = 'ready';
    $payload['lastSuccessfulSyncAt'] = mattrics_foundation_read_latest_successful_sync_at();

    $latestBatch = mattrics_foundation_read_latest_import_batch($pdo, (string) $context['userId'], null, 'succeeded');
    if (is_array($latestBatch)) {
        $payload['lastSuccessfulImportBatchKind'] = trim((string) ($latestBatch['batch_kind'] ?? '')) !== ''
            ? (string) $latestBatch['batch_kind']
            : null;
        $payload['lastSuccessfulImportBatchFinishedAt'] = mattrics_foundation_read_iso_or_null($latestBatch['finished_marker'] ?? null);
    }
} catch (Throwable $throwable) {
    $message = $throwable->getMessage();
    if (str_contains($message, 'Canonical foundation user not found')) {
        $payload['canonicalReadStatus'] = 'user_missing';
        $payload['migrationStatus'] = $payload['databaseReachable'] ? $payload['migrationStatus'] : 'unreachable';
    } else {
        $payload['canonicalReadStatus'] = 'unreachable';
        $payload['migrationStatus'] = $payload['databaseReachable'] ? $payload['migrationStatus'] : 'unreachable';
    }
}

mattrics_send_json($payload);
