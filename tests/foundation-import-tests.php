<?php
declare(strict_types=1);

$tempRoot = sys_get_temp_dir() . '/mattrics-foundation-import-tests-' . bin2hex(random_bytes(4));
mkdir($tempRoot, 0775, true);
define('MATTWARDEN_SITE_DIR', $tempRoot);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/foundation-import.php';

$passed = 0;
$failed = 0;

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


$privateRoot = $tempRoot . '/private';
mattrics_ensure_dir($privateRoot . '/data');
mattrics_ensure_dir($privateRoot . '/cache');
mattrics_ensure_dir($privateRoot . '/storage');

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
foreach ([
    'Logged with Hevy',
    'HevyApp workout',
    'Logged with HevyApp.com',
    'Logged with hevyapp.com',
    'Mit hevyapp.com protokolliert',
    'MIT HEVY APP PROTOKOLLIERT',
] as $header) {
    $description = "\n{$header}\r\n\r\nBench Press\r\n80 kg x 5";
    $exercises = mattrics_foundation_parse_hevy_description($description);
    foundation_test_assert(mattrics_foundation_hevy_is_description($description), "parser recognizes first-line header: {$header}");
    foundation_test_assert(mattrics_foundation_strip_hevy_header($description) === "Bench Press\r\n80 kg x 5", "parser strips entire header: {$header}");
    foundation_test_assert(is_array($exercises) && count($exercises) === 1 && $exercises[0]['name'] === 'Bench Press', "parser preserves first exercise: {$header}");
}
foreach ([
    "Ordinary workout\nLogged with Hevy\n\nBench Press\n80 kg x 5",
    "My hevyweight workout\n\nBench Press\n80 kg x 5",
    "My heavy workout\n\nBench Press\n80 kg x 5",
] as $description) {
    foundation_test_assert(!mattrics_foundation_hevy_is_description($description), 'parser rejects later mentions and longer unrelated words');
    foundation_test_assert(mattrics_foundation_parse_hevy_description($description) === null, 'non-Hevy descriptions do not produce exercise blocks');
    foundation_test_assert(mattrics_foundation_strip_hevy_header($description) === $description, 'non-Hevy descriptions retain their first line');
}

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

if ($failed > 0) {
    fwrite(STDERR, "foundation-import-tests: {$passed} passed, {$failed} failed\n");
    exit(1);
}

fwrite(STDOUT, "foundation-import-tests: {$passed} passed, 0 failed\n");
