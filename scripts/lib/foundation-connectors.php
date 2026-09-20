<?php
declare(strict_types=1);

const MATTRICS_FOUNDATION_CONNECTOR_STORE_VERSION = 2;
const MATTRICS_FOUNDATION_CONNECTOR_STORE_RELATIVE_PATH = 'storage/live-connectors.json';
const MATTRICS_FOUNDATION_HEVY_INCREMENTAL_OVERLAP_DAYS = 30;

function mattrics_foundation_connector_definitions(): array
{
    return [
        'hevy' => [
            'sourceKey' => 'hevy-live-api',
            'sourceKind' => 'hevy',
            'displayName' => 'Hevy Live API',
            'authType' => 'api_key',
            'batchKind' => 'hevy_live_api_sync',
        ],
        'garmin' => [
            'sourceKey' => 'garmin-connect-live',
            'sourceKind' => 'garmin',
            'displayName' => 'Garmin Connect Live',
            'authType' => 'deferred',
            'batchKind' => 'garmin_live_sync',
        ],
    ];
}

function mattrics_foundation_connector_store_path(?string $privateRoot = null): string
{
    $privateRoot = rtrim((string) ($privateRoot ?? mattrics_private_root()), '/');
    if ($privateRoot === '') {
        throw new RuntimeException('Private root cannot be empty.');
    }

    return $privateRoot . '/' . MATTRICS_FOUNDATION_CONNECTOR_STORE_RELATIVE_PATH;
}

function mattrics_foundation_default_connector_sync_state(string $connectorKey): array
{
    return [
        'lastAttemptAt' => null,
        'lastSucceededAt' => null,
        'lastErrorAt' => null,
        'lastError' => null,
        'lastStrategy' => null,
        'overlapDays' => $connectorKey === 'hevy' ? MATTRICS_FOUNDATION_HEVY_INCREMENTAL_OVERLAP_DAYS : null,
        'lastSyncWindowStartedAt' => null,
        'cursorStartedAt' => null,
        'lastRemoteMarkerAt' => null,
        'lastFetchedPageCount' => 0,
        'lastFetchedWorkoutCount' => 0,
        'lastNewestFetchedStartedAt' => null,
        'lastOldestFetchedStartedAt' => null,
    ];
}

function mattrics_foundation_default_connector_test_state(): array
{
    return [
        'lastAttemptAt' => null,
        'lastSucceededAt' => null,
        'lastErrorAt' => null,
        'lastError' => null,
    ];
}

function mattrics_foundation_default_connector_record(string $connectorKey): array
{
    $definitions = mattrics_foundation_connector_definitions();
    $definition = $definitions[$connectorKey] ?? null;
    if (!is_array($definition)) {
        throw new RuntimeException('Unknown connector key: ' . $connectorKey);
    }

    return [
        'authType' => (string) $definition['authType'],
        'enabled' => false,
        'apiKey' => null,
        'credentials' => [],
        'headerName' => $connectorKey === 'hevy' ? 'api-key' : null,
        'apiBaseUrl' => $connectorKey === 'hevy' ? 'https://api.hevyapp.com' : null,
        'updatedAt' => null,
        'syncState' => mattrics_foundation_default_connector_sync_state($connectorKey),
        'testState' => mattrics_foundation_default_connector_test_state(),
    ];
}

function mattrics_foundation_connector_nullable_string(mixed $value): ?string
{
    $text = trim((string) $value);
    return $text === '' ? null : $text;
}

function mattrics_foundation_normalize_connector_sync_state(string $connectorKey, mixed $value, array $legacyRecord = []): array
{
    $default = mattrics_foundation_default_connector_sync_state($connectorKey);
    $state = is_array($value) ? array_merge($default, $value) : $default;

    if (!is_array($value)) {
        $state['lastAttemptAt'] = $legacyRecord['lastSyncAttemptAt'] ?? $state['lastAttemptAt'];
        $state['lastSucceededAt'] = $legacyRecord['lastSyncSucceededAt'] ?? $state['lastSucceededAt'];
        $state['lastErrorAt'] = $legacyRecord['lastErrorAt'] ?? $state['lastErrorAt'];
        $state['lastError'] = $legacyRecord['lastError'] ?? $state['lastError'];
    }

    $state['lastAttemptAt'] = mattrics_foundation_connector_nullable_string($state['lastAttemptAt'] ?? null);
    $state['lastSucceededAt'] = mattrics_foundation_connector_nullable_string($state['lastSucceededAt'] ?? null);
    $state['lastErrorAt'] = mattrics_foundation_connector_nullable_string($state['lastErrorAt'] ?? null);
    $state['lastError'] = mattrics_foundation_connector_nullable_string($state['lastError'] ?? null);
    $state['lastStrategy'] = mattrics_foundation_connector_nullable_string($state['lastStrategy'] ?? null);
    $state['overlapDays'] = isset($state['overlapDays']) && is_numeric($state['overlapDays'])
        ? max(1, (int) $state['overlapDays'])
        : $default['overlapDays'];
    $state['lastSyncWindowStartedAt'] = mattrics_foundation_connector_nullable_string($state['lastSyncWindowStartedAt'] ?? null);
    $state['cursorStartedAt'] = mattrics_foundation_connector_nullable_string($state['cursorStartedAt'] ?? null);
    $state['lastRemoteMarkerAt'] = mattrics_foundation_connector_nullable_string($state['lastRemoteMarkerAt'] ?? null);
    $state['lastFetchedPageCount'] = max(0, (int) ($state['lastFetchedPageCount'] ?? 0));
    $state['lastFetchedWorkoutCount'] = max(0, (int) ($state['lastFetchedWorkoutCount'] ?? 0));
    $state['lastNewestFetchedStartedAt'] = mattrics_foundation_connector_nullable_string($state['lastNewestFetchedStartedAt'] ?? null);
    $state['lastOldestFetchedStartedAt'] = mattrics_foundation_connector_nullable_string($state['lastOldestFetchedStartedAt'] ?? null);

    return $state;
}

function mattrics_foundation_normalize_connector_test_state(mixed $value): array
{
    $default = mattrics_foundation_default_connector_test_state();
    $state = is_array($value) ? array_merge($default, $value) : $default;
    $state['lastAttemptAt'] = mattrics_foundation_connector_nullable_string($state['lastAttemptAt'] ?? null);
    $state['lastSucceededAt'] = mattrics_foundation_connector_nullable_string($state['lastSucceededAt'] ?? null);
    $state['lastErrorAt'] = mattrics_foundation_connector_nullable_string($state['lastErrorAt'] ?? null);
    $state['lastError'] = mattrics_foundation_connector_nullable_string($state['lastError'] ?? null);

    return $state;
}

function mattrics_foundation_normalize_connector_record(string $connectorKey, mixed $value): array
{
    $default = mattrics_foundation_default_connector_record($connectorKey);
    $rawRecord = is_array($value) ? $value : [];
    $record = $rawRecord !== [] ? array_merge($default, $rawRecord) : $default;
    $record['authType'] = trim((string) ($record['authType'] ?? $default['authType']));
    $record['enabled'] = !empty($record['enabled']);
    $record['apiKey'] = mattrics_foundation_connector_nullable_string($record['apiKey'] ?? null);
    $record['headerName'] = mattrics_foundation_connector_nullable_string($record['headerName'] ?? null);
    $record['apiBaseUrl'] = mattrics_foundation_connector_nullable_string($record['apiBaseUrl'] ?? null);
    $record['updatedAt'] = mattrics_foundation_connector_nullable_string($record['updatedAt'] ?? null);
    $record['credentials'] = is_array($record['credentials'] ?? null) ? $record['credentials'] : [];
    $record['syncState'] = mattrics_foundation_normalize_connector_sync_state(
        $connectorKey,
        array_key_exists('syncState', $rawRecord) ? ($rawRecord['syncState'] ?? null) : null,
        $record
    );
    $record['testState'] = mattrics_foundation_normalize_connector_test_state(
        array_key_exists('testState', $rawRecord) ? ($rawRecord['testState'] ?? null) : null
    );

    unset(
        $record['lastSyncAttemptAt'],
        $record['lastSyncSucceededAt'],
        $record['lastErrorAt'],
        $record['lastError']
    );

    return $record;
}

function mattrics_foundation_normalize_connector_store(mixed $value): array
{
    $store = is_array($value) ? $value : [];
    $connectors = is_array($store['connectors'] ?? null) ? $store['connectors'] : [];

    $normalized = [
        'version' => MATTRICS_FOUNDATION_CONNECTOR_STORE_VERSION,
        'connectors' => [],
    ];

    foreach (array_keys(mattrics_foundation_connector_definitions()) as $connectorKey) {
        $normalized['connectors'][$connectorKey] = mattrics_foundation_normalize_connector_record(
            $connectorKey,
            $connectors[$connectorKey] ?? null
        );
    }

    return $normalized;
}

function mattrics_foundation_load_connector_store(?string $privateRoot = null): array
{
    $path = mattrics_foundation_connector_store_path($privateRoot);
    if (!is_file($path)) {
        return mattrics_foundation_normalize_connector_store(null);
    }

    $raw = @file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException('Failed to read live connector store.');
    }

    $decoded = json_decode($raw, true);
    if ($decoded === null && trim($raw) !== 'null') {
        throw new RuntimeException('Live connector store contains invalid JSON.');
    }

    return mattrics_foundation_normalize_connector_store($decoded);
}

function mattrics_foundation_save_connector_store(array $store, ?string $privateRoot = null): array
{
    $normalized = mattrics_foundation_normalize_connector_store($store);
    $path = mattrics_foundation_connector_store_path($privateRoot);
    $dir = dirname($path);
    mattrics_ensure_dir($dir);

    $temp = $dir . '/live-connectors.' . bin2hex(random_bytes(6)) . '.tmp';
    $json = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Failed to encode live connector store.');
    }

    if (@file_put_contents($temp, $json, LOCK_EX) === false) {
        throw new RuntimeException('Failed to write live connector store.');
    }

    if (!@rename($temp, $path)) {
        @unlink($temp);
        throw new RuntimeException('Failed to publish live connector store.');
    }

    return $normalized;
}

function mattrics_foundation_connector_has_credential(string $connectorKey, array $record): bool
{
    if ($connectorKey === 'hevy') {
        return trim((string) ($record['apiKey'] ?? '')) !== '';
    }

    return is_array($record['credentials'] ?? null) && $record['credentials'] !== [];
}

function mattrics_foundation_connector_latest_success_at(array $record): ?string
{
    $latest = null;
    foreach ([
        $record['syncState']['lastSucceededAt'] ?? null,
        $record['testState']['lastSucceededAt'] ?? null,
    ] as $candidate) {
        $candidate = mattrics_foundation_connector_nullable_string($candidate);
        if ($candidate !== null && ($latest === null || strcmp($candidate, $latest) > 0)) {
            $latest = $candidate;
        }
    }

    return $latest;
}

function mattrics_foundation_connector_latest_error_at(array $record): ?string
{
    $latest = null;
    foreach ([
        $record['syncState']['lastErrorAt'] ?? null,
        $record['testState']['lastErrorAt'] ?? null,
    ] as $candidate) {
        $candidate = mattrics_foundation_connector_nullable_string($candidate);
        if ($candidate !== null && ($latest === null || strcmp($candidate, $latest) > 0)) {
            $latest = $candidate;
        }
    }

    return $latest;
}

function mattrics_foundation_connector_status(string $connectorKey, array $record): string
{
    if (empty($record['enabled'])) {
        return 'paused';
    }

    if (!mattrics_foundation_connector_has_credential($connectorKey, $record)) {
        return 'paused';
    }

    $lastSuccessAt = mattrics_foundation_connector_latest_success_at($record);
    $lastErrorAt = mattrics_foundation_connector_latest_error_at($record);

    if ($lastErrorAt !== null && ($lastSuccessAt === null || strcmp($lastErrorAt, $lastSuccessAt) > 0)) {
        return 'error';
    }

    if ($lastSuccessAt !== null) {
        return 'active';
    }

    return 'ready';
}

function mattrics_foundation_connector_public_record(string $connectorKey, array $record): array
{
    $definitions = mattrics_foundation_connector_definitions();
    $definition = $definitions[$connectorKey] ?? null;
    if (!is_array($definition)) {
        throw new RuntimeException('Unknown connector key: ' . $connectorKey);
    }

    $syncState = is_array($record['syncState'] ?? null)
        ? $record['syncState']
        : mattrics_foundation_default_connector_sync_state($connectorKey);
    $testState = is_array($record['testState'] ?? null)
        ? $record['testState']
        : mattrics_foundation_default_connector_test_state();

    return [
        'sourceKey' => (string) $definition['sourceKey'],
        'sourceKind' => (string) $definition['sourceKind'],
        'authType' => (string) $record['authType'],
        'enabled' => !empty($record['enabled']),
        'hasCredential' => mattrics_foundation_connector_has_credential($connectorKey, $record),
        'connectionStatus' => mattrics_foundation_connector_status($connectorKey, $record),
        'lastSyncAttemptAt' => $syncState['lastAttemptAt'] ?? null,
        'lastSyncSucceededAt' => $syncState['lastSucceededAt'] ?? null,
        'lastErrorAt' => mattrics_foundation_connector_latest_error_at($record),
        'lastTestAttemptAt' => $testState['lastAttemptAt'] ?? null,
        'lastTestSucceededAt' => $testState['lastSucceededAt'] ?? null,
        'updatedAt' => $record['updatedAt'] ?? null,
        'syncStrategy' => $syncState['lastStrategy'] ?? null,
        'overlapDays' => $syncState['overlapDays'] ?? null,
        'cursorStartedAt' => $syncState['cursorStartedAt'] ?? null,
        'lastSyncWindowStartedAt' => $syncState['lastSyncWindowStartedAt'] ?? null,
        'lastFetchedPageCount' => (int) ($syncState['lastFetchedPageCount'] ?? 0),
        'lastFetchedWorkoutCount' => (int) ($syncState['lastFetchedWorkoutCount'] ?? 0),
        'lastNewestFetchedStartedAt' => $syncState['lastNewestFetchedStartedAt'] ?? null,
        'lastOldestFetchedStartedAt' => $syncState['lastOldestFetchedStartedAt'] ?? null,
        'sync' => [
            'lastAttemptAt' => $syncState['lastAttemptAt'] ?? null,
            'lastSucceededAt' => $syncState['lastSucceededAt'] ?? null,
            'lastErrorAt' => $syncState['lastErrorAt'] ?? null,
            'strategy' => $syncState['lastStrategy'] ?? null,
            'overlapDays' => $syncState['overlapDays'] ?? null,
            'cursorStartedAt' => $syncState['cursorStartedAt'] ?? null,
            'lastSyncWindowStartedAt' => $syncState['lastSyncWindowStartedAt'] ?? null,
            'lastFetchedPageCount' => (int) ($syncState['lastFetchedPageCount'] ?? 0),
            'lastFetchedWorkoutCount' => (int) ($syncState['lastFetchedWorkoutCount'] ?? 0),
            'lastNewestFetchedStartedAt' => $syncState['lastNewestFetchedStartedAt'] ?? null,
            'lastOldestFetchedStartedAt' => $syncState['lastOldestFetchedStartedAt'] ?? null,
        ],
        'test' => [
            'lastAttemptAt' => $testState['lastAttemptAt'] ?? null,
            'lastSucceededAt' => $testState['lastSucceededAt'] ?? null,
            'lastErrorAt' => $testState['lastErrorAt'] ?? null,
        ],
    ];
}

function mattrics_foundation_connector_public_payload(?string $privateRoot = null): array
{
    $store = mattrics_foundation_load_connector_store($privateRoot);
    $payload = [];

    foreach (($store['connectors'] ?? []) as $connectorKey => $record) {
        $payload[$connectorKey] = mattrics_foundation_connector_public_record($connectorKey, $record);
    }

    return $payload;
}

function mattrics_foundation_safe_connector_public_payload(?string $privateRoot = null): array
{
    try {
        return mattrics_foundation_connector_public_payload($privateRoot);
    } catch (Throwable $throwable) {
        $payload = [];
        foreach (array_keys(mattrics_foundation_connector_definitions()) as $connectorKey) {
            $payload[$connectorKey] = mattrics_foundation_connector_public_record(
                $connectorKey,
                mattrics_foundation_default_connector_record($connectorKey)
            );
        }

        return $payload;
    }
}

function mattrics_foundation_connector_record_for_update(
    array $store,
    string $connectorKey,
    callable $mutator
): array {
    $normalized = mattrics_foundation_normalize_connector_store($store);
    $record = $normalized['connectors'][$connectorKey] ?? mattrics_foundation_default_connector_record($connectorKey);
    $updated = $mutator($record);
    if (!is_array($updated)) {
        throw new RuntimeException('Connector mutator must return an array.');
    }

    $updated['updatedAt'] = gmdate('c');
    $normalized['connectors'][$connectorKey] = mattrics_foundation_normalize_connector_record($connectorKey, $updated);

    return $normalized;
}
