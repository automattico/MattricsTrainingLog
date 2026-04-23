<?php
declare(strict_types=1);

$tmpRoot = sys_get_temp_dir() . '/mattrics-exercise-config-tests-' . bin2hex(random_bytes(4));
$privateRoot = $tmpRoot . '/private';
$dataRoot = $privateRoot . '/data';
mkdir($dataRoot, 0775, true);

$configPath = $privateRoot . '/config.php';
file_put_contents($configPath, "<?php\nreturn [\n    'auth_require_https' => false,\n];\n");

putenv('MATTRICS_CONFIG=' . $configPath);
putenv('MATTRICS_AUTH_REQUIRE_HTTPS=0');

require_once dirname(__DIR__) . '/public/api/bootstrap.php';
require_once dirname(__DIR__) . '/public/api/exercise-config-repository.php';
require_once dirname(__DIR__) . '/public/api/exercise-config-ai.php';

$passed = 0;
$failed = 0;

function test_assert(bool $condition, string $message): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }

    $failed++;
    fwrite(STDERR, "FAIL: {$message}\n");
}

function write_raw(string $path, string $contents): void
{
    file_put_contents($path, $contents);
}

function read_ai_log_entries(): array
{
    $path = mattrics_exercise_ai_log_path();
    if (!is_file($path)) {
        return [];
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return [];
    }

    $entries = [];
    foreach ($lines as $line) {
        $decoded = json_decode($line, true);
        if (is_array($decoded)) {
            $entries[] = $decoded;
        }
    }

    return $entries;
}

function expect_runtime_exception(callable $fn, string $message): void
{
    try {
        $fn();
        test_assert(false, $message);
    } catch (RuntimeException $exception) {
        test_assert(true, $message);
    }
}

function run_php_fixture(string $fixturePath, string $stdin = ''): array
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

$validExercises = [[
    'id' => 'bench-press',
    'canonicalName' => 'Bench Press',
    'normalizedName' => 'bench press',
    'aliases' => ['Push Up'],
    'matchTerms' => ['bench', 'push up'],
    'muscleWeights' => ['chest' => 1.0, 'triceps' => 0.52],
    'fatigueMultiplier' => 1,
    'bodyweightEligible' => true,
    'setTypeHandling' => 'weight_reps',
    'status' => 'approved',
    'reviewNeeded' => false,
    'source' => 'manual',
    'lastUpdatedAt' => '2026-04-20T00:00:00Z',
    'lastUpdatedType' => 'manual',
]];

$validActivityTypes = [[
    'id' => 'run',
    'canonicalName' => 'Run',
    'normalizedName' => 'run',
    'aliases' => [],
    'muscleWeights' => ['quadriceps' => 1.0, 'hamstrings' => 1.0],
    'fatigueMultiplier' => 1,
    'status' => 'approved',
    'reviewNeeded' => false,
    'source' => 'manual',
    'lastUpdatedAt' => '2026-04-20T00:00:00Z',
    'lastUpdatedType' => 'manual',
]];

$validUnknowns = [[
    'id' => 'exercise:mystery curl',
    'sourceType' => 'exercise',
    'normalizedName' => 'mystery curl',
    'rawNames' => ['Mystery Curl'],
    'timesSeen' => 1,
    'firstSeenAt' => '2026-04-20T00:00:00Z',
    'lastSeenAt' => '2026-04-20T00:00:00Z',
    'aiStatus' => 'not_requested',
]];

$validDatasetRecords = [[
    'canonicalName' => 'Lateral Raise',
    'aliases' => ['Machine Lateral Raise', 'Cable Lateral Raise'],
    'muscleWeights' => ['deltoids' => 1.0, 'trapezius' => 0.18],
    'fatigueMultiplier' => 0.82,
    'bodyweightEligible' => false,
    'setTypeHandling' => 'weight_reps',
]];

mattrics_write_exercise_config_records($validExercises);
mattrics_write_activity_type_config_records($validActivityTypes);
mattrics_write_unknown_exercise_records($validUnknowns);

$loadedExercises = mattrics_read_exercise_config_records();
$loadedActivityTypes = mattrics_read_activity_type_config_records();
$loadedUnknowns = mattrics_read_unknown_exercise_records();

test_assert(count($loadedExercises) === 1, 'repository reads exercise configs');
test_assert(($loadedExercises[0]['canonicalName'] ?? '') === 'Bench Press', 'repository preserves exercise canonical name');
test_assert(count($loadedActivityTypes) === 1, 'repository reads activity type configs');
test_assert(($loadedActivityTypes[0]['canonicalName'] ?? '') === 'Run', 'repository preserves activity type canonical name');
test_assert(count($loadedUnknowns) === 1, 'repository reads unknown exercise records');
test_assert(($loadedUnknowns[0]['id'] ?? '') === 'exercise:mystery curl', 'repository preserves unknown exercise ids');

$prompt = mattrics_build_unknown_suggestion_prompt($validUnknowns[0]);
test_assert(str_contains($prompt['system'] ?? '', 'Return only valid JSON'), 'AI prompt includes JSON-only system instruction');
test_assert(str_contains($prompt['user'] ?? '', 'mystery curl'), 'AI prompt includes normalized unknown name');
test_assert(MATTRICS_EXERCISE_AI_PROMPT_VERSION === 'exercise-config-admin-v1', 'AI prompt version constant is defined');

$openAiSchema = mattrics_openai_suggestion_schema();
test_assert(($openAiSchema['properties']['muscleWeights']['additionalProperties'] ?? true) === false, 'OpenAI schema locks muscleWeights to explicit keys');
test_assert(
    ($openAiSchema['properties']['muscleWeights']['required'] ?? []) === MATTRICS_EXERCISE_CONFIG_ALLOWED_MUSCLES,
    'OpenAI schema requires all allowed muscle keys in muscleWeights'
);

mattrics_write_exercise_dataset_records($validDatasetRecords);
$loadedDataset = mattrics_read_exercise_dataset_records();
test_assert(count($loadedDataset) === 1, 'repository reads external dataset records');
test_assert(($loadedDataset[0]['canonicalName'] ?? '') === 'Lateral Raise', 'repository preserves external dataset canonical name');
test_assert(abs((float) ($loadedDataset[0]['muscleWeights']['trapezius'] ?? 0) - 0.20) < 0.00001, 'repository normalizes dataset weights to the semantic ladder');

$datasetUnknown = [
    'id' => 'exercise:machine lateral raise',
    'sourceType' => 'exercise',
    'normalizedName' => 'machine lateral raise',
    'rawNames' => ['Machine Lateral Raise'],
    'timesSeen' => 1,
    'firstSeenAt' => '2026-04-20T00:00:00Z',
    'lastSeenAt' => '2026-04-20T00:00:00Z',
    'aiStatus' => 'not_requested',
];
$datasetMatch = mattrics_find_exercise_dataset_match($datasetUnknown);
test_assert(($datasetMatch['canonicalName'] ?? '') === 'Lateral Raise', 'dataset lookup matches unknown exercises before AI');

write_raw(mattrics_exercise_config_path(), json_encode([[
    'id' => 'legacy-bench-press',
    'canonicalName' => 'Legacy Bench Press',
    'normalizedName' => 'legacy bench press',
    'aliases' => [],
    'matchTerms' => [],
    'muscleWeights' => ['chest' => 0.33, 'triceps' => 0.11],
    'fatigueMultiplier' => 1,
    'bodyweightEligible' => true,
    'setTypeHandling' => 'weight_reps',
    'status' => 'approved',
    'reviewNeeded' => false,
    'source' => 'manual',
    'lastUpdatedAt' => '2026-04-20T00:00:00Z',
    'lastUpdatedType' => 'manual',
]], JSON_UNESCAPED_SLASHES));
$legacyExercises = mattrics_read_exercise_config_records();
test_assert(count($legacyExercises) === 1, 'repository still loads legacy numeric exercise configs');
test_assert(abs((float) ($legacyExercises[0]['muscleWeights']['chest'] ?? 0) - 0.33) < 0.00001, 'repository preserves legacy numeric weights on read');
test_assert(abs((float) ($legacyExercises[0]['muscleWeights']['triceps'] ?? 0) - 0.11) < 0.00001, 'repository keeps legacy read validation backward-compatible');
test_assert(!array_key_exists('status', $legacyExercises[0]), 'repository strips legacy status fields on read');
test_assert(!array_key_exists('reviewNeeded', $legacyExercises[0]), 'repository strips legacy review flags on read');
mattrics_write_exercise_config_records($validExercises);

write_raw(mattrics_exercise_config_path(), '{bad json');
expect_runtime_exception(
    static function (): void {
        mattrics_read_exercise_config_records();
    },
    'repository rejects malformed exercise JSON'
);

mattrics_write_exercise_config_records($validExercises);
mattrics_write_activity_type_config_records($validActivityTypes);
mattrics_write_unknown_exercise_records($validUnknowns);

expect_runtime_exception(
    static function (): void {
        mattrics_write_unknown_exercise_records([[
            'id' => 'exercise:mystery curl',
            'sourceType' => 'bad',
            'normalizedName' => 'mystery curl',
            'rawNames' => ['Mystery Curl'],
            'timesSeen' => 1,
            'firstSeenAt' => '2026-04-20T00:00:00Z',
            'lastSeenAt' => '2026-04-20T00:00:00Z',
            'aiStatus' => 'not_requested',
        ]]);
    },
    'repository rejects invalid unknown sourceType'
);

expect_runtime_exception(
    static function (): void {
        mattrics_validate_unknown_sync_input([
            'sourceType' => 'exercise',
            'normalizedName' => 'mystery curl',
            'rawNames' => ['Mystery Curl'],
            'timesSeen' => 0,
        ]);
    },
    'unknown sync validation rejects non-positive timesSeen'
);

$firstSync = mattrics_sync_unknown_exercise_records([
    mattrics_validate_unknown_sync_input([
        'sourceType' => 'exercise',
        'normalizedName' => 'mystery curl',
        'rawNames' => ['Mystery Curl', 'mystery-curl'],
        'timesSeen' => 2,
    ]),
    mattrics_validate_unknown_sync_input([
        'sourceType' => 'activityType',
        'normalizedName' => 'unknown activity type',
        'rawNames' => ['Unknown Activity Type'],
        'timesSeen' => 1,
    ]),
]);
$secondSync = mattrics_sync_unknown_exercise_records([
    mattrics_validate_unknown_sync_input([
        'sourceType' => 'exercise',
        'normalizedName' => 'mystery curl',
        'rawNames' => ['Mystery Curl', 'mystery-curl'],
        'timesSeen' => 2,
    ]),
    mattrics_validate_unknown_sync_input([
        'sourceType' => 'activityType',
        'normalizedName' => 'unknown activity type',
        'rawNames' => ['Unknown Activity Type'],
        'timesSeen' => 1,
    ]),
]);

test_assert(count($firstSync) === 2, 'unknown sync stores the current unresolved snapshot');
test_assert(count($secondSync) === 2, 'unknown sync is idempotent for the same snapshot');
test_assert(($secondSync[0]['timesSeen'] ?? null) !== 3, 'unknown sync does not inflate timesSeen across repeated syncs');
test_assert(($secondSync[0]['firstSeenAt'] ?? '') === ($firstSync[0]['firstSeenAt'] ?? ''), 'unknown sync preserves firstSeenAt for existing unresolved records');

$replacementSync = mattrics_sync_unknown_exercise_records([
    mattrics_validate_unknown_sync_input([
        'sourceType' => 'activityType',
        'normalizedName' => 'unknown activity type',
        'rawNames' => ['Unknown Activity Type'],
        'timesSeen' => 1,
    ]),
]);
test_assert(count($replacementSync) === 1, 'unknown sync replaces the unresolved snapshot');
test_assert(($replacementSync[0]['id'] ?? '') === 'activityType:unknown activity type', 'unknown sync removes records missing from the latest snapshot');

mattrics_write_exercise_config_records($validExercises);
mattrics_write_activity_type_config_records($validActivityTypes);
mattrics_write_unknown_exercise_records($validUnknowns);

$updatedExercise = mattrics_update_exercise_config_record('bench-press', [
    'canonicalName' => 'Bench Press',
    'aliases' => ['Push Up', 'Mystery Curl'],
    'matchTerms' => ['bench', 'push up', 'mystery curl'],
    'muscleWeights' => ['chest' => 1.0, 'triceps' => 0.52],
    'fatigueMultiplier' => 1.05,
    'bodyweightEligible' => true,
    'setTypeHandling' => 'weight_reps',
]);
test_assert(!array_key_exists('status', $updatedExercise), 'repository update returns simplified configured exercise records');
test_assert(!array_key_exists('reviewNeeded', $updatedExercise), 'repository update no longer exposes reviewNeeded');

$prunedUnknowns = mattrics_prune_resolved_unknown_exercise_records();
test_assert(count($prunedUnknowns) === 0, 'resolved unknown exercises are pruned after alias updates');

$mergeExercises = [
    $validExercises[0],
    [
        'id' => 'hammer-curl',
        'canonicalName' => 'Hammer Curl',
        'normalizedName' => 'hammer curl',
        'aliases' => ['Neutral Grip Curl'],
        'matchTerms' => ['hammer curl', 'neutral grip curl'],
        'muscleWeights' => ['biceps' => 1.0, 'trapezius' => 0.15],
        'fatigueMultiplier' => 0.85,
        'bodyweightEligible' => false,
        'setTypeHandling' => 'weight_reps',
        'status' => 'draft_active',
        'reviewNeeded' => true,
        'source' => 'ai_suggested',
        'lastUpdatedAt' => '2026-04-21T00:00:00Z',
        'lastUpdatedType' => 'ai',
    ],
];

mattrics_write_exercise_config_records($mergeExercises);
mattrics_write_activity_type_config_records($validActivityTypes);
mattrics_write_unknown_exercise_records($validUnknowns);

$mergedUnknown = mattrics_merge_unknown_exercise_record_as_alias('exercise:mystery curl', 'bench-press');
test_assert(in_array('Mystery Curl', $mergedUnknown['aliases'] ?? [], true), 'unknown merge adds the observed name as an alias');
test_assert(in_array('mystery curl', $mergedUnknown['matchTerms'] ?? [], true), 'unknown merge preserves the normalized match term');
test_assert(mattrics_find_unknown_exercise_record('exercise:mystery curl') === null, 'unknown merge removes the unknown record');

mattrics_write_exercise_config_records($mergeExercises);
mattrics_write_activity_type_config_records($validActivityTypes);
mattrics_write_unknown_exercise_records($validUnknowns);

$mergedDraft = mattrics_merge_exercise_config_record_as_alias('hammer-curl', 'bench-press');
$afterMergeRecords = mattrics_read_exercise_config_records();
test_assert(count($afterMergeRecords) === 1, 'exercise merge removes the source config');
test_assert(in_array('Hammer Curl', $mergedDraft['aliases'] ?? [], true), 'exercise merge carries the source canonical name into aliases');
test_assert(
    in_array('neutral grip curl', array_map('mattrics_normalize_config_name', $mergedDraft['matchTerms'] ?? []), true),
    'exercise merge carries source match terms into the target'
);

expect_runtime_exception(
    static function (): void {
        mattrics_merge_exercise_config_record_as_alias('bench-press', 'bench-press');
    },
    'exercise merge rejects merging into the same config'
);

mattrics_write_exercise_config_records($mergeExercises);
mattrics_write_activity_type_config_records($validActivityTypes);
mattrics_write_unknown_exercise_records($validUnknowns);

$deletedExercise = mattrics_delete_exercise_config_record('hammer-curl');
test_assert(($deletedExercise['id'] ?? '') === 'hammer-curl', 'delete returns the removed exercise');
test_assert(mattrics_find_exercise_config_by_id('hammer-curl') === null, 'delete removes the exercise config from storage');

$collisionExercises = [
    $validExercises[0],
    [
        'id' => 'row',
        'canonicalName' => 'Row',
        'normalizedName' => 'row',
        'aliases' => ['Face Pull'],
        'matchTerms' => ['row', 'face pull'],
        'muscleWeights' => ['upperBack' => 0.8, 'biceps' => 0.3],
        'fatigueMultiplier' => 1,
        'bodyweightEligible' => false,
        'setTypeHandling' => 'weight_reps',
        'status' => 'approved',
        'reviewNeeded' => false,
        'source' => 'manual',
        'lastUpdatedAt' => '2026-04-20T00:00:00Z',
        'lastUpdatedType' => 'manual',
    ],
];
mattrics_write_exercise_config_records($collisionExercises);
expect_runtime_exception(
    static function (): void {
        mattrics_update_exercise_config_record('bench-press', [
            'canonicalName' => 'Bench Press',
            'aliases' => ['Face Pull'],
            'matchTerms' => ['bench'],
            'muscleWeights' => ['chest' => 1.0],
            'fatigueMultiplier' => 1,
            'bodyweightEligible' => true,
            'setTypeHandling' => 'weight_reps',
        ]);
    },
    'repository update rejects duplicate canonical or alias collisions'
);

mattrics_write_exercise_config_records($validExercises);
mattrics_write_activity_type_config_records($validActivityTypes);
mattrics_write_unknown_exercise_records($validUnknowns);

$decodedSuggestion = mattrics_decode_unknown_suggestion_output(json_encode([
    'canonicalName' => 'Hammer Curl',
    'aliases' => ['Dumbbell Hammer Curl'],
    'muscleWeights' => ['biceps' => 1.0, 'triceps' => 0.1],
    'fatigueMultiplier' => 0.8,
    'bodyweightEligible' => false,
    'setTypeHandling' => 'weight_reps',
    'confidence' => 0.78,
    'shortReason' => 'Common curl variation.',
]));
test_assert(($decodedSuggestion['canonicalName'] ?? '') === 'Hammer Curl', 'AI response decoder returns canonical name');
test_assert(abs((float) ($decodedSuggestion['muscleWeights']['triceps'] ?? 0) - 0.08) < 0.00001, 'AI response decoder normalizes muscle weights');

expect_runtime_exception(
    static function (): void {
        mattrics_update_exercise_config_record('bench-press', [
            'canonicalName' => 'Bench Press',
            'aliases' => ['Push Up'],
            'matchTerms' => ['bench'],
            'muscleWeights' => ['chest' => 0, 'triceps' => 0],
            'fatigueMultiplier' => 1,
            'bodyweightEligible' => true,
            'setTypeHandling' => 'weight_reps',
        ]);
    },
    'repository rejects all-zero muscle weights on update'
);

expect_runtime_exception(
    static function (): void {
        mattrics_decode_unknown_suggestion_output(json_encode([
            'canonicalName' => 'Bad Curl',
            'aliases' => [],
            'muscleWeights' => ['forearms' => 1.0],
            'fatigueMultiplier' => 1,
            'bodyweightEligible' => false,
            'setTypeHandling' => 'weight_reps',
            'confidence' => 0.5,
            'shortReason' => 'Bad muscle key.',
        ]));
    },
    'AI response decoder rejects invalid muscle keys'
);

$result = run_php_fixture(dirname(__DIR__) . '/tests/fixtures/run-exercises-endpoint.php');
if (!$result['started']) {
    test_assert(false, 'endpoint smoke runner starts');
} else {
    $decoded = $result['decoded'];

    test_assert($result['exitCode'] === 0, 'endpoint smoke runner exits successfully');
    test_assert(is_array($decoded), 'endpoint returns valid JSON');
    test_assert(count($decoded['exercises'] ?? []) === 1, 'endpoint returns exercise configs');
    test_assert(count($decoded['activityTypes'] ?? []) === 1, 'endpoint returns activity type configs');
    test_assert(count($decoded['unknowns'] ?? []) === 1, 'endpoint returns unknown exercise records');
    test_assert(($decoded['meta']['seedVersion'] ?? null) === 1, 'endpoint returns seed version meta');
    test_assert(trim($result['stderr']) === '', 'endpoint smoke runner does not emit stderr');
}

mattrics_write_exercise_config_records($validExercises);
mattrics_write_activity_type_config_records($validActivityTypes);
mattrics_write_unknown_exercise_records($validUnknowns);

$patchDraftResult = run_php_fixture(
    dirname(__DIR__) . '/tests/fixtures/run-exercise-patch-endpoint.php',
    json_encode([
        'canonicalName' => 'Bench Press',
        'aliases' => ['Push Up', 'Mystery Curl'],
        'matchTerms' => ['bench', 'push up', 'mystery curl'],
        'muscleWeights' => ['chest' => 1.0, 'triceps' => 0.52],
        'fatigueMultiplier' => 1.1,
        'bodyweightEligible' => true,
        'setTypeHandling' => 'weight_reps',
    ], JSON_UNESCAPED_SLASHES)
);

if (!$patchDraftResult['started']) {
    test_assert(false, 'patch endpoint runner starts');
} else {
    $decoded = $patchDraftResult['decoded'];
    test_assert($patchDraftResult['exitCode'] === 0, 'patch endpoint runner exits successfully');
    test_assert(($decoded['ok'] ?? false) === true, 'patch endpoint returns ok');
    test_assert(!array_key_exists('status', $decoded['exercise'] ?? []), 'patch endpoint returns simplified exercise records');
    test_assert(!array_key_exists('reviewNeeded', $decoded['exercise'] ?? []), 'patch endpoint omits review flags');
    test_assert(abs((float) ($decoded['exercise']['muscleWeights']['triceps'] ?? 0) - 0.45) < 0.00001, 'patch endpoint normalizes muscle weights');
    test_assert(count($decoded['unknowns'] ?? []) === 0, 'patch endpoint prunes now-resolved unknowns');
    test_assert(trim($patchDraftResult['stderr']) === '', 'patch endpoint runner does not emit stderr');
}

$storedAfterDraftPatch = mattrics_find_exercise_config_by_id('bench-press');
test_assert(in_array('Mystery Curl', $storedAfterDraftPatch['aliases'] ?? [], true), 'patch endpoint persists updated aliases');
test_assert(abs((float) ($storedAfterDraftPatch['muscleWeights']['triceps'] ?? 0) - 0.45) < 0.00001, 'patch endpoint stores normalized muscle weights');

mattrics_write_exercise_config_records($validExercises);
mattrics_write_activity_type_config_records($validActivityTypes);
mattrics_write_unknown_exercise_records($validUnknowns);

$createResult = run_php_fixture(
    dirname(__DIR__) . '/tests/fixtures/run-exercise-create-endpoint.php',
    json_encode([
        'canonicalName' => 'Mystery Curl',
        'aliases' => ['Alt Mystery Curl'],
        'matchTerms' => ['mystery curl', 'alt mystery curl'],
        'muscleWeights' => ['biceps' => 1.0, 'trapezius' => 0.12],
        'fatigueMultiplier' => 0.85,
        'bodyweightEligible' => false,
        'setTypeHandling' => 'weight_reps',
    ], JSON_UNESCAPED_SLASHES)
);

if (!$createResult['started']) {
    test_assert(false, 'create endpoint runner starts');
} else {
    $decoded = $createResult['decoded'];
    test_assert(($decoded['ok'] ?? false) === true, 'create endpoint returns ok');
    test_assert(($decoded['exercise']['canonicalName'] ?? '') === 'Mystery Curl', 'create endpoint returns the created exercise');
    test_assert(count($decoded['exercises'] ?? []) === 2, 'create endpoint returns refreshed exercise configs');
    test_assert(count($decoded['unknowns'] ?? []) === 0, 'create endpoint prunes the resolved unknown');
    test_assert(trim($createResult['stderr']) === '', 'create endpoint runner does not emit stderr');
}

mattrics_write_exercise_config_records($validExercises);
mattrics_write_activity_type_config_records($validActivityTypes);
mattrics_write_unknown_exercise_records($validUnknowns);

$regenerateMock = json_encode([
    'model' => 'gpt-5.4-mini',
    'outputText' => json_encode([
        'canonicalName' => 'Bench Press',
        'aliases' => ['Barbell Bench Press', 'Push Up'],
        'muscleWeights' => ['chest' => 1.0, 'triceps' => 0.52],
        'fatigueMultiplier' => 1.05,
        'bodyweightEligible' => true,
        'setTypeHandling' => 'weight_reps',
        'confidence' => 0.82,
        'shortReason' => 'Refined from the existing bench press mapping.',
    ], JSON_UNESCAPED_SLASHES),
], JSON_UNESCAPED_SLASHES);
putenv('MATTRICS_OPENAI_MOCK_RESPONSE=' . $regenerateMock);
$regenerateResult = run_php_fixture(dirname(__DIR__) . '/tests/fixtures/run-exercise-regenerate-endpoint.php');
if (!$regenerateResult['started']) {
    test_assert(false, 'regenerate endpoint runner starts');
} else {
    $decoded = $regenerateResult['decoded'];
    test_assert(($decoded['ok'] ?? false) === true, 'regenerate endpoint returns ok');
    test_assert(($decoded['suggestion']['canonicalName'] ?? '') === 'Bench Press', 'regenerate endpoint returns a preview suggestion');
    test_assert(abs((float) ($decoded['suggestion']['muscleWeights']['triceps'] ?? 0) - 0.45) < 0.00001, 'regenerate endpoint normalizes preview muscle weights');
    test_assert(count($decoded['exercises'] ?? []) === 1, 'regenerate endpoint does not create a new exercise');
    test_assert(trim($regenerateResult['stderr']) === '', 'regenerate endpoint runner does not emit stderr');
}
putenv('MATTRICS_OPENAI_MOCK_RESPONSE');

mattrics_write_exercise_config_records($collisionExercises);
mattrics_write_activity_type_config_records($validActivityTypes);
mattrics_write_unknown_exercise_records($validUnknowns);

$patchCollisionResult = run_php_fixture(
    dirname(__DIR__) . '/tests/fixtures/run-exercise-patch-endpoint.php',
    json_encode([
        'canonicalName' => 'Bench Press',
        'aliases' => ['Face Pull'],
        'matchTerms' => ['bench'],
        'muscleWeights' => ['chest' => 1.0],
        'fatigueMultiplier' => 1,
        'bodyweightEligible' => true,
        'setTypeHandling' => 'weight_reps',
    ], JSON_UNESCAPED_SLASHES)
);
test_assert(($patchCollisionResult['decoded']['error'] ?? '') !== '', 'patch endpoint rejects duplicate alias collisions');

mattrics_write_exercise_config_records($mergeExercises);
mattrics_write_activity_type_config_records($validActivityTypes);
mattrics_write_unknown_exercise_records($validUnknowns);

$unknownMergeResult = run_php_fixture(
    dirname(__DIR__) . '/tests/fixtures/run-unknown-merge-endpoint.php',
    json_encode([
        'targetExerciseId' => 'bench-press',
    ], JSON_UNESCAPED_SLASHES)
);
if (!$unknownMergeResult['started']) {
    test_assert(false, 'unknown merge endpoint runner starts');
} else {
    $decoded = $unknownMergeResult['decoded'];
    test_assert(($decoded['ok'] ?? false) === true, 'unknown merge endpoint returns ok');
    test_assert(($decoded['exercise']['id'] ?? '') === 'bench-press', 'unknown merge endpoint returns the updated target exercise');
    test_assert(count($decoded['unknowns'] ?? []) === 0, 'unknown merge endpoint removes the merged unknown');
}

mattrics_write_exercise_config_records($mergeExercises);
mattrics_write_activity_type_config_records($validActivityTypes);
mattrics_write_unknown_exercise_records($validUnknowns);

$exerciseMergeResult = run_php_fixture(
    dirname(__DIR__) . '/tests/fixtures/run-exercise-merge-endpoint.php',
    json_encode([
        'targetExerciseId' => 'bench-press',
    ], JSON_UNESCAPED_SLASHES)
);
if (!$exerciseMergeResult['started']) {
    test_assert(false, 'exercise merge endpoint runner starts');
} else {
    $decoded = $exerciseMergeResult['decoded'];
    test_assert(($decoded['ok'] ?? false) === true, 'exercise merge endpoint returns ok');
    test_assert(($decoded['exercise']['id'] ?? '') === 'bench-press', 'exercise merge endpoint returns the updated target exercise');
    test_assert(count($decoded['exercises'] ?? []) === 1, 'exercise merge endpoint removes the merged source config');
    test_assert(trim($exerciseMergeResult['stderr']) === '', 'exercise merge endpoint runner does not emit stderr');
}

mattrics_write_exercise_config_records($mergeExercises);
mattrics_write_activity_type_config_records($validActivityTypes);
mattrics_write_unknown_exercise_records($validUnknowns);

$deleteResult = run_php_fixture(dirname(__DIR__) . '/tests/fixtures/run-exercise-delete-endpoint.php');
if (!$deleteResult['started']) {
    test_assert(false, 'delete endpoint runner starts');
} else {
    $decoded = $deleteResult['decoded'];
    test_assert(($decoded['ok'] ?? false) === true, 'delete endpoint returns ok');
    test_assert(($decoded['deletedExercise']['id'] ?? '') === 'bench-press', 'delete endpoint returns the removed exercise');
    test_assert(count($decoded['exercises'] ?? []) === 1, 'delete endpoint returns the remaining exercise list');
    test_assert(is_string($decoded['recalculatedAt'] ?? null), 'delete endpoint returns recalculation timestamp');
    test_assert(trim($deleteResult['stderr']) === '', 'delete endpoint runner does not emit stderr');
}

mattrics_write_exercise_config_records($validExercises);
mattrics_write_activity_type_config_records($validActivityTypes);
mattrics_write_unknown_exercise_records($validUnknowns);
mattrics_write_exercise_dataset_records($validDatasetRecords);

$datasetUnknowns = [[
    'id' => 'exercise:machine lateral raise',
    'sourceType' => 'exercise',
    'normalizedName' => 'machine lateral raise',
    'rawNames' => ['Machine Lateral Raise'],
    'timesSeen' => 1,
    'firstSeenAt' => '2026-04-20T00:00:00Z',
    'lastSeenAt' => '2026-04-20T00:00:00Z',
    'aiStatus' => 'not_requested',
]];
mattrics_write_unknown_exercise_records($datasetUnknowns);
putenv('MATTRICS_TEST_UNKNOWN_ID=exercise:machine lateral raise');
putenv('MATTRICS_OPENAI_MOCK_RESPONSE');
write_raw(mattrics_exercise_ai_log_path(), '');

$datasetSuggestResult = run_php_fixture(dirname(__DIR__) . '/tests/fixtures/run-exercise-suggest-endpoint.php');
if (!$datasetSuggestResult['started']) {
    test_assert(false, 'dataset suggest endpoint runner starts');
} else {
    $decoded = $datasetSuggestResult['decoded'];
    test_assert(($decoded['ok'] ?? false) === true, 'dataset suggest endpoint returns ok');
    test_assert(($decoded['suggestion']['source'] ?? '') === 'external_dataset', 'dataset suggest endpoint returns an external-dataset preview');
    test_assert(abs((float) ($decoded['suggestion']['muscleWeights']['trapezius'] ?? 0) - 0.20) < 0.00001, 'dataset suggest endpoint normalizes preview muscle weights');
    test_assert(($decoded['meta']['matchedVia'] ?? '') === 'external_dataset', 'dataset suggest endpoint reports dataset matching');
    test_assert(count($decoded['unknowns'] ?? []) === 1, 'dataset suggest endpoint keeps the unknown until save');
    test_assert(($decoded['unknown']['aiStatus'] ?? '') === 'succeeded', 'dataset suggest endpoint marks the unknown suggestion as ready');
}
$datasetLogEntries = read_ai_log_entries();
$datasetLogEntry = $datasetLogEntries[count($datasetLogEntries) - 1] ?? [];
test_assert(($datasetLogEntry['sourceType'] ?? '') === 'exercise', 'dataset suggest logs sourceType');
test_assert(($datasetLogEntry['model'] ?? '') === 'external_dataset', 'dataset suggest logs dataset marker as model');
test_assert(($datasetLogEntry['promptVersion'] ?? '') === MATTRICS_EXERCISE_AI_PROMPT_VERSION, 'dataset suggest logs prompt version');
test_assert(($datasetLogEntry['resultStatus'] ?? '') === 'preview_ready', 'dataset suggest logs preview-ready resultStatus');
test_assert(($datasetLogEntry['configCreated'] ?? null) === false, 'dataset suggest logs configCreated false');

putenv('MATTRICS_TEST_UNKNOWN_ID');
mattrics_write_exercise_config_records($validExercises);
mattrics_write_activity_type_config_records($validActivityTypes);
mattrics_write_unknown_exercise_records($validUnknowns);

$mockSuccess = json_encode([
    'model' => 'gpt-5.4-mini',
    'outputText' => json_encode([
        'canonicalName' => 'Hammer Curl',
        'aliases' => ['Dumbbell Hammer Curl', 'Neutral Grip Curl'],
        'muscleWeights' => ['biceps' => 1.0, 'trapezius' => 0.15],
        'fatigueMultiplier' => 0.85,
        'bodyweightEligible' => false,
        'setTypeHandling' => 'weight_reps',
        'confidence' => 0.83,
        'shortReason' => 'Common dumbbell curl pattern.',
    ], JSON_UNESCAPED_SLASHES),
], JSON_UNESCAPED_SLASHES);

putenv('MATTRICS_OPENAI_MOCK_RESPONSE=' . $mockSuccess);
write_raw(mattrics_exercise_ai_log_path(), '');
$result = run_php_fixture(dirname(__DIR__) . '/tests/fixtures/run-exercise-suggest-endpoint.php');
if (!$result['started']) {
    test_assert(false, 'suggest endpoint runner starts');
} else {
    $decoded = $result['decoded'];

    test_assert($result['exitCode'] === 0, 'suggest endpoint runner exits successfully');
    test_assert(($decoded['ok'] ?? false) === true, 'suggest endpoint returns ok');
    test_assert(($decoded['suggestion']['source'] ?? '') === 'ai_suggested', 'suggest endpoint returns an AI preview');
    test_assert(abs((float) ($decoded['suggestion']['muscleWeights']['trapezius'] ?? 0) - 0.20) < 0.00001, 'suggest endpoint normalizes AI preview muscle weights');
    test_assert(count($decoded['exercises'] ?? []) === 1, 'suggest endpoint returns the existing exercise payload');
    test_assert(count($decoded['unknowns'] ?? []) === 1, 'suggest endpoint keeps the unknown until save');
    test_assert(($decoded['unknown']['aiStatus'] ?? '') === 'succeeded', 'suggest endpoint marks the unknown suggestion as ready');
    test_assert(trim($result['stderr']) === '', 'suggest endpoint runner does not emit stderr');
}

$storedExercises = mattrics_read_exercise_config_records();
$storedUnknowns = mattrics_read_unknown_exercise_records();
test_assert(count($storedExercises) === 1, 'suggest endpoint does not persist a created config');
test_assert(count($storedUnknowns) === 1, 'suggest endpoint keeps the unknown persisted until save');
$successLogEntries = read_ai_log_entries();
$successLogEntry = $successLogEntries[count($successLogEntries) - 1] ?? [];
test_assert(($successLogEntry['rawName'] ?? '') === 'Mystery Curl', 'suggest success logs rawName');
test_assert(($successLogEntry['normalizedName'] ?? '') === 'mystery curl', 'suggest success logs normalizedName');
test_assert(($successLogEntry['sourceType'] ?? '') === 'exercise', 'suggest success logs sourceType');
test_assert(($successLogEntry['model'] ?? '') === 'gpt-5.4-mini', 'suggest success logs model');
test_assert(($successLogEntry['promptVersion'] ?? '') === MATTRICS_EXERCISE_AI_PROMPT_VERSION, 'suggest success logs prompt version');
test_assert(($successLogEntry['resultStatus'] ?? '') === 'preview_ready', 'suggest success logs preview-ready resultStatus');
test_assert(($successLogEntry['configCreated'] ?? null) === false, 'suggest success logs configCreated false');

mattrics_write_exercise_config_records($validExercises);
mattrics_write_activity_type_config_records($validActivityTypes);
mattrics_write_unknown_exercise_records($validUnknowns);

$mockInvalid = json_encode([
    'model' => 'gpt-5.4-mini',
    'outputText' => '{"canonicalName":"Broken Curl","aliases":[]}',
], JSON_UNESCAPED_SLASHES);

putenv('MATTRICS_OPENAI_MOCK_RESPONSE=' . $mockInvalid);
write_raw(mattrics_exercise_ai_log_path(), '');
$result = run_php_fixture(dirname(__DIR__) . '/tests/fixtures/run-exercise-suggest-endpoint.php');
if (!$result['started']) {
    test_assert(false, 'invalid suggest endpoint runner starts');
} else {
    $decoded = $result['decoded'];

    test_assert($result['exitCode'] === 0, 'invalid suggest endpoint runner exits successfully');
    test_assert(($decoded['error'] ?? '') !== '', 'invalid suggest endpoint returns an error');
    test_assert(($decoded['unknown']['aiStatus'] ?? '') === 'failed' || ($decoded['unknown']['aiStatus'] ?? '') === 'invalid_response', 'invalid suggest endpoint updates unknown aiStatus');
    test_assert(trim($result['stderr']) === '', 'invalid suggest endpoint runner does not emit stderr');
}

$postFailureUnknown = mattrics_find_unknown_exercise_record('exercise:mystery curl');
test_assert(($postFailureUnknown['aiStatus'] ?? '') === 'invalid_response', 'invalid suggest endpoint persists invalid_response status');
$failureLogEntries = read_ai_log_entries();
$failureLogEntry = $failureLogEntries[count($failureLogEntries) - 1] ?? [];
test_assert(($failureLogEntry['sourceType'] ?? '') === 'exercise', 'invalid suggest logs sourceType');
test_assert(($failureLogEntry['promptVersion'] ?? '') === MATTRICS_EXERCISE_AI_PROMPT_VERSION, 'invalid suggest logs prompt version');
test_assert(($failureLogEntry['resultStatus'] ?? '') === 'invalid_response', 'invalid suggest logs resultStatus');
test_assert(($failureLogEntry['configCreated'] ?? null) === false, 'invalid suggest logs configCreated false');

putenv('MATTRICS_OPENAI_MOCK_RESPONSE');

echo "Exercise config tests: {$passed}/" . ($passed + $failed) . " passed\n";

if ($failed > 0) {
    exit(1);
}
