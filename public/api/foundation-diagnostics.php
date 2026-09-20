<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/scripts/lib/foundation-connectors.php';

function mattrics_foundation_runtime_mode(): string
{
    $mode = trim((string) (getenv('MATTRICS_RUNTIME_MODE') ?: 'default'));
    return $mode !== '' ? $mode : 'default';
}

function mattrics_foundation_local_diagnostics_allowed(): bool
{
    return mattrics_foundation_runtime_mode() === 'foundation' || mattrics_is_local_host();
}

function mattrics_foundation_map_import_batch_metadata(?array $row): ?array
{
    if (!is_array($row)) {
        return null;
    }

    return [
        'batchKind' => trim((string) ($row['batch_kind'] ?? '')) !== '' ? (string) $row['batch_kind'] : null,
        'status' => trim((string) ($row['status'] ?? '')) !== '' ? (string) $row['status'] : null,
        'startedAt' => mattrics_foundation_read_iso_or_null($row['started_at'] ?? null),
        'finishedAt' => mattrics_foundation_read_iso_or_null($row['finished_at'] ?? null),
        'importedRowCount' => isset($row['imported_row_count']) ? (int) $row['imported_row_count'] : 0,
        'appliedActivityCount' => isset($row['applied_activity_count']) ? (int) $row['applied_activity_count'] : 0,
    ];
}

function mattrics_foundation_read_latest_import_batch(
    PDO $pdo,
    string $userId,
    ?string $dataSourceId = null,
    ?string $status = null
): ?array {
    $conditions = ['user_id = :user_id'];
    $params = ['user_id' => $userId];

    if ($dataSourceId !== null && trim($dataSourceId) !== '') {
        $conditions[] = 'data_source_id = :data_source_id';
        $params['data_source_id'] = $dataSourceId;
    }

    if ($status !== null && trim($status) !== '') {
        $conditions[] = 'status = :status';
        $params['status'] = $status;
    }

    $statement = $pdo->prepare(sprintf(
        <<<'SQL'
SELECT
    data_source_id,
    batch_kind,
    status,
    started_at,
    finished_at,
    imported_row_count,
    applied_activity_count,
    COALESCE(finished_at, updated_at, created_at) AS finished_marker
FROM mattrics.mattrics_import_batches
WHERE %s
ORDER BY COALESCE(finished_at, updated_at, created_at) DESC, created_at DESC, id DESC
LIMIT 1
SQL,
        implode(' AND ', $conditions)
    ));
    $statement->execute($params);
    $row = $statement->fetch();

    return is_array($row) ? $row : null;
}

function mattrics_foundation_empty_selection_diagnostics(): array
{
    $empty = [
        'activityCount' => 0,
        'selectedActivityCount' => 0,
        'suppressedActivityCount' => 0,
        'dedupeEligibleActivityCount' => 0,
        'nonDeterministicActivityCount' => 0,
    ];

    return [
        'directHevy' => $empty,
        'directGarmin' => $empty,
        'directConcept2' => $empty,
        'legacySnapshot' => $empty,
    ];
}

function mattrics_foundation_empty_import_diagnostics_payload(): array
{
    return [
        'summary' => [
            'sourceCount' => 0,
            'activityCount' => 0,
            'selectedActivityCount' => 0,
            'suppressedActivityCount' => 0,
            'latestSuccessfulImportAt' => null,
            'unresolvedActivityTypeCount' => 0,
            'metadataOverlayCount' => 0,
        ],
        'selectionDiagnostics' => mattrics_foundation_empty_selection_diagnostics(),
        'sources' => [],
    ];
}

function mattrics_foundation_import_diagnostics_source_group(string $sourceRole): ?string
{
    return match ($sourceRole) {
        'direct_hevy' => 'directHevy',
        'direct_garmin' => 'directGarmin',
        'direct_concept2' => 'directConcept2',
        'legacy_snapshot' => 'legacySnapshot',
        default => null,
    };
}

function mattrics_foundation_import_diagnostics_role_rank(string $sourceRole): int
{
    return match ($sourceRole) {
        'legacy_snapshot' => 0,
        'direct_hevy' => 1,
        'direct_garmin' => 2,
        'direct_concept2' => 3,
        default => 4,
    };
}

function mattrics_foundation_import_diagnostics_is_signature_family(string $sourceRole): bool
{
    return in_array($sourceRole, ['direct_hevy', 'direct_garmin', 'direct_concept2', 'legacy_snapshot'], true);
}

function mattrics_foundation_import_diagnostics_latest_activity_at(array $row): ?string
{
    $startedAt = mattrics_foundation_read_iso_or_null($row['started_at'] ?? null);
    if ($startedAt !== null) {
        return $startedAt;
    }

    $activityDate = trim((string) ($row['activity_date'] ?? ''));
    return $activityDate !== '' ? $activityDate : null;
}

function mattrics_foundation_import_diagnostics_read_batch_maps(PDO $pdo, string $userId): array
{
    $statement = $pdo->prepare(<<<'SQL'
SELECT
    data_source_id,
    batch_kind,
    status,
    started_at,
    finished_at,
    imported_row_count,
    applied_activity_count,
    COALESCE(finished_at, updated_at, created_at) AS finished_marker
FROM mattrics.mattrics_import_batches
WHERE user_id = :user_id
ORDER BY COALESCE(finished_at, updated_at, created_at) DESC, created_at DESC, id DESC
SQL);
    $statement->execute(['user_id' => $userId]);
    $rows = $statement->fetchAll();

    $latestBySourceId = [];
    $latestSuccessfulBySourceId = [];
    $latestSuccessfulImportAt = null;

    foreach ($rows as $row) {
        $dataSourceId = (string) ($row['data_source_id'] ?? '');
        if ($dataSourceId === '') {
            continue;
        }

        if (!isset($latestBySourceId[$dataSourceId])) {
            $latestBySourceId[$dataSourceId] = $row;
        }

        if (($row['status'] ?? '') === 'succeeded') {
            if (!isset($latestSuccessfulBySourceId[$dataSourceId])) {
                $latestSuccessfulBySourceId[$dataSourceId] = $row;
            }

            $finishedAt = mattrics_foundation_read_iso_or_null($row['finished_marker'] ?? null);
            if ($finishedAt !== null && ($latestSuccessfulImportAt === null || $finishedAt > $latestSuccessfulImportAt)) {
                $latestSuccessfulImportAt = $finishedAt;
            }
        }
    }

    return [
        'latestBySourceId' => $latestBySourceId,
        'latestSuccessfulBySourceId' => $latestSuccessfulBySourceId,
        'latestSuccessfulImportAt' => $latestSuccessfulImportAt,
    ];
}

function mattrics_foundation_build_import_diagnostics(PDO $pdo, string $userId, string $timezoneName): array
{
    $sourceStatement = $pdo->prepare(<<<'SQL'
SELECT
    id,
    source_key,
    source_kind,
    display_name,
    connection_status,
    is_active
FROM mattrics.mattrics_data_sources
WHERE user_id = :user_id
ORDER BY display_name ASC, created_at ASC, id ASC
SQL);
    $sourceStatement->execute(['user_id' => $userId]);
    $sourceRows = $sourceStatement->fetchAll();

    $activityRows = mattrics_foundation_read_all_activity_rows();
    $selectionState = mattrics_foundation_read_build_selection_state($activityRows, $timezoneName);
    $selectedIds = is_array($selectionState['selectedIds'] ?? null) ? $selectionState['selectedIds'] : [];
    $metadataDonorCountsBySourceId = is_array($selectionState['metadataDonorCountsBySourceId'] ?? null)
        ? $selectionState['metadataDonorCountsBySourceId']
        : [];
    $connectorPayloads = mattrics_foundation_safe_connector_public_payload();

    $batchMaps = mattrics_foundation_import_diagnostics_read_batch_maps($pdo, $userId);
    $selectionDiagnostics = mattrics_foundation_empty_selection_diagnostics();
    $sourcesById = [];

    foreach ($sourceRows as $sourceRow) {
        $sourceId = (string) ($sourceRow['id'] ?? '');
        if ($sourceId === '') {
            continue;
        }

        $latestBatchRow = $batchMaps['latestBySourceId'][$sourceId] ?? null;
        $latestSuccessfulBatchRow = $batchMaps['latestSuccessfulBySourceId'][$sourceId] ?? null;
        $sourceRole = mattrics_foundation_read_source_role($sourceRow);
        $sourcesById[$sourceId] = [
            'sourceKey' => (string) ($sourceRow['source_key'] ?? ''),
            'sourceKind' => (string) ($sourceRow['source_kind'] ?? ''),
            'displayName' => (string) ($sourceRow['display_name'] ?? ''),
            'connectionStatus' => (string) ($sourceRow['connection_status'] ?? ''),
            'isActive' => (bool) ($sourceRow['is_active'] ?? false),
            'authType' => null,
            'hasCredential' => false,
            'lastSyncAttemptAt' => null,
            'lastSyncSucceededAt' => null,
            'lastErrorAt' => null,
            'lastTestAttemptAt' => null,
            'lastTestSucceededAt' => null,
            'syncStrategy' => null,
            'overlapDays' => null,
            'cursorStartedAt' => null,
            'lastSyncWindowStartedAt' => null,
            'lastFetchedPageCount' => 0,
            'lastFetchedWorkoutCount' => 0,
            'lastNewestFetchedStartedAt' => null,
            'lastOldestFetchedStartedAt' => null,
            'sync' => null,
            'test' => null,
            'activityCount' => 0,
            'selectedActivityCount' => 0,
            'suppressedActivityCount' => 0,
            'dedupeEligibleActivityCount' => 0,
            'nonDeterministicActivityCount' => 0,
            'metadataDonorActivityCount' => 0,
            'latestActivityAt' => null,
            'latestSuccessfulImportAt' => mattrics_foundation_read_iso_or_null($latestSuccessfulBatchRow['finished_marker'] ?? null),
            'latestBatch' => mattrics_foundation_map_import_batch_metadata($latestBatchRow),
            'latestSuccessfulBatch' => mattrics_foundation_map_import_batch_metadata($latestSuccessfulBatchRow),
            'unresolvedActivityTypeCount' => 0,
            'unresolvedActivityTypesSample' => [],
            '_sourceRole' => $sourceRole,
            '_sampleLookup' => [],
        ];

        foreach ($connectorPayloads as $connectorPayload) {
            if (($connectorPayload['sourceKey'] ?? null) !== $sourcesById[$sourceId]['sourceKey']) {
                continue;
            }

            $sourcesById[$sourceId]['authType'] = $connectorPayload['authType'] ?? null;
            $sourcesById[$sourceId]['hasCredential'] = !empty($connectorPayload['hasCredential']);
            $sourcesById[$sourceId]['lastSyncAttemptAt'] = $connectorPayload['lastSyncAttemptAt'] ?? null;
            $sourcesById[$sourceId]['lastSyncSucceededAt'] = $connectorPayload['lastSyncSucceededAt'] ?? null;
            $sourcesById[$sourceId]['lastErrorAt'] = $connectorPayload['lastErrorAt'] ?? null;
            $sourcesById[$sourceId]['lastTestAttemptAt'] = $connectorPayload['lastTestAttemptAt'] ?? null;
            $sourcesById[$sourceId]['lastTestSucceededAt'] = $connectorPayload['lastTestSucceededAt'] ?? null;
            $sourcesById[$sourceId]['syncStrategy'] = $connectorPayload['syncStrategy'] ?? null;
            $sourcesById[$sourceId]['overlapDays'] = $connectorPayload['overlapDays'] ?? null;
            $sourcesById[$sourceId]['cursorStartedAt'] = $connectorPayload['cursorStartedAt'] ?? null;
            $sourcesById[$sourceId]['lastSyncWindowStartedAt'] = $connectorPayload['lastSyncWindowStartedAt'] ?? null;
            $sourcesById[$sourceId]['lastFetchedPageCount'] = (int) ($connectorPayload['lastFetchedPageCount'] ?? 0);
            $sourcesById[$sourceId]['lastFetchedWorkoutCount'] = (int) ($connectorPayload['lastFetchedWorkoutCount'] ?? 0);
            $sourcesById[$sourceId]['lastNewestFetchedStartedAt'] = $connectorPayload['lastNewestFetchedStartedAt'] ?? null;
            $sourcesById[$sourceId]['lastOldestFetchedStartedAt'] = $connectorPayload['lastOldestFetchedStartedAt'] ?? null;
            $sourcesById[$sourceId]['sync'] = is_array($connectorPayload['sync'] ?? null) ? $connectorPayload['sync'] : null;
            $sourcesById[$sourceId]['test'] = is_array($connectorPayload['test'] ?? null) ? $connectorPayload['test'] : null;
            break;
        }
    }

    foreach ($activityRows as $row) {
        $sourceId = (string) ($row['data_source_id'] ?? '');
        $activityId = (string) ($row['id'] ?? '');
        if ($sourceId === '' || $activityId === '' || !isset($sourcesById[$sourceId])) {
            continue;
        }

        $sourceRole = $sourcesById[$sourceId]['_sourceRole'];
        $group = mattrics_foundation_import_diagnostics_source_group($sourceRole);
        $signaturePayload = mattrics_foundation_read_activity_signature_payload($row, $timezoneName);
        $isSelected = isset($selectedIds[$activityId]);

        $sourcesById[$sourceId]['activityCount']++;
        if ($sourcesById[$sourceId]['latestActivityAt'] === null) {
            $sourcesById[$sourceId]['latestActivityAt'] = mattrics_foundation_import_diagnostics_latest_activity_at($row);
        }

        if ($isSelected) {
            $sourcesById[$sourceId]['selectedActivityCount']++;
        } else {
            $sourcesById[$sourceId]['suppressedActivityCount']++;
        }

        if (isset($metadataDonorCountsBySourceId[$sourceId])) {
            $sourcesById[$sourceId]['metadataDonorActivityCount'] = (int) $metadataDonorCountsBySourceId[$sourceId];
        }

        if (is_array($signaturePayload)) {
            $sourcesById[$sourceId]['dedupeEligibleActivityCount']++;
        } elseif (mattrics_foundation_import_diagnostics_is_signature_family($sourceRole)) {
            $sourcesById[$sourceId]['nonDeterministicActivityCount']++;
        }

        $activityTypeId = $row['activity_type_id'] ?? null;
        if ($activityTypeId === null || $activityTypeId === '') {
            $sourcesById[$sourceId]['unresolvedActivityTypeCount']++;
            $activityTypeName = trim((string) ($row['activity_type_name'] ?? ''));
            if (
                $activityTypeName !== ''
                && !isset($sourcesById[$sourceId]['_sampleLookup'][$activityTypeName])
                && count($sourcesById[$sourceId]['unresolvedActivityTypesSample']) < 5
            ) {
                $sourcesById[$sourceId]['_sampleLookup'][$activityTypeName] = true;
                $sourcesById[$sourceId]['unresolvedActivityTypesSample'][] = $activityTypeName;
            }
        }

        if ($group !== null) {
            $selectionDiagnostics[$group]['activityCount']++;
            if ($isSelected) {
                $selectionDiagnostics[$group]['selectedActivityCount']++;
            } else {
                $selectionDiagnostics[$group]['suppressedActivityCount']++;
            }

            if (is_array($signaturePayload)) {
                $selectionDiagnostics[$group]['dedupeEligibleActivityCount']++;
            } else {
                $selectionDiagnostics[$group]['nonDeterministicActivityCount']++;
            }
        }
    }

    $sources = array_values($sourcesById);
    usort($sources, static function (array $left, array $right): int {
        $leftRank = mattrics_foundation_import_diagnostics_role_rank((string) ($left['_sourceRole'] ?? 'other'));
        $rightRank = mattrics_foundation_import_diagnostics_role_rank((string) ($right['_sourceRole'] ?? 'other'));
        if ($leftRank !== $rightRank) {
            return $leftRank <=> $rightRank;
        }

        $displayCompare = strcasecmp((string) ($left['displayName'] ?? ''), (string) ($right['displayName'] ?? ''));
        if ($displayCompare !== 0) {
            return $displayCompare;
        }

        return strcmp((string) ($left['sourceKey'] ?? ''), (string) ($right['sourceKey'] ?? ''));
    });

    $summary = [
        'sourceCount' => count($sources),
        'activityCount' => 0,
        'selectedActivityCount' => 0,
        'suppressedActivityCount' => 0,
        'latestSuccessfulImportAt' => $batchMaps['latestSuccessfulImportAt'],
        'unresolvedActivityTypeCount' => 0,
        'metadataOverlayCount' => (int) ($selectionState['metadataOverlayCount'] ?? 0),
    ];

    foreach ($sources as &$source) {
        $summary['activityCount'] += (int) $source['activityCount'];
        $summary['selectedActivityCount'] += (int) $source['selectedActivityCount'];
        $summary['suppressedActivityCount'] += (int) $source['suppressedActivityCount'];
        $summary['unresolvedActivityTypeCount'] += (int) $source['unresolvedActivityTypeCount'];
        unset($source['_sourceRole'], $source['_sampleLookup']);
    }
    unset($source);

    return [
        'summary' => $summary,
        'selectionDiagnostics' => $selectionDiagnostics,
        'sources' => $sources,
    ];
}
