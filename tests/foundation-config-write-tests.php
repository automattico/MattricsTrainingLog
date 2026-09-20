<?php
declare(strict_types=1);

$tempRoot = sys_get_temp_dir() . '/mattrics-foundation-config-write-tests-' . bin2hex(random_bytes(4));
mkdir($tempRoot, 0775, true);

require_once dirname(__DIR__) . '/public/api/bootstrap.php';
require_once dirname(__DIR__) . '/public/api/exercise-config-repository.php';
require_once dirname(__DIR__) . '/public/api/foundation-read.php';
require_once dirname(__DIR__) . '/public/api/foundation-write.php';
require_once dirname(__DIR__) . '/scripts/lib/foundation-import.php';

$passed = 0;
$failed = 0;
$skipped = 0;

function foundation_write_test_assert(bool $condition, string $message): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }

    $failed++;
    fwrite(STDERR, "FAIL: {$message}\n");
}

function foundation_write_test_skip(string $message): void
{
    global $skipped;
    $skipped++;
    fwrite(STDOUT, "SKIP: {$message}\n");
}

function foundation_write_php_config(string $path, array $config): void
{
    file_put_contents($path, "<?php\nreturn " . var_export($config, true) . ";\n");
}

function foundation_write_json(string $path, mixed $value): void
{
    file_put_contents($path, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function foundation_write_read_json(string $path): array
{
    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

function foundation_write_run_php_fixture(string $fixturePath, string $stdin = ''): array
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

function foundation_write_apply_env(string $configPath): void
{
    putenv('MATTRICS_CONFIG=' . $configPath);
    putenv('MATTRICS_AUTH_REQUIRE_HTTPS=0');
    mattrics_foundation_read_reset_cache();
}

function foundation_write_make_case_root(string $baseRoot, string $caseName): string
{
    $path = $baseRoot . '/' . $caseName . '-' . bin2hex(random_bytes(3));
    mkdir($path . '/data', 0775, true);
    mkdir($path . '/cache', 0775, true);
    return $path;
}

function foundation_write_seed_case(
    PDO $pdo,
    string $baseRoot,
    string $caseName,
    array $exerciseRecords,
    array $activityTypeRecords,
    array $unknownRecords
): array {
    $privateRoot = foundation_write_make_case_root($baseRoot, $caseName);
    $configPath = $privateRoot . '/config.php';
    $userKey = 'foundation-config-write-' . $caseName . '-' . bin2hex(random_bytes(4));

    foundation_write_json($privateRoot . '/data/exercise-configs.json', $exerciseRecords);
    foundation_write_json($privateRoot . '/data/activity-type-configs.json', $activityTypeRecords);
    foundation_write_json($privateRoot . '/data/exercise-unknowns.json', $unknownRecords);
    foundation_write_json($privateRoot . '/cache/training-data.json', [
        'rows' => [],
        'count' => 0,
        'meta' => [],
    ]);
    foundation_write_php_config($configPath, [
        'auth_require_https' => false,
        'foundation_database_url' => getenv('MATTRICS_FOUNDATION_TEST_DATABASE_URL') ?: 'postgres://mattrics:mattrics-local-only-change-me@127.0.0.1:5433/mattrics',
        'foundation_user_key' => $userKey,
    ]);

    mattrics_foundation_run_import($pdo, [
        'userKey' => $userKey,
        'displayName' => 'Foundation Config Write Test User',
        'timezone' => 'Europe/Berlin',
        'privateRoot' => $privateRoot,
        'dryRun' => false,
    ]);

    foundation_write_apply_env($configPath);

    return [
        'privateRoot' => $privateRoot,
        'configPath' => $configPath,
        'userKey' => $userKey,
    ];
}

$validExercises = [[
    'id' => 'bench-press',
    'canonicalName' => 'Bench Press',
    'normalizedName' => 'bench press',
    'aliases' => ['Push Up'],
    'matchTerms' => ['bench'],
    'muscleWeights' => ['chest' => 1.0, 'triceps' => 0.45],
    'fatigueImpact' => 'normal',
    'fatigueMultiplier' => 1.0,
    'bodyweightEligible' => true,
    'setTypeHandling' => 'weight_reps',
    'source' => 'manual',
    'lastUpdatedAt' => '2026-06-07T00:00:00Z',
    'lastUpdatedType' => 'manual',
    'exerciseFamily' => 'horizontal_press',
    'fatigueArchetype' => 'freeweight_compound',
]];

$mergeExercises = [
    $validExercises[0],
    [
        'id' => 'hammer-curl',
        'canonicalName' => 'Hammer Curl',
        'normalizedName' => 'hammer curl',
        'aliases' => ['Dumbbell Hammer Curl'],
        'matchTerms' => ['hammer curl'],
        'muscleWeights' => ['biceps' => 1.0, 'trapezius' => 0.08],
        'fatigueImpact' => 'normal',
        'fatigueMultiplier' => 1.0,
        'bodyweightEligible' => false,
        'setTypeHandling' => 'weight_reps',
        'source' => 'manual',
        'lastUpdatedAt' => '2026-06-07T00:00:00Z',
        'lastUpdatedType' => 'manual',
        'exerciseFamily' => 'arm_isolation',
        'fatigueArchetype' => 'isolation',
    ],
];

$collisionExercises = [
    $validExercises[0],
    [
        'id' => 'face-pull',
        'canonicalName' => 'Face Pull',
        'normalizedName' => 'face pull',
        'aliases' => [],
        'matchTerms' => ['face pull'],
        'muscleWeights' => ['upperBack' => 1.0, 'deltoids' => 0.45],
        'fatigueImpact' => 'normal',
        'fatigueMultiplier' => 1.0,
        'bodyweightEligible' => false,
        'setTypeHandling' => 'weight_reps',
        'source' => 'manual',
        'lastUpdatedAt' => '2026-06-07T00:00:00Z',
        'lastUpdatedType' => 'manual',
        'exerciseFamily' => 'horizontal_pull',
        'fatigueArchetype' => 'machine_compound',
    ],
];

$validActivityTypes = [[
    'id' => 'run',
    'canonicalName' => 'Run',
    'normalizedName' => 'run',
    'aliases' => ['Easy Run'],
    'muscleWeights' => ['quadriceps' => 1.0, 'hamstrings' => 0.65],
    'fatigueMultiplier' => 1.0,
    'status' => 'approved',
    'reviewNeeded' => false,
    'source' => 'manual',
    'lastUpdatedAt' => '2026-06-07T00:00:00Z',
    'lastUpdatedType' => 'manual',
    'exerciseFamily' => 'conditioning_lower',
    'fatigueArchetype' => 'conditioning_hybrid',
]];

$exerciseUnknowns = [[
    'id' => 'exercise:mystery curl',
    'sourceType' => 'exercise',
    'normalizedName' => 'mystery curl',
    'rawNames' => ['Mystery Curl'],
    'timesSeen' => 1,
    'firstSeenAt' => '2026-06-07T00:00:00Z',
    'lastSeenAt' => '2026-06-07T00:00:00Z',
    'aiStatus' => 'not_requested',
]];

$activityTypeUnknowns = [[
    'id' => 'activityType:mobility flow',
    'sourceType' => 'activityType',
    'normalizedName' => 'mobility flow',
    'rawNames' => ['Mobility Flow'],
    'timesSeen' => 1,
    'firstSeenAt' => '2026-06-07T00:00:00Z',
    'lastSeenAt' => '2026-06-07T00:00:00Z',
    'aiStatus' => 'not_requested',
]];

$integrationDatabaseUrl = getenv('MATTRICS_FOUNDATION_TEST_DATABASE_URL') ?: 'postgres://mattrics:mattrics-local-only-change-me@127.0.0.1:5433/mattrics';
$integrationPdo = null;
try {
    $integrationPdo = mattrics_foundation_read_connect($integrationDatabaseUrl);
} catch (Throwable $throwable) {
    foundation_write_test_skip('Canonical config-write tests skipped: ' . $throwable->getMessage());
}

if ($integrationPdo instanceof PDO) {
    $createCase = foundation_write_seed_case(
        $integrationPdo,
        $tempRoot,
        'create-exercise',
        $validExercises,
        $validActivityTypes,
        $exerciseUnknowns
    );
    $createResult = foundation_write_run_php_fixture(
        dirname(__DIR__) . '/tests/fixtures/run-exercise-create-endpoint.php',
        json_encode([
            'canonicalName' => 'Mystery Curl',
            'aliases' => ['Alt Mystery Curl'],
            'matchTerms' => ['mystery curl', 'alt mystery curl'],
            'muscleWeights' => ['biceps' => 1.0, 'trapezius' => 0.12],
            'fatigueImpact' => 'normal',
            'fatigueMultiplier' => 1,
            'bodyweightEligible' => false,
            'setTypeHandling' => 'weight_reps',
        ], JSON_UNESCAPED_SLASHES)
    );
    foundation_write_test_assert(($createResult['exitCode'] ?? 1) === 0, 'canonical exercise create fixture exits successfully');
    foundation_write_test_assert(($createResult['decoded']['ok'] ?? false) === true, 'canonical exercise create returns ok');
    foundation_write_test_assert(($createResult['decoded']['meta']['source'] ?? '') === 'canonical', 'canonical exercise create response reports canonical source');
    foundation_write_test_assert(($createResult['decoded']['exercise']['id'] ?? '') === 'mystery-curl', 'canonical exercise create returns the slug-style stable id');
    foundation_write_test_assert(count($createResult['decoded']['unknowns'] ?? []) === 0, 'canonical exercise create prunes the resolved unknown');
    $createShadowExercises = foundation_write_read_json($createCase['privateRoot'] . '/data/exercise-configs.json');
    foundation_write_test_assert(count($createShadowExercises) === 2, 'canonical exercise create shadow-syncs the exercise catalog');
    foundation_write_test_assert(
        in_array('Mystery Curl', array_column($createShadowExercises, 'canonicalName'), true),
        'canonical exercise create writes the new exercise into the legacy shadow file'
    );
    $createGetResult = foundation_write_run_php_fixture(dirname(__DIR__) . '/tests/fixtures/run-exercises-endpoint.php');
    foundation_write_test_assert(($createGetResult['decoded']['meta']['source'] ?? '') === 'canonical', 'canonical GET stays canonical after exercise create');
    foundation_write_test_assert(count($createGetResult['decoded']['exercises'] ?? []) === 2, 'canonical GET immediately reflects exercise create');

    $updateCase = foundation_write_seed_case(
        $integrationPdo,
        $tempRoot,
        'update-exercise',
        $validExercises,
        $validActivityTypes,
        $exerciseUnknowns
    );
    $updateResult = foundation_write_run_php_fixture(
        dirname(__DIR__) . '/tests/fixtures/run-exercise-patch-endpoint.php',
        json_encode([
            'canonicalName' => 'Bench Press',
            'aliases' => ['Flat Bench', 'Push Up'],
            'matchTerms' => ['bench', 'flat bench'],
            'muscleWeights' => ['chest' => 1.0, 'triceps' => 0.52],
            'fatigueImpact' => 'normal',
            'fatigueMultiplier' => 1,
            'bodyweightEligible' => true,
            'setTypeHandling' => 'weight_reps',
        ], JSON_UNESCAPED_SLASHES)
    );
    foundation_write_test_assert(($updateResult['decoded']['ok'] ?? false) === true, 'canonical exercise update returns ok');
    foundation_write_test_assert(($updateResult['decoded']['exercise']['id'] ?? '') === 'bench-press', 'canonical exercise update preserves the stable id');
    $updateGetResult = foundation_write_run_php_fixture(dirname(__DIR__) . '/tests/fixtures/run-exercises-endpoint.php');
    $updatedExercise = array_values(array_filter(
        $updateGetResult['decoded']['exercises'] ?? [],
        static fn(array $record): bool => ($record['id'] ?? '') === 'bench-press'
    ))[0] ?? [];
    foundation_write_test_assert(in_array('Flat Bench', $updatedExercise['aliases'] ?? [], true), 'canonical exercise update is visible through the next GET');
    $updateShadowExercises = foundation_write_read_json($updateCase['privateRoot'] . '/data/exercise-configs.json');
    foundation_write_test_assert(in_array('Flat Bench', $updateShadowExercises[0]['aliases'] ?? [], true), 'canonical exercise update shadow-syncs aliases');

    $unknownMergeCase = foundation_write_seed_case(
        $integrationPdo,
        $tempRoot,
        'merge-unknown',
        $validExercises,
        $validActivityTypes,
        $exerciseUnknowns
    );
    $unknownMergeResult = foundation_write_run_php_fixture(
        dirname(__DIR__) . '/tests/fixtures/run-unknown-merge-endpoint.php',
        json_encode(['targetExerciseId' => 'bench-press'], JSON_UNESCAPED_SLASHES)
    );
    foundation_write_test_assert(($unknownMergeResult['decoded']['ok'] ?? false) === true, 'canonical unknown merge returns ok');
    foundation_write_test_assert(count($unknownMergeResult['decoded']['unknowns'] ?? []) === 0, 'canonical unknown merge removes the unknown from the response');
    $unknownMergeShadowExercises = foundation_write_read_json($unknownMergeCase['privateRoot'] . '/data/exercise-configs.json');
    foundation_write_test_assert(in_array('Mystery Curl', $unknownMergeShadowExercises[0]['aliases'] ?? [], true), 'canonical unknown merge shadow-syncs the merged alias');
    foundation_write_test_assert(
        foundation_write_read_json($unknownMergeCase['privateRoot'] . '/data/exercise-unknowns.json') === [],
        'canonical unknown merge persists unknown removal to the legacy review file'
    );

    $exerciseMergeCase = foundation_write_seed_case(
        $integrationPdo,
        $tempRoot,
        'merge-exercise',
        $mergeExercises,
        $validActivityTypes,
        []
    );
    $exerciseMergeResult = foundation_write_run_php_fixture(
        dirname(__DIR__) . '/tests/fixtures/run-exercise-merge-endpoint.php',
        json_encode(['targetExerciseId' => 'bench-press'], JSON_UNESCAPED_SLASHES)
    );
    foundation_write_test_assert(($exerciseMergeResult['decoded']['ok'] ?? false) === true, 'canonical exercise merge returns ok');
    foundation_write_test_assert(count($exerciseMergeResult['decoded']['exercises'] ?? []) === 1, 'canonical exercise merge removes the merged source config');
    $exerciseMergeShadowExercises = foundation_write_read_json($exerciseMergeCase['privateRoot'] . '/data/exercise-configs.json');
    foundation_write_test_assert(count($exerciseMergeShadowExercises) === 1, 'canonical exercise merge shadow-syncs the merged exercise list');
    foundation_write_test_assert(
        in_array('Hammer Curl', $exerciseMergeShadowExercises[0]['aliases'] ?? [], true),
        'canonical exercise merge carries the source canonical name into the target aliases'
    );

    $deleteCase = foundation_write_seed_case(
        $integrationPdo,
        $tempRoot,
        'delete-exercise',
        $mergeExercises,
        $validActivityTypes,
        []
    );
    $deleteResult = foundation_write_run_php_fixture(dirname(__DIR__) . '/tests/fixtures/run-exercise-delete-endpoint.php');
    foundation_write_test_assert(($deleteResult['decoded']['ok'] ?? false) === true, 'canonical exercise delete returns ok');
    foundation_write_test_assert(($deleteResult['decoded']['deletedExercise']['id'] ?? '') === 'bench-press', 'canonical exercise delete returns the deleted stable id');
    $deleteShadowExercises = foundation_write_read_json($deleteCase['privateRoot'] . '/data/exercise-configs.json');
    foundation_write_test_assert(count($deleteShadowExercises) === 1, 'canonical exercise delete shadow-syncs the remaining exercise list');

    $activityTypeCreateCase = foundation_write_seed_case(
        $integrationPdo,
        $tempRoot,
        'create-activity-type',
        $validExercises,
        $validActivityTypes,
        $activityTypeUnknowns
    );
    $activityTypeCreateResult = foundation_write_run_php_fixture(
        dirname(__DIR__) . '/tests/fixtures/run-activity-type-create-endpoint.php',
        json_encode([
            'configType' => 'activityType',
            'canonicalName' => 'Mobility Flow',
            'aliases' => ['Mobility'],
            'muscleWeights' => ['abs' => 0.42, 'obliques' => 0.22],
            'fatigueMultiplier' => 0.5,
        ], JSON_UNESCAPED_SLASHES)
    );
    foundation_write_test_assert(($activityTypeCreateResult['decoded']['ok'] ?? false) === true, 'canonical activity type create returns ok');
    foundation_write_test_assert(($activityTypeCreateResult['decoded']['meta']['source'] ?? '') === 'canonical', 'canonical activity type create reports canonical source');
    foundation_write_test_assert(count($activityTypeCreateResult['decoded']['unknowns'] ?? []) === 0, 'canonical activity type create prunes the resolved unknown');
    $activityTypeShadowTypes = foundation_write_read_json($activityTypeCreateCase['privateRoot'] . '/data/activity-type-configs.json');
    foundation_write_test_assert(count($activityTypeShadowTypes) === 2, 'canonical activity type create shadow-syncs the activity type catalog');

    $activityTypeUpdateCase = foundation_write_seed_case(
        $integrationPdo,
        $tempRoot,
        'update-activity-type',
        $validExercises,
        $validActivityTypes,
        []
    );
    $activityTypeUpdateResult = foundation_write_run_php_fixture(
        dirname(__DIR__) . '/tests/fixtures/run-activity-type-patch-endpoint.php',
        json_encode([
            'configType' => 'activityType',
            'canonicalName' => 'Run',
            'aliases' => ['Jog'],
            'muscleWeights' => ['quadriceps' => 1.0, 'hamstrings' => 0.62],
            'fatigueMultiplier' => 1,
        ], JSON_UNESCAPED_SLASHES)
    );
    foundation_write_test_assert(($activityTypeUpdateResult['decoded']['ok'] ?? false) === true, 'canonical activity type update returns ok');
    foundation_write_test_assert(($activityTypeUpdateResult['decoded']['activityType']['id'] ?? '') === 'run', 'canonical activity type update preserves the stable id');
    $activityTypeUpdateShadow = foundation_write_read_json($activityTypeUpdateCase['privateRoot'] . '/data/activity-type-configs.json');
    foundation_write_test_assert(in_array('Jog', $activityTypeUpdateShadow[0]['aliases'] ?? [], true), 'canonical activity type update shadow-syncs aliases');

    $activityTypeDeleteCase = foundation_write_seed_case(
        $integrationPdo,
        $tempRoot,
        'delete-activity-type',
        $validExercises,
        $validActivityTypes,
        []
    );
    $activityTypeDeleteResult = foundation_write_run_php_fixture(dirname(__DIR__) . '/tests/fixtures/run-activity-type-delete-endpoint.php');
    foundation_write_test_assert(($activityTypeDeleteResult['decoded']['ok'] ?? false) === true, 'canonical activity type delete returns ok');
    foundation_write_test_assert(($activityTypeDeleteResult['decoded']['deletedActivityType']['id'] ?? '') === 'run', 'canonical activity type delete returns the deleted stable id');
    foundation_write_test_assert(
        foundation_write_read_json($activityTypeDeleteCase['privateRoot'] . '/data/activity-type-configs.json') === [],
        'canonical activity type delete shadow-syncs the empty activity type catalog'
    );

    $collisionCase = foundation_write_seed_case(
        $integrationPdo,
        $tempRoot,
        'collision-exercise',
        $collisionExercises,
        $validActivityTypes,
        []
    );
    $collisionResult = foundation_write_run_php_fixture(
        dirname(__DIR__) . '/tests/fixtures/run-exercise-patch-endpoint.php',
        json_encode([
            'canonicalName' => 'Bench Press',
            'aliases' => ['Face Pull'],
            'matchTerms' => ['bench'],
            'muscleWeights' => ['chest' => 1.0],
            'fatigueImpact' => 'normal',
            'fatigueMultiplier' => 1,
            'bodyweightEligible' => true,
            'setTypeHandling' => 'weight_reps',
        ], JSON_UNESCAPED_SLASHES)
    );
    foundation_write_test_assert(($collisionResult['decoded']['error'] ?? '') !== '', 'canonical exercise update rejects duplicate alias collisions');
    $collisionGetResult = foundation_write_run_php_fixture(dirname(__DIR__) . '/tests/fixtures/run-exercises-endpoint.php');
    foundation_write_test_assert(count($collisionGetResult['decoded']['exercises'] ?? []) === 2, 'collision rejection leaves the canonical exercise list unchanged');

    $fallbackRoot = foundation_write_make_case_root($tempRoot, 'fallback-legacy');
    $fallbackConfigPath = $fallbackRoot . '/config.php';
    foundation_write_json($fallbackRoot . '/data/exercise-configs.json', $validExercises);
    foundation_write_json($fallbackRoot . '/data/activity-type-configs.json', $validActivityTypes);
    foundation_write_json($fallbackRoot . '/data/exercise-unknowns.json', $exerciseUnknowns);
    foundation_write_json($fallbackRoot . '/cache/training-data.json', ['rows' => [], 'count' => 0, 'meta' => []]);
    foundation_write_php_config($fallbackConfigPath, [
        'auth_require_https' => false,
        'foundation_database_url' => 'postgres://mattrics:mattrics-local-only-change-me@127.0.0.1:1/mattrics',
        'foundation_user_key' => 'foundation-fallback-' . bin2hex(random_bytes(4)),
    ]);
    foundation_write_apply_env($fallbackConfigPath);
    $fallbackCreateResult = foundation_write_run_php_fixture(
        dirname(__DIR__) . '/tests/fixtures/run-exercise-create-endpoint.php',
        json_encode([
            'canonicalName' => 'Mystery Curl',
            'aliases' => ['Alt Mystery Curl'],
            'matchTerms' => ['mystery curl', 'alt mystery curl'],
            'muscleWeights' => ['biceps' => 1.0, 'trapezius' => 0.12],
            'fatigueImpact' => 'normal',
            'fatigueMultiplier' => 1,
            'bodyweightEligible' => false,
            'setTypeHandling' => 'weight_reps',
        ], JSON_UNESCAPED_SLASHES)
    );
    foundation_write_test_assert(($fallbackCreateResult['decoded']['ok'] ?? false) === true, 'legacy fallback create succeeds when canonical DB is unreachable');
    foundation_write_test_assert(!array_key_exists('source', $fallbackCreateResult['decoded']['meta'] ?? []), 'legacy fallback mutation keeps the legacy write response contract');
    foundation_write_test_assert(count(foundation_write_read_json($fallbackRoot . '/data/exercise-configs.json')) === 2, 'legacy fallback create writes to the legacy JSON catalog');
    $fallbackGetResult = foundation_write_run_php_fixture(dirname(__DIR__) . '/tests/fixtures/run-exercises-endpoint.php');
    foundation_write_test_assert(($fallbackGetResult['decoded']['meta']['source'] ?? '') === 'legacy', 'GET falls back to legacy when canonical reads are unavailable');
    foundation_write_test_assert(
        str_contains((string) ($fallbackGetResult['decoded']['meta']['warning'] ?? ''), 'Canonical read failed'),
        'legacy fallback GET exposes the canonical warning'
    );
}

if ($failed > 0) {
    fwrite(STDERR, "foundation-config-write-tests: {$passed} passed, {$failed} failed, {$skipped} skipped\n");
    exit(1);
}

fwrite(STDOUT, "foundation-config-write-tests: {$passed} passed, 0 failed, {$skipped} skipped\n");
