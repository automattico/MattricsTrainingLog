<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/foundation-read.php';
require_once __DIR__ . '/foundation-diagnostics.php';
require_once dirname(__DIR__, 2) . '/scripts/lib/foundation-connectors.php';
require_once dirname(__DIR__, 2) . '/scripts/lib/foundation-import.php';

if (!mattrics_foundation_local_diagnostics_allowed()) {
    mattrics_send_json(['error' => 'Not found.'], 404);
}

mattrics_require_auth();

function mattrics_connectors_requester(): ?callable
{
    $requester = $GLOBALS['mattrics_test_connector_requester'] ?? null;
    return is_callable($requester) ? $requester : null;
}

function mattrics_connectors_payload(?string $privateRoot = null): array
{
    return [
        'connectors' => mattrics_foundation_connector_public_payload($privateRoot),
    ];
}

function mattrics_connectors_require_hevy_record(array $store): array
{
    $record = $store['connectors']['hevy'] ?? null;
    if (!is_array($record)) {
        throw new RuntimeException('Hevy connector record is missing.');
    }

    return $record;
}

function mattrics_connectors_save_hevy(array $store, array $body): array
{
    $enabled = !empty($body['enabled']);
    $apiKeyProvided = array_key_exists('apiKey', $body);
    $apiKey = $apiKeyProvided ? trim((string) ($body['apiKey'] ?? '')) : null;

    return mattrics_foundation_connector_record_for_update(
        $store,
        'hevy',
        static function (array $record) use ($enabled, $apiKeyProvided, $apiKey): array {
            $record['enabled'] = $enabled;
            if ($apiKeyProvided && $apiKey !== '') {
                $record['apiKey'] = $apiKey;
            }

            return $record;
        }
    );
}

function mattrics_connectors_clear_hevy(array $store): array
{
    return mattrics_foundation_connector_record_for_update(
        $store,
        'hevy',
        static function (array $record): array {
            $record['enabled'] = false;
            $record['apiKey'] = null;
            $record['syncState'] = mattrics_foundation_default_connector_sync_state('hevy');
            $record['testState'] = mattrics_foundation_default_connector_test_state();

            return $record;
        }
    );
}

function mattrics_connectors_test_hevy(array $store): array
{
    $record = mattrics_connectors_require_hevy_record($store);
    if (!mattrics_foundation_connector_has_credential('hevy', $record)) {
        mattrics_send_json(['error' => 'Hevy API key is not configured.'], 422);
    }

    $updatedStore = mattrics_foundation_update_connector_store_after_attempt(
        $store,
        'hevy',
        'testState',
        'attempt'
    );
    $updatedStore = mattrics_foundation_save_connector_store($updatedStore);

    try {
        mattrics_foundation_hevy_api_request(
            $updatedStore['connectors']['hevy'],
            '/v1/workouts',
            ['page' => 1, 'pageSize' => 1],
            mattrics_connectors_requester()
        );
        $updatedStore = mattrics_foundation_update_connector_store_after_attempt(
            $updatedStore,
            'hevy',
            'testState',
            'success'
        );
    } catch (Throwable $throwable) {
        $updatedStore = mattrics_foundation_update_connector_store_after_attempt(
            $updatedStore,
            'hevy',
            'testState',
            'error',
            [],
            mb_substr($throwable->getMessage(), 0, 500)
        );
        $updatedStore = mattrics_foundation_save_connector_store($updatedStore);
        mattrics_send_json(array_merge(
            ['error' => 'Hevy connection test failed.'],
            mattrics_connectors_payload()
        ), 502);
    }

    return mattrics_foundation_save_connector_store($updatedStore);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    mattrics_send_json(mattrics_connectors_payload());
}

if ($method !== 'POST') {
    header('Allow: GET, POST');
    mattrics_send_json(['error' => 'Method not allowed.'], 405);
}

mattrics_require_csrf();
$body = mattrics_read_json_body();
$connector = trim((string) ($body['connector'] ?? ''));
$action = trim((string) ($body['action'] ?? ''));

if ($connector !== 'hevy') {
    mattrics_send_json(['error' => 'Unsupported connector.'], 422);
}

if (!in_array($action, ['save', 'clear', 'test'], true)) {
    mattrics_send_json(['error' => 'Unsupported action.'], 422);
}

$store = mattrics_foundation_load_connector_store();

if ($action === 'save') {
    $saved = mattrics_connectors_save_hevy($store, $body);
    mattrics_foundation_save_connector_store($saved);
    mattrics_send_json(array_merge(
        ['savedAt' => gmdate('c')],
        mattrics_connectors_payload()
    ));
}

if ($action === 'clear') {
    $cleared = mattrics_connectors_clear_hevy($store);
    mattrics_foundation_save_connector_store($cleared);
    mattrics_send_json(array_merge(
        ['clearedAt' => gmdate('c')],
        mattrics_connectors_payload()
    ));
}

mattrics_connectors_test_hevy($store);
mattrics_send_json(array_merge(
    ['testedAt' => gmdate('c')],
    mattrics_connectors_payload()
));
