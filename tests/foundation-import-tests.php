<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/scripts/lib/foundation-import.php';

$passed = 0;
$failed = 0;
$skipped = 0;

function foundation_test_assert(bool $condition, string $message): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }

    $failed++;
    fwrite(STDERR, "FAIL: {$message}\n");
}

function foundation_test_skip(string $message): void
{
    global $skipped;
    $skipped++;
    fwrite(STDOUT, "SKIP: {$message}\n");
}

$tempRoot = sys_get_temp_dir() . '/mattrics-foundation-import-tests-' . bin2hex(random_bytes(4));
$privateRoot = $tempRoot . '/private';
mattrics_ensure_dir($privateRoot . '/data');
mattrics_ensure_dir($privateRoot . '/cache');
mattrics_ensure_dir($privateRoot . '/storage');
mattrics_foundation_set_private_root($privateRoot);

$exerciseRecord = [
    'id' => 'bench-press',
    'canonicalName' => 'Bench Press',
    'normalizedName' => 'bench press',
    'aliases' => ['Push Up', 'Push-Up', 'Flat Bench'],
    'matchTerms' => ['bench', 'smith machine bench'],
    'muscleWeights' => ['chest' => 1.0, 'triceps' => 0.45, 'upperBack' => 0.08],
    'fatigueImpact' => 'normal',
    'fatigueMultiplier' => 1.0,
    'bodyweightEligible' => true,
    'setTypeHandling' => 'weight_reps',
    'source' => 'manual',
    'lastUpdatedAt' => '2026-06-06T00:00:00Z',
    'lastUpdatedType' => 'manual',
    'exerciseFamily' => 'horizontal_press',
    'fatigueArchetype' => 'freeweight_compound',
];
$activityTypeRecord = [
    'id' => 'canoeing',
    'canonicalName' => 'Canoeing',
    'normalizedName' => 'canoeing',
    'aliases' => ['Canoe', 'Paddle Session'],
    'muscleWeights' => ['biceps' => 0.45, 'upperBack' => 0.65],
    'fatigueMultiplier' => 1.0,
    'status' => 'approved',
    'reviewNeeded' => false,
    'source' => 'manual',
    'lastUpdatedAt' => '2026-06-06T00:00:00Z',
    'lastUpdatedType' => 'manual',
    'exerciseFamily' => 'horizontal_pull',
    'fatigueArchetype' => 'conditioning_hybrid',
];

$mappedWeights = mattrics_foundation_map_muscle_weights($exerciseRecord['muscleWeights']);
foundation_test_assert(isset($mappedWeights['upper_back_weight']) && abs($mappedWeights['upper_back_weight'] - 0.08) < 0.00001, 'muscle weights map camelCase keys to snake_case columns');
foundation_test_assert(isset($mappedWeights['hamstrings_weight']) && $mappedWeights['hamstrings_weight'] === 0.0, 'missing muscle weights default to zero');

$exerciseAliasRows = mattrics_foundation_build_exercise_alias_rows($exerciseRecord);
foundation_test_assert(count($exerciseAliasRows) === 4, 'exercise alias rows merge duplicate normalized aliases and include match terms');
foundation_test_assert($exerciseAliasRows[0]['aliasKind'] === 'alias', 'exercise alias rows mark canonical aliases correctly');
foundation_test_assert($exerciseAliasRows[2]['aliasKind'] === 'match_term', 'exercise alias rows mark substring terms correctly');

$activityAliasRows = mattrics_foundation_build_activity_type_alias_rows($activityTypeRecord);
foundation_test_assert(count($activityAliasRows) === 2, 'activity type alias rows preserve configured aliases');
foundation_test_assert($activityAliasRows[0]['normalizedAliasName'] === 'canoe', 'activity type aliases normalize names');

$parsedSet = mattrics_foundation_parse_hevy_set_line('80 kg x 5', $exerciseRecord, 'Bench Press');
foundation_test_assert($parsedSet['kind'] === 'parsed' && (int) $parsedSet['reps'] === 5 && abs((float) $parsedSet['weightKg'] - 80.0) < 0.00001, 'parser handles weight x reps sets');

$weightForReps = mattrics_foundation_parse_hevy_set_line('80 kg for 5 reps', $exerciseRecord, 'Bench Press');
foundation_test_assert($weightForReps['kind'] === 'parsed' && (int) $weightForReps['reps'] === 5, 'parser handles weight-for-reps sets');

$bodyweightSet = mattrics_foundation_parse_hevy_set_line('12 reps', $exerciseRecord, 'Push Up');
foundation_test_assert($bodyweightSet['kind'] === 'parsed' && abs((float) $bodyweightSet['weightKg'] - 30.0) < 0.00001, 'parser handles bodyweight rep-only sets');

$timeSet = mattrics_foundation_parse_hevy_set_line('6km - 12min', null, 'Cycling');
foundation_test_assert($timeSet['kind'] === 'time' && abs((float) $timeSet['minutes'] - 12.0) < 0.00001 && abs((float) $timeSet['distanceKm'] - 6.0) < 0.00001, 'parser handles time and distance sets');

$unknownSet = mattrics_foundation_parse_hevy_set_line('warmup', null, 'Bench Press');
foundation_test_assert($unknownSet['kind'] === 'unknown', 'parser preserves unknown set fallback');

$parsedDescription = mattrics_foundation_parse_hevy_description("Logged with HevyApp.com\n\nBench Press\n80 kg x 5\n80 kg for 5 reps\n\nMystery Curl\n12 reps");
foundation_test_assert(is_array($parsedDescription) && count($parsedDescription) === 2, 'parser splits Hevy descriptions into exercise blocks');

$exerciseIndex = [
    'byNormalized' => [
        'bench press' => $exerciseRecord + ['dbId' => 'db-bench'],
    ],
    'byAlias' => [
        'push up' => $exerciseRecord + ['dbId' => 'db-bench'],
    ],
    'matchTerms' => [
        ['term' => 'bench', 'record' => $exerciseRecord + ['dbId' => 'db-bench']],
    ],
];
$activityIndex = [
    'byNormalized' => [
        'canoeing' => $activityTypeRecord + ['dbId' => 'db-canoeing'],
        'run' => [
            'id' => 'run',
            'canonicalName' => 'Run',
            'normalizedName' => 'run',
            'aliases' => ['Running', 'Treadmill Running', 'Trail Running', 'Virtual Running'],
            'dbId' => 'db-run',
        ],
        'walk' => [
            'id' => 'walk',
            'canonicalName' => 'Walk',
            'normalizedName' => 'walk',
            'aliases' => ['Walking'],
            'dbId' => 'db-walk',
        ],
        'hike' => [
            'id' => 'hike',
            'canonicalName' => 'Hike',
            'normalizedName' => 'hike',
            'aliases' => ['Hiking'],
            'dbId' => 'db-hike',
        ],
        'ride' => [
            'id' => 'ride',
            'canonicalName' => 'Ride',
            'normalizedName' => 'ride',
            'aliases' => ['Cycling', 'Indoor Cycling', 'Virtual Cycling', 'Road Biking', 'Mountain Biking'],
            'dbId' => 'db-ride',
        ],
        'weight training' => [
            'id' => 'weighttraining',
            'canonicalName' => 'WeightTraining',
            'normalizedName' => 'weight training',
            'aliases' => ['Workout', 'Weight Training', 'Strength Training', 'Strength', 'Strength Workout'],
            'dbId' => 'db-weighttraining',
        ],
    ],
    'byAlias' => [
        'canoe' => $activityTypeRecord + ['dbId' => 'db-canoeing'],
        'running' => ['dbId' => 'db-run'],
        'treadmill running' => ['dbId' => 'db-run'],
        'trail running' => ['dbId' => 'db-run'],
        'virtual running' => ['dbId' => 'db-run'],
        'walking' => ['dbId' => 'db-walk'],
        'hiking' => ['dbId' => 'db-hike'],
        'cycling' => ['dbId' => 'db-ride'],
        'indoor cycling' => ['dbId' => 'db-ride'],
        'virtual cycling' => ['dbId' => 'db-ride'],
        'road biking' => ['dbId' => 'db-ride'],
        'mountain biking' => ['dbId' => 'db-ride'],
        'strength training' => ['dbId' => 'db-weighttraining'],
        'strength' => ['dbId' => 'db-weighttraining'],
        'strength workout' => ['dbId' => 'db-weighttraining'],
    ],
];

$resolvedCanonical = mattrics_foundation_resolve_exercise_record('Bench Press', $exerciseIndex);
$resolvedAlias = mattrics_foundation_resolve_exercise_record('Push Up', $exerciseIndex);
$resolvedMatchTerm = mattrics_foundation_resolve_exercise_record('Smith Machine Bench Press', $exerciseIndex);
$resolvedActivityType = mattrics_foundation_resolve_activity_type_record('Canoe', $activityIndex);
foundation_test_assert(($resolvedCanonical['dbId'] ?? '') === 'db-bench', 'exercise resolver matches canonical names');
foundation_test_assert(($resolvedAlias['dbId'] ?? '') === 'db-bench', 'exercise resolver matches aliases');
foundation_test_assert(($resolvedMatchTerm['dbId'] ?? '') === 'db-bench', 'exercise resolver matches substring terms');
foundation_test_assert(($resolvedActivityType['dbId'] ?? '') === 'db-canoeing', 'activity type resolver matches aliases');
foundation_test_assert((mattrics_foundation_resolve_activity_type_record('Running', $activityIndex)['dbId'] ?? '') === 'db-run', 'activity type resolver matches Garmin Running aliases');
foundation_test_assert((mattrics_foundation_resolve_activity_type_record('Treadmill Running', $activityIndex)['dbId'] ?? '') === 'db-run', 'activity type resolver matches Garmin Treadmill Running aliases');
foundation_test_assert((mattrics_foundation_resolve_activity_type_record('Trail Running', $activityIndex)['dbId'] ?? '') === 'db-run', 'activity type resolver matches Garmin Trail Running aliases');
foundation_test_assert((mattrics_foundation_resolve_activity_type_record('Virtual Running', $activityIndex)['dbId'] ?? '') === 'db-run', 'activity type resolver matches Garmin Virtual Running aliases');
foundation_test_assert((mattrics_foundation_resolve_activity_type_record('Walking', $activityIndex)['dbId'] ?? '') === 'db-walk', 'activity type resolver matches Garmin Walking aliases');
foundation_test_assert((mattrics_foundation_resolve_activity_type_record('Hiking', $activityIndex)['dbId'] ?? '') === 'db-hike', 'activity type resolver matches Garmin Hiking aliases');
foundation_test_assert((mattrics_foundation_resolve_activity_type_record('Cycling', $activityIndex)['dbId'] ?? '') === 'db-ride', 'activity type resolver matches Garmin Cycling aliases');
foundation_test_assert((mattrics_foundation_resolve_activity_type_record('Indoor Cycling', $activityIndex)['dbId'] ?? '') === 'db-ride', 'activity type resolver matches Garmin Indoor Cycling aliases');
foundation_test_assert((mattrics_foundation_resolve_activity_type_record('Mountain Biking', $activityIndex)['dbId'] ?? '') === 'db-ride', 'activity type resolver matches Garmin Mountain Biking aliases');
foundation_test_assert((mattrics_foundation_resolve_activity_type_record('Strength Training', $activityIndex)['dbId'] ?? '') === 'db-weighttraining', 'activity type resolver matches Garmin Strength Training aliases');
foundation_test_assert((mattrics_foundation_resolve_activity_type_record('Strength_Workout', $activityIndex)['dbId'] ?? '') === 'db-weighttraining', 'activity type resolver matches normalized Garmin Strength Workout aliases');
foundation_test_assert((mattrics_foundation_resolve_activity_type_record('Indoor Rowing', [
    'byNormalized' => [
        'rowing' => [
            'id' => 'rowing',
            'canonicalName' => 'Rowing',
            'normalizedName' => 'rowing',
            'aliases' => ['Erg', 'Indoor Rowing', 'Row Erg'],
            'dbId' => 'db-rowing',
        ],
    ],
    'byAlias' => [
        'erg' => ['dbId' => 'db-rowing'],
        'indoor rowing' => ['dbId' => 'db-rowing'],
        'row erg' => ['dbId' => 'db-rowing'],
    ],
])['dbId'] ?? '') === 'db-rowing', 'activity type resolver matches indoor rowing aliases');
foundation_test_assert(mattrics_normalize_config_name('HIITRun') === 'hiit run', 'shared config normalizer splits acronym-prefixed camel case names');

$childRows = mattrics_foundation_collect_activity_child_rows([
    'Description' => "Logged with Hevy\n\nBench Press\n80 kg x 5\n80 kg for 5 reps\n\nMystery Curl\n12 reps",
], $exerciseIndex);
foundation_test_assert(count($childRows['exercises']) === 2, 'child row builder creates one exercise row per Hevy block');
foundation_test_assert(($childRows['exercises'][0]['exerciseId'] ?? '') === 'db-bench', 'child row builder links resolved exercise blocks');
foundation_test_assert($childRows['exercises'][1]['exerciseId'] === null, 'child row builder preserves unresolved exercise blocks');
foundation_test_assert(($childRows['exercises'][1]['sets'][0]['parsedKind'] ?? '') === 'unknown', 'child row builder preserves unknown unresolved set parsing');

$activityDate = mattrics_foundation_parse_activity_date('2026-06-05T07:30:00+02:00');
foundation_test_assert($activityDate['activityDate'] === '2026-06-05' && $activityDate['startedAt'] !== null, 'activity date parser keeps date and started_at for timestamps');

$dateOnly = mattrics_foundation_parse_activity_date('2026-06-05');
foundation_test_assert($dateOnly['activityDate'] === '2026-06-05' && $dateOnly['startedAt'] === null, 'activity date parser leaves started_at null for date-only rows');

$snapshotPath = $privateRoot . '/cache/training-data.json';
file_put_contents($snapshotPath, json_encode([
    'rows' => [
        ['Name' => 'Bench', 'Type' => 'WeightTraining', 'Date' => '2026-06-05'],
    ],
    'count' => 1,
    'meta' => ['sourceVersion' => 1],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
$snapshot = mattrics_foundation_read_snapshot($snapshotPath);
foundation_test_assert(count($snapshot['rows']) === 1 && $snapshot['count'] === 1, 'snapshot reader loads rows and count');

$hevyImportRoot = $privateRoot . '/import-hevy';
mattrics_ensure_dir($hevyImportRoot);
$hevyCsvPath = $hevyImportRoot . '/workouts.csv';
file_put_contents($hevyCsvPath, <<<CSV
"title","start_time","end_time","description","exercise_title","superset_id","exercise_notes","set_index","set_type","weight_kg","reps","distance_km","duration_seconds","rpe"
"Mühsam ernährt sich das Eichhörnchen 🐿️","05.06.2026, 07:30","05.06.2026, 08:30","Warm üp 😅","Bench Press","","Erste Übung 💪","0","normal","80","5","","","8.5"
"Mühsam ernährt sich das Eichhörnchen 🐿️","05.06.2026, 07:30","05.06.2026, 08:30","Warm üp 😅","Push Up","","Erste Übung 💪","0","normal","","12","","",""
"Mühsam ernährt sich das Eichhörnchen 🐿️","05.06.2026, 07:30","05.06.2026, 08:30","Warm üp 😅","Row Erg","","Erste Übung 💪","0","drop","","","0.5","90",""
CSV);

$parsedHevyCsv = mattrics_foundation_read_hevy_csv_workouts($hevyCsvPath, 'Europe/Berlin');
foundation_test_assert($parsedHevyCsv['rowCount'] === 3, 'Hevy CSV reader counts source rows');
foundation_test_assert(count($parsedHevyCsv['workouts']) === 1, 'Hevy CSV reader groups rows into workouts');
foundation_test_assert(($parsedHevyCsv['workouts'][0]['title'] ?? '') === 'Mühsam ernährt sich das Eichhörnchen 🐿️', 'Hevy CSV reader preserves UTF-8 workout titles');
foundation_test_assert(abs((float) ($parsedHevyCsv['workouts'][0]['durationMinutes'] ?? 0) - 60.0) < 0.00001, 'Hevy CSV reader computes workout duration');
foundation_test_assert(str_starts_with((string) ($parsedHevyCsv['workouts'][0]['sourceActivityIdRaw'] ?? ''), 'hevy-csv-'), 'Hevy CSV reader derives deterministic source ids');

$hevyChildRows = mattrics_foundation_collect_hevy_csv_child_rows($parsedHevyCsv['workouts'][0], $exerciseIndex);
foundation_test_assert(($hevyChildRows['exercises'][0]['sourceExerciseName'] ?? '') === 'Bench Press', 'Hevy CSV child rows preserve exercise names');
foundation_test_assert(($hevyChildRows['exercises'][1]['sets'][0]['parsedKind'] ?? '') === 'parsed', 'Hevy CSV child rows estimate bodyweight-only rep sets');
foundation_test_assert(($hevyChildRows['exercises'][1]['sets'][0]['sourceSetText'] ?? '') === '12 reps', 'Hevy CSV child rows preserve original bodyweight set text');
foundation_test_assert(($hevyChildRows['exercises'][2]['sets'][0]['parsedKind'] ?? '') === 'time', 'Hevy CSV child rows map distance and duration sets');
foundation_test_assert(str_contains((string) ($hevyChildRows['exercises'][0]['sets'][0]['notes'] ?? ''), 'Warm üp 😅'), 'Hevy CSV child rows preserve workout descriptions in notes');
foundation_test_assert(str_contains((string) ($hevyChildRows['description'] ?? ''), 'Bench Press'), 'Hevy CSV child rows rebuild Hevy-compatible descriptions');

$invalidHevyCsvPath = $hevyImportRoot . '/invalid-workouts.csv';
file_put_contents($invalidHevyCsvPath, <<<CSV
"title","start_time","end_time","exercise_title"
"Broken","05.06.2026, 07:30","05.06.2026, 08:30","Bench Press"
CSV);

$invalidHeaderFailed = false;
try {
    mattrics_foundation_read_hevy_csv_workouts($invalidHevyCsvPath, 'Europe/Berlin');
} catch (RuntimeException $runtimeException) {
    $invalidHeaderFailed = str_contains($runtimeException->getMessage(), 'headers');
}
foundation_test_assert($invalidHeaderFailed, 'Hevy CSV reader rejects unsupported headers');

$garminFixturePath = dirname(__DIR__) . '/tests/fixtures/garmin-activities.csv';
$parsedGarminCsv = mattrics_foundation_read_garmin_csv_activities($garminFixturePath, 'Europe/Berlin');
foundation_test_assert($parsedGarminCsv['rowCount'] === 2, 'Garmin CSV reader counts source rows');
foundation_test_assert(count($parsedGarminCsv['activities']) === 2, 'Garmin CSV reader returns one record per activity row');
foundation_test_assert(($parsedGarminCsv['activities'][0]['title'] ?? '') === 'Backnang Running', 'Garmin CSV reader preserves titles');
foundation_test_assert(($parsedGarminCsv['activities'][0]['activityType'] ?? '') === 'Running', 'Garmin CSV reader preserves literal Garmin activity types');
foundation_test_assert(($parsedGarminCsv['activities'][0]['startedAt'] ?? '') === '2026-06-07T12:10:28+00:00', 'Garmin CSV reader converts local timestamps to UTC started_at values');
foundation_test_assert(abs((float) ($parsedGarminCsv['activities'][0]['durationMinutes'] ?? 0) - 45.0833333333) < 0.00001, 'Garmin CSV reader parses duration fields into minutes');
foundation_test_assert(abs((float) ($parsedGarminCsv['activities'][0]['avgPaceMinPerKm'] ?? 0) - 7.8666666667) < 0.00001, 'Garmin CSV reader parses Garmin pace fields into minutes per km');
foundation_test_assert(abs((float) ($parsedGarminCsv['activities'][0]['avgSpeedKmh'] ?? 0) - 7.63) < 0.00001, 'Garmin CSV reader derives average speed from distance and duration');
foundation_test_assert(array_key_exists('avgPaceMinPerKm', $parsedGarminCsv['activities'][1]) && $parsedGarminCsv['activities'][1]['avgPaceMinPerKm'] === null, 'Garmin CSV reader treats -- pace values as null');
foundation_test_assert(array_key_exists('elevationGainM', $parsedGarminCsv['activities'][1]) && $parsedGarminCsv['activities'][1]['elevationGainM'] === null, 'Garmin CSV reader treats -- elevation values as null');
foundation_test_assert(str_starts_with((string) ($parsedGarminCsv['activities'][0]['sourceActivityIdRaw'] ?? ''), 'garmin-csv-'), 'Garmin CSV reader derives deterministic source ids');
foundation_test_assert(
    ($parsedGarminCsv['activities'][0]['sourceActivityIdRaw'] ?? '') === ($parsedGarminCsv['activities'][0]['sourceActivityId'] ?? ''),
    'Garmin CSV reader uses the same deterministic id for source_activity_id and source_activity_id_raw'
);

$expandedGarminFixturePath = dirname(__DIR__) . '/tests/fixtures/garmin-activities-expanded.csv';
$parsedExpandedGarminCsv = mattrics_foundation_read_garmin_csv_activities($expandedGarminFixturePath, 'Europe/Berlin');
foundation_test_assert($parsedExpandedGarminCsv['rowCount'] === 3, 'expanded Garmin CSV reader counts source rows');
foundation_test_assert(count($parsedExpandedGarminCsv['activities']) === 3, 'expanded Garmin CSV reader returns one record per activity row');
foundation_test_assert(($parsedExpandedGarminCsv['activities'][1]['activityType'] ?? '') === 'Other', 'expanded Garmin CSV reader preserves literal Garmin activity types');
foundation_test_assert(array_key_exists('avgPaceMinPerKm', $parsedExpandedGarminCsv['activities'][1]) && $parsedExpandedGarminCsv['activities'][1]['avgPaceMinPerKm'] === null, 'expanded Garmin CSV reader treats decimal-like pace values as null instead of failing');
foundation_test_assert(abs((float) ($parsedExpandedGarminCsv['activities'][2]['durationMinutes'] ?? 0) - 20.0) < 0.00001, 'expanded Garmin CSV reader supports Garmin mm:ss.s duration fields');
foundation_test_assert(abs((float) ($parsedExpandedGarminCsv['activities'][2]['avgPaceMinPerKm'] ?? 0) - 4.7666666667) < 0.00001, 'expanded Garmin CSV reader still parses clock pace values');
foundation_test_assert(($parsedExpandedGarminCsv['activities'][2]['title'] ?? '') === 'Rowing - MyFitnessPal', 'expanded Garmin CSV reader safely ignores extra Garmin-only columns');

$invalidGarminHeaderFailed = false;
try {
    mattrics_foundation_read_garmin_csv_activities(dirname(__DIR__) . '/tests/fixtures/garmin-activities-invalid.csv', 'Europe/Berlin');
} catch (RuntimeException $runtimeException) {
    $invalidGarminHeaderFailed = str_contains($runtimeException->getMessage(), 'headers');
}
foundation_test_assert($invalidGarminHeaderFailed, 'Garmin CSV reader rejects unsupported headers');

$outsideGarminPath = $tempRoot . '/outside-garmin.csv';
copy($garminFixturePath, $outsideGarminPath);
$outsidePrivateRootRejected = false;
try {
    mattrics_foundation_resolve_private_input_path($outsideGarminPath, $privateRoot);
} catch (RuntimeException $runtimeException) {
    $outsidePrivateRootRejected = str_contains($runtimeException->getMessage(), 'private root');
}
foundation_test_assert($outsidePrivateRootRejected, 'private input resolver rejects Garmin files outside the selected private root');

$migrationFiles = mattrics_foundation_list_migration_files();
foundation_test_assert($migrationFiles !== [], 'foundation migration helper discovers forward-only SQL files');
foundation_test_assert(
    basename($migrationFiles[0] ?? '') === '20260608_001_foundation_hardening.sql',
    'foundation migration helper keeps migration files in deterministic order'
);
foundation_test_assert(
    in_array('20260609_001_live_connectors.sql', array_map('basename', $migrationFiles), true),
    'foundation migration helper includes the live connector migration'
);

$connectorStore = mattrics_foundation_load_connector_store($privateRoot);
foundation_test_assert(($connectorStore['version'] ?? null) === 2, 'connector store defaults to schema version 2');
foundation_test_assert(($connectorStore['connectors']['hevy']['enabled'] ?? true) === false, 'connector store defaults Hevy to disabled');
foundation_test_assert(($connectorStore['connectors']['garmin']['authType'] ?? '') === 'deferred', 'connector store defaults Garmin auth type to deferred');

file_put_contents(
    mattrics_foundation_connector_store_path($privateRoot),
    json_encode([
        'version' => 1,
        'connectors' => [
            'hevy' => [
                'enabled' => true,
                'apiKey' => 'legacy-hevy-key',
                'lastSyncAttemptAt' => '2026-06-09T10:00:00Z',
                'lastSyncSucceededAt' => '2026-06-09T10:05:00Z',
            ],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);
$legacyConnectorStore = mattrics_foundation_load_connector_store($privateRoot);
foundation_test_assert(($legacyConnectorStore['version'] ?? 0) === 2, 'connector store upgrades legacy schema version on read');
foundation_test_assert(
    ($legacyConnectorStore['connectors']['hevy']['syncState']['lastSucceededAt'] ?? '') === '2026-06-09T10:05:00Z',
    'connector store upgrades legacy top-level sync timestamps into nested sync state'
);

$savedConnectorStore = mattrics_foundation_save_connector_store([
    'version' => 1,
    'connectors' => [
        'hevy' => [
            'enabled' => true,
            'apiKey' => 'test-hevy-key',
            'syncState' => [
                'lastAttemptAt' => '2026-06-09T10:00:00Z',
                'lastSucceededAt' => '2026-06-09T10:05:00Z',
                'lastStrategy' => 'incremental_overlap',
                'cursorStartedAt' => '2026-06-09T09:55:00Z',
                'lastSyncWindowStartedAt' => '2026-05-10T09:55:00Z',
                'lastFetchedPageCount' => 2,
                'lastFetchedWorkoutCount' => 4,
            ],
        ],
        'garmin' => [
            'enabled' => true,
            'credentials' => ['mode' => 'deferred'],
            'syncState' => [
                'lastErrorAt' => '2026-06-09T10:10:00Z',
            ],
        ],
    ],
], $privateRoot);
foundation_test_assert(is_file(mattrics_foundation_connector_store_path($privateRoot)), 'connector store writes to private storage');

$connectorPayload = mattrics_foundation_connector_public_payload($privateRoot);
foundation_test_assert(($connectorPayload['hevy']['hasCredential'] ?? false) === true, 'connector payload reports Hevy credentials without exposing values');
foundation_test_assert(($connectorPayload['hevy']['connectionStatus'] ?? '') === 'active', 'connector payload reports active Hevy status after a successful sync');
foundation_test_assert(($connectorPayload['garmin']['connectionStatus'] ?? '') === 'error', 'connector payload reports Garmin connector errors from app-safe timestamps');
foundation_test_assert(!isset($connectorPayload['hevy']['apiKey']), 'connector payload never exposes secret API key values');
foundation_test_assert(($savedConnectorStore['connectors']['hevy']['apiKey'] ?? '') === 'test-hevy-key', 'connector store preserves private API key values on disk');
foundation_test_assert(($connectorPayload['hevy']['syncStrategy'] ?? '') === 'incremental_overlap', 'connector payload reports incremental Hevy sync strategy');
foundation_test_assert(($connectorPayload['hevy']['lastFetchedWorkoutCount'] ?? 0) === 4, 'connector payload reports app-safe incremental fetch counts');

$hevyLiveWorkout = mattrics_foundation_map_hevy_live_workout([
    'id' => 'hevy-live-1',
    'title' => 'Stronger Every Day',
    'description' => 'Live sync workout',
    'start_time' => '2026-06-05T07:30:00+02:00',
    'end_time' => '2026-06-05T08:15:00+02:00',
    'exercises' => [
        [
            'title' => 'Bench Press',
            'notes' => 'Pause reps',
            'sets' => [
                ['type' => 'normal', 'weight_kg' => 80, 'reps' => 5, 'rpe' => 8.5],
            ],
        ],
    ],
], 'Europe/Berlin');
foundation_test_assert(($hevyLiveWorkout['sourceActivityIdRaw'] ?? '') === 'hevy-live-1', 'Hevy live workout mapping keeps stable provider workout ids');
foundation_test_assert(($hevyLiveWorkout['activityDate'] ?? '') === '2026-06-05', 'Hevy live workout mapping preserves local activity dates');
foundation_test_assert(abs((float) ($hevyLiveWorkout['durationMinutes'] ?? 0) - 45.0) < 0.00001, 'Hevy live workout mapping derives workout duration from timestamps');
foundation_test_assert(($hevyLiveWorkout['exercises'][0]['sets'][0]['weightKg'] ?? null) === 80.0, 'Hevy live workout mapping normalizes set payloads for the shared importer');

$integrationDatabaseUrl = getenv('MATTRICS_FOUNDATION_TEST_DATABASE_URL') ?: 'postgres://mattrics:mattrics-local-only-change-me@127.0.0.1:5433/mattrics';
$integrationPdo = null;
try {
    $integrationPdo = mattrics_foundation_connect($integrationDatabaseUrl);
} catch (Throwable $throwable) {
    foundation_test_skip('Foundation migration integration tests skipped: ' . $throwable->getMessage());
}

if ($integrationPdo instanceof PDO) {
    $appliedFirst = mattrics_foundation_apply_migrations($integrationPdo);
    $appliedSecond = mattrics_foundation_apply_migrations($integrationPdo);
    foundation_test_assert(is_array($appliedFirst), 'foundation migrations return an applied-version list');
    foundation_test_assert($appliedSecond === [], 'foundation migrations are idempotent after the first successful apply');

    $appliedRows = mattrics_foundation_read_applied_migrations($integrationPdo);
    foundation_test_assert(
        isset($appliedRows['20260608_001_foundation_hardening.sql']),
        'foundation migrations record applied versions in the schema migration table'
    );

    $integrationPdo->beginTransaction();
    try {
        $userKey = 'foundation-migration-test-' . bin2hex(random_bytes(4));
        $userId = mattrics_foundation_ensure_user($integrationPdo, $userKey, 'Foundation Migration Test User', 'Europe/Berlin');
        $concept2SourceId = mattrics_foundation_ensure_data_source(
            $integrationPdo,
            $userId,
            'concept2-logbook-export',
            'concept2',
            'Concept2 Logbook Export'
        );
        $batch = mattrics_foundation_start_import_batch(
            $integrationPdo,
            $userId,
            $concept2SourceId,
            'concept2_logbook_export',
            __FILE__,
            'tests/foundation-import-tests.php'
        );
        foundation_test_assert($concept2SourceId !== '', 'foundation schema accepts concept2 source_kind values');
        foundation_test_assert(($batch['id'] ?? '') !== '', 'foundation schema accepts concept2_logbook_export batch kinds');

        $garminSourceId = mattrics_foundation_ensure_data_source(
            $integrationPdo,
            $userId,
            'garmin-activities-csv-export',
            'garmin',
            'Garmin Activities CSV Export'
        );
        $garminBatch = mattrics_foundation_start_import_batch(
            $integrationPdo,
            $userId,
            $garminSourceId,
            'garmin_export',
            __FILE__,
            'tests/foundation-import-tests.php'
        );
        foundation_test_assert($garminSourceId !== '', 'foundation schema accepts garmin source_kind values');
        foundation_test_assert(($garminBatch['id'] ?? '') !== '', 'foundation schema accepts garmin_export batch kinds');

        $hevyLiveSourceId = mattrics_foundation_ensure_data_source(
            $integrationPdo,
            $userId,
            'hevy-live-api',
            'hevy',
            'Hevy Live API',
            ['connectionStatus' => 'paused']
        );
        $hevyLiveBatch = mattrics_foundation_start_import_batch(
            $integrationPdo,
            $userId,
            $hevyLiveSourceId,
            'hevy_live_api_sync'
        );
        foundation_test_assert($hevyLiveSourceId !== '', 'foundation schema accepts live Hevy source keys');
        foundation_test_assert(($hevyLiveBatch['id'] ?? '') !== '', 'foundation schema accepts hevy_live_api_sync batch kinds');
    } finally {
        if ($integrationPdo->inTransaction()) {
            $integrationPdo->rollBack();
        }
    }

    $garminImportPrivateRoot = $tempRoot . '/garmin-import-private';
    mattrics_ensure_dir($garminImportPrivateRoot . '/data');
    mattrics_ensure_dir($garminImportPrivateRoot . '/import-garmin');
    file_put_contents(
        $garminImportPrivateRoot . '/data/activity-type-configs.json',
        json_encode([$activityTypeRecord], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
    copy($expandedGarminFixturePath, $garminImportPrivateRoot . '/import-garmin/Activities.csv');

    $garminImportSummary = mattrics_foundation_run_garmin_import($integrationPdo, [
        'userKey' => 'foundation-garmin-import-test-' . bin2hex(random_bytes(4)),
        'displayName' => 'Foundation Garmin Import Test User',
        'timezone' => 'Europe/Berlin',
        'privateRoot' => $garminImportPrivateRoot,
        'input' => 'import-garmin/Activities.csv',
        'dryRun' => true,
    ]);
    foundation_test_assert(
        (($garminImportSummary['batches']['garminExport']['importedRowCount'] ?? 0) === 3),
        'run_garmin_import reports Garmin batch row counts through the foundation import framework'
    );
    foundation_test_assert(
        (($garminImportSummary['counts']['activities'] ?? 0) === 3),
        'run_garmin_import reports imported Garmin activity counts through the foundation import framework'
    );

    $liveSyncPrivateRoot = $tempRoot . '/hevy-live-private';
    mattrics_ensure_dir($liveSyncPrivateRoot . '/data');
    mattrics_ensure_dir($liveSyncPrivateRoot . '/storage');
    file_put_contents(
        $liveSyncPrivateRoot . '/data/exercise-configs.json',
        json_encode([$exerciseRecord], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
    file_put_contents(
        $liveSyncPrivateRoot . '/data/activity-type-configs.json',
        json_encode([[
            'id' => 'weight-training',
            'canonicalName' => 'WeightTraining',
            'normalizedName' => 'weight training',
            'aliases' => ['Strength'],
            'muscleWeights' => ['chest' => 0.45],
            'fatigueMultiplier' => 1.0,
            'status' => 'approved',
            'reviewNeeded' => false,
            'source' => 'manual',
            'lastUpdatedAt' => '2026-06-09T00:00:00Z',
            'lastUpdatedType' => 'manual',
            'exerciseFamily' => 'conditioning_lower',
            'fatigueArchetype' => 'conditioning_hybrid',
        ]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
    mattrics_foundation_save_connector_store([
        'version' => 1,
        'connectors' => [
            'hevy' => [
                'enabled' => true,
                'apiKey' => 'test-hevy-key',
            ],
            'garmin' => [
                'enabled' => false,
                'credentials' => [],
            ],
        ],
    ], $liveSyncPrivateRoot);

    $liveSyncSummary = mattrics_foundation_run_live_connector_sync($integrationPdo, [
        'userKey' => 'foundation-hevy-live-test-' . bin2hex(random_bytes(4)),
        'displayName' => 'Foundation Hevy Live Test User',
        'timezone' => 'Europe/Berlin',
        'privateRoot' => $liveSyncPrivateRoot,
        'dryRun' => true,
        'requester' => static function (string $url, array $query, array $connectorRecord, string $path): array {
            if ($path === '/v1/workouts') {
                return [
                    'workouts' => [
                        ['id' => 'hevy-live-1'],
                    ],
                    'page_count' => 1,
                ];
            }

            if ($path === '/v1/workouts/hevy-live-1') {
                return [
                    'id' => 'hevy-live-1',
                    'title' => 'Live Workout',
                    'description' => 'Imported from API',
                    'start_time' => '2026-06-05T07:30:00+02:00',
                    'end_time' => '2026-06-05T08:15:00+02:00',
                    'exercises' => [
                        [
                            'title' => 'Bench Press',
                            'sets' => [
                                ['type' => 'normal', 'weight_kg' => 80, 'reps' => 5, 'rpe' => 8.5],
                            ],
                        ],
                    ],
                ];
            }

            return [];
        },
    ]);
    foundation_test_assert(
        (($liveSyncSummary['batches']['hevyLive']['importedRowCount'] ?? 0) === 1),
        'run_live_connector_sync reports imported Hevy API workout counts through the shared foundation importer'
    );
    foundation_test_assert(
        (($liveSyncSummary['batches']['hevyLive']['status'] ?? '') === 'succeeded'),
        'run_live_connector_sync reports successful Hevy live batches'
    );
    foundation_test_assert(
        (($liveSyncSummary['batches']['hevyLive']['strategy'] ?? '') === 'initial_full'),
        'run_live_connector_sync keeps the first live Hevy sync on full-history strategy'
    );
    foundation_test_assert(
        (($liveSyncSummary['sources']['garmin']['sourceKey'] ?? '') === 'garmin-connect-live'),
        'run_live_connector_sync registers Garmin live source groundwork even without a live fetcher'
    );

    $incrementalPrivateRoot = $tempRoot . '/hevy-live-incremental-private';
    mattrics_ensure_dir($incrementalPrivateRoot . '/data');
    mattrics_ensure_dir($incrementalPrivateRoot . '/storage');
    file_put_contents(
        $incrementalPrivateRoot . '/data/exercise-configs.json',
        json_encode([$exerciseRecord], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
    file_put_contents(
        $incrementalPrivateRoot . '/data/activity-type-configs.json',
        json_encode([[
            'id' => 'weight-training',
            'canonicalName' => 'WeightTraining',
            'normalizedName' => 'weight training',
            'aliases' => ['Strength'],
            'muscleWeights' => ['chest' => 0.45],
            'fatigueMultiplier' => 1.0,
            'status' => 'approved',
            'reviewNeeded' => false,
            'source' => 'manual',
            'lastUpdatedAt' => '2026-06-09T00:00:00Z',
            'lastUpdatedType' => 'manual',
            'exerciseFamily' => 'conditioning_lower',
            'fatigueArchetype' => 'conditioning_hybrid',
        ]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
    mattrics_foundation_save_connector_store([
        'version' => 2,
        'connectors' => [
            'hevy' => [
                'enabled' => true,
                'apiKey' => 'test-hevy-key',
            ],
            'garmin' => [
                'enabled' => false,
                'credentials' => [],
            ],
        ],
    ], $incrementalPrivateRoot);

    $incrementalUserKey = 'foundation-hevy-incremental-test-' . bin2hex(random_bytes(4));
    $initialRequestedPages = [];
    $initialIncrementalSummary = mattrics_foundation_run_live_connector_sync($integrationPdo, [
        'userKey' => $incrementalUserKey,
        'displayName' => 'Foundation Hevy Incremental Test User',
        'timezone' => 'Europe/Berlin',
        'privateRoot' => $incrementalPrivateRoot,
        'dryRun' => false,
        'requester' => static function (string $url, array $query, array $connectorRecord, string $path) use (&$initialRequestedPages): array {
            if ($path === '/v1/workouts') {
                $page = (int) ($query['page'] ?? 1);
                $initialRequestedPages[] = $page;
                if ($page === 1) {
                    return [
                        'workouts' => [
                            ['id' => 'initial-hevy-live-1'],
                        ],
                        'page_count' => 2,
                    ];
                }

                return [
                    'workouts' => [
                        ['id' => 'initial-hevy-live-2'],
                    ],
                    'page_count' => 2,
                ];
            }

            if ($path === '/v1/workouts/initial-hevy-live-1') {
                return [
                    'id' => 'initial-hevy-live-1',
                    'title' => 'Initial Live Workout A',
                    'description' => 'Imported from API',
                    'start_time' => '2026-06-08T07:30:00+02:00',
                    'end_time' => '2026-06-08T08:15:00+02:00',
                    'exercises' => [[
                        'title' => 'Bench Press',
                        'sets' => [['type' => 'normal', 'weight_kg' => 80, 'reps' => 5, 'rpe' => 8.5]],
                    ]],
                ];
            }

            if ($path === '/v1/workouts/initial-hevy-live-2') {
                return [
                    'id' => 'initial-hevy-live-2',
                    'title' => 'Initial Live Workout B',
                    'description' => 'Imported from API',
                    'start_time' => '2026-02-01T09:00:00+01:00',
                    'end_time' => '2026-02-01T09:45:00+01:00',
                    'exercises' => [[
                        'title' => 'Bench Press',
                        'sets' => [['type' => 'normal', 'weight_kg' => 75, 'reps' => 6, 'rpe' => 8.0]],
                    ]],
                ];
            }

            return [];
        },
    ]);
    foundation_test_assert(
        (($initialIncrementalSummary['batches']['hevyLive']['strategy'] ?? '') === 'initial_full'),
        'run_live_connector_sync uses initial_full strategy before any prior Hevy cursor exists'
    );
    foundation_test_assert(
        (($initialIncrementalSummary['batches']['hevyLive']['fetchedPageCount'] ?? 0) === 2),
        'run_live_connector_sync fetches every page on the initial full-history Hevy sync'
    );
    foundation_test_assert(
        $initialRequestedPages === [1, 2],
        'run_live_connector_sync requests all Hevy list pages on the initial full-history sync'
    );
    $savedIncrementalStore = mattrics_foundation_load_connector_store($incrementalPrivateRoot);
    foundation_test_assert(
        ($savedIncrementalStore['connectors']['hevy']['syncState']['lastStrategy'] ?? '') === 'initial_full',
        'run_live_connector_sync persists initial_full strategy in the private connector store'
    );
    foundation_test_assert(
        ($savedIncrementalStore['connectors']['hevy']['syncState']['cursorStartedAt'] ?? '') === '2026-06-08T05:30:00+00:00',
        'run_live_connector_sync persists the latest seen Hevy workout start time as the incremental cursor'
    );

    $incrementalRequestedPages = [];
    $incrementalSummary = mattrics_foundation_run_live_connector_sync($integrationPdo, [
        'userKey' => $incrementalUserKey,
        'displayName' => 'Foundation Hevy Incremental Test User',
        'timezone' => 'Europe/Berlin',
        'privateRoot' => $incrementalPrivateRoot,
        'dryRun' => false,
        'requester' => static function (string $url, array $query, array $connectorRecord, string $path) use (&$incrementalRequestedPages): array {
            if ($path === '/v1/workouts') {
                $page = (int) ($query['page'] ?? 1);
                $incrementalRequestedPages[] = $page;
                if ($page === 1) {
                    return [
                        'workouts' => [
                            ['id' => 'incremental-hevy-live-1'],
                        ],
                        'page_count' => 3,
                    ];
                }
                if ($page === 2) {
                    return [
                        'workouts' => [
                            ['id' => 'incremental-hevy-live-2'],
                        ],
                        'page_count' => 3,
                    ];
                }

                return [
                    'workouts' => [
                        ['id' => 'incremental-hevy-live-3'],
                    ],
                    'page_count' => 3,
                ];
            }

            if ($path === '/v1/workouts/incremental-hevy-live-1') {
                return [
                    'id' => 'incremental-hevy-live-1',
                    'title' => 'Incremental Live Workout Fresh',
                    'description' => 'Imported from API',
                    'start_time' => '2026-06-09T07:30:00+02:00',
                    'end_time' => '2026-06-09T08:15:00+02:00',
                    'exercises' => [[
                        'title' => 'Bench Press',
                        'sets' => [['type' => 'normal', 'weight_kg' => 82.5, 'reps' => 5, 'rpe' => 8.5]],
                    ]],
                ];
            }

            if ($path === '/v1/workouts/incremental-hevy-live-2') {
                return [
                    'id' => 'incremental-hevy-live-2',
                    'title' => 'Incremental Live Workout Old',
                    'description' => 'Imported from API',
                    'start_time' => '2026-04-01T07:30:00+02:00',
                    'end_time' => '2026-04-01T08:15:00+02:00',
                    'exercises' => [[
                        'title' => 'Bench Press',
                        'sets' => [['type' => 'normal', 'weight_kg' => 70, 'reps' => 8, 'rpe' => 7.5]],
                    ]],
                ];
            }

            if ($path === '/v1/workouts/incremental-hevy-live-3') {
                return [
                    'id' => 'incremental-hevy-live-3',
                    'title' => 'Incremental Live Workout Too Old',
                    'description' => 'Imported from API',
                    'start_time' => '2026-02-01T07:30:00+02:00',
                    'end_time' => '2026-02-01T08:15:00+02:00',
                    'exercises' => [[
                        'title' => 'Bench Press',
                        'sets' => [['type' => 'normal', 'weight_kg' => 67.5, 'reps' => 8, 'rpe' => 7.0]],
                    ]],
                ];
            }

            return [];
        },
    ]);
    foundation_test_assert(
        (($incrementalSummary['batches']['hevyLive']['strategy'] ?? '') === 'incremental_overlap'),
        'run_live_connector_sync switches to incremental_overlap after the first successful Hevy live sync'
    );
    foundation_test_assert(
        (($incrementalSummary['batches']['hevyLive']['fetchedPageCount'] ?? 0) === 2),
        'run_live_connector_sync stops paging once a Hevy page is fully older than the overlap window'
    );
    foundation_test_assert(
        $incrementalRequestedPages === [1, 2],
        'run_live_connector_sync does not request older Hevy pages once the bounded overlap window is crossed'
    );

    $corruptStore = mattrics_foundation_load_connector_store($incrementalPrivateRoot);
    $corruptStore['connectors']['hevy']['syncState']['cursorStartedAt'] = 'not-a-date';
    $corruptStore['connectors']['hevy']['syncState']['lastRemoteMarkerAt'] = null;
    $corruptStore['connectors']['hevy']['syncState']['lastNewestFetchedStartedAt'] = null;
    $corruptStore['connectors']['hevy']['syncState']['lastSucceededAt'] = '2026-06-10T00:00:00Z';
    mattrics_foundation_save_connector_store($corruptStore, $incrementalPrivateRoot);

    $corruptRequestedPages = [];
    $corruptSummary = mattrics_foundation_run_live_connector_sync($integrationPdo, [
        'userKey' => $incrementalUserKey,
        'displayName' => 'Foundation Hevy Incremental Test User',
        'timezone' => 'Europe/Berlin',
        'privateRoot' => $incrementalPrivateRoot,
        'dryRun' => false,
        'requester' => static function (string $url, array $query, array $connectorRecord, string $path) use (&$corruptRequestedPages): array {
            if ($path === '/v1/workouts') {
                $page = (int) ($query['page'] ?? 1);
                $corruptRequestedPages[] = $page;
                if ($page === 1) {
                    return [
                        'workouts' => [
                            ['id' => 'corrupt-hevy-live-1'],
                        ],
                        'page_count' => 3,
                    ];
                }
                if ($page === 2) {
                    return [
                        'workouts' => [
                            ['id' => 'corrupt-hevy-live-2'],
                        ],
                        'page_count' => 3,
                    ];
                }

                return [
                    'workouts' => [
                        ['id' => 'corrupt-hevy-live-3'],
                    ],
                    'page_count' => 3,
                ];
            }

            if ($path === '/v1/workouts/corrupt-hevy-live-1') {
                return [
                    'id' => 'corrupt-hevy-live-1',
                    'title' => 'Corrupt Cursor Fresh Workout',
                    'description' => 'Imported from API',
                    'start_time' => '2026-06-09T08:00:00+02:00',
                    'end_time' => '2026-06-09T08:45:00+02:00',
                    'exercises' => [[
                        'title' => 'Bench Press',
                        'sets' => [['type' => 'normal', 'weight_kg' => 80, 'reps' => 5, 'rpe' => 8.0]],
                    ]],
                ];
            }

            if ($path === '/v1/workouts/corrupt-hevy-live-2') {
                return [
                    'id' => 'corrupt-hevy-live-2',
                    'title' => 'Corrupt Cursor Old Workout',
                    'description' => 'Imported from API',
                    'start_time' => '2026-04-01T08:00:00+02:00',
                    'end_time' => '2026-04-01T08:45:00+02:00',
                    'exercises' => [[
                        'title' => 'Bench Press',
                        'sets' => [['type' => 'normal', 'weight_kg' => 72.5, 'reps' => 7, 'rpe' => 7.5]],
                    ]],
                ];
            }

            if ($path === '/v1/workouts/corrupt-hevy-live-3') {
                return [
                    'id' => 'corrupt-hevy-live-3',
                    'title' => 'Corrupt Cursor Too Old Workout',
                    'description' => 'Imported from API',
                    'start_time' => '2026-01-01T08:00:00+02:00',
                    'end_time' => '2026-01-01T08:45:00+02:00',
                    'exercises' => [[
                        'title' => 'Bench Press',
                        'sets' => [['type' => 'normal', 'weight_kg' => 65, 'reps' => 10, 'rpe' => 7.0]],
                    ]],
                ];
            }

            return [];
        },
    ]);
    foundation_test_assert(
        (($corruptSummary['batches']['hevyLive']['strategy'] ?? '') === 'incremental_overlap'),
        'run_live_connector_sync keeps a corrupt Hevy cursor on bounded incremental overlap instead of falling back to full history'
    );
    foundation_test_assert(
        $corruptRequestedPages === [1, 2],
        'run_live_connector_sync still stops at the overlap boundary when the saved Hevy cursor is corrupt but prior sync history exists'
    );
}

if ($failed > 0) {
    fwrite(STDERR, "foundation-import-tests: {$passed} passed, {$failed} failed, {$skipped} skipped\n");
    exit(1);
}

fwrite(STDOUT, "foundation-import-tests: {$passed} passed, 0 failed, {$skipped} skipped\n");
