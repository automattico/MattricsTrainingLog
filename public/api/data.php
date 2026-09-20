<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/foundation-read.php';

mattrics_require_auth();
mattrics_require_method('GET');

$allowedFields = array_flip([
    'Date',
    'Type',
    'Name',
    'Distance (km)',
    'Duration (min)',
    'Elevation Gain (m)',
    'Avg HR',
    'Max HR',
    'Avg Pace (min/km)',
    'Avg Speed (km/h)',
    'Avg Cadence',
    'Description',
    'Device Name',
    'Activity ID',
    'Activity ID raw',
]);

function mattrics_filter_rows(array $rows, array $allowedFields): array
{
    return array_map(static function ($row) use ($allowedFields) {
        if (!is_array($row)) {
            return [];
        }

        return array_intersect_key($row, $allowedFields);
    }, $rows);
}

function mattrics_build_snapshot_response(array $snapshot, bool $stale = false, string $source = 'cache', ?string $warning = null): array
{
    $rows = $snapshot['rows'] ?? [];
    $meta = $snapshot['meta'] ?? [];

    return [
        'rows' => is_array($rows) ? $rows : [],
        'count' => (int) ($snapshot['count'] ?? (is_array($rows) ? count($rows) : 0)),
        'meta' => [
            'source' => $source,
            'stale' => $stale,
            'lastSuccessfulSyncAt' => $meta['lastSuccessfulSyncAt'] ?? null,
            'lastLiveAttemptAt' => $meta['lastLiveAttemptAt'] ?? null,
            'warning' => $warning,
            'sourceVersion' => $meta['sourceVersion'] ?? 1,
        ],
    ];
}

function mattrics_merge_warning_messages(?string ...$warnings): ?string
{
    $parts = [];
    foreach ($warnings as $warning) {
        $clean = trim((string) $warning);
        if ($clean !== '') {
            $parts[] = $clean;
        }
    }

    return $parts === [] ? null : implode(' ', $parts);
}

function mattrics_build_canonical_data_response(bool $includeChildren = false): array
{
    $rows = mattrics_foundation_read_activities();

    $response = [
        'rows' => $rows,
        'count' => count($rows),
        'meta' => [
            'source' => 'canonical',
            'stale' => false,
            'lastSuccessfulSyncAt' => mattrics_foundation_read_latest_successful_sync_at(),
            'lastLiveAttemptAt' => null,
            'warning' => null,
            'sourceVersion' => 1,
        ],
    ];

    if ($includeChildren) {
        $response['activityChildren'] = mattrics_foundation_read_activity_children();
    }

    return $response;
}

function mattrics_fetch_live_snapshot(array $allowedFields, ?array $previousSnapshot = null, ?string $warning = null): array
{
    $config = mattrics_load_config();
    $sheetUrl = trim((string) ($config['sheet_url'] ?? ''));
    $sheetToken = trim((string) ($config['sheet_token'] ?? ''));

    if ($sheetUrl === '' || $sheetToken === '') {
        mattrics_send_json(['error' => 'sheet_url and sheet_token must be configured on the server.'], 500);
    }

    $timestamp = gmdate('c');

    try {
        $payload = mattrics_request_json(mattrics_build_url($sheetUrl, ['key' => $sheetToken]));
        $rows = $payload['rows'] ?? null;
        if (!is_array($rows)) {
            throw new RuntimeException('Sheet response did not contain rows.');
        }

        $filteredRows = mattrics_filter_rows($rows, $allowedFields);
        $snapshot = [
            'rows' => $filteredRows,
            'count' => count($filteredRows),
            'meta' => [
                'lastSuccessfulSyncAt' => $timestamp,
                'lastLiveAttemptAt' => $timestamp,
                'sourceVersion' => 1,
            ],
        ];

        mattrics_write_snapshot($snapshot);
        return mattrics_build_snapshot_response($snapshot, false, 'live', $warning);
    } catch (Throwable $exception) {
        if ($previousSnapshot !== null) {
            $fallback = $previousSnapshot;
            $fallback['meta'] = is_array($fallback['meta'] ?? null) ? $fallback['meta'] : [];
            $fallback['meta']['lastLiveAttemptAt'] = $timestamp;
            mattrics_write_snapshot($fallback);

            return mattrics_build_snapshot_response(
                $fallback,
                true,
                'cache',
                mattrics_merge_warning_messages(
                    $warning,
                    'Live refresh failed. Showing the last successful sync instead.'
                )
            );
        }

        mattrics_send_json(['error' => $exception->getMessage()], 502);
    }
}

$canonicalEnabled = mattrics_foundation_read_enabled();
$includeChildren = isset($_GET['includeChildren']) && $_GET['includeChildren'] === '1';
$forceRefresh = isset($_GET['refresh']) && $_GET['refresh'] === '1';
$legacyForceRefresh = $forceRefresh && !$canonicalEnabled;
$canonicalFallbackWarning = null;

if ($canonicalEnabled) {
    try {
        mattrics_send_json(mattrics_build_canonical_data_response($includeChildren));
    } catch (Throwable $throwable) {
        $canonicalFallbackWarning = 'Canonical read failed. Showing legacy training data instead.';
    }
}

$snapshot = mattrics_read_snapshot();

if (!$legacyForceRefresh && $snapshot !== null) {
    mattrics_send_json(mattrics_build_snapshot_response($snapshot, false, 'cache', $canonicalFallbackWarning));
}

$response = mattrics_with_refresh_lock(static function () use ($allowedFields, $legacyForceRefresh, $snapshot, $canonicalFallbackWarning) {
    $latestSnapshot = mattrics_read_snapshot();

    if (!$legacyForceRefresh && $latestSnapshot !== null) {
        return mattrics_build_snapshot_response($latestSnapshot, false, 'cache', $canonicalFallbackWarning);
    }

    return mattrics_fetch_live_snapshot($allowedFields, $latestSnapshot ?: $snapshot, $canonicalFallbackWarning);
});

mattrics_send_json($response);
