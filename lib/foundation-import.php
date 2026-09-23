<?php
declare(strict_types=1);

const MATTRICS_FOUNDATION_DEFAULT_DATABASE_URL = 'postgres://mattrics:mattrics-local-only-change-me@127.0.0.1:5433/mattrics';

if (!function_exists('mattrics_ensure_dir')) {
    function mattrics_ensure_dir(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (!mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException('Failed to create directory: ' . $path);
        }
    }
}

require_once __DIR__ . '/exercise-config-repository.php';
require_once __DIR__ . '/foundation-connectors.php';
require_once __DIR__ . '/foundation-migrations.php';
require_once __DIR__ . '/garmin-import-parser.php';
require_once __DIR__ . '/hevy-import-parser.php';

function mattrics_foundation_connect(string $databaseUrl): PDO
{
    $parts = parse_url($databaseUrl);
    if ($parts === false || ($parts['scheme'] ?? '') !== 'postgres') {
        throw new RuntimeException('DATABASE_URL must use the postgres:// scheme.');
    }

    $host = (string) ($parts['host'] ?? '');
    $port = (int) ($parts['port'] ?? 5432);
    $database = isset($parts['path']) ? ltrim($parts['path'], '/') : '';
    $user = rawurldecode((string) ($parts['user'] ?? ''));
    $password = rawurldecode((string) ($parts['pass'] ?? ''));

    if ($host === '' || $database === '' || $user === '') {
        throw new RuntimeException('DATABASE_URL must include host, database, and username.');
    }

    $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $port, $database);
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec("SET TIME ZONE 'UTC'");

    return $pdo;
}

function mattrics_foundation_execute_params(PDOStatement $statement, array $params): void
{
    foreach ($params as $name => $value) {
        $placeholder = str_starts_with((string) $name, ':') ? (string) $name : ':' . (string) $name;

        if (is_bool($value)) {
            $statement->bindValue($placeholder, $value, PDO::PARAM_BOOL);
            continue;
        }

        if ($value === null) {
            $statement->bindValue($placeholder, null, PDO::PARAM_NULL);
            continue;
        }

        if (is_int($value)) {
            $statement->bindValue($placeholder, $value, PDO::PARAM_INT);
            continue;
        }

        $statement->bindValue($placeholder, (string) $value, PDO::PARAM_STR);
    }

    $statement->execute();
}

function mattrics_foundation_build_import_summary(
    string $privateRoot,
    bool $dryRun,
    string $userKey,
    string $displayName,
    string $timezone
): array {
    return [
        'privateRoot' => $privateRoot,
        'dryRun' => $dryRun,
        'user' => [
            'externalKey' => $userKey,
            'displayName' => $displayName,
            'timezone' => $timezone,
        ],
        'sources' => [],
        'counts' => [
            'exercises' => 0,
            'exerciseAliases' => 0,
            'activityTypes' => 0,
            'activityTypeAliases' => 0,
            'activities' => 0,
            'hevyActivities' => 0,
            'hevyExerciseBlocks' => 0,
            'resolvedHevyExerciseBlocks' => 0,
            'unresolvedHevyExerciseBlocks' => 0,
            'parsedSets' => 0,
            'timeSets' => 0,
            'unknownSets' => 0,
        ],
        'unresolvedExercises' => [],
        'batches' => [],
    ];
}

function mattrics_foundation_read_exercise_config_records_from_root(string $privateRoot): array
{
    return mattrics_read_config_collection(
        rtrim($privateRoot, '/') . '/data/exercise-configs.json',
        'Exercise',
        'mattrics_validate_exercise_config_record'
    );
}

function mattrics_foundation_read_activity_type_config_records_from_root(string $privateRoot): array
{
    return mattrics_read_config_collection(
        rtrim($privateRoot, '/') . '/data/activity-type-configs.json',
        'Activity type',
        'mattrics_validate_activity_type_config_record'
    );
}

function mattrics_foundation_sync_connector_sources(PDO $pdo, string $userId, array $connectorStore): array
{
    $sources = [];
    foreach (mattrics_foundation_connector_definitions() as $connectorKey => $definition) {
        $record = $connectorStore['connectors'][$connectorKey] ?? mattrics_foundation_default_connector_record($connectorKey);
        $publicRecord = mattrics_foundation_connector_public_record($connectorKey, $record);
        $sourceId = mattrics_foundation_ensure_data_source(
            $pdo,
            $userId,
            (string) $definition['sourceKey'],
            (string) $definition['sourceKind'],
            (string) $definition['displayName'],
            [
                'connectionStatus' => $publicRecord['connectionStatus'],
                'isActive' => true,
                'lastSyncedAt' => $publicRecord['lastSyncSucceededAt'],
                'notes' => null,
            ]
        );

        $sources[$connectorKey] = [
            'dataSourceId' => $sourceId,
            'publicRecord' => $publicRecord,
        ];
    }

    return $sources;
}

function mattrics_foundation_hevy_live_sync_state_has_history(array $connectorRecord): bool
{
    $syncState = is_array($connectorRecord['syncState'] ?? null) ? $connectorRecord['syncState'] : [];

    foreach ([
        'lastAttemptAt',
        'lastSucceededAt',
        'lastErrorAt',
        'lastError',
        'lastStrategy',
        'lastSyncWindowStartedAt',
        'cursorStartedAt',
        'lastRemoteMarkerAt',
        'lastNewestFetchedStartedAt',
        'lastOldestFetchedStartedAt',
    ] as $key) {
        if (trim((string) ($syncState[$key] ?? '')) !== '') {
            return true;
        }
    }

    return (int) ($syncState['lastFetchedPageCount'] ?? 0) > 0
        || (int) ($syncState['lastFetchedWorkoutCount'] ?? 0) > 0;
}

function mattrics_foundation_hevy_live_anchor_started_at(array $connectorRecord): ?DateTimeImmutable
{
    $syncState = is_array($connectorRecord['syncState'] ?? null) ? $connectorRecord['syncState'] : [];
    foreach ([
        $syncState['cursorStartedAt'] ?? null,
        $syncState['lastNewestFetchedStartedAt'] ?? null,
        $syncState['lastRemoteMarkerAt'] ?? null,
        $syncState['lastSucceededAt'] ?? null,
    ] as $candidate) {
        $date = mattrics_foundation_hevy_live_iso_or_null($candidate);
        if ($date instanceof DateTimeImmutable) {
            return $date;
        }
    }

    return null;
}

function mattrics_foundation_hevy_live_fetch_plan(array $connectorRecord, array $options = []): array
{
    $syncState = is_array($connectorRecord['syncState'] ?? null) ? $connectorRecord['syncState'] : [];
    $overlapDays = isset($options['overlapDays']) && is_numeric($options['overlapDays'])
        ? max(1, (int) $options['overlapDays'])
        : max(1, (int) ($syncState['overlapDays'] ?? MATTRICS_FOUNDATION_HEVY_INCREMENTAL_OVERLAP_DAYS));
    $hasHistory = mattrics_foundation_hevy_live_sync_state_has_history($connectorRecord);
    $anchor = mattrics_foundation_hevy_live_anchor_started_at($connectorRecord);

    if (!$hasHistory) {
        return [
            'strategy' => 'initial_full',
            'overlapDays' => $overlapDays,
            'cursorStartedAt' => null,
            'lastSyncWindowStartedAt' => null,
            'refetchNotBefore' => null,
        ];
    }

    if (!($anchor instanceof DateTimeImmutable)) {
        $anchor = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    $refetchNotBefore = $anchor->modify('-' . $overlapDays . ' days');

    return [
        'strategy' => 'incremental_overlap',
        'overlapDays' => $overlapDays,
        'cursorStartedAt' => $anchor->setTimezone(new DateTimeZone('UTC'))->format('c'),
        'lastSyncWindowStartedAt' => $refetchNotBefore->setTimezone(new DateTimeZone('UTC'))->format('c'),
        'refetchNotBefore' => $refetchNotBefore,
    ];
}

function mattrics_foundation_extract_hevy_items(array $response): array
{
    foreach (['workouts', 'data', 'items'] as $key) {
        if (is_array($response[$key] ?? null)) {
            return array_values(array_filter($response[$key], 'is_array'));
        }
    }

    if (array_is_list($response)) {
        return array_values(array_filter($response, 'is_array'));
    }

    return [];
}

function mattrics_foundation_hevy_response_has_next_page(array $response, int $page, int $pageSize, int $itemCount): bool
{
    foreach (['page_count', 'pageCount', 'total_pages', 'totalPages'] as $key) {
        if (isset($response[$key]) && is_numeric($response[$key])) {
            return $page < (int) $response[$key];
        }
    }

    foreach (['next_page', 'nextPage'] as $key) {
        if (isset($response[$key])) {
            return $response[$key] !== null && $response[$key] !== false && $response[$key] !== '';
        }
    }

    return $itemCount >= $pageSize;
}

function mattrics_foundation_fetch_hevy_live_workouts(array $connectorRecord, array $options = []): array
{
    $requester = isset($options['requester']) && is_callable($options['requester'])
        ? $options['requester']
        : null;
    $pageSize = max(1, (int) ($options['pageSize'] ?? 100));
    $maxPages = max(1, (int) ($options['maxPages'] ?? 50));
    $fetchPlan = mattrics_foundation_hevy_live_fetch_plan($connectorRecord, $options);
    $refetchNotBefore = $fetchPlan['refetchNotBefore'] ?? null;
    $workouts = [];
    $fetchedPageCount = 0;
    $newestFetchedStartedAt = null;
    $oldestFetchedStartedAt = null;

    for ($page = 1; $page <= $maxPages; $page++) {
        $listResponse = mattrics_foundation_hevy_api_request(
            $connectorRecord,
            '/v1/workouts',
            ['page' => $page, 'pageSize' => $pageSize],
            $requester
        );
        $fetchedPageCount++;
        $pageItems = mattrics_foundation_extract_hevy_items($listResponse);
        $pageAllOlderThanWindow = $refetchNotBefore instanceof DateTimeImmutable && $pageItems !== [];
        foreach ($pageItems as $item) {
            $workoutId = trim((string) ($item['id'] ?? $item['workout_id'] ?? ''));
            if ($workoutId === '') {
                continue;
            }

            $detail = $item;
            if (!is_array($detail['exercises'] ?? null)) {
                $detail = mattrics_foundation_hevy_api_request(
                    $connectorRecord,
                    '/v1/workouts/' . rawurlencode($workoutId),
                    [],
                    $requester
                );
            }

            $startedAt = mattrics_foundation_hevy_live_iso_or_null(
                $detail['start_time'] ?? $detail['startTime'] ?? $item['start_time'] ?? $item['startTime'] ?? null
            );
            if ($startedAt instanceof DateTimeImmutable) {
                $startedAtUtc = $startedAt->setTimezone(new DateTimeZone('UTC'))->format('c');
                if ($newestFetchedStartedAt === null || strcmp($startedAtUtc, $newestFetchedStartedAt) > 0) {
                    $newestFetchedStartedAt = $startedAtUtc;
                }
                if ($oldestFetchedStartedAt === null || strcmp($startedAtUtc, $oldestFetchedStartedAt) < 0) {
                    $oldestFetchedStartedAt = $startedAtUtc;
                }
                if ($refetchNotBefore instanceof DateTimeImmutable && $startedAt >= $refetchNotBefore) {
                    $pageAllOlderThanWindow = false;
                }
            } else {
                $pageAllOlderThanWindow = false;
            }

            $workouts[] = $detail;
        }

        if (($fetchPlan['strategy'] ?? '') === 'incremental_overlap' && $pageAllOlderThanWindow) {
            break;
        }

        if (!mattrics_foundation_hevy_response_has_next_page($listResponse, $page, $pageSize, count($pageItems))) {
            break;
        }
    }

    return [
        'workouts' => $workouts,
        'strategy' => (string) ($fetchPlan['strategy'] ?? 'initial_full'),
        'overlapDays' => (int) ($fetchPlan['overlapDays'] ?? MATTRICS_FOUNDATION_HEVY_INCREMENTAL_OVERLAP_DAYS),
        'cursorStartedAt' => $fetchPlan['cursorStartedAt'] ?? null,
        'lastSyncWindowStartedAt' => $fetchPlan['lastSyncWindowStartedAt'] ?? null,
        'fetchedPageCount' => $fetchedPageCount,
        'fetchedWorkoutCount' => count($workouts),
        'newestFetchedStartedAt' => $newestFetchedStartedAt,
        'oldestFetchedStartedAt' => $oldestFetchedStartedAt,
    ];
}

function mattrics_foundation_hevy_live_iso_or_null(mixed $value): ?DateTimeImmutable
{
    $text = trim((string) $value);
    if ($text === '') {
        return null;
    }

    try {
        return new DateTimeImmutable($text);
    } catch (Throwable $throwable) {
        return null;
    }
}

function mattrics_foundation_hevy_live_map_set(array $set): array
{
    $distanceKm = mattrics_foundation_nullable_float(
        $set['distance_km'] ?? $set['distanceKm'] ?? $set['km'] ?? null
    );
    $durationSeconds = mattrics_foundation_nullable_float(
        $set['duration_seconds'] ?? $set['durationSeconds'] ?? $set['seconds'] ?? null
    );

    return [
        'setType' => trim((string) ($set['type'] ?? $set['set_type'] ?? $set['setType'] ?? 'normal')),
        'weightKg' => mattrics_foundation_nullable_float($set['weight_kg'] ?? $set['weightKg'] ?? null),
        'reps' => mattrics_foundation_nullable_int($set['reps'] ?? null),
        'distanceKm' => $distanceKm,
        'durationSeconds' => $durationSeconds,
        'rpe' => mattrics_foundation_nullable_float($set['rpe'] ?? null),
    ];
}

function mattrics_foundation_hevy_live_map_exercise(array $exercise): ?array
{
    $sourceExerciseName = trim((string) (
        $exercise['title']
        ?? $exercise['name']
        ?? $exercise['exercise_title']
        ?? $exercise['exerciseTitle']
        ?? ($exercise['exercise_template']['title'] ?? '')
        ?? ($exercise['exerciseTemplate']['title'] ?? '')
    ));
    if ($sourceExerciseName === '') {
        return null;
    }

    $sets = [];
    foreach (($exercise['sets'] ?? []) as $set) {
        if (is_array($set)) {
            $sets[] = mattrics_foundation_hevy_live_map_set($set);
        }
    }

    return [
        'exerciseTitle' => $sourceExerciseName,
        'exerciseNotes' => trim((string) ($exercise['notes'] ?? $exercise['exercise_notes'] ?? '')),
        'supersetId' => trim((string) ($exercise['superset_id'] ?? $exercise['supersetId'] ?? '')),
        'sets' => $sets,
    ];
}

function mattrics_foundation_map_hevy_live_workout(array $workout, string $timezone): ?array
{
    $workoutId = trim((string) ($workout['id'] ?? $workout['workout_id'] ?? ''));
    if ($workoutId === '') {
        return null;
    }

    $title = trim((string) ($workout['title'] ?? $workout['name'] ?? ''));
    if ($title === '') {
        $title = 'Hevy Workout';
    }

    $startedAt = mattrics_foundation_hevy_live_iso_or_null(
        $workout['start_time'] ?? $workout['started_at'] ?? $workout['startedAt'] ?? null
    );
    if (!$startedAt instanceof DateTimeImmutable) {
        return null;
    }

    $endedAt = mattrics_foundation_hevy_live_iso_or_null(
        $workout['end_time'] ?? $workout['ended_at'] ?? $workout['endedAt'] ?? null
    );
    $durationMinutes = mattrics_foundation_nullable_float(
        $workout['duration_minutes'] ?? $workout['durationMinutes'] ?? null
    );
    if ($durationMinutes === null) {
        $durationSeconds = mattrics_foundation_nullable_float(
            $workout['duration_seconds'] ?? $workout['durationSeconds'] ?? null
        );
        if ($durationSeconds !== null) {
            $durationMinutes = round($durationSeconds / 60, 3);
        } elseif ($endedAt instanceof DateTimeImmutable) {
            $durationMinutes = round(
                max(0, $endedAt->getTimestamp() - $startedAt->getTimestamp()) / 60,
                3
            );
        }
    }

    $exerciseRows = [];
    foreach (($workout['exercises'] ?? []) as $exercise) {
        if (!is_array($exercise)) {
            continue;
        }

        $mapped = mattrics_foundation_hevy_live_map_exercise($exercise);
        if ($mapped !== null) {
            $exerciseRows[] = $mapped;
        }
    }

    $timezoneObject = new DateTimeZone($timezone);
    $startedAtUtc = $startedAt->setTimezone(new DateTimeZone('UTC'));
    $activityDate = $startedAt->setTimezone($timezoneObject)->format('Y-m-d');

    return [
        'title' => $title,
        'description' => trim((string) ($workout['description'] ?? $workout['notes'] ?? '')),
        'activityDate' => $activityDate,
        'startedAt' => $startedAtUtc->format('c'),
        'durationMinutes' => $durationMinutes,
        'sourceActivityId' => $workoutId,
        'sourceActivityIdRaw' => $workoutId,
        'exercises' => $exerciseRows,
    ];
}

function mattrics_foundation_run_import(PDO $pdo, array $options = []): array
{
    mattrics_foundation_apply_migrations($pdo);

    $privateRoot = rtrim((string) ($options['privateRoot'] ?? mattrics_private_root()), '/');

    $userKey = trim((string) ($options['userKey'] ?? 'legacy-local-user'));
    $displayName = trim((string) ($options['displayName'] ?? 'Legacy Local User'));
    $timezone = trim((string) ($options['timezone'] ?? 'Europe/Berlin'));
    $dryRun = !empty($options['dryRun']);

    if ($userKey === '' || $displayName === '' || $timezone === '') {
        throw new RuntimeException('User key, display name, and timezone are required.');
    }

    $summary = mattrics_foundation_build_import_summary($privateRoot, $dryRun, $userKey, $displayName, $timezone);

    $batchStates = [];
    $pdo->beginTransaction();
    try {
        $userId = mattrics_foundation_ensure_user($pdo, $userKey, $displayName, $timezone);

        $exerciseRecords = mattrics_foundation_read_exercise_config_records_from_root($privateRoot);
        $activityTypeRecords = mattrics_foundation_read_activity_type_config_records_from_root($privateRoot);
        $snapshot = mattrics_foundation_read_snapshot($privateRoot . '/cache/training-data.json');

        $sources = [
            'exerciseConfigs' => [
                'sourceKey' => 'legacy-exercise-configs',
                'sourceKind' => 'manual',
                'displayName' => 'Legacy Exercise Config Catalog',
                'batchKind' => 'manual_seed',
                'relativePath' => 'data/exercise-configs.json',
                'records' => $exerciseRecords,
            ],
            'activityTypeConfigs' => [
                'sourceKey' => 'legacy-activity-type-configs',
                'sourceKind' => 'manual',
                'displayName' => 'Legacy Activity Type Config Catalog',
                'batchKind' => 'manual_seed',
                'relativePath' => 'data/activity-type-configs.json',
                'records' => $activityTypeRecords,
            ],
            'googleSheetSnapshot' => [
                'sourceKey' => 'legacy-google-sheet-snapshot',
                'sourceKind' => 'google_sheet',
                'displayName' => 'Legacy Google Sheet Snapshot',
                'batchKind' => 'google_sheet_snapshot',
                'relativePath' => 'cache/training-data.json',
                'records' => $snapshot['rows'],
            ],
        ];

        foreach ($sources as $key => $source) {
            $dataSourceId = mattrics_foundation_ensure_data_source(
                $pdo,
                $userId,
                $source['sourceKey'],
                $source['sourceKind'],
                $source['displayName']
            );

            $batch = mattrics_foundation_start_import_batch(
                $pdo,
                $userId,
                $dataSourceId,
                $source['batchKind'],
                $privateRoot . '/' . $source['relativePath'],
                $source['relativePath']
            );

            $sources[$key]['dataSourceId'] = $dataSourceId;
            $sources[$key]['batchId'] = $batch['id'];
            $summary['sources'][$key] = [
                'sourceKey' => $source['sourceKey'],
                'dataSourceId' => $dataSourceId,
                'batchId' => $batch['id'],
                'relativePath' => $source['relativePath'],
            ];
            $batchStates[$key] = $batch;
        }

        $exerciseIndex = mattrics_foundation_import_exercises(
            $pdo,
            $userId,
            $sources['exerciseConfigs']['dataSourceId'],
            $exerciseRecords,
            $summary
        );
        mattrics_foundation_finish_import_batch(
            $pdo,
            $sources['exerciseConfigs']['batchId'],
            count($exerciseRecords),
            0,
            'succeeded'
        );

        $activityTypeIndex = mattrics_foundation_import_activity_types(
            $pdo,
            $userId,
            $sources['activityTypeConfigs']['dataSourceId'],
            $activityTypeRecords,
            $summary
        );
        mattrics_foundation_finish_import_batch(
            $pdo,
            $sources['activityTypeConfigs']['batchId'],
            count($activityTypeRecords),
            0,
            'succeeded'
        );

        $activityImport = mattrics_foundation_import_activities(
            $pdo,
            $userId,
            $sources['googleSheetSnapshot']['dataSourceId'],
            $sources['googleSheetSnapshot']['batchId'],
            $snapshot['rows'],
            $activityTypeIndex,
            $exerciseIndex,
            $summary
        );
        mattrics_foundation_finish_import_batch(
            $pdo,
            $sources['googleSheetSnapshot']['batchId'],
            count($snapshot['rows']),
            $activityImport['appliedActivityCount'],
            'succeeded'
        );

        $summary['batches'] = [
            'exerciseConfigs' => ['status' => 'succeeded', 'importedRowCount' => count($exerciseRecords), 'appliedActivityCount' => 0],
            'activityTypeConfigs' => ['status' => 'succeeded', 'importedRowCount' => count($activityTypeRecords), 'appliedActivityCount' => 0],
            'googleSheetSnapshot' => ['status' => 'succeeded', 'importedRowCount' => count($snapshot['rows']), 'appliedActivityCount' => $activityImport['appliedActivityCount']],
        ];

        if ($dryRun) {
            $pdo->rollBack();
        } else {
            $pdo->commit();
        }

        return $summary;
    } catch (Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        try {
            foreach ($batchStates as $key => $batch) {
                if (!isset($batch['id'])) {
                    continue;
                }

                mattrics_foundation_finish_import_batch(
                    $pdo,
                    $batch['id'],
                    0,
                    0,
                    'failed',
                    mb_substr($throwable->getMessage(), 0, 500)
                );
            }
        } catch (Throwable $ignored) {
            // Ignore follow-up status write failures after the main import error.
        }

        throw $throwable;
    }
}

function mattrics_foundation_run_hevy_import(PDO $pdo, array $options = []): array
{
    mattrics_foundation_apply_migrations($pdo);

    $privateRoot = rtrim((string) ($options['privateRoot'] ?? mattrics_private_root()), '/');

    $userKey = trim((string) ($options['userKey'] ?? 'legacy-local-user'));
    $displayName = trim((string) ($options['displayName'] ?? 'Legacy Local User'));
    $timezone = trim((string) ($options['timezone'] ?? 'Europe/Berlin'));
    $dryRun = !empty($options['dryRun']);
    $inputOption = (string) ($options['input'] ?? 'import-hevy/workouts.csv');

    if ($userKey === '' || $displayName === '' || $timezone === '') {
        throw new RuntimeException('User key, display name, and timezone are required.');
    }

    $input = mattrics_foundation_resolve_private_input_path($inputOption, $privateRoot);
    $summary = mattrics_foundation_build_import_summary($privateRoot, $dryRun, $userKey, $displayName, $timezone);
    $summary['input'] = [
        'relativePath' => $input['relativePath'],
    ];

    $batchStates = [];
    $pdo->beginTransaction();
    try {
        $userId = mattrics_foundation_ensure_user($pdo, $userKey, $displayName, $timezone);

        $exerciseRecords = mattrics_foundation_read_exercise_config_records_from_root($privateRoot);
        $activityTypeRecords = mattrics_foundation_read_activity_type_config_records_from_root($privateRoot);

        $exerciseSourceId = mattrics_foundation_ensure_data_source(
            $pdo,
            $userId,
            'legacy-exercise-configs',
            'manual',
            'Legacy Exercise Config Catalog'
        );
        $activityTypeSourceId = mattrics_foundation_ensure_data_source(
            $pdo,
            $userId,
            'legacy-activity-type-configs',
            'manual',
            'Legacy Activity Type Config Catalog'
        );
        $hevySourceId = mattrics_foundation_ensure_data_source(
            $pdo,
            $userId,
            'hevy-workout-export',
            'hevy',
            'Hevy Workout Export'
        );

        $exerciseIndex = mattrics_foundation_import_exercises(
            $pdo,
            $userId,
            $exerciseSourceId,
            $exerciseRecords,
            $summary
        );
        $activityTypeIndex = mattrics_foundation_import_activity_types(
            $pdo,
            $userId,
            $activityTypeSourceId,
            $activityTypeRecords,
            $summary
        );

        $hevyBatch = mattrics_foundation_start_import_batch(
            $pdo,
            $userId,
            $hevySourceId,
            'hevy_export',
            $input['absolutePath'],
            $input['relativePath']
        );
        $batchStates['hevyExport'] = $hevyBatch;
        $summary['sources']['hevyExport'] = [
            'sourceKey' => 'hevy-workout-export',
            'dataSourceId' => $hevySourceId,
            'batchId' => $hevyBatch['id'],
            'relativePath' => $input['relativePath'],
        ];

        $parsed = mattrics_foundation_read_hevy_csv_workouts($input['absolutePath'], $timezone);
        $importResult = mattrics_foundation_import_hevy_workouts(
            $pdo,
            $userId,
            $hevySourceId,
            $hevyBatch['id'],
            $parsed['workouts'],
            $activityTypeIndex,
            $exerciseIndex,
            $summary
        );

        mattrics_foundation_finish_import_batch(
            $pdo,
            $hevyBatch['id'],
            $parsed['rowCount'],
            $importResult['appliedActivityCount'],
            'succeeded'
        );
        $summary['batches']['hevyExport'] = [
            'status' => 'succeeded',
            'importedRowCount' => $parsed['rowCount'],
            'appliedActivityCount' => $importResult['appliedActivityCount'],
        ];

        if ($dryRun) {
            $pdo->rollBack();
        } else {
            $pdo->commit();
        }

        return $summary;
    } catch (Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        try {
            foreach ($batchStates as $batch) {
                if (!isset($batch['id'])) {
                    continue;
                }

                mattrics_foundation_finish_import_batch(
                    $pdo,
                    $batch['id'],
                    0,
                    0,
                    'failed',
                    mb_substr($throwable->getMessage(), 0, 500)
                );
            }
        } catch (Throwable $ignored) {
            // Ignore follow-up status write failures after the main import error.
        }

        throw $throwable;
    }
}

function mattrics_foundation_run_garmin_import(PDO $pdo, array $options = []): array
{
    mattrics_foundation_apply_migrations($pdo);

    $privateRoot = rtrim((string) ($options['privateRoot'] ?? mattrics_private_root()), '/');

    $userKey = trim((string) ($options['userKey'] ?? 'legacy-local-user'));
    $displayName = trim((string) ($options['displayName'] ?? 'Legacy Local User'));
    $timezone = trim((string) ($options['timezone'] ?? 'Europe/Berlin'));
    $dryRun = !empty($options['dryRun']);
    $inputOption = (string) ($options['input'] ?? 'import-garmin/Activities.csv');

    if ($userKey === '' || $displayName === '' || $timezone === '') {
        throw new RuntimeException('User key, display name, and timezone are required.');
    }

    $input = mattrics_foundation_resolve_private_input_path($inputOption, $privateRoot);
    $summary = mattrics_foundation_build_import_summary($privateRoot, $dryRun, $userKey, $displayName, $timezone);
    $summary['input'] = [
        'relativePath' => $input['relativePath'],
    ];

    $batchStates = [];
    $pdo->beginTransaction();
    try {
        $userId = mattrics_foundation_ensure_user($pdo, $userKey, $displayName, $timezone);

        $activityTypeRecords = mattrics_foundation_read_activity_type_config_records_from_root($privateRoot);
        $activityTypeSourceId = mattrics_foundation_ensure_data_source(
            $pdo,
            $userId,
            'legacy-activity-type-configs',
            'manual',
            'Legacy Activity Type Config Catalog'
        );
        $garminSourceId = mattrics_foundation_ensure_data_source(
            $pdo,
            $userId,
            'garmin-activities-csv-export',
            'garmin',
            'Garmin Activities CSV Export'
        );

        $activityTypeIndex = mattrics_foundation_import_activity_types(
            $pdo,
            $userId,
            $activityTypeSourceId,
            $activityTypeRecords,
            $summary
        );

        $garminBatch = mattrics_foundation_start_import_batch(
            $pdo,
            $userId,
            $garminSourceId,
            'garmin_export',
            $input['absolutePath'],
            $input['relativePath']
        );
        $batchStates['garminExport'] = $garminBatch;
        $summary['sources']['garminExport'] = [
            'sourceKey' => 'garmin-activities-csv-export',
            'dataSourceId' => $garminSourceId,
            'batchId' => $garminBatch['id'],
            'relativePath' => $input['relativePath'],
        ];

        $parsed = mattrics_foundation_read_garmin_csv_activities($input['absolutePath'], $timezone);
        $importResult = mattrics_foundation_import_garmin_activities(
            $pdo,
            $userId,
            $garminSourceId,
            $garminBatch['id'],
            $parsed['activities'],
            $activityTypeIndex,
            $summary
        );

        mattrics_foundation_finish_import_batch(
            $pdo,
            $garminBatch['id'],
            $parsed['rowCount'],
            $importResult['appliedActivityCount'],
            'succeeded'
        );
        $summary['batches']['garminExport'] = [
            'status' => 'succeeded',
            'importedRowCount' => $parsed['rowCount'],
            'appliedActivityCount' => $importResult['appliedActivityCount'],
        ];

        if ($dryRun) {
            $pdo->rollBack();
        } else {
            $pdo->commit();
        }

        return $summary;
    } catch (Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        try {
            foreach ($batchStates as $batch) {
                if (!isset($batch['id'])) {
                    continue;
                }

                mattrics_foundation_finish_import_batch(
                    $pdo,
                    $batch['id'],
                    0,
                    0,
                    'failed',
                    mb_substr($throwable->getMessage(), 0, 500)
                );
            }
        } catch (Throwable $ignored) {
            // Ignore follow-up status write failures after the main import error.
        }

        throw $throwable;
    }
}

function mattrics_foundation_run_live_connector_sync(PDO $pdo, array $options = []): array
{
    mattrics_foundation_apply_migrations($pdo);

    $privateRoot = rtrim((string) ($options['privateRoot'] ?? mattrics_private_root()), '/');

    $userKey = trim((string) ($options['userKey'] ?? 'legacy-local-user'));
    $displayName = trim((string) ($options['displayName'] ?? 'Legacy Local User'));
    $timezone = trim((string) ($options['timezone'] ?? 'Europe/Berlin'));
    $dryRun = !empty($options['dryRun']);
    $requester = isset($options['requester']) && is_callable($options['requester'])
        ? $options['requester']
        : null;

    if ($userKey === '' || $displayName === '' || $timezone === '') {
        throw new RuntimeException('User key, display name, and timezone are required.');
    }

    $summary = mattrics_foundation_build_import_summary($privateRoot, $dryRun, $userKey, $displayName, $timezone);
    $connectorStore = mattrics_foundation_load_connector_store($privateRoot);
    $summary['connectors'] = mattrics_foundation_connector_public_payload($privateRoot);

    $userId = mattrics_foundation_ensure_user($pdo, $userKey, $displayName, $timezone);
    $sourceState = mattrics_foundation_sync_connector_sources($pdo, $userId, $connectorStore);
    foreach ($sourceState as $connectorKey => $state) {
        $summary['sources'][$connectorKey] = [
            'sourceKey' => (string) ($state['publicRecord']['sourceKey'] ?? ''),
            'dataSourceId' => (string) ($state['dataSourceId'] ?? ''),
            'batchId' => null,
            'relativePath' => null,
        ];
    }

    $hevyRecord = $connectorStore['connectors']['hevy'] ?? mattrics_foundation_default_connector_record('hevy');
    if (empty($hevyRecord['enabled']) || !mattrics_foundation_connector_has_credential('hevy', $hevyRecord)) {
        $summary['batches']['hevyLive'] = [
            'status' => 'skipped',
            'importedRowCount' => 0,
            'appliedActivityCount' => 0,
        ];
        return $summary;
    }

    $updatedStore = mattrics_foundation_update_connector_store_after_attempt(
        $connectorStore,
        'hevy',
        'syncState',
        'attempt'
    );
    if (!$dryRun) {
        $updatedStore = mattrics_foundation_save_connector_store($updatedStore, $privateRoot);
    }

    $sourceId = (string) ($sourceState['hevy']['dataSourceId'] ?? '');
    $batchId = null;

    try {
        $fetchResult = mattrics_foundation_fetch_hevy_live_workouts(
            $updatedStore['connectors']['hevy'],
            ['requester' => $requester]
        );
        $rawWorkouts = is_array($fetchResult['workouts'] ?? null) ? $fetchResult['workouts'] : [];
        $normalizedWorkouts = [];
        foreach ($rawWorkouts as $workout) {
            if (!is_array($workout)) {
                continue;
            }

            $mapped = mattrics_foundation_map_hevy_live_workout($workout, $timezone);
            if ($mapped !== null) {
                $normalizedWorkouts[] = $mapped;
            }
        }

        $pdo->beginTransaction();
        $exerciseRecords = mattrics_foundation_read_exercise_config_records_from_root($privateRoot);
        $activityTypeRecords = mattrics_foundation_read_activity_type_config_records_from_root($privateRoot);
        $exerciseSourceId = mattrics_foundation_ensure_data_source(
            $pdo,
            $userId,
            'legacy-exercise-configs',
            'manual',
            'Legacy Exercise Config Catalog'
        );
        $activityTypeSourceId = mattrics_foundation_ensure_data_source(
            $pdo,
            $userId,
            'legacy-activity-type-configs',
            'manual',
            'Legacy Activity Type Config Catalog'
        );

        $exerciseIndex = mattrics_foundation_import_exercises(
            $pdo,
            $userId,
            $exerciseSourceId,
            $exerciseRecords,
            $summary
        );
        $activityTypeIndex = mattrics_foundation_import_activity_types(
            $pdo,
            $userId,
            $activityTypeSourceId,
            $activityTypeRecords,
            $summary
        );

        $hevyBatch = mattrics_foundation_start_import_batch(
            $pdo,
            $userId,
            $sourceId,
            'hevy_live_api_sync'
        );
        $batchId = (string) ($hevyBatch['id'] ?? '');
        $summary['sources']['hevy']['batchId'] = $batchId;

        $importResult = mattrics_foundation_import_hevy_workouts(
            $pdo,
            $userId,
            $sourceId,
            $batchId,
            $normalizedWorkouts,
            $activityTypeIndex,
            $exerciseIndex,
            $summary
        );

        mattrics_foundation_finish_import_batch(
            $pdo,
            $batchId,
            count($normalizedWorkouts),
            $importResult['appliedActivityCount'],
            'succeeded'
        );
        $summary['batches']['hevyLive'] = [
            'status' => 'succeeded',
            'importedRowCount' => count($normalizedWorkouts),
            'appliedActivityCount' => $importResult['appliedActivityCount'],
            'strategy' => $fetchResult['strategy'] ?? null,
            'overlapDays' => $fetchResult['overlapDays'] ?? null,
            'cursorStartedAt' => $fetchResult['cursorStartedAt'] ?? null,
            'lastSyncWindowStartedAt' => $fetchResult['lastSyncWindowStartedAt'] ?? null,
            'fetchedPageCount' => $fetchResult['fetchedPageCount'] ?? 0,
            'fetchedWorkoutCount' => $fetchResult['fetchedWorkoutCount'] ?? 0,
            'newestFetchedStartedAt' => $fetchResult['newestFetchedStartedAt'] ?? null,
            'oldestFetchedStartedAt' => $fetchResult['oldestFetchedStartedAt'] ?? null,
        ];

        if ($dryRun) {
            $pdo->rollBack();
        } else {
            $pdo->commit();
            $latestSeenStartedAt = $fetchResult['newestFetchedStartedAt']
                ?? (($updatedStore['connectors']['hevy']['syncState']['cursorStartedAt'] ?? null) ?: null);
            $updatedStore = mattrics_foundation_update_connector_store_after_attempt(
                $updatedStore,
                'hevy',
                'syncState',
                'success',
                [
                    'lastStrategy' => $fetchResult['strategy'] ?? null,
                    'overlapDays' => $fetchResult['overlapDays'] ?? MATTRICS_FOUNDATION_HEVY_INCREMENTAL_OVERLAP_DAYS,
                    'lastSyncWindowStartedAt' => $fetchResult['lastSyncWindowStartedAt'] ?? null,
                    'cursorStartedAt' => $latestSeenStartedAt,
                    'lastRemoteMarkerAt' => $latestSeenStartedAt,
                    'lastFetchedPageCount' => (int) ($fetchResult['fetchedPageCount'] ?? 0),
                    'lastFetchedWorkoutCount' => (int) ($fetchResult['fetchedWorkoutCount'] ?? 0),
                    'lastNewestFetchedStartedAt' => $fetchResult['newestFetchedStartedAt'] ?? null,
                    'lastOldestFetchedStartedAt' => $fetchResult['oldestFetchedStartedAt'] ?? null,
                ]
            );
            $updatedStore = mattrics_foundation_save_connector_store($updatedStore, $privateRoot);
            mattrics_foundation_sync_connector_sources($pdo, $userId, $updatedStore);
            $summary['connectors'] = mattrics_foundation_connector_public_payload($privateRoot);
        }

        return $summary;
    } catch (Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($batchId !== null && $batchId !== '') {
            try {
                mattrics_foundation_finish_import_batch(
                    $pdo,
                    $batchId,
                    0,
                    0,
                    'failed',
                    mb_substr($throwable->getMessage(), 0, 500)
                );
            } catch (Throwable $ignored) {
                // Ignore follow-up batch status failures after the main live sync error.
            }
        }

        if (!$dryRun) {
            $updatedStore = mattrics_foundation_update_connector_store_after_attempt(
                $updatedStore,
                'hevy',
                'syncState',
                'error',
                [],
                mb_substr($throwable->getMessage(), 0, 500)
            );
            $updatedStore = mattrics_foundation_save_connector_store($updatedStore, $privateRoot);
            mattrics_foundation_sync_connector_sources($pdo, $userId, $updatedStore);
        }

        throw $throwable;
    }
}

function mattrics_foundation_resolve_private_input_path(string $input, string $privateRoot): array
{
    $input = trim($input);
    if ($input === '') {
        throw new RuntimeException('Import input path is required.');
    }

    $privateRootReal = realpath($privateRoot);
    if ($privateRootReal === false || !is_dir($privateRootReal)) {
        throw new RuntimeException('Private root is not available.');
    }

    $candidate = str_starts_with($input, '/')
        ? $input
        : $privateRootReal . '/' . ltrim($input, '/');
    $resolved = realpath($candidate);

    if ($resolved === false || !is_file($resolved)) {
        throw new RuntimeException('Import input file is missing.');
    }

    $privateRootNormalized = rtrim(str_replace('\\', '/', $privateRootReal), '/');
    $resolvedNormalized = str_replace('\\', '/', $resolved);
    if ($resolvedNormalized !== $privateRootNormalized
        && !str_starts_with($resolvedNormalized, $privateRootNormalized . '/')
    ) {
        throw new RuntimeException('Import input must live inside the private root.');
    }

    $relativePath = ltrim(substr($resolvedNormalized, strlen($privateRootNormalized)), '/');
    if ($relativePath === '') {
        throw new RuntimeException('Import input must resolve to a file inside the private root.');
    }

    return [
        'absolutePath' => $resolvedNormalized,
        'relativePath' => $relativePath,
    ];
}

function mattrics_foundation_read_snapshot(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException('Snapshot file is missing: ' . $path);
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException('Failed to read snapshot file.');
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Snapshot file is not valid JSON.');
    }

    $rows = $decoded['rows'] ?? null;
    if (!is_array($rows) || !array_is_list($rows)) {
        throw new RuntimeException('Snapshot rows must be a JSON list.');
    }

    return [
        'rows' => $rows,
        'count' => (int) ($decoded['count'] ?? count($rows)),
        'meta' => is_array($decoded['meta'] ?? null) ? $decoded['meta'] : [],
    ];
}

function mattrics_foundation_is_valid_utf8(string $value): bool
{
    if (function_exists('mb_check_encoding')) {
        return mb_check_encoding($value, 'UTF-8');
    }

    return preg_match('//u', $value) === 1;
}

function mattrics_foundation_assert_utf8(string $value, string $field, int $rowNumber): string
{
    if (!mattrics_foundation_is_valid_utf8($value)) {
        throw new RuntimeException('Hevy CSV field is not valid UTF-8: ' . $field . ' (row ' . $rowNumber . ').');
    }

    return $value;
}

function mattrics_foundation_read_hevy_csv_workouts(string $path, string $timezone): array
{
    $handle = fopen($path, 'rb');
    if (!is_resource($handle)) {
        throw new RuntimeException('Failed to open Hevy CSV input.');
    }

    try {
        $header = fgetcsv($handle, 0, ',', '"', '\\');
        if (!is_array($header) || $header === []) {
            throw new RuntimeException('Hevy CSV file is empty.');
        }

        $header = array_map(static function (mixed $value): string {
            return (string) $value;
        }, $header);
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]) ?? $header[0];
        $header = mattrics_foundation_validate_hevy_csv_headers($header);

        $workouts = [];
        $rowCount = 0;
        $csvRowNumber = 1;
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $csvRowNumber++;
            $rowCount++;

            if (!is_array($row)) {
                throw new RuntimeException('Hevy CSV row could not be read at row ' . $csvRowNumber . '.');
            }

            if (count($row) !== count($header)) {
                throw new RuntimeException('Hevy CSV row has the wrong number of columns at row ' . $csvRowNumber . '.');
            }

            $record = array_combine($header, $row);
            if (!is_array($record)) {
                throw new RuntimeException('Hevy CSV row could not be mapped at row ' . $csvRowNumber . '.');
            }

            $title = trim(mattrics_foundation_assert_utf8((string) $record['title'], 'title', $csvRowNumber));
            $startTimeText = trim((string) $record['start_time']);
            $endTimeText = trim((string) $record['end_time']);
            $exerciseTitle = trim(mattrics_foundation_assert_utf8((string) $record['exercise_title'], 'exercise_title', $csvRowNumber));
            $description = trim(mattrics_foundation_assert_utf8((string) $record['description'], 'description', $csvRowNumber));
            $exerciseNotes = trim(mattrics_foundation_assert_utf8((string) $record['exercise_notes'], 'exercise_notes', $csvRowNumber));
            $supersetId = trim((string) $record['superset_id']);
            $setType = trim((string) $record['set_type']);

            if ($title === '' || $startTimeText === '' || $endTimeText === '' || $exerciseTitle === '') {
                throw new RuntimeException('Hevy CSV row is missing required workout fields at row ' . $csvRowNumber . '.');
            }

            $groupKey = mattrics_foundation_build_hevy_csv_group_key($title, $startTimeText, $endTimeText);
            if (!isset($workouts[$groupKey])) {
                $startTime = mattrics_foundation_parse_hevy_csv_datetime($startTimeText, $timezone, $csvRowNumber, 'start_time');
                $endTime = mattrics_foundation_parse_hevy_csv_datetime($endTimeText, $timezone, $csvRowNumber, 'end_time');
                $durationMinutes = max(0.0, ($endTime->getTimestamp() - $startTime->getTimestamp()) / 60);

                $workouts[$groupKey] = [
                    'title' => $title,
                    'startTimeText' => $startTimeText,
                    'endTimeText' => $endTimeText,
                    'activityDate' => $startTime->format('Y-m-d'),
                    'startedAt' => $startTime->setTimezone(new DateTimeZone('UTC'))->format('c'),
                    'durationMinutes' => $durationMinutes,
                    'description' => $description,
                    'sourceActivityIdRaw' => mattrics_foundation_build_hevy_csv_activity_id($title, $startTimeText, $endTimeText),
                    'exercises' => [],
                    '_lastExerciseSignature' => null,
                ];
            } elseif ($description !== '' && $workouts[$groupKey]['description'] === '') {
                $workouts[$groupKey]['description'] = $description;
            }

            $exerciseSignature = hash(
                'sha256',
                json_encode(
                    [$exerciseTitle, $supersetId, $exerciseNotes],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ) ?: ($exerciseTitle . '|' . $supersetId . '|' . $exerciseNotes)
            );

            if (($workouts[$groupKey]['_lastExerciseSignature'] ?? null) !== $exerciseSignature) {
                $workouts[$groupKey]['exercises'][] = [
                    'exerciseTitle' => $exerciseTitle,
                    'exerciseNotes' => $exerciseNotes,
                    'supersetId' => $supersetId !== '' ? $supersetId : null,
                    'sets' => [],
                ];
                $workouts[$groupKey]['_lastExerciseSignature'] = $exerciseSignature;
            }

            $exerciseIndex = count($workouts[$groupKey]['exercises']) - 1;
            $workouts[$groupKey]['exercises'][$exerciseIndex]['sets'][] = [
                'rowNumber' => $csvRowNumber,
                'setIndex' => mattrics_foundation_nullable_int($record['set_index'] ?? null),
                'setType' => $setType,
                'weightKg' => mattrics_foundation_nullable_float($record['weight_kg'] ?? null),
                'reps' => mattrics_foundation_nullable_int($record['reps'] ?? null),
                'distanceKm' => mattrics_foundation_nullable_float($record['distance_km'] ?? null),
                'durationSeconds' => mattrics_foundation_nullable_float($record['duration_seconds'] ?? null),
                'rpe' => mattrics_foundation_nullable_float($record['rpe'] ?? null),
            ];
        }

        foreach ($workouts as &$workout) {
            unset($workout['_lastExerciseSignature']);
        }
        unset($workout);

        return [
            'rowCount' => $rowCount,
            'workouts' => array_values($workouts),
        ];
    } finally {
        fclose($handle);
    }
}

function mattrics_foundation_validate_hevy_csv_headers(array $headers): array
{
    $expected = [
        'title',
        'start_time',
        'end_time',
        'description',
        'exercise_title',
        'superset_id',
        'exercise_notes',
        'set_index',
        'set_type',
        'weight_kg',
        'reps',
        'distance_km',
        'duration_seconds',
        'rpe',
    ];

    if ($headers !== $expected) {
        throw new RuntimeException('Hevy CSV headers do not match the supported export format.');
    }

    return $headers;
}

function mattrics_foundation_build_hevy_csv_group_key(string $title, string $startTimeText, string $endTimeText): string
{
    return hash(
        'sha256',
        json_encode(
            [$title, $startTimeText, $endTimeText],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) ?: ($title . '|' . $startTimeText . '|' . $endTimeText)
    );
}

function mattrics_foundation_build_hevy_csv_activity_id(string $title, string $startTimeText, string $endTimeText): string
{
    return 'hevy-csv-' . mattrics_foundation_build_hevy_csv_group_key($title, $startTimeText, $endTimeText);
}

function mattrics_foundation_parse_hevy_csv_datetime(
    string $value,
    string $timezone,
    int $rowNumber,
    string $field
): DateTimeImmutable {
    $dateTime = DateTimeImmutable::createFromFormat('d.m.Y, H:i', $value, new DateTimeZone($timezone));
    $errors = DateTimeImmutable::getLastErrors();
    $hasErrors = is_array($errors)
        && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0);

    if (!$dateTime instanceof DateTimeImmutable || $hasErrors) {
        throw new RuntimeException('Hevy CSV timestamp is invalid for ' . $field . ' at row ' . $rowNumber . '.');
    }

    return $dateTime;
}

function mattrics_foundation_ensure_user(PDO $pdo, string $externalKey, string $displayName, string $timezone): string
{
    $statement = $pdo->prepare(<<<'SQL'
INSERT INTO mattrics.mattrics_users (external_key, display_name, timezone_name)
VALUES (:external_key, :display_name, :timezone_name)
ON CONFLICT (external_key) DO UPDATE
SET display_name = EXCLUDED.display_name,
    timezone_name = EXCLUDED.timezone_name,
    is_active = true
RETURNING id
SQL);
    $statement->execute([
        'external_key' => $externalKey,
        'display_name' => $displayName,
        'timezone_name' => $timezone,
    ]);

    return (string) $statement->fetchColumn();
}

function mattrics_foundation_ensure_data_source(
    PDO $pdo,
    string $userId,
    string $sourceKey,
    string $sourceKind,
    string $displayName,
    array $options = []
): string
{
    $connectionStatus = trim((string) ($options['connectionStatus'] ?? 'active'));
    if ($connectionStatus === '') {
        $connectionStatus = 'active';
    }

    $isActive = array_key_exists('isActive', $options) ? !empty($options['isActive']) : true;
    $lastSyncedAt = mattrics_foundation_null_if_blank($options['lastSyncedAt'] ?? null);
    $notes = mattrics_foundation_null_if_blank($options['notes'] ?? null);

    $statement = $pdo->prepare(<<<'SQL'
INSERT INTO mattrics.mattrics_data_sources (
    user_id,
    source_key,
    source_kind,
    display_name,
    connection_status,
    is_active,
    last_synced_at,
    notes
)
VALUES (
    :user_id,
    :source_key,
    :source_kind,
    :display_name,
    :connection_status,
    :is_active,
    :last_synced_at,
    :notes
)
ON CONFLICT (user_id, source_key) DO UPDATE
SET source_kind = EXCLUDED.source_kind,
    display_name = EXCLUDED.display_name,
    connection_status = EXCLUDED.connection_status,
    is_active = EXCLUDED.is_active,
    last_synced_at = EXCLUDED.last_synced_at,
    notes = EXCLUDED.notes
RETURNING id
SQL);
    mattrics_foundation_execute_params($statement, [
        'user_id' => $userId,
        'source_key' => $sourceKey,
        'source_kind' => $sourceKind,
        'display_name' => $displayName,
        'connection_status' => $connectionStatus,
        'is_active' => $isActive,
        'last_synced_at' => $lastSyncedAt,
        'notes' => $notes,
    ]);

    return (string) $statement->fetchColumn();
}

function mattrics_foundation_start_import_batch(
    PDO $pdo,
    string $userId,
    string $dataSourceId,
    string $batchKind,
    ?string $absolutePath = null,
    ?string $relativePath = null,
    ?string $sourceBatchKey = null
): array {
    $relativePath = $relativePath !== null ? trim($relativePath) : null;
    if ($sourceBatchKey === null && $absolutePath !== null && $relativePath !== null && $relativePath !== '') {
        $sourceBatchKey = mattrics_foundation_build_source_batch_key($absolutePath, $relativePath);
    }

    $startedAt = gmdate('c');
    $statement = $pdo->prepare(<<<'SQL'
INSERT INTO mattrics.mattrics_import_batches (
    user_id,
    data_source_id,
    batch_kind,
    status,
    source_batch_key,
    private_payload_path,
    started_at,
    finished_at,
    imported_row_count,
    applied_activity_count,
    error_summary
)
VALUES (
    :user_id,
    :data_source_id,
    :batch_kind,
    'running',
    :source_batch_key,
    :private_payload_path,
    :started_at,
    NULL,
    0,
    0,
    NULL
)
ON CONFLICT (data_source_id, source_batch_key) WHERE source_batch_key IS NOT NULL DO UPDATE
SET batch_kind = EXCLUDED.batch_kind,
    status = 'running',
    private_payload_path = EXCLUDED.private_payload_path,
    started_at = EXCLUDED.started_at,
    finished_at = NULL,
    imported_row_count = 0,
    applied_activity_count = 0,
    error_summary = NULL
RETURNING id, source_batch_key
SQL);
    $statement->execute([
        'user_id' => $userId,
        'data_source_id' => $dataSourceId,
        'batch_kind' => $batchKind,
        'source_batch_key' => $sourceBatchKey,
        'private_payload_path' => $relativePath,
        'started_at' => $startedAt,
    ]);

    $row = $statement->fetch();
    if (!is_array($row)) {
        throw new RuntimeException('Failed to create import batch.');
    }

    return [
        'id' => (string) $row['id'],
        'sourceBatchKey' => (string) $row['source_batch_key'],
    ];
}

function mattrics_foundation_finish_import_batch(
    PDO $pdo,
    string $batchId,
    int $importedRowCount,
    int $appliedActivityCount,
    string $status,
    ?string $errorSummary = null
): void {
    $statement = $pdo->prepare(<<<'SQL'
UPDATE mattrics.mattrics_import_batches
SET status = :status,
    finished_at = timezone('utc', now()),
    imported_row_count = :imported_row_count,
    applied_activity_count = :applied_activity_count,
    error_summary = :error_summary
WHERE id = :id
SQL);
    $statement->execute([
        'id' => $batchId,
        'status' => $status,
        'imported_row_count' => $importedRowCount,
        'applied_activity_count' => $appliedActivityCount,
        'error_summary' => $errorSummary,
    ]);
}

function mattrics_foundation_build_source_batch_key(string $absolutePath, string $relativePath): string
{
    if (!is_file($absolutePath)) {
        throw new RuntimeException('Import source file is missing: ' . $relativePath);
    }

    $size = filesize($absolutePath);
    $mtime = filemtime($absolutePath);
    if ($size === false || $mtime === false) {
        throw new RuntimeException('Failed to stat import source file: ' . $relativePath);
    }

    return $relativePath . '|' . $size . '|' . $mtime;
}

function mattrics_foundation_import_exercises(PDO $pdo, string $userId, string $dataSourceId, array $records, array &$summary): array
{
    $upsert = $pdo->prepare(<<<'SQL'
INSERT INTO mattrics.mattrics_exercises (
    user_id,
    legacy_config_id,
    canonical_name,
    normalized_name,
    fatigue_impact,
    fatigue_multiplier,
    bodyweight_eligible,
    set_type_handling,
    config_source,
    last_updated_at,
    last_updated_type,
    exercise_family,
    fatigue_archetype,
    chest_weight,
    deltoids_weight,
    trapezius_weight,
    upper_back_weight,
    triceps_weight,
    biceps_weight,
    abs_weight,
    obliques_weight,
    lower_back_weight,
    gluteal_weight,
    adductors_weight,
    quadriceps_weight,
    hamstrings_weight,
    calves_weight
)
VALUES (
    :user_id,
    :legacy_config_id,
    :canonical_name,
    :normalized_name,
    :fatigue_impact,
    :fatigue_multiplier,
    :bodyweight_eligible,
    :set_type_handling,
    :config_source,
    :last_updated_at,
    :last_updated_type,
    :exercise_family,
    :fatigue_archetype,
    :chest_weight,
    :deltoids_weight,
    :trapezius_weight,
    :upper_back_weight,
    :triceps_weight,
    :biceps_weight,
    :abs_weight,
    :obliques_weight,
    :lower_back_weight,
    :gluteal_weight,
    :adductors_weight,
    :quadriceps_weight,
    :hamstrings_weight,
    :calves_weight
)
ON CONFLICT (user_id, normalized_name) DO UPDATE
SET legacy_config_id = EXCLUDED.legacy_config_id,
    canonical_name = EXCLUDED.canonical_name,
    fatigue_impact = EXCLUDED.fatigue_impact,
    fatigue_multiplier = EXCLUDED.fatigue_multiplier,
    bodyweight_eligible = EXCLUDED.bodyweight_eligible,
    set_type_handling = EXCLUDED.set_type_handling,
    config_source = EXCLUDED.config_source,
    last_updated_at = EXCLUDED.last_updated_at,
    last_updated_type = EXCLUDED.last_updated_type,
    exercise_family = EXCLUDED.exercise_family,
    fatigue_archetype = EXCLUDED.fatigue_archetype,
    chest_weight = EXCLUDED.chest_weight,
    deltoids_weight = EXCLUDED.deltoids_weight,
    trapezius_weight = EXCLUDED.trapezius_weight,
    upper_back_weight = EXCLUDED.upper_back_weight,
    triceps_weight = EXCLUDED.triceps_weight,
    biceps_weight = EXCLUDED.biceps_weight,
    abs_weight = EXCLUDED.abs_weight,
    obliques_weight = EXCLUDED.obliques_weight,
    lower_back_weight = EXCLUDED.lower_back_weight,
    gluteal_weight = EXCLUDED.gluteal_weight,
    adductors_weight = EXCLUDED.adductors_weight,
    quadriceps_weight = EXCLUDED.quadriceps_weight,
    hamstrings_weight = EXCLUDED.hamstrings_weight,
    calves_weight = EXCLUDED.calves_weight
RETURNING id
SQL);
    $deleteAliases = $pdo->prepare('DELETE FROM mattrics.mattrics_exercise_aliases WHERE exercise_id = :exercise_id');
    $insertAlias = $pdo->prepare(<<<'SQL'
INSERT INTO mattrics.mattrics_exercise_aliases (
    user_id,
    exercise_id,
    alias_kind,
    alias_name,
    normalized_alias_name
)
VALUES (:user_id, :exercise_id, :alias_kind, :alias_name, :normalized_alias_name)
SQL);

    $index = [
        'byNormalized' => [],
        'byAlias' => [],
        'matchTerms' => [],
    ];

    foreach ($records as $record) {
        $params = array_merge(
            [
                'user_id' => $userId,
                'legacy_config_id' => (string) $record['id'],
                'canonical_name' => (string) $record['canonicalName'],
                'normalized_name' => (string) $record['normalizedName'],
                'fatigue_impact' => (string) ($record['fatigueImpact'] ?? 'normal'),
                'fatigue_multiplier' => (float) ($record['fatigueMultiplier'] ?? 1.0),
                'bodyweight_eligible' => (bool) ($record['bodyweightEligible'] ?? false),
                'set_type_handling' => (string) ($record['setTypeHandling'] ?? 'weight_reps'),
                'config_source' => (string) ($record['source'] ?? 'manual'),
                'last_updated_at' => mattrics_foundation_parse_optional_timestamp($record['lastUpdatedAt'] ?? null),
                'last_updated_type' => (string) ($record['lastUpdatedType'] ?? 'manual'),
                'exercise_family' => (string) ($record['exerciseFamily'] ?? 'conditioning_lower'),
                'fatigue_archetype' => (string) ($record['fatigueArchetype'] ?? 'conditioning_hybrid'),
            ],
            mattrics_foundation_map_muscle_weights($record['muscleWeights'] ?? [])
        );
        mattrics_foundation_execute_params($upsert, $params);
        $exerciseId = (string) $upsert->fetchColumn();

        $deleteAliases->execute(['exercise_id' => $exerciseId]);
        $aliasRows = mattrics_foundation_build_exercise_alias_rows($record);
        foreach ($aliasRows as $aliasRow) {
            $insertAlias->execute([
                'user_id' => $userId,
                'exercise_id' => $exerciseId,
                'alias_kind' => $aliasRow['aliasKind'],
                'alias_name' => $aliasRow['aliasName'],
                'normalized_alias_name' => $aliasRow['normalizedAliasName'],
            ]);
        }

        $summary['counts']['exercises']++;
        $summary['counts']['exerciseAliases'] += count($aliasRows);

        $indexedRecord = $record + ['dbId' => $exerciseId, 'dataSourceId' => $dataSourceId];
        $index['byNormalized'][(string) $record['normalizedName']] = $indexedRecord;
        foreach (($record['aliases'] ?? []) as $alias) {
            $index['byAlias'][mattrics_normalize_config_name((string) $alias)] = $indexedRecord;
        }
        foreach (($record['matchTerms'] ?? []) as $term) {
            $normalizedTerm = mattrics_normalize_config_name((string) $term);
            if ($normalizedTerm !== '') {
                $index['matchTerms'][] = ['term' => $normalizedTerm, 'record' => $indexedRecord];
            }
        }
    }

    usort($index['matchTerms'], static function (array $left, array $right): int {
        return strlen($right['term']) <=> strlen($left['term']);
    });

    return $index;
}

function mattrics_foundation_import_activity_types(PDO $pdo, string $userId, string $dataSourceId, array $records, array &$summary): array
{
    $upsert = $pdo->prepare(<<<'SQL'
INSERT INTO mattrics.mattrics_activity_types (
    user_id,
    legacy_config_id,
    canonical_name,
    normalized_name,
    status,
    review_needed,
    config_source,
    last_updated_at,
    last_updated_type,
    exercise_family,
    fatigue_archetype,
    fatigue_multiplier,
    chest_weight,
    deltoids_weight,
    trapezius_weight,
    upper_back_weight,
    triceps_weight,
    biceps_weight,
    abs_weight,
    obliques_weight,
    lower_back_weight,
    gluteal_weight,
    adductors_weight,
    quadriceps_weight,
    hamstrings_weight,
    calves_weight
)
VALUES (
    :user_id,
    :legacy_config_id,
    :canonical_name,
    :normalized_name,
    :status,
    :review_needed,
    :config_source,
    :last_updated_at,
    :last_updated_type,
    :exercise_family,
    :fatigue_archetype,
    :fatigue_multiplier,
    :chest_weight,
    :deltoids_weight,
    :trapezius_weight,
    :upper_back_weight,
    :triceps_weight,
    :biceps_weight,
    :abs_weight,
    :obliques_weight,
    :lower_back_weight,
    :gluteal_weight,
    :adductors_weight,
    :quadriceps_weight,
    :hamstrings_weight,
    :calves_weight
)
ON CONFLICT (user_id, normalized_name) DO UPDATE
SET legacy_config_id = EXCLUDED.legacy_config_id,
    canonical_name = EXCLUDED.canonical_name,
    status = EXCLUDED.status,
    review_needed = EXCLUDED.review_needed,
    config_source = EXCLUDED.config_source,
    last_updated_at = EXCLUDED.last_updated_at,
    last_updated_type = EXCLUDED.last_updated_type,
    exercise_family = EXCLUDED.exercise_family,
    fatigue_archetype = EXCLUDED.fatigue_archetype,
    fatigue_multiplier = EXCLUDED.fatigue_multiplier,
    chest_weight = EXCLUDED.chest_weight,
    deltoids_weight = EXCLUDED.deltoids_weight,
    trapezius_weight = EXCLUDED.trapezius_weight,
    upper_back_weight = EXCLUDED.upper_back_weight,
    triceps_weight = EXCLUDED.triceps_weight,
    biceps_weight = EXCLUDED.biceps_weight,
    abs_weight = EXCLUDED.abs_weight,
    obliques_weight = EXCLUDED.obliques_weight,
    lower_back_weight = EXCLUDED.lower_back_weight,
    gluteal_weight = EXCLUDED.gluteal_weight,
    adductors_weight = EXCLUDED.adductors_weight,
    quadriceps_weight = EXCLUDED.quadriceps_weight,
    hamstrings_weight = EXCLUDED.hamstrings_weight,
    calves_weight = EXCLUDED.calves_weight
RETURNING id
SQL);
    $deleteAliases = $pdo->prepare('DELETE FROM mattrics.mattrics_activity_type_aliases WHERE activity_type_id = :activity_type_id');
    $insertAlias = $pdo->prepare(<<<'SQL'
INSERT INTO mattrics.mattrics_activity_type_aliases (
    user_id,
    activity_type_id,
    alias_name,
    normalized_alias_name
)
VALUES (:user_id, :activity_type_id, :alias_name, :normalized_alias_name)
SQL);

    $index = [
        'byNormalized' => [],
        'byAlias' => [],
    ];

    foreach ($records as $record) {
        $params = array_merge(
            [
                'user_id' => $userId,
                'legacy_config_id' => (string) $record['id'],
                'canonical_name' => (string) $record['canonicalName'],
                'normalized_name' => (string) $record['normalizedName'],
                'status' => (string) ($record['status'] ?? 'approved'),
                'review_needed' => (bool) ($record['reviewNeeded'] ?? false),
                'config_source' => (string) ($record['source'] ?? 'manual'),
                'last_updated_at' => mattrics_foundation_parse_optional_timestamp($record['lastUpdatedAt'] ?? null),
                'last_updated_type' => (string) ($record['lastUpdatedType'] ?? 'manual'),
                'exercise_family' => (string) ($record['exerciseFamily'] ?? 'conditioning_lower'),
                'fatigue_archetype' => (string) ($record['fatigueArchetype'] ?? 'conditioning_hybrid'),
                'fatigue_multiplier' => (float) ($record['fatigueMultiplier'] ?? 1.0),
            ],
            mattrics_foundation_map_muscle_weights($record['muscleWeights'] ?? [])
        );
        mattrics_foundation_execute_params($upsert, $params);
        $activityTypeId = (string) $upsert->fetchColumn();

        $deleteAliases->execute(['activity_type_id' => $activityTypeId]);
        $aliasRows = mattrics_foundation_build_activity_type_alias_rows($record);
        foreach ($aliasRows as $aliasRow) {
            $insertAlias->execute([
                'user_id' => $userId,
                'activity_type_id' => $activityTypeId,
                'alias_name' => $aliasRow['aliasName'],
                'normalized_alias_name' => $aliasRow['normalizedAliasName'],
            ]);
        }

        $summary['counts']['activityTypes']++;
        $summary['counts']['activityTypeAliases'] += count($aliasRows);

        $indexedRecord = $record + ['dbId' => $activityTypeId, 'dataSourceId' => $dataSourceId];
        $index['byNormalized'][(string) $record['normalizedName']] = $indexedRecord;
        foreach (($record['aliases'] ?? []) as $alias) {
            $index['byAlias'][mattrics_normalize_config_name((string) $alias)] = $indexedRecord;
        }
    }

    return $index;
}

function mattrics_foundation_insert_activity_children(
    PDOStatement $insertActivityExercise,
    PDOStatement $insertActivitySet,
    string $userId,
    string $activityId,
    array $exerciseRows,
    array &$summary
): void {
    foreach ($exerciseRows as $exerciseRow) {
        $insertActivityExercise->execute([
            'user_id' => $userId,
            'activity_id' => $activityId,
            'exercise_id' => $exerciseRow['exerciseId'],
            'exercise_order' => $exerciseRow['exerciseOrder'],
            'source_exercise_name' => $exerciseRow['sourceExerciseName'],
            'normalized_source_exercise_name' => $exerciseRow['normalizedSourceExerciseName'],
        ]);
        $activityExerciseId = (string) $insertActivityExercise->fetchColumn();

        $summary['counts']['hevyExerciseBlocks']++;
        if ($exerciseRow['exerciseId'] !== null) {
            $summary['counts']['resolvedHevyExerciseBlocks']++;
        } else {
            $summary['counts']['unresolvedHevyExerciseBlocks']++;
            $summary['unresolvedExercises'][$exerciseRow['normalizedSourceExerciseName']] = $exerciseRow['sourceExerciseName'];
        }

        foreach ($exerciseRow['sets'] as $setRow) {
            $insertActivitySet->execute([
                'user_id' => $userId,
                'activity_exercise_id' => $activityExerciseId,
                'set_order' => $setRow['setOrder'],
                'parsed_kind' => $setRow['parsedKind'],
                'source_set_text' => $setRow['sourceSetText'],
                'reps' => $setRow['reps'],
                'weight_kg' => $setRow['weightKg'],
                'duration_minutes' => $setRow['durationMinutes'],
                'distance_km' => $setRow['distanceKm'],
                'rpe' => $setRow['rpe'],
                'effort_factor' => $setRow['effortFactor'],
                'computed_load' => $setRow['computedLoad'],
                'notes' => $setRow['notes'],
            ]);

            if ($setRow['parsedKind'] === 'parsed') {
                $summary['counts']['parsedSets']++;
            } elseif ($setRow['parsedKind'] === 'time') {
                $summary['counts']['timeSets']++;
            } else {
                $summary['counts']['unknownSets']++;
            }
        }
    }
}

function mattrics_foundation_import_activities(
    PDO $pdo,
    string $userId,
    string $dataSourceId,
    string $importBatchId,
    array $rows,
    array $activityTypeIndex,
    array $exerciseIndex,
    array &$summary
): array {
    $upsertBySourceId = $pdo->prepare(<<<'SQL'
INSERT INTO mattrics.mattrics_activities (
    user_id,
    data_source_id,
    import_batch_id,
    activity_type_id,
    source_row_number,
    activity_date,
    started_at,
    activity_name,
    activity_type_name,
    source_activity_id,
    source_activity_id_raw,
    distance_km,
    duration_minutes,
    elevation_gain_m,
    avg_hr,
    max_hr,
    avg_pace_min_per_km,
    avg_speed_kmh,
    avg_cadence,
    description,
    device_name
)
VALUES (
    :user_id,
    :data_source_id,
    :import_batch_id,
    :activity_type_id,
    :source_row_number,
    :activity_date,
    :started_at,
    :activity_name,
    :activity_type_name,
    :source_activity_id,
    :source_activity_id_raw,
    :distance_km,
    :duration_minutes,
    :elevation_gain_m,
    :avg_hr,
    :max_hr,
    :avg_pace_min_per_km,
    :avg_speed_kmh,
    :avg_cadence,
    :description,
    :device_name
)
ON CONFLICT (data_source_id, source_activity_id_raw) WHERE source_activity_id_raw IS NOT NULL DO UPDATE
SET import_batch_id = EXCLUDED.import_batch_id,
    activity_type_id = EXCLUDED.activity_type_id,
    source_row_number = EXCLUDED.source_row_number,
    activity_date = EXCLUDED.activity_date,
    started_at = EXCLUDED.started_at,
    activity_name = EXCLUDED.activity_name,
    activity_type_name = EXCLUDED.activity_type_name,
    source_activity_id = EXCLUDED.source_activity_id,
    distance_km = EXCLUDED.distance_km,
    duration_minutes = EXCLUDED.duration_minutes,
    elevation_gain_m = EXCLUDED.elevation_gain_m,
    avg_hr = EXCLUDED.avg_hr,
    max_hr = EXCLUDED.max_hr,
    avg_pace_min_per_km = EXCLUDED.avg_pace_min_per_km,
    avg_speed_kmh = EXCLUDED.avg_speed_kmh,
    avg_cadence = EXCLUDED.avg_cadence,
    description = EXCLUDED.description,
    device_name = EXCLUDED.device_name
RETURNING id
SQL);
    $upsertByBatchRow = $pdo->prepare(<<<'SQL'
INSERT INTO mattrics.mattrics_activities (
    user_id,
    data_source_id,
    import_batch_id,
    activity_type_id,
    source_row_number,
    activity_date,
    started_at,
    activity_name,
    activity_type_name,
    source_activity_id,
    source_activity_id_raw,
    distance_km,
    duration_minutes,
    elevation_gain_m,
    avg_hr,
    max_hr,
    avg_pace_min_per_km,
    avg_speed_kmh,
    avg_cadence,
    description,
    device_name
)
VALUES (
    :user_id,
    :data_source_id,
    :import_batch_id,
    :activity_type_id,
    :source_row_number,
    :activity_date,
    :started_at,
    :activity_name,
    :activity_type_name,
    :source_activity_id,
    :source_activity_id_raw,
    :distance_km,
    :duration_minutes,
    :elevation_gain_m,
    :avg_hr,
    :max_hr,
    :avg_pace_min_per_km,
    :avg_speed_kmh,
    :avg_cadence,
    :description,
    :device_name
)
ON CONFLICT (import_batch_id, source_row_number) WHERE import_batch_id IS NOT NULL AND source_row_number IS NOT NULL DO UPDATE
SET activity_type_id = EXCLUDED.activity_type_id,
    activity_date = EXCLUDED.activity_date,
    started_at = EXCLUDED.started_at,
    activity_name = EXCLUDED.activity_name,
    activity_type_name = EXCLUDED.activity_type_name,
    source_activity_id = EXCLUDED.source_activity_id,
    source_activity_id_raw = EXCLUDED.source_activity_id_raw,
    distance_km = EXCLUDED.distance_km,
    duration_minutes = EXCLUDED.duration_minutes,
    elevation_gain_m = EXCLUDED.elevation_gain_m,
    avg_hr = EXCLUDED.avg_hr,
    max_hr = EXCLUDED.max_hr,
    avg_pace_min_per_km = EXCLUDED.avg_pace_min_per_km,
    avg_speed_kmh = EXCLUDED.avg_speed_kmh,
    avg_cadence = EXCLUDED.avg_cadence,
    description = EXCLUDED.description,
    device_name = EXCLUDED.device_name
RETURNING id
SQL);
    $deleteActivityExercises = $pdo->prepare('DELETE FROM mattrics.mattrics_activity_exercises WHERE activity_id = :activity_id');
    $insertActivityExercise = $pdo->prepare(<<<'SQL'
INSERT INTO mattrics.mattrics_activity_exercises (
    user_id,
    activity_id,
    exercise_id,
    exercise_order,
    source_exercise_name,
    normalized_source_exercise_name
)
VALUES (
    :user_id,
    :activity_id,
    :exercise_id,
    :exercise_order,
    :source_exercise_name,
    :normalized_source_exercise_name
)
RETURNING id
SQL);
    $insertActivitySet = $pdo->prepare(<<<'SQL'
INSERT INTO mattrics.mattrics_activity_sets (
    user_id,
    activity_exercise_id,
    set_order,
    parsed_kind,
    source_set_text,
    reps,
    weight_kg,
    duration_minutes,
    distance_km,
    rpe,
    effort_factor,
    computed_load,
    notes
)
VALUES (
    :user_id,
    :activity_exercise_id,
    :set_order,
    :parsed_kind,
    :source_set_text,
    :reps,
    :weight_kg,
    :duration_minutes,
    :distance_km,
    :rpe,
    :effort_factor,
    :computed_load,
    :notes
)
SQL);

    $appliedActivityCount = 0;

    foreach ($rows as $index => $row) {
        if (!is_array($row)) {
            throw new RuntimeException('Snapshot row must be an object at index ' . $index . '.');
        }

        $sourceRowNumber = $index + 1;
        $activityTypeName = trim((string) ($row['Type'] ?? ''));
        $activityTypeRecord = mattrics_foundation_resolve_activity_type_record($activityTypeName, $activityTypeIndex);
        $dateInfo = mattrics_foundation_parse_activity_date($row['Date'] ?? null);
        $sourceActivityIdRaw = mattrics_foundation_null_if_blank($row['Activity ID raw'] ?? null);
        $params = [
            'user_id' => $userId,
            'data_source_id' => $dataSourceId,
            'import_batch_id' => $importBatchId,
            'activity_type_id' => $activityTypeRecord['dbId'] ?? null,
            'source_row_number' => $sourceRowNumber,
            'activity_date' => $dateInfo['activityDate'],
            'started_at' => $dateInfo['startedAt'],
            'activity_name' => mattrics_foundation_required_string($row['Name'] ?? null, 'Snapshot activity name'),
            'activity_type_name' => mattrics_foundation_required_string($activityTypeName, 'Snapshot activity type'),
            'source_activity_id' => mattrics_foundation_null_if_blank($row['Activity ID'] ?? null),
            'source_activity_id_raw' => $sourceActivityIdRaw,
            'distance_km' => mattrics_foundation_nullable_float($row['Distance (km)'] ?? null),
            'duration_minutes' => mattrics_foundation_nullable_float($row['Duration (min)'] ?? null),
            'elevation_gain_m' => mattrics_foundation_nullable_int($row['Elevation Gain (m)'] ?? null),
            'avg_hr' => mattrics_foundation_nullable_int($row['Avg HR'] ?? null),
            'max_hr' => mattrics_foundation_nullable_int($row['Max HR'] ?? null),
            'avg_pace_min_per_km' => mattrics_foundation_nullable_float($row['Avg Pace (min/km)'] ?? null),
            'avg_speed_kmh' => mattrics_foundation_nullable_float($row['Avg Speed (km/h)'] ?? null),
            'avg_cadence' => mattrics_foundation_nullable_float($row['Avg Cadence'] ?? null),
            'description' => mattrics_foundation_null_if_blank($row['Description'] ?? null),
            'device_name' => mattrics_foundation_null_if_blank($row['Device Name'] ?? null),
        ];

        $statement = $sourceActivityIdRaw !== null ? $upsertBySourceId : $upsertByBatchRow;
        $statement->execute($params);
        $activityId = (string) $statement->fetchColumn();

        $summary['counts']['activities']++;
        $appliedActivityCount++;

        $deleteActivityExercises->execute(['activity_id' => $activityId]);

        $hevyExercises = mattrics_foundation_parse_hevy_description($params['description']);
        if ($hevyExercises === null) {
            continue;
        }

        $summary['counts']['hevyActivities']++;
        $childRows = mattrics_foundation_collect_activity_child_rows($row, $exerciseIndex);
        mattrics_foundation_insert_activity_children(
            $insertActivityExercise,
            $insertActivitySet,
            $userId,
            $activityId,
            $childRows['exercises'],
            $summary
        );
    }

    $summary['unresolvedExercises'] = array_values($summary['unresolvedExercises']);

    return [
        'appliedActivityCount' => $appliedActivityCount,
    ];
}

function mattrics_foundation_import_hevy_workouts(
    PDO $pdo,
    string $userId,
    string $dataSourceId,
    string $importBatchId,
    array $workouts,
    array $activityTypeIndex,
    array $exerciseIndex,
    array &$summary
): array {
    $upsertActivity = $pdo->prepare(<<<'SQL'
INSERT INTO mattrics.mattrics_activities (
    user_id,
    data_source_id,
    import_batch_id,
    activity_type_id,
    source_row_number,
    activity_date,
    started_at,
    activity_name,
    activity_type_name,
    source_activity_id,
    source_activity_id_raw,
    distance_km,
    duration_minutes,
    elevation_gain_m,
    avg_hr,
    max_hr,
    avg_pace_min_per_km,
    avg_speed_kmh,
    avg_cadence,
    description,
    device_name
)
VALUES (
    :user_id,
    :data_source_id,
    :import_batch_id,
    :activity_type_id,
    :source_row_number,
    :activity_date,
    :started_at,
    :activity_name,
    :activity_type_name,
    :source_activity_id,
    :source_activity_id_raw,
    :distance_km,
    :duration_minutes,
    :elevation_gain_m,
    :avg_hr,
    :max_hr,
    :avg_pace_min_per_km,
    :avg_speed_kmh,
    :avg_cadence,
    :description,
    :device_name
)
ON CONFLICT (data_source_id, source_activity_id_raw) WHERE source_activity_id_raw IS NOT NULL DO UPDATE
SET import_batch_id = EXCLUDED.import_batch_id,
    activity_type_id = EXCLUDED.activity_type_id,
    source_row_number = EXCLUDED.source_row_number,
    activity_date = EXCLUDED.activity_date,
    started_at = EXCLUDED.started_at,
    activity_name = EXCLUDED.activity_name,
    activity_type_name = EXCLUDED.activity_type_name,
    source_activity_id = EXCLUDED.source_activity_id,
    distance_km = EXCLUDED.distance_km,
    duration_minutes = EXCLUDED.duration_minutes,
    elevation_gain_m = EXCLUDED.elevation_gain_m,
    avg_hr = EXCLUDED.avg_hr,
    max_hr = EXCLUDED.max_hr,
    avg_pace_min_per_km = EXCLUDED.avg_pace_min_per_km,
    avg_speed_kmh = EXCLUDED.avg_speed_kmh,
    avg_cadence = EXCLUDED.avg_cadence,
    description = EXCLUDED.description,
    device_name = EXCLUDED.device_name
RETURNING id
SQL);
    $deleteActivityExercises = $pdo->prepare('DELETE FROM mattrics.mattrics_activity_exercises WHERE activity_id = :activity_id');
    $insertActivityExercise = $pdo->prepare(<<<'SQL'
INSERT INTO mattrics.mattrics_activity_exercises (
    user_id,
    activity_id,
    exercise_id,
    exercise_order,
    source_exercise_name,
    normalized_source_exercise_name
)
VALUES (
    :user_id,
    :activity_id,
    :exercise_id,
    :exercise_order,
    :source_exercise_name,
    :normalized_source_exercise_name
)
RETURNING id
SQL);
    $insertActivitySet = $pdo->prepare(<<<'SQL'
INSERT INTO mattrics.mattrics_activity_sets (
    user_id,
    activity_exercise_id,
    set_order,
    parsed_kind,
    source_set_text,
    reps,
    weight_kg,
    duration_minutes,
    distance_km,
    rpe,
    effort_factor,
    computed_load,
    notes
)
VALUES (
    :user_id,
    :activity_exercise_id,
    :set_order,
    :parsed_kind,
    :source_set_text,
    :reps,
    :weight_kg,
    :duration_minutes,
    :distance_km,
    :rpe,
    :effort_factor,
    :computed_load,
    :notes
)
SQL);

    $weightTraining = mattrics_foundation_resolve_activity_type_record('WeightTraining', $activityTypeIndex);
    $appliedActivityCount = 0;

    foreach ($workouts as $index => $workout) {
        if (!is_array($workout)) {
            throw new RuntimeException('Hevy workout must be an object at index ' . $index . '.');
        }

        $childRows = mattrics_foundation_collect_hevy_csv_child_rows($workout, $exerciseIndex);
        $params = [
            'user_id' => $userId,
            'data_source_id' => $dataSourceId,
            'import_batch_id' => $importBatchId,
            'activity_type_id' => $weightTraining['dbId'] ?? null,
            'source_row_number' => $index + 1,
            'activity_date' => mattrics_foundation_required_string($workout['activityDate'] ?? null, 'Hevy workout date'),
            'started_at' => mattrics_foundation_required_string($workout['startedAt'] ?? null, 'Hevy workout started_at'),
            'activity_name' => mattrics_foundation_required_string($workout['title'] ?? null, 'Hevy workout title'),
            'activity_type_name' => 'WeightTraining',
            'source_activity_id' => mattrics_foundation_required_string($workout['sourceActivityIdRaw'] ?? null, 'Hevy workout source id'),
            'source_activity_id_raw' => mattrics_foundation_required_string($workout['sourceActivityIdRaw'] ?? null, 'Hevy workout source raw id'),
            'distance_km' => null,
            'duration_minutes' => isset($workout['durationMinutes']) ? round((float) $workout['durationMinutes'], 2) : null,
            'elevation_gain_m' => null,
            'avg_hr' => null,
            'max_hr' => null,
            'avg_pace_min_per_km' => null,
            'avg_speed_kmh' => null,
            'avg_cadence' => null,
            'description' => $childRows['description'],
            'device_name' => 'Hevy',
        ];

        mattrics_foundation_execute_params($upsertActivity, $params);
        $activityId = (string) $upsertActivity->fetchColumn();

        $summary['counts']['activities']++;
        $summary['counts']['hevyActivities']++;
        $appliedActivityCount++;

        $deleteActivityExercises->execute(['activity_id' => $activityId]);
        mattrics_foundation_insert_activity_children(
            $insertActivityExercise,
            $insertActivitySet,
            $userId,
            $activityId,
            $childRows['exercises'],
            $summary
        );
    }

    $summary['unresolvedExercises'] = array_values($summary['unresolvedExercises']);

    return [
        'appliedActivityCount' => $appliedActivityCount,
    ];
}

function mattrics_foundation_import_garmin_activities(
    PDO $pdo,
    string $userId,
    string $dataSourceId,
    string $importBatchId,
    array $activities,
    array $activityTypeIndex,
    array &$summary
): array {
    $upsertActivity = $pdo->prepare(<<<'SQL'
INSERT INTO mattrics.mattrics_activities (
    user_id,
    data_source_id,
    import_batch_id,
    activity_type_id,
    source_row_number,
    activity_date,
    started_at,
    activity_name,
    activity_type_name,
    source_activity_id,
    source_activity_id_raw,
    distance_km,
    duration_minutes,
    elevation_gain_m,
    avg_hr,
    max_hr,
    avg_pace_min_per_km,
    avg_speed_kmh,
    avg_cadence,
    description,
    device_name
)
VALUES (
    :user_id,
    :data_source_id,
    :import_batch_id,
    :activity_type_id,
    :source_row_number,
    :activity_date,
    :started_at,
    :activity_name,
    :activity_type_name,
    :source_activity_id,
    :source_activity_id_raw,
    :distance_km,
    :duration_minutes,
    :elevation_gain_m,
    :avg_hr,
    :max_hr,
    :avg_pace_min_per_km,
    :avg_speed_kmh,
    :avg_cadence,
    :description,
    :device_name
)
ON CONFLICT (data_source_id, source_activity_id_raw) WHERE source_activity_id_raw IS NOT NULL DO UPDATE
SET import_batch_id = EXCLUDED.import_batch_id,
    activity_type_id = EXCLUDED.activity_type_id,
    source_row_number = EXCLUDED.source_row_number,
    activity_date = EXCLUDED.activity_date,
    started_at = EXCLUDED.started_at,
    activity_name = EXCLUDED.activity_name,
    activity_type_name = EXCLUDED.activity_type_name,
    source_activity_id = EXCLUDED.source_activity_id,
    distance_km = EXCLUDED.distance_km,
    duration_minutes = EXCLUDED.duration_minutes,
    elevation_gain_m = EXCLUDED.elevation_gain_m,
    avg_hr = EXCLUDED.avg_hr,
    max_hr = EXCLUDED.max_hr,
    avg_pace_min_per_km = EXCLUDED.avg_pace_min_per_km,
    avg_speed_kmh = EXCLUDED.avg_speed_kmh,
    avg_cadence = EXCLUDED.avg_cadence,
    description = EXCLUDED.description,
    device_name = EXCLUDED.device_name
RETURNING id
SQL);

    $appliedActivityCount = 0;

    foreach ($activities as $index => $activity) {
        if (!is_array($activity)) {
            throw new RuntimeException('Garmin activity must be an object at index ' . $index . '.');
        }

        $activityTypeName = mattrics_foundation_required_string($activity['activityType'] ?? null, 'Garmin activity type');
        $activityTypeRecord = mattrics_foundation_resolve_activity_type_record($activityTypeName, $activityTypeIndex);
        $params = [
            'user_id' => $userId,
            'data_source_id' => $dataSourceId,
            'import_batch_id' => $importBatchId,
            'activity_type_id' => $activityTypeRecord['dbId'] ?? null,
            'source_row_number' => $index + 1,
            'activity_date' => mattrics_foundation_required_string($activity['activityDate'] ?? null, 'Garmin activity date'),
            'started_at' => mattrics_foundation_required_string($activity['startedAt'] ?? null, 'Garmin activity started_at'),
            'activity_name' => mattrics_foundation_required_string($activity['title'] ?? null, 'Garmin activity title'),
            'activity_type_name' => $activityTypeName,
            'source_activity_id' => mattrics_foundation_required_string($activity['sourceActivityId'] ?? null, 'Garmin activity source id'),
            'source_activity_id_raw' => mattrics_foundation_required_string($activity['sourceActivityIdRaw'] ?? null, 'Garmin activity source raw id'),
            'distance_km' => isset($activity['distanceKm']) ? round((float) $activity['distanceKm'], 3) : null,
            'duration_minutes' => isset($activity['durationMinutes']) ? round((float) $activity['durationMinutes'], 2) : null,
            'elevation_gain_m' => $activity['elevationGainM'] ?? null,
            'avg_hr' => $activity['avgHr'] ?? null,
            'max_hr' => $activity['maxHr'] ?? null,
            'avg_pace_min_per_km' => isset($activity['avgPaceMinPerKm']) ? round((float) $activity['avgPaceMinPerKm'], 4) : null,
            'avg_speed_kmh' => isset($activity['avgSpeedKmh']) ? round((float) $activity['avgSpeedKmh'], 2) : null,
            'avg_cadence' => isset($activity['avgCadence']) ? round((float) $activity['avgCadence'], 2) : null,
            'description' => null,
            'device_name' => 'Garmin',
        ];

        mattrics_foundation_execute_params($upsertActivity, $params);
        $upsertActivity->fetchColumn();

        $summary['counts']['activities']++;
        $appliedActivityCount++;
    }

    return [
        'appliedActivityCount' => $appliedActivityCount,
    ];
}

function mattrics_foundation_map_muscle_weights(array $weights): array
{
    $mapped = [];
    foreach ([
        'chest',
        'deltoids',
        'trapezius',
        'upperBack',
        'triceps',
        'biceps',
        'abs',
        'obliques',
        'lowerBack',
        'gluteal',
        'adductors',
        'quadriceps',
        'hamstrings',
        'calves',
    ] as $key) {
        $mapped[mattrics_foundation_muscle_column_name($key)] = isset($weights[$key]) ? (float) $weights[$key] : 0.0;
    }

    return $mapped;
}

function mattrics_foundation_muscle_column_name(string $muscleKey): string
{
    return strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1_$2', $muscleKey)) . '_weight';
}

function mattrics_foundation_build_exercise_alias_rows(array $record): array
{
    $rows = [];
    foreach (mattrics_normalize_unique_name_list($record['aliases'] ?? []) as $alias) {
        $rows[] = [
            'aliasKind' => 'alias',
            'aliasName' => $alias,
            'normalizedAliasName' => mattrics_normalize_config_name($alias),
        ];
    }
    foreach (mattrics_normalize_unique_name_list($record['matchTerms'] ?? []) as $matchTerm) {
        $rows[] = [
            'aliasKind' => 'match_term',
            'aliasName' => $matchTerm,
            'normalizedAliasName' => mattrics_normalize_config_name($matchTerm),
        ];
    }

    return $rows;
}

function mattrics_foundation_build_activity_type_alias_rows(array $record): array
{
    $rows = [];
    foreach (mattrics_normalize_unique_name_list($record['aliases'] ?? []) as $alias) {
        $rows[] = [
            'aliasName' => $alias,
            'normalizedAliasName' => mattrics_normalize_config_name($alias),
        ];
    }

    return $rows;
}

function mattrics_foundation_resolve_activity_type_record(string $name, array $index): ?array
{
    $normalized = mattrics_normalize_config_name($name);
    if ($normalized === '') {
        return null;
    }

    if (isset($index['byNormalized'][$normalized])) {
        return $index['byNormalized'][$normalized];
    }

    return $index['byAlias'][$normalized] ?? null;
}

function mattrics_foundation_resolve_exercise_record(string $name, array $index): ?array
{
    $normalized = mattrics_normalize_config_name($name);
    if ($normalized === '') {
        return null;
    }

    if (isset($index['byNormalized'][$normalized])) {
        return $index['byNormalized'][$normalized];
    }

    if (isset($index['byAlias'][$normalized])) {
        return $index['byAlias'][$normalized];
    }

    foreach ($index['matchTerms'] as $entry) {
        if (str_contains($normalized, $entry['term'])) {
            return $entry['record'];
        }
    }

    return null;
}

function mattrics_foundation_collect_activity_child_rows(array $activityRow, array $exerciseIndex): array
{
    $blocks = mattrics_foundation_parse_hevy_description((string) ($activityRow['Description'] ?? ''));
    if ($blocks === null) {
        return ['exercises' => []];
    }

    $exerciseRows = [];
    foreach ($blocks as $exerciseOffset => $block) {
        $sourceExerciseName = trim((string) ($block['name'] ?? ''));
        if ($sourceExerciseName === '') {
            continue;
        }

        $resolvedExercise = mattrics_foundation_resolve_exercise_record($sourceExerciseName, $exerciseIndex);
        $exerciseConfig = $resolvedExercise ?? null;
        $setRows = [];
        foreach (($block['sets'] ?? []) as $setOffset => $setText) {
            $parsed = mattrics_foundation_parse_hevy_set_line((string) $setText, $exerciseConfig, $sourceExerciseName);
            $setRows[] = [
                'setOrder' => $setOffset + 1,
                'parsedKind' => (string) $parsed['kind'],
                'sourceSetText' => trim((string) $setText),
                'reps' => isset($parsed['reps']) ? (int) $parsed['reps'] : null,
                'weightKg' => isset($parsed['weightKg']) ? (float) $parsed['weightKg'] : null,
                'durationMinutes' => isset($parsed['minutes']) ? (float) $parsed['minutes'] : null,
                'distanceKm' => isset($parsed['distanceKm']) ? (float) $parsed['distanceKm'] : null,
                'rpe' => isset($parsed['rpe']) ? (float) $parsed['rpe'] : null,
                'effortFactor' => isset($parsed['effortFactor']) ? (float) $parsed['effortFactor'] : null,
                'computedLoad' => isset($parsed['load']) ? (float) $parsed['load'] : null,
                'notes' => null,
            ];
        }

        $exerciseRows[] = [
            'exerciseId' => $resolvedExercise['dbId'] ?? null,
            'exerciseOrder' => $exerciseOffset + 1,
            'sourceExerciseName' => $sourceExerciseName,
            'normalizedSourceExerciseName' => mattrics_normalize_config_name($sourceExerciseName),
            'sets' => $setRows,
        ];
    }

    return ['exercises' => $exerciseRows];
}

function mattrics_foundation_collect_hevy_csv_child_rows(array $workout, array $exerciseIndex): array
{
    $exerciseRows = [];
    $descriptionBlocks = [];
    $workoutDescription = trim((string) ($workout['description'] ?? ''));

    foreach (($workout['exercises'] ?? []) as $exerciseOffset => $exercise) {
        $sourceExerciseName = trim((string) ($exercise['exerciseTitle'] ?? ''));
        if ($sourceExerciseName === '') {
            continue;
        }

        $resolvedExercise = mattrics_foundation_resolve_exercise_record($sourceExerciseName, $exerciseIndex);
        $exerciseConfig = $resolvedExercise ?? null;
        $setRows = [];
        $setTexts = [];

        foreach (($exercise['sets'] ?? []) as $setOffset => $set) {
            $mapped = mattrics_foundation_map_hevy_csv_set_row(
                $set,
                $exerciseConfig,
                $sourceExerciseName,
                [
                    'exerciseNotes' => trim((string) ($exercise['exerciseNotes'] ?? '')),
                    'supersetId' => trim((string) ($exercise['supersetId'] ?? '')),
                    'workoutDescription' => $workoutDescription,
                    'setOrder' => $setOffset + 1,
                    'includeExerciseNotes' => $setOffset === 0,
                    'includeWorkoutDescription' => $exerciseOffset === 0 && $setOffset === 0,
                ]
            );
            $setRows[] = $mapped['row'];
            $setTexts[] = $mapped['sourceSetText'];
        }

        $exerciseRows[] = [
            'exerciseId' => $resolvedExercise['dbId'] ?? null,
            'exerciseOrder' => $exerciseOffset + 1,
            'sourceExerciseName' => $sourceExerciseName,
            'normalizedSourceExerciseName' => mattrics_normalize_config_name($sourceExerciseName),
            'sets' => $setRows,
        ];

        $descriptionBlock = $sourceExerciseName;
        if ($setTexts !== []) {
            $descriptionBlock .= "\n" . implode("\n", $setTexts);
        }
        $descriptionBlocks[] = $descriptionBlock;
    }

    $description = 'Logged with HevyApp.com';
    if ($descriptionBlocks !== []) {
        $description .= "\n\n" . implode("\n\n", $descriptionBlocks);
    }

    return [
        'description' => $description,
        'exercises' => $exerciseRows,
    ];
}

function mattrics_foundation_map_hevy_csv_set_row(
    array $set,
    ?array $exerciseConfig,
    string $exerciseName,
    array $context = []
): array {
    $reps = isset($set['reps']) ? mattrics_foundation_nullable_int($set['reps']) : null;
    $weightKg = isset($set['weightKg']) ? mattrics_foundation_nullable_float($set['weightKg']) : null;
    $distanceKm = isset($set['distanceKm']) ? mattrics_foundation_nullable_float($set['distanceKm']) : null;
    $durationSeconds = isset($set['durationSeconds']) ? mattrics_foundation_nullable_float($set['durationSeconds']) : null;
    $durationMinutes = $durationSeconds !== null ? round($durationSeconds / 60, 3) : null;
    $rpe = isset($set['rpe']) ? mattrics_foundation_nullable_float($set['rpe']) : null;
    $setType = trim((string) ($set['setType'] ?? ''));

    $bodyweightEligible = (bool) ($exerciseConfig['bodyweightEligible'] ?? false)
        || preg_match('/(push ?up|pull ?up|chin ?up|dip|sit ?up|crunch|leg raise|bodyweight|bw|air squat|pistol squat)/i', $exerciseName) === 1;

    $parsedKind = 'unknown';
    $effectiveWeightKg = $weightKg;
    $effectiveRpe = $rpe;
    $effortFactor = null;
    $computedLoad = null;

    if ($effectiveWeightKg !== null && $effectiveWeightKg > 0 && $reps !== null && $reps > 0) {
        $parsedKind = 'parsed';
    } elseif ($reps !== null && $reps > 0 && $bodyweightEligible) {
        $parsedKind = 'parsed';
        $effectiveWeightKg = MATTRICS_FOUNDATION_ESTIMATED_BODYWEIGHT_KG * MATTRICS_FOUNDATION_BODYWEIGHT_LOAD_FACTOR;
    } elseif (($durationSeconds !== null && $durationSeconds > 0) || ($distanceKm !== null && $distanceKm > 0)) {
        $parsedKind = 'time';
    }

    if ($parsedKind === 'parsed') {
        $effectiveRpe ??= MATTRICS_FOUNDATION_DEFAULT_RPE;
        $effortFactor = 0.5 + ($effectiveRpe / 10);
        if ($effectiveWeightKg !== null && $reps !== null) {
            $computedLoad = round($effectiveWeightKg * $reps * $effortFactor, 4);
        }
    }

    return [
        'sourceSetText' => mattrics_foundation_build_hevy_csv_set_text([
            'setType' => $setType,
            'reps' => $reps,
            'weightKg' => $weightKg,
            'distanceKm' => $distanceKm,
            'durationSeconds' => $durationSeconds,
        ]),
        'row' => [
            'setOrder' => (int) ($context['setOrder'] ?? 1),
            'parsedKind' => $parsedKind,
            'sourceSetText' => mattrics_foundation_build_hevy_csv_set_text([
                'setType' => $setType,
                'reps' => $reps,
                'weightKg' => $weightKg,
                'distanceKm' => $distanceKm,
                'durationSeconds' => $durationSeconds,
            ]),
            'reps' => $parsedKind === 'parsed' ? $reps : null,
            'weightKg' => $parsedKind === 'parsed' ? $effectiveWeightKg : null,
            'durationMinutes' => $parsedKind === 'time' ? $durationMinutes : null,
            'distanceKm' => $parsedKind === 'time' ? $distanceKm : null,
            'rpe' => $parsedKind === 'parsed' ? $effectiveRpe : null,
            'effortFactor' => $parsedKind === 'parsed' ? $effortFactor : null,
            'computedLoad' => $parsedKind === 'parsed' ? $computedLoad : null,
            'notes' => mattrics_foundation_build_hevy_csv_set_notes($context + ['setType' => $setType]),
        ],
    ];
}

function mattrics_foundation_build_hevy_csv_set_notes(array $context): ?string
{
    $parts = [];
    $setType = trim((string) ($context['setType'] ?? ''));
    if ($setType !== '' && strtolower($setType) !== 'normal') {
        $parts[] = 'Set type: ' . $setType;
    }

    $exerciseNotes = trim((string) ($context['exerciseNotes'] ?? ''));
    if (($context['includeExerciseNotes'] ?? true) && $exerciseNotes !== '') {
        $parts[] = 'Exercise notes: ' . $exerciseNotes;
    }

    $supersetId = trim((string) ($context['supersetId'] ?? ''));
    if ($supersetId !== '') {
        $parts[] = 'Superset ID: ' . $supersetId;
    }

    if (!empty($context['includeWorkoutDescription'])) {
        $workoutDescription = trim((string) ($context['workoutDescription'] ?? ''));
        if ($workoutDescription !== '') {
            $parts[] = 'Workout description: ' . $workoutDescription;
        }
    }

    return $parts === [] ? null : implode("\n", $parts);
}

function mattrics_foundation_build_hevy_csv_set_text(array $set): string
{
    $weightKg = $set['weightKg'] ?? null;
    $reps = $set['reps'] ?? null;
    $distanceKm = $set['distanceKm'] ?? null;
    $durationSeconds = $set['durationSeconds'] ?? null;

    if ($weightKg !== null && $reps !== null && (float) $weightKg > 0 && (int) $reps > 0) {
        return mattrics_foundation_format_number((float) $weightKg) . ' kg x ' . (int) $reps;
    }

    if ($reps !== null && (int) $reps > 0) {
        return (int) $reps . ' reps';
    }

    $parts = [];
    if ($distanceKm !== null && (float) $distanceKm > 0) {
        $parts[] = mattrics_foundation_format_number((float) $distanceKm) . 'km';
    }
    if ($durationSeconds !== null && (float) $durationSeconds > 0) {
        $parts[] = mattrics_foundation_format_duration_text((float) $durationSeconds);
    }
    if ($parts !== []) {
        return implode(' - ', $parts);
    }

    $setType = trim((string) ($set['setType'] ?? ''));
    if ($setType !== '' && strtolower($setType) !== 'normal') {
        return $setType;
    }

    return 'unparsed set';
}

function mattrics_foundation_format_duration_text(float $durationSeconds): string
{
    if (fmod($durationSeconds, 60.0) === 0.0) {
        return mattrics_foundation_format_number($durationSeconds / 60) . 'min';
    }

    return mattrics_foundation_format_number($durationSeconds) . 'sec';
}

function mattrics_foundation_format_number(float $value): string
{
    $text = number_format($value, 3, '.', '');
    $text = rtrim(rtrim($text, '0'), '.');
    return $text === '-0' ? '0' : $text;
}

function mattrics_foundation_parse_activity_date(mixed $value): array
{
    $text = trim((string) $value);
    if ($text === '') {
        throw new RuntimeException('Snapshot activity date is required.');
    }

    try {
        $date = new DateTimeImmutable($text);
    } catch (Throwable $throwable) {
        throw new RuntimeException('Snapshot activity date is invalid: ' . $text);
    }

    $startedAt = str_contains($text, 'T') ? $date->setTimezone(new DateTimeZone('UTC'))->format('c') : null;

    return [
        'activityDate' => $date->format('Y-m-d'),
        'startedAt' => $startedAt,
    ];
}

function mattrics_foundation_required_string(mixed $value, string $field): string
{
    $text = trim((string) $value);
    if ($text === '') {
        throw new RuntimeException($field . ' is required.');
    }

    return $text;
}

function mattrics_foundation_null_if_blank(mixed $value): ?string
{
    $text = trim((string) $value);
    return $text === '' ? null : $text;
}

function mattrics_foundation_nullable_float(mixed $value): ?float
{
    $text = trim((string) $value);
    if ($text === '') {
        return null;
    }

    $normalized = str_replace(',', '.', $text);
    if (!is_numeric($normalized)) {
        throw new RuntimeException('Expected numeric value, got: ' . $text);
    }

    return (float) $normalized;
}

function mattrics_foundation_nullable_int(mixed $value): ?int
{
    $float = mattrics_foundation_nullable_float($value);
    return $float === null ? null : (int) round($float);
}

function mattrics_foundation_parse_optional_timestamp(mixed $value): ?string
{
    $text = trim((string) $value);
    if ($text === '') {
        return null;
    }

    try {
        return (new DateTimeImmutable($text))->setTimezone(new DateTimeZone('UTC'))->format('c');
    } catch (Throwable $throwable) {
        throw new RuntimeException('Invalid timestamp value: ' . $text);
    }
}
