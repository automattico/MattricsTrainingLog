<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

mattrics_require_method('GET');

$config = mattrics_load_config();
$sheetUrl = trim((string) ($config['sheet_url'] ?? ''));
$sheetToken = trim((string) ($config['sheet_token'] ?? ''));

if ($sheetUrl === '' || $sheetToken === '') {
    mattrics_send_json(['error' => 'sheet_url and sheet_token must be configured on the server.'], 500);
}

$payload = mattrics_fetch_json(mattrics_build_url($sheetUrl, ['key' => $sheetToken]));
$rows = $payload['rows'] ?? null;

if (!is_array($rows)) {
    mattrics_send_json(['error' => 'Sheet response did not contain rows.'], 502);
}

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

$filteredRows = array_map(static function ($row) use ($allowedFields) {
    if (!is_array($row)) {
        return [];
    }

    return array_intersect_key($row, $allowedFields);
}, $rows);

mattrics_send_json([
    'rows' => $filteredRows,
    'count' => count($filteredRows),
]);
