<?php
declare(strict_types=1);

$tempRoot = sys_get_temp_dir() . '/mattrics-foundation-compat-tests-' . bin2hex(random_bytes(4));
$privateRoot = $tempRoot . '/private';
$dataRoot = $privateRoot . '/data';
$cacheRoot = $privateRoot . '/cache';
mkdir($dataRoot, 0775, true);
mkdir($cacheRoot, 0775, true);
$siteRoot = $tempRoot . '/site';
mkdir($siteRoot, 0775, true);

define('MATTWARDEN_SITE_DIR', $siteRoot);
putenv('MATTWARDEN_TEST_SITE_DIR=' . $siteRoot);
require_once dirname(__DIR__) . '/tests/stubs/mattwarden.php';
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/exercise-config-repository.php';
require_once dirname(__DIR__) . '/lib/foundation-read.php';
require_once dirname(__DIR__) . '/lib/foundation-import.php';

$passed = 0;
$failed = 0;
$skipped = 0;

function compat_test_assert(bool $condition, string $message): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }

    $failed++;
    fwrite(STDERR, "FAIL: {$message}\n");
}

function compat_test_skip(string $message): void
{
    global $skipped;
    $skipped++;
    fwrite(STDOUT, "SKIP: {$message}\n");
}

function compat_write_php_config(string $path, array $config): void
{
    file_put_contents($path, "<?php\nreturn " . var_export($config, true) . ";\n");
}

function compat_write_json(string $path, mixed $value): void
{
    file_put_contents($path, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function compat_run_php_fixture(string $fixturePath, string $stdin = ''): array
{
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($fixturePath);
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptorSpec, $pipes, dirname(__DIR__));

    if (!is_resource($process)) {
        return [
            'started' => false,
            'stdout' => '',
            'stderr' => '',
            'exitCode' => 1,
            'decoded' => null,
        ];
    }

    fwrite($pipes[0], $stdin);
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [
        'started' => true,
        'stdout' => $stdout,
        'stderr' => $stderr,
        'exitCode' => proc_close($process),
        'decoded' => json_decode($stdout, true),
    ];
}

function compat_reset_config(string $configPath, array $config): void
{
    compat_write_php_config($configPath, $config);
    $sitePrivate = MATTWARDEN_SITE_DIR . '/private';
    if (is_link($sitePrivate)) {
        unlink($sitePrivate);
    }
    if (!symlink(dirname($configPath), $sitePrivate)) {
        throw new RuntimeException('Could not point the test site at the case private directory.');
    }
    mattrics_foundation_read_reset_cache();
}

function compat_seed_direct_concept2_activity(PDO $pdo, string $userKey, array $activity): void
{
    $userId = mattrics_foundation_ensure_user($pdo, $userKey, 'Compatibility API Test User', 'Europe/Berlin');
    $dataSourceId = mattrics_foundation_ensure_data_source(
        $pdo,
        $userId,
        'concept2-logbook-export',
        'concept2',
        'Concept2 Logbook Export'
    );
    $batch = mattrics_foundation_start_import_batch(
        $pdo,
        $userId,
        $dataSourceId,
        'concept2_logbook_export',
        __FILE__,
        'tests/foundation-compat-api-tests.php'
    );

    $statement = $pdo->prepare(<<<'SQL'
INSERT INTO mattrics.mattrics_activities (
    user_id,
    data_source_id,
    import_batch_id,
    activity_date,
    started_at,
    activity_name,
    activity_type_name,
    source_activity_id,
    source_activity_id_raw,
    distance_km,
    duration_minutes,
    description,
    device_name
)
VALUES (
    :user_id,
    :data_source_id,
    :import_batch_id,
    :activity_date,
    :started_at,
    :activity_name,
    :activity_type_name,
    :source_activity_id,
    :source_activity_id_raw,
    :distance_km,
    :duration_minutes,
    :description,
    :device_name
)
ON CONFLICT (data_source_id, source_activity_id_raw) WHERE source_activity_id_raw IS NOT NULL DO UPDATE
SET import_batch_id = EXCLUDED.import_batch_id,
    activity_date = EXCLUDED.activity_date,
    started_at = EXCLUDED.started_at,
    activity_name = EXCLUDED.activity_name,
    activity_type_name = EXCLUDED.activity_type_name,
    source_activity_id = EXCLUDED.source_activity_id,
    distance_km = EXCLUDED.distance_km,
    duration_minutes = EXCLUDED.duration_minutes,
    description = EXCLUDED.description,
    device_name = EXCLUDED.device_name
SQL);
    $statement->execute([
        'user_id' => $userId,
        'data_source_id' => $dataSourceId,
        'import_batch_id' => $batch['id'],
        'activity_date' => $activity['activityDate'],
        'started_at' => $activity['startedAt'],
        'activity_name' => $activity['activityName'],
        'activity_type_name' => $activity['activityTypeName'] ?? 'Rowing',
        'source_activity_id' => $activity['sourceActivityId'],
        'source_activity_id_raw' => $activity['sourceActivityIdRaw'],
        'distance_km' => $activity['distanceKm'],
        'duration_minutes' => $activity['durationMinutes'],
        'description' => $activity['description'] ?? null,
        'device_name' => 'Concept2',
    ]);

    mattrics_foundation_finish_import_batch($pdo, $batch['id'], 1, 1, 'succeeded');
}

$configPath = $privateRoot . '/config.php';
compat_reset_config($configPath, [
    'auth_require_https' => false,
    'foundation_database_url' => '',
    'foundation_user_key' => 'legacy-local-user',
]);

$legacyExercises = [[
    'id' => 'bench-press',
    'canonicalName' => 'Bench Press',
    'normalizedName' => 'bench press',
    'aliases' => ['Push Up'],
    'matchTerms' => ['bench'],
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
]];

$legacyActivityTypes = [[
    'id' => 'run',
    'canonicalName' => 'Run',
    'normalizedName' => 'run',
    'aliases' => ['Easy Run'],
    'muscleWeights' => ['quadriceps' => 1.0, 'hamstrings' => 0.65],
    'fatigueMultiplier' => 1.0,
    'status' => 'approved',
    'reviewNeeded' => false,
    'source' => 'manual',
    'lastUpdatedAt' => '2026-06-06T00:00:00Z',
    'lastUpdatedType' => 'manual',
    'exerciseFamily' => 'conditioning_lower',
    'fatigueArchetype' => 'conditioning_hybrid',
]];

$legacyUnknowns = [[
    'id' => 'exercise:mystery curl',
    'sourceType' => 'exercise',
    'normalizedName' => 'mystery curl',
    'rawNames' => ['Mystery Curl'],
    'timesSeen' => 1,
    'firstSeenAt' => '2026-06-06T00:00:00Z',
    'lastSeenAt' => '2026-06-06T00:00:00Z',
    'aiStatus' => 'not_requested',
]];

$legacySnapshot = [
    'rows' => [[
        'Date' => '2026-06-05',
        'Type' => 'Run',
        'Name' => 'Legacy Run',
        'Distance (km)' => 5.5,
        'Duration (min)' => 30,
        'Activity ID' => 'legacy-run-1',
        'Activity ID raw' => 'legacy-run-raw-1',
    ]],
    'count' => 1,
    'meta' => [
        'lastSuccessfulSyncAt' => '2026-06-06T00:00:00Z',
        'sourceVersion' => 1,
    ],
];

mattrics_write_exercise_config_records($legacyExercises);
mattrics_write_activity_type_config_records($legacyActivityTypes);
mattrics_write_unknown_exercise_records($legacyUnknowns);
mattrics_write_snapshot($legacySnapshot);

$mappedActivity = mattrics_foundation_read_map_activity_row([
    'activity_date' => '2026-06-05',
    'started_at' => '2026-06-05T05:30:00+00:00',
    'activity_name' => 'Morning Lift',
    'activity_type_name' => 'WeightTraining',
    'display_activity_name' => 'Edited Morning Lift',
    'display_activity_type_name' => 'Strength',
    'source_activity_id' => 'canonical-1',
    'source_activity_id_raw' => 'raw-canonical-1',
    'distance_km' => '0',
    'duration_minutes' => '55.50',
    'elevation_gain_m' => '0',
    'avg_hr' => '122',
    'max_hr' => '155',
    'avg_pace_min_per_km' => null,
    'avg_speed_kmh' => null,
    'avg_cadence' => null,
    'description' => 'Test description',
    'device_name' => 'Hevy',
]);
compat_test_assert(($mappedActivity['Date'] ?? '') === '2026-06-05T05:30:00+00:00', 'activity mapping prefers started_at for Date');
compat_test_assert(($mappedActivity['Activity ID raw'] ?? '') === 'raw-canonical-1', 'activity mapping preserves Activity ID raw');
compat_test_assert(($mappedActivity['Avg HR'] ?? null) === 122, 'activity mapping casts numeric metrics');
compat_test_assert(($mappedActivity['Name'] ?? '') === 'Edited Morning Lift', 'activity mapping prefers duplicate-resolution display titles when present');
compat_test_assert(($mappedActivity['Type'] ?? '') === 'Strength', 'activity mapping prefers duplicate-resolution display activity types when present');

$mappedExercise = mattrics_foundation_read_map_exercise_record([
    'legacy_config_id' => 'bench-press',
    'canonical_name' => 'Bench Press',
    'normalized_name' => 'bench press',
    'fatigue_impact' => 'normal',
    'fatigue_multiplier' => '1.000',
    'bodyweight_eligible' => true,
    'set_type_handling' => 'weight_reps',
    'config_source' => 'manual',
    'last_updated_at' => '2026-06-06 00:00:00+00',
    'last_updated_type' => 'manual',
    'exercise_family' => 'horizontal_press',
    'fatigue_archetype' => 'freeweight_compound',
    'chest_weight' => '1.000',
    'triceps_weight' => '0.450',
    'upper_back_weight' => '0.080',
], ['Push Up'], ['bench']);
compat_test_assert(($mappedExercise['id'] ?? '') === 'bench-press', 'exercise mapping prefers legacy config ids');
compat_test_assert(($mappedExercise['aliases'][0] ?? '') === 'Push Up', 'exercise mapping preserves aliases');
compat_test_assert(abs((float) ($mappedExercise['muscleWeights']['upperBack'] ?? 0.0) - 0.08) < 0.00001, 'exercise mapping converts muscle columns back to camelCase keys');

$mappedActivityType = mattrics_foundation_read_map_activity_type_record([
    'legacy_config_id' => null,
    'canonical_name' => 'Run',
    'normalized_name' => 'run',
    'status' => 'approved',
    'review_needed' => false,
    'config_source' => 'manual',
    'last_updated_at' => '2026-06-06 00:00:00+00',
    'last_updated_type' => 'manual',
    'exercise_family' => 'conditioning_lower',
    'fatigue_archetype' => 'conditioning_hybrid',
    'fatigue_multiplier' => '1.000',
    'quadriceps_weight' => '1.000',
    'hamstrings_weight' => '0.650',
], ['Easy Run']);
compat_test_assert(($mappedActivityType['id'] ?? '') === 'run', 'activity type mapping falls back to normalized_name when legacy id is missing');
compat_test_assert(($mappedActivityType['aliases'][0] ?? '') === 'Easy Run', 'activity type mapping preserves aliases');

$mappedChildren = mattrics_foundation_read_map_activity_children([
    [
        'activity_db_id' => 'activity-1',
        'activity_name' => 'Morning Lift',
        'source_activity_id' => 'canonical-1',
        'source_activity_id_raw' => 'raw-canonical-1',
        'activity_exercise_id' => 'exercise-row-1',
        'source_exercise_name' => 'Bench Press',
        'normalized_source_exercise_name' => 'bench press',
        'exercise_id' => 'db-bench',
        'canonical_exercise_name' => 'Bench Press',
        'set_id' => 'set-1',
        'set_order' => '1',
        'parsed_kind' => 'parsed',
        'source_set_text' => '80 kg x 5',
        'reps' => '5',
        'weight_kg' => '80.000',
        'duration_minutes' => null,
        'distance_km' => null,
        'rpe' => '8.50',
        'effort_factor' => '1.000',
        'computed_load' => '400.0000',
        'notes' => null,
    ],
    [
        'activity_db_id' => 'activity-1',
        'activity_name' => 'Morning Lift',
        'source_activity_id' => 'canonical-1',
        'source_activity_id_raw' => 'raw-canonical-1',
        'activity_exercise_id' => 'exercise-row-1',
        'source_exercise_name' => 'Bench Press',
        'normalized_source_exercise_name' => 'bench press',
        'exercise_id' => 'db-bench',
        'canonical_exercise_name' => 'Bench Press',
        'set_id' => 'set-2',
        'set_order' => '2',
        'parsed_kind' => 'parsed',
        'source_set_text' => '82.5 kg x 5',
        'reps' => '5',
        'weight_kg' => '82.500',
        'duration_minutes' => null,
        'distance_km' => null,
        'rpe' => null,
        'effort_factor' => '1.000',
        'computed_load' => '412.5000',
        'notes' => null,
    ],
]);
compat_test_assert(count($mappedChildren) === 1, 'child mapping groups rows by activity');
compat_test_assert(($mappedChildren[0]['activityIdRaw'] ?? '') === 'raw-canonical-1', 'child mapping exposes activityIdRaw');
compat_test_assert(count($mappedChildren[0]['exercises'][0]['sets'] ?? []) === 2, 'child mapping nests ordered set rows');

$mappedTimeSet = mattrics_foundation_read_map_child_set([
    'set_id' => 'time-set-1',
    'set_order' => '3',
    'parsed_kind' => 'time',
    'source_set_text' => 'steady state',
    'reps' => null,
    'weight_kg' => null,
    'duration_minutes' => '12.50',
    'distance_km' => '4.25',
    'rpe' => null,
    'effort_factor' => null,
    'computed_load' => null,
    'notes' => 'Tempo',
]);
compat_test_assert(($mappedTimeSet['parsedKind'] ?? '') === 'time', 'child set mapping preserves parsedKind for time-based sets');
compat_test_assert(abs((float) ($mappedTimeSet['durationMinutes'] ?? 0) - 12.5) < 0.00001, 'child set mapping exposes typed durationMinutes values');
compat_test_assert(abs((float) ($mappedTimeSet['distanceKm'] ?? 0) - 4.25) < 0.00001, 'child set mapping exposes typed distanceKm values');

$directConcept2Role = mattrics_foundation_read_source_role([
    'source_kind' => 'concept2',
    'source_key' => 'concept2-logbook-export',
]);
compat_test_assert($directConcept2Role === 'direct_concept2', 'source role mapping recognizes direct Concept2 provenance');

$directGarminRole = mattrics_foundation_read_source_role([
    'source_kind' => 'garmin',
    'source_key' => 'garmin-activities-csv-export',
]);
compat_test_assert($directGarminRole === 'direct_garmin', 'source role mapping recognizes direct Garmin provenance');

$directHevyRole = mattrics_foundation_read_source_role([
    'source_kind' => 'hevy',
    'source_key' => 'hevy-live-api',
]);
compat_test_assert($directHevyRole === 'direct_hevy', 'source role mapping recognizes live Hevy provenance');

$hevySignature = mattrics_foundation_read_activity_signature_payload([
    'source_kind' => 'hevy',
    'source_key' => 'hevy-live-api',
    'started_at' => '2026-06-05T07:30:00+02:00',
    'activity_date' => '2026-06-05',
    'activity_name' => 'Edited In Strava',
    'activity_type_name' => 'Strength',
    'duration_minutes' => '60.00',
    'description' => "Logged with HevyApp.com\n\nBench Press\n80 kg x 5",
    'device_name' => 'Hevy',
], 'Europe/Berlin');
compat_test_assert(($hevySignature['family'] ?? '') === 'hevy', 'Hevy signature payload uses the Hevy dedupe family');
compat_test_assert(trim((string) ($hevySignature['signature'] ?? '')) !== '', 'Hevy signature payload is created without relying on edited workout titles');
compat_test_assert(
    mattrics_foundation_read_hevy_description_anchor(['description' => "Mit hevyapp.com protokolliert\n\nBench Press\n80 kg x 5"]) === 'bench press',
    'Hevy dedupe skips the German header and uses the first exercise as its fallback anchor'
);

$garminSignature = mattrics_foundation_read_activity_signature_payload([
    'source_kind' => 'garmin',
    'source_key' => 'garmin-activities-csv-export',
    'started_at' => '2026-06-04T06:00:00+02:00',
    'activity_date' => '2026-06-04',
    'activity_name' => 'Edited Easy Run',
    'activity_type_name' => 'Run',
    'distance_km' => '5.200',
    'duration_minutes' => '29.00',
    'device_name' => 'Garmin Forerunner 55',
], 'Europe/Berlin');
compat_test_assert(($garminSignature['family'] ?? '') === 'garmin', 'Garmin signature payload uses the Garmin dedupe family');
compat_test_assert(trim((string) ($garminSignature['signature'] ?? '')) !== '', 'Garmin signature payload is created without relying on edited activity types');

$unsafeGarminSignature = mattrics_foundation_read_activity_signature_payload([
    'source_kind' => 'garmin',
    'source_key' => 'garmin-activities-csv-export',
    'started_at' => '',
    'activity_date' => '2026-06-01',
    'activity_name' => '',
    'activity_type_name' => 'Walking',
    'distance_km' => '',
    'duration_minutes' => '70.58',
    'device_name' => 'Garmin',
], 'Europe/Berlin');
compat_test_assert($unsafeGarminSignature === null, 'Garmin dedupe stays disabled when deterministic Garmin signature fields are missing');

$concept2Signature = mattrics_foundation_read_activity_signature_payload([
    'source_kind' => 'concept2',
    'source_key' => 'concept2-logbook-export',
    'started_at' => '2026-06-05T07:15:00+02:00',
    'activity_date' => '2026-06-05',
    'activity_name' => 'Morning Row',
    'distance_km' => '10.000',
    'duration_minutes' => '42.50',
    'device_name' => 'Concept2',
], 'Europe/Berlin');
compat_test_assert(($concept2Signature['family'] ?? '') === 'concept2', 'Concept2 signature payload uses the concept2 dedupe family');
compat_test_assert(trim((string) ($concept2Signature['signature'] ?? '')) !== '', 'Concept2 signature payload is created for deterministic direct imports');

$unsafeConcept2Signature = mattrics_foundation_read_activity_signature_payload([
    'source_kind' => 'concept2',
    'source_key' => 'concept2-logbook-export',
    'started_at' => '',
    'activity_date' => '2026-06-05',
    'activity_name' => '',
    'distance_km' => '10.000',
    'duration_minutes' => '42.50',
    'device_name' => 'Concept2',
], 'Europe/Berlin');
compat_test_assert($unsafeConcept2Signature === null, 'Concept2 dedupe stays disabled when no stable anchor exists');

$legacyDataResult = compat_run_php_fixture(dirname(__DIR__) . '/tests/fixtures/run-data-endpoint.php');
compat_test_assert(($legacyDataResult['exitCode'] ?? 1) === 0, 'legacy data fixture exits successfully');
compat_test_assert(($legacyDataResult['decoded']['meta']['source'] ?? '') === 'cache', 'legacy data endpoint still serves cached snapshot when canonical mode is off');
compat_test_assert(count($legacyDataResult['decoded']['rows'] ?? []) === 1, 'legacy data endpoint returns snapshot rows');

$legacyExercisesResult = compat_run_php_fixture(dirname(__DIR__) . '/tests/fixtures/run-exercises-endpoint.php');
compat_test_assert(($legacyExercisesResult['exitCode'] ?? 1) === 0, 'legacy exercises fixture exits successfully');
compat_test_assert(($legacyExercisesResult['decoded']['meta']['source'] ?? '') === 'legacy', 'legacy exercises endpoint reports legacy source when canonical mode is off');
compat_test_assert(array_key_exists('lastSuccessfulSyncAt', $legacyExercisesResult['decoded']['meta'] ?? []), 'legacy exercises endpoint always includes lastSuccessfulSyncAt meta');
compat_test_assert(($legacyExercisesResult['decoded']['meta']['lastSuccessfulSyncAt'] ?? null) === null, 'legacy exercises endpoint reports null lastSuccessfulSyncAt when canonical mode is off');
compat_test_assert(count($legacyExercisesResult['decoded']['exercises'] ?? []) === 1, 'legacy exercises endpoint still returns legacy exercise configs when canonical mode is off');
compat_test_assert(count($legacyExercisesResult['decoded']['activityTypes'] ?? []) === 1, 'legacy exercises endpoint still returns legacy activity types when canonical mode is off');

$integrationDatabaseUrl = getenv('MATTRICS_FOUNDATION_TEST_DATABASE_URL') ?: 'postgres://mattrics:mattrics-local-only-change-me@127.0.0.1:5433/mattrics';
$integrationPdo = null;
try {
    $integrationPdo = mattrics_foundation_read_connect($integrationDatabaseUrl);
} catch (Throwable $throwable) {
    compat_test_skip('Canonical integration tests skipped: ' . $throwable->getMessage());
}

if ($integrationPdo instanceof PDO) {
    $integrationRoot = $tempRoot . '/integration-private';
    $integrationDataRoot = $integrationRoot . '/data';
    $integrationCacheRoot = $integrationRoot . '/cache';
    mkdir($integrationDataRoot, 0775, true);
    mkdir($integrationCacheRoot, 0775, true);

    $integrationExercises = [[
        'id' => 'bench-press',
        'canonicalName' => 'Bench Press',
        'normalizedName' => 'bench press',
        'aliases' => ['Flat Bench'],
        'matchTerms' => ['bench'],
        'muscleWeights' => ['chest' => 1.0, 'triceps' => 0.45],
        'fatigueImpact' => 'normal',
        'fatigueMultiplier' => 1.0,
        'bodyweightEligible' => false,
        'setTypeHandling' => 'weight_reps',
        'source' => 'manual',
        'lastUpdatedAt' => '2026-06-06T00:00:00Z',
        'lastUpdatedType' => 'manual',
        'exerciseFamily' => 'horizontal_press',
        'fatigueArchetype' => 'freeweight_compound',
    ]];
    $integrationActivityTypes = [[
        'id' => 'run',
        'canonicalName' => 'Run',
        'normalizedName' => 'run',
        'aliases' => ['Easy Run', 'Running', 'Treadmill Running'],
        'muscleWeights' => ['quadriceps' => 1.0, 'hamstrings' => 0.65],
        'fatigueMultiplier' => 1.0,
        'status' => 'approved',
        'reviewNeeded' => false,
        'source' => 'manual',
        'lastUpdatedAt' => '2026-06-06T00:00:00Z',
        'lastUpdatedType' => 'manual',
        'exerciseFamily' => 'conditioning_lower',
        'fatigueArchetype' => 'conditioning_hybrid',
    ], [
        'id' => 'walk',
        'canonicalName' => 'Walk',
        'normalizedName' => 'walk',
        'aliases' => ['Walking'],
        'muscleWeights' => ['quadriceps' => 0.42, 'hamstrings' => 0.31, 'calves' => 0.41],
        'fatigueMultiplier' => 1.0,
        'status' => 'approved',
        'reviewNeeded' => false,
        'source' => 'manual',
        'lastUpdatedAt' => '2026-06-06T00:00:00Z',
        'lastUpdatedType' => 'manual',
        'exerciseFamily' => 'conditioning_lower',
        'fatigueArchetype' => 'isolation',
    ], [
        'id' => 'rowing',
        'canonicalName' => 'Rowing',
        'normalizedName' => 'rowing',
        'aliases' => ['Erg'],
        'muscleWeights' => ['upperBack' => 0.65, 'biceps' => 0.35, 'quadriceps' => 0.35],
        'fatigueMultiplier' => 1.0,
        'status' => 'approved',
        'reviewNeeded' => false,
        'source' => 'manual',
        'lastUpdatedAt' => '2026-06-06T00:00:00Z',
        'lastUpdatedType' => 'manual',
        'exerciseFamily' => 'conditioning_lower',
        'fatigueArchetype' => 'conditioning_hybrid',
    ], [
        'id' => 'weight-training',
        'canonicalName' => 'WeightTraining',
        'normalizedName' => 'weight training',
        'aliases' => ['Strength'],
        'muscleWeights' => ['chest' => 0.45, 'quadriceps' => 0.45],
        'fatigueMultiplier' => 1.0,
        'status' => 'approved',
        'reviewNeeded' => false,
        'source' => 'manual',
        'lastUpdatedAt' => '2026-06-06T00:00:00Z',
        'lastUpdatedType' => 'manual',
        'exerciseFamily' => 'conditioning_lower',
        'fatigueArchetype' => 'conditioning_hybrid',
    ]];
    $integrationUnknowns = [[
        'id' => 'exercise:mystery curl',
        'sourceType' => 'exercise',
        'normalizedName' => 'mystery curl',
        'rawNames' => ['Mystery Curl'],
        'timesSeen' => 1,
        'firstSeenAt' => '2026-06-06T00:00:00Z',
        'lastSeenAt' => '2026-06-06T00:00:00Z',
        'aiStatus' => 'not_requested',
    ]];
    $integrationSnapshot = [
        'rows' => [
            [
                'Date' => '2026-06-05T07:30:00+02:00',
                'Type' => 'Strength',
                'Name' => 'Edited Push Title',
                'Distance (km)' => '',
                'Duration (min)' => '60',
                'Elevation Gain (m)' => '',
                'Avg HR' => '120',
                'Max HR' => '150',
                'Avg Pace (min/km)' => '',
                'Avg Speed (km/h)' => '',
                'Avg Cadence' => '',
                'Description' => "Logged with HevyApp.com\n\nBench Press\n80 kg x 5\n82.5 kg x 5",
                'Device Name' => 'Hevy',
                'Activity ID' => 'compat-lift-1',
                'Activity ID raw' => 'compat-lift-raw-1',
            ],
            [
                'Date' => '2026-06-04T06:00:00+02:00',
                'Type' => 'Run',
                'Name' => 'Edited Easy Run',
                'Distance (km)' => '5.2',
                'Duration (min)' => '29',
                'Elevation Gain (m)' => '20',
                'Avg HR' => '135',
                'Max HR' => '148',
                'Avg Pace (min/km)' => '5.58',
                'Avg Speed (km/h)' => '10.76',
                'Avg Cadence' => '168',
                'Description' => '',
                'Device Name' => 'Garmin',
                'Activity ID' => 'compat-run-1',
                'Activity ID raw' => 'compat-run-raw-1',
            ],
            [
                'Date' => '2026-06-01',
                'Type' => 'Walking',
                'Name' => 'Slow Walk',
                'Distance (km)' => '2.2',
                'Duration (min)' => '70.58',
                'Elevation Gain (m)' => '12',
                'Avg HR' => '90',
                'Max HR' => '120',
                'Avg Pace (min/km)' => '31.87',
                'Avg Speed (km/h)' => '1.87',
                'Avg Cadence' => '29',
                'Description' => '',
                'Device Name' => 'Garmin',
                'Activity ID' => 'compat-walk-legacy-1',
                'Activity ID raw' => 'compat-walk-legacy-raw-1',
            ],
            [
                'Date' => '2026-06-03T06:15:00+02:00',
                'Type' => 'Rowing',
                'Name' => 'Morning Row',
                'Distance (km)' => '10',
                'Duration (min)' => '42.5',
                'Elevation Gain (m)' => '',
                'Avg HR' => '142',
                'Max HR' => '160',
                'Avg Pace (min/km)' => '4.25',
                'Avg Speed (km/h)' => '14.12',
                'Avg Cadence' => '',
                'Description' => '',
                'Device Name' => 'Concept2',
                'Activity ID' => 'compat-row-legacy-1',
                'Activity ID raw' => 'compat-row-legacy-raw-1',
            ],
            [
                'Date' => '2026-06-02',
                'Type' => 'Rowing',
                'Name' => 'Legacy Erg Mystery',
                'Distance (km)' => '7.5',
                'Duration (min)' => '',
                'Elevation Gain (m)' => '',
                'Avg HR' => '138',
                'Max HR' => '149',
                'Avg Pace (min/km)' => '',
                'Avg Speed (km/h)' => '',
                'Avg Cadence' => '',
                'Description' => '',
                'Device Name' => 'Concept2',
                'Activity ID' => 'compat-row-legacy-2',
                'Activity ID raw' => 'compat-row-legacy-raw-2',
            ],
        ],
        'count' => 5,
        'meta' => [
            'lastSuccessfulSyncAt' => '2026-06-06T00:00:00Z',
            'sourceVersion' => 1,
        ],
    ];

    compat_write_json($integrationDataRoot . '/exercise-configs.json', $integrationExercises);
    compat_write_json($integrationDataRoot . '/activity-type-configs.json', $integrationActivityTypes);
    compat_write_json($integrationDataRoot . '/exercise-unknowns.json', $integrationUnknowns);
    compat_write_json($integrationCacheRoot . '/training-data.json', $integrationSnapshot);
    mkdir($integrationRoot . '/import-hevy', 0775, true);
    mkdir($integrationRoot . '/import-garmin', 0775, true);
    file_put_contents($integrationRoot . '/import-hevy/workouts.csv', <<<CSV
"title","start_time","end_time","description","exercise_title","superset_id","exercise_notes","set_index","set_type","weight_kg","reps","distance_km","duration_seconds","rpe"
"Äwesome Push 💪","05.06.2026, 07:30","05.06.2026, 08:30","Warm üp 😅","Bench Press","","Tempo halten","0","normal","80","5","","","8.5"
"Äwesome Push 💪","05.06.2026, 07:30","05.06.2026, 08:30","Warm üp 😅","Bench Press","","Tempo halten","1","normal","82.5","5","","",""
CSV);
    file_put_contents($integrationRoot . '/import-garmin/Activities.csv', <<<CSV
Activity Type,Date,Favorite,Title,Distance,Calories,Time,Avg HR,Max HR,Avg Run Cadence,Max Run Cadence,Avg Pace,Best Pace,Total Ascent,Total Descent,Avg Stride Length,Training Stress Score®,Total Strokes,Avg. Swolf,Avg Stroke Rate,Steps,Total Reps,Total Sets,Decompression,Best Lap Time,Number of Laps,Avg Resp,Min Resp,Max Resp,Stress Change,Stress Start,Stress End,Avg Stress,Max Stress,Moving Time,Elapsed Time,Min Elevation,Max Elevation
Running,2026-06-04 06:00:00,false,"Easy Run","5.2","350","00:29:00","135","148","168","182","5:35","4:50","20","21","0.95","0.0","--","--","--","4,812","--","--","No","00:04:40.0","6","--","--","--","--","--","--","--","--","00:28:45","00:29:00","210","230"
Walking,2026-06-01 10:51:40,false,"Slow Walk","--","311","01:10:35","90","120","29","158","31:52","10:12","12","13","1.07","0.0","--","--","--","2,642","--","--","No","01:10:35","1","--","--","--","--","--","--","--","--","00:18:00","01:23:31","806","894"
CSV);

    $integrationUserKey = 'compat-api-test-' . bin2hex(random_bytes(4));
    mattrics_foundation_run_import($integrationPdo, [
        'userKey' => $integrationUserKey,
        'displayName' => 'Compatibility API Test User',
        'timezone' => 'Europe/Berlin',
        'privateRoot' => $integrationRoot,
        'dryRun' => false,
    ]);
    mattrics_foundation_run_hevy_import($integrationPdo, [
        'userKey' => $integrationUserKey,
        'displayName' => 'Compatibility API Test User',
        'timezone' => 'Europe/Berlin',
        'privateRoot' => $integrationRoot,
        'dryRun' => false,
    ]);
    mattrics_foundation_run_garmin_import($integrationPdo, [
        'userKey' => $integrationUserKey,
        'displayName' => 'Compatibility API Test User',
        'timezone' => 'Europe/Berlin',
        'privateRoot' => $integrationRoot,
        'dryRun' => false,
    ]);
    compat_seed_direct_concept2_activity($integrationPdo, $integrationUserKey, [
        'activityDate' => '2026-06-03',
        'startedAt' => '2026-06-03T04:15:00+00:00',
        'activityName' => 'Morning Row',
        'activityTypeName' => 'Rowing',
        'sourceActivityId' => 'concept2-direct-1',
        'sourceActivityIdRaw' => 'concept2-direct-raw-1',
        'distanceKm' => '10.000',
        'durationMinutes' => '42.50',
    ]);
    compat_seed_direct_concept2_activity($integrationPdo, $integrationUserKey, [
        'activityDate' => '2026-06-02',
        'startedAt' => null,
        'activityName' => 'Legacy Erg Mystery',
        'activityTypeName' => 'Rowing',
        'sourceActivityId' => 'concept2-direct-2',
        'sourceActivityIdRaw' => 'concept2-direct-raw-2',
        'distanceKm' => '7.500',
        'durationMinutes' => '30.00',
    ]);

    $integrationConfigPath = $integrationRoot . '/config.php';
    compat_reset_config($integrationConfigPath, [
        'auth_require_https' => false,
        'foundation_database_url' => $integrationDatabaseUrl,
        'foundation_user_key' => $integrationUserKey,
    ]);

    $canonicalDataResult = compat_run_php_fixture(dirname(__DIR__) . '/tests/fixtures/run-data-endpoint.php');
    compat_test_assert(($canonicalDataResult['exitCode'] ?? 1) === 0, 'canonical data fixture exits successfully');
    compat_test_assert(($canonicalDataResult['decoded']['meta']['source'] ?? '') === 'canonical', 'canonical data endpoint reports canonical source');
    compat_test_assert(count($canonicalDataResult['decoded']['rows'] ?? []) === 7, 'canonical data endpoint suppresses only deterministic direct-source duplicates');
    compat_test_assert(($canonicalDataResult['decoded']['rows'][0]['Name'] ?? '') === 'Edited Push Title', 'canonical data endpoint overlays legacy-edited Hevy titles onto selected direct rows');
    compat_test_assert(($canonicalDataResult['decoded']['rows'][0]['Type'] ?? '') === 'Strength', 'canonical data endpoint overlays legacy-edited Hevy activity types onto selected direct rows');
    compat_test_assert(str_starts_with((string) ($canonicalDataResult['decoded']['rows'][0]['Activity ID raw'] ?? ''), 'hevy-csv-'), 'canonical data endpoint prefers direct Hevy rows over legacy snapshot duplicates');
    compat_test_assert(abs((float) ($canonicalDataResult['decoded']['rows'][0]['Duration (min)'] ?? 0) - 60.0) < 0.00001, 'canonical data endpoint exposes imported workout duration');
    compat_test_assert(($canonicalDataResult['decoded']['rows'][0]['Date'] ?? '') === '2026-06-05T05:30:00+00:00', 'canonical data endpoint exposes imported workout start time through Date');
    $canonicalRows = $canonicalDataResult['decoded']['rows'] ?? [];
    $canonicalIds = array_map(
        static fn(array $row): string => (string) ($row['Activity ID raw'] ?? ''),
        is_array($canonicalRows) ? $canonicalRows : []
    );
    compat_test_assert(in_array('concept2-direct-raw-1', $canonicalIds, true), 'canonical data endpoint keeps the direct Concept2 row for deterministic duplicates');
    compat_test_assert(!in_array('compat-row-legacy-raw-1', $canonicalIds, true), 'canonical data endpoint suppresses deterministic legacy Concept2 duplicates');
    compat_test_assert(in_array('concept2-direct-raw-2', $canonicalIds, true), 'canonical data endpoint keeps direct Concept2 rows when duplicate suppression is unsafe');
    compat_test_assert(in_array('compat-row-legacy-raw-2', $canonicalIds, true), 'canonical data endpoint keeps legacy Concept2 rows when duplicate suppression is unsafe');
    $directGarminRow = null;
    foreach ($canonicalRows as $row) {
        if (str_starts_with((string) ($row['Activity ID raw'] ?? ''), 'garmin-csv-') && abs((float) ($row['Distance (km)'] ?? 0) - 5.2) < 0.00001) {
            $directGarminRow = $row;
            break;
        }
    }
    compat_test_assert($directGarminRow !== null, 'canonical data endpoint exposes the direct Garmin row');
    compat_test_assert(!in_array('compat-run-raw-1', $canonicalIds, true), 'canonical data endpoint suppresses deterministic legacy Garmin duplicates');
    compat_test_assert(in_array('compat-walk-legacy-raw-1', $canonicalIds, true), 'canonical data endpoint keeps legacy Garmin rows when Garmin duplicate suppression is unsafe');
    compat_test_assert(count(array_filter($canonicalRows, static fn(array $row): bool => ($row['Name'] ?? '') === 'Slow Walk')) === 2, 'canonical data endpoint keeps both Garmin rows when the duplicate signature is incomplete');
    compat_test_assert(($directGarminRow['Name'] ?? '') === 'Edited Easy Run', 'canonical data endpoint overlays legacy-edited Garmin titles onto selected direct rows');
    compat_test_assert(($directGarminRow['Type'] ?? '') === 'Run', 'canonical data endpoint overlays legacy-edited Garmin activity types onto selected direct rows');
    compat_test_assert(abs((float) ($directGarminRow['Distance (km)'] ?? 0) - 5.2) < 0.00001, 'canonical data endpoint exposes Garmin distance values');
    compat_test_assert(abs((float) ($directGarminRow['Duration (min)'] ?? 0) - 29.0) < 0.00001, 'canonical data endpoint exposes Garmin duration values');
    compat_test_assert(($directGarminRow['Avg HR'] ?? null) === 135, 'canonical data endpoint exposes Garmin average heart rate values');
    compat_test_assert(($directGarminRow['Max HR'] ?? null) === 148, 'canonical data endpoint exposes Garmin max heart rate values');
    compat_test_assert(abs((float) ($directGarminRow['Avg Cadence'] ?? 0) - 168.0) < 0.00001, 'canonical data endpoint exposes Garmin cadence values');
    compat_test_assert(abs((float) ($directGarminRow['Avg Pace (min/km)'] ?? 0) - 5.5833) < 0.01, 'canonical data endpoint exposes Garmin pace values');
    compat_test_assert(abs((float) ($directGarminRow['Elevation Gain (m)'] ?? 0) - 20.0) < 0.00001, 'canonical data endpoint exposes Garmin elevation gain values');

    putenv('MATTRICS_TEST_REQUEST_URI=/api/data.php?includeChildren=1');
    $canonicalChildrenResult = compat_run_php_fixture(dirname(__DIR__) . '/tests/fixtures/run-data-endpoint.php');
    putenv('MATTRICS_TEST_REQUEST_URI');
    compat_test_assert(count($canonicalChildrenResult['decoded']['activityChildren'] ?? []) >= 1, 'canonical data endpoint returns activityChildren when includeChildren=1');
    $firstChild = $canonicalChildrenResult['decoded']['activityChildren'][0]['exercises'][0] ?? [];
    compat_test_assert(
        ($canonicalChildrenResult['decoded']['activityChildren'][0]['activityIdRaw'] ?? '') === ($canonicalChildrenResult['decoded']['rows'][0]['Activity ID raw'] ?? ''),
        'canonical child payload stays aligned with the selected direct-Hevy activity rows'
    );
    compat_test_assert(($firstChild['sourceExerciseName'] ?? '') === 'Bench Press', 'canonical child payload preserves source exercise names');
    compat_test_assert(count($firstChild['sets'] ?? []) === 2, 'canonical child payload exposes parsed sets');
    compat_test_assert(($firstChild['sets'][0]['parsedKind'] ?? '') === 'parsed', 'canonical child payload exposes typed parsedKind values for frontend fatigue consumers');
    compat_test_assert(abs((float) ($firstChild['sets'][0]['weightKg'] ?? 0) - 80.0) < 0.00001, 'canonical child payload exposes typed weightKg values for frontend fatigue consumers');
    compat_test_assert(str_contains((string) ($firstChild['sets'][0]['notes'] ?? ''), 'Warm üp 😅'), 'canonical child payload preserves UTF-8 workout descriptions in notes');

    $canonicalExercisesResult = compat_run_php_fixture(dirname(__DIR__) . '/tests/fixtures/run-exercises-endpoint.php');
    compat_test_assert(($canonicalExercisesResult['decoded']['meta']['source'] ?? '') === 'canonical', 'canonical exercises endpoint reports canonical source');
    compat_test_assert(($canonicalExercisesResult['decoded']['meta']['lastSuccessfulSyncAt'] ?? '') !== '', 'canonical exercises endpoint reports lastSuccessfulSyncAt');
    compat_test_assert(count($canonicalExercisesResult['decoded']['exercises'] ?? []) === 1, 'canonical exercises endpoint returns canonical exercise configs');
    compat_test_assert(count($canonicalExercisesResult['decoded']['activityTypes'] ?? []) === 4, 'canonical exercises endpoint returns canonical activity types');
    compat_test_assert(count($canonicalExercisesResult['decoded']['unknowns'] ?? []) === 1, 'canonical exercises endpoint keeps legacy unknown review data');

    $fallbackRoot = $tempRoot . '/fallback-private';
    $fallbackDataRoot = $fallbackRoot . '/data';
    $fallbackCacheRoot = $fallbackRoot . '/cache';
    mkdir($fallbackDataRoot, 0775, true);
    mkdir($fallbackCacheRoot, 0775, true);
    compat_write_json($fallbackDataRoot . '/exercise-configs.json', $integrationExercises);
    compat_write_json($fallbackDataRoot . '/activity-type-configs.json', $integrationActivityTypes);
    compat_write_json($fallbackDataRoot . '/exercise-unknowns.json', $integrationUnknowns);
    compat_write_json($fallbackCacheRoot . '/training-data.json', $integrationSnapshot);
    $fallbackConfigPath = $fallbackRoot . '/config.php';
    compat_reset_config($fallbackConfigPath, [
        'auth_require_https' => false,
        'foundation_database_url' => 'postgres://mattrics:mattrics-local-only-change-me@127.0.0.1:1/mattrics',
        'foundation_user_key' => $integrationUserKey,
    ]);

    $fallbackDataResult = compat_run_php_fixture(dirname(__DIR__) . '/tests/fixtures/run-data-endpoint.php');
    compat_test_assert(($fallbackDataResult['decoded']['meta']['source'] ?? '') === 'cache', 'data endpoint falls back to legacy snapshot when canonical read fails');
    compat_test_assert(str_contains((string) ($fallbackDataResult['decoded']['meta']['warning'] ?? ''), 'Canonical read failed'), 'data endpoint fallback includes a canonical warning');

    $fallbackExercisesResult = compat_run_php_fixture(dirname(__DIR__) . '/tests/fixtures/run-exercises-endpoint.php');
    compat_test_assert(($fallbackExercisesResult['decoded']['meta']['source'] ?? '') === 'legacy', 'exercises endpoint falls back to legacy payload when canonical read fails');
    compat_test_assert(str_contains((string) ($fallbackExercisesResult['decoded']['meta']['warning'] ?? ''), 'Canonical read failed'), 'exercises endpoint fallback includes a canonical warning');
    compat_test_assert(($fallbackExercisesResult['decoded']['meta']['lastSuccessfulSyncAt'] ?? null) === null, 'exercises endpoint fallback keeps legacy lastSuccessfulSyncAt as null');
}

if ($failed > 0) {
    fwrite(STDERR, "foundation-compat-api-tests: {$passed} passed, {$failed} failed, {$skipped} skipped\n");
    exit(1);
}

fwrite(STDOUT, "foundation-compat-api-tests: {$passed} passed, 0 failed, {$skipped} skipped\n");
