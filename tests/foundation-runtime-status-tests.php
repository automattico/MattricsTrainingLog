<?php
declare(strict_types=1);

$tempRoot = sys_get_temp_dir() . '/mattrics-foundation-runtime-status-tests-' . bin2hex(random_bytes(4));
mkdir($tempRoot, 0775, true);

require_once dirname(__DIR__) . '/public/api/bootstrap.php';
require_once dirname(__DIR__) . '/public/api/foundation-read.php';
require_once dirname(__DIR__) . '/scripts/lib/foundation-import.php';

$passed = 0;
$failed = 0;
$skipped = 0;

function foundation_runtime_test_assert(bool $condition, string $message): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }

    $failed++;
    fwrite(STDERR, "FAIL: {$message}\n");
}

function foundation_runtime_test_skip(string $message): void
{
    global $skipped;
    $skipped++;
    fwrite(STDOUT, "SKIP: {$message}\n");
}

function foundation_runtime_write_php_config(string $path, array $config): void
{
    file_put_contents($path, "<?php\nreturn " . var_export($config, true) . ";\n");
}

function foundation_runtime_write_json(string $path, mixed $value): void
{
    file_put_contents($path, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function foundation_runtime_run_fixture(string $fixturePath): array
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

function foundation_runtime_apply_env(string $configPath, string $runtimeMode = 'foundation'): void
{
    putenv('MATTRICS_CONFIG=' . $configPath);
    putenv('MATTRICS_AUTH_REQUIRE_HTTPS=0');
    putenv('MATTRICS_RUNTIME_MODE=' . $runtimeMode);
    putenv('MATTRICS_TEST_HTTP_HOST');
    mattrics_foundation_read_reset_cache();
}

$disabledRoot = $tempRoot . '/disabled';
mkdir($disabledRoot . '/data', 0775, true);
mkdir($disabledRoot . '/cache', 0775, true);
mkdir($disabledRoot . '/storage', 0775, true);
foundation_runtime_write_json($disabledRoot . '/data/exercise-configs.json', []);
foundation_runtime_write_json($disabledRoot . '/data/activity-type-configs.json', []);
foundation_runtime_write_json($disabledRoot . '/data/exercise-unknowns.json', []);
foundation_runtime_write_json($disabledRoot . '/storage/live-connectors.json', [
    'version' => 1,
    'connectors' => [
        'hevy' => ['enabled' => false],
        'garmin' => ['enabled' => false],
    ],
]);
foundation_runtime_write_json($disabledRoot . '/cache/training-data.json', ['rows' => [], 'count' => 0, 'meta' => []]);
$disabledConfig = $disabledRoot . '/config.php';
foundation_runtime_write_php_config($disabledConfig, [
    'auth_require_https' => false,
    'foundation_database_url' => '',
    'foundation_user_key' => 'legacy-local-user',
]);
foundation_runtime_apply_env($disabledConfig, 'foundation');

$disabledResult = foundation_runtime_run_fixture(dirname(__DIR__) . '/tests/fixtures/run-runtime-status-endpoint.php');
foundation_runtime_test_assert(($disabledResult['exitCode'] ?? 1) === 0, 'runtime status fixture exits successfully in disabled mode');
foundation_runtime_test_assert(($disabledResult['decoded']['canonicalModeEnabled'] ?? true) === false, 'runtime status reports canonical mode disabled without DB config');
foundation_runtime_test_assert(($disabledResult['decoded']['canonicalReadStatus'] ?? '') === 'disabled', 'runtime status reports disabled read status without DB config');
foundation_runtime_test_assert(isset($disabledResult['decoded']['connectors']['hevy']), 'runtime status exposes app-safe connector payloads even when canonical mode is disabled');
foundation_runtime_test_assert(!isset($disabledResult['decoded']['connectors']['hevy']['apiKey']), 'runtime status never exposes raw connector secrets');
foundation_runtime_test_assert(isset($disabledResult['decoded']['connectors']['hevy']['sync']), 'runtime status exposes app-safe nested sync metadata');

putenv('MATTRICS_RUNTIME_MODE=default');
putenv('MATTRICS_TEST_HTTP_HOST=example.com');
$blockedResult = foundation_runtime_run_fixture(dirname(__DIR__) . '/tests/fixtures/run-runtime-status-endpoint.php');
foundation_runtime_test_assert(($blockedResult['exitCode'] ?? 1) === 0, 'runtime status fixture exits successfully for blocked remote request');
foundation_runtime_test_assert(($blockedResult['decoded']['error'] ?? '') === 'Not found.', 'runtime status endpoint is hidden for non-local non-foundation requests');

$integrationDatabaseUrl = getenv('MATTRICS_FOUNDATION_TEST_DATABASE_URL') ?: 'postgres://mattrics:mattrics-local-only-change-me@127.0.0.1:5433/mattrics';
$integrationPdo = null;
try {
    $integrationPdo = mattrics_foundation_connect($integrationDatabaseUrl);
} catch (Throwable $throwable) {
    foundation_runtime_test_skip('Foundation runtime status integration tests skipped: ' . $throwable->getMessage());
}

if ($integrationPdo instanceof PDO) {
    $integrationRoot = $tempRoot . '/integration';
    mkdir($integrationRoot . '/data', 0775, true);
    mkdir($integrationRoot . '/cache', 0775, true);
    mkdir($integrationRoot . '/import-hevy', 0775, true);
    mkdir($integrationRoot . '/storage', 0775, true);

    foundation_runtime_write_json($integrationRoot . '/data/exercise-configs.json', [[
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
        'lastUpdatedAt' => '2026-06-08T00:00:00Z',
        'lastUpdatedType' => 'manual',
        'exerciseFamily' => 'horizontal_press',
        'fatigueArchetype' => 'freeweight_compound',
    ]]);
    foundation_runtime_write_json($integrationRoot . '/data/activity-type-configs.json', [[
        'id' => 'weight-training',
        'canonicalName' => 'WeightTraining',
        'normalizedName' => 'weight training',
        'aliases' => ['Strength'],
        'muscleWeights' => ['chest' => 0.45, 'quadriceps' => 0.45],
        'fatigueMultiplier' => 1.0,
        'status' => 'approved',
        'reviewNeeded' => false,
        'source' => 'manual',
        'lastUpdatedAt' => '2026-06-08T00:00:00Z',
        'lastUpdatedType' => 'manual',
        'exerciseFamily' => 'conditioning_lower',
        'fatigueArchetype' => 'conditioning_hybrid',
    ]]);
    foundation_runtime_write_json($integrationRoot . '/data/exercise-unknowns.json', []);
    foundation_runtime_write_json($integrationRoot . '/storage/live-connectors.json', [
        'version' => 1,
        'connectors' => [
            'hevy' => [
                'enabled' => true,
                'apiKey' => 'test-hevy-key',
                'lastSyncAttemptAt' => '2026-06-09T10:00:00Z',
                'lastSyncSucceededAt' => '2026-06-09T10:05:00Z',
            ],
            'garmin' => [
                'enabled' => false,
                'credentials' => [],
            ],
        ],
    ]);
    foundation_runtime_write_json($integrationRoot . '/cache/training-data.json', [
        'rows' => [[
            'Date' => '2026-06-05T07:30:00+02:00',
            'Type' => 'WeightTraining',
            'Name' => 'Foundation Runtime Lift',
            'Duration (min)' => '60',
            'Description' => "Logged with HevyApp.com\n\nBench Press\n80 kg x 5",
            'Device Name' => 'Hevy',
            'Activity ID' => 'runtime-lift-1',
            'Activity ID raw' => 'runtime-lift-raw-1',
        ]],
        'count' => 1,
        'meta' => ['lastSuccessfulSyncAt' => '2026-06-08T00:00:00Z'],
    ]);
    file_put_contents($integrationRoot . '/import-hevy/workouts.csv', <<<CSV
"title","start_time","end_time","description","exercise_title","superset_id","exercise_notes","set_index","set_type","weight_kg","reps","distance_km","duration_seconds","rpe"
"Foundation Runtime Lift","05.06.2026, 07:30","05.06.2026, 08:30","Warm up","Bench Press","","Tempo","0","normal","80","5","","","8.5"
CSV);

    $integrationUserKey = 'foundation-runtime-status-' . bin2hex(random_bytes(4));
    $integrationConfig = $integrationRoot . '/config.php';
    foundation_runtime_write_php_config($integrationConfig, [
        'auth_require_https' => false,
        'foundation_database_url' => $integrationDatabaseUrl,
        'foundation_user_key' => $integrationUserKey,
    ]);

    mattrics_foundation_run_import($integrationPdo, [
        'userKey' => $integrationUserKey,
        'displayName' => 'Foundation Runtime Status User',
        'timezone' => 'Europe/Berlin',
        'privateRoot' => $integrationRoot,
        'dryRun' => false,
    ]);
    mattrics_foundation_run_hevy_import($integrationPdo, [
        'userKey' => $integrationUserKey,
        'displayName' => 'Foundation Runtime Status User',
        'timezone' => 'Europe/Berlin',
        'privateRoot' => $integrationRoot,
        'dryRun' => false,
    ]);

    foundation_runtime_apply_env($integrationConfig, 'foundation');
    $integrationResult = foundation_runtime_run_fixture(dirname(__DIR__) . '/tests/fixtures/run-runtime-status-endpoint.php');
    foundation_runtime_test_assert(($integrationResult['exitCode'] ?? 1) === 0, 'runtime status fixture exits successfully in canonical mode');
    foundation_runtime_test_assert(($integrationResult['decoded']['canonicalModeEnabled'] ?? false) === true, 'runtime status reports canonical mode enabled');
    foundation_runtime_test_assert(($integrationResult['decoded']['databaseReachable'] ?? false) === true, 'runtime status reports reachable database');
    foundation_runtime_test_assert(($integrationResult['decoded']['foundationUserFound'] ?? false) === true, 'runtime status reports configured foundation user found');
    foundation_runtime_test_assert(($integrationResult['decoded']['canonicalReadStatus'] ?? '') === 'ready', 'runtime status reports ready canonical read status');
    foundation_runtime_test_assert(($integrationResult['decoded']['migrationStatus'] ?? '') === 'up_to_date', 'runtime status reports up-to-date migrations after importer setup');
    foundation_runtime_test_assert(($integrationResult['decoded']['lastSuccessfulImportBatchKind'] ?? '') !== '', 'runtime status exposes latest successful batch kind');
    foundation_runtime_test_assert(($integrationResult['decoded']['lastSuccessfulSyncAt'] ?? '') !== '', 'runtime status exposes last successful sync timestamp');
    foundation_runtime_test_assert(($integrationResult['decoded']['connectors']['hevy']['hasCredential'] ?? false) === true, 'runtime status reports app-safe Hevy credential presence');
    foundation_runtime_test_assert(($integrationResult['decoded']['connectors']['hevy']['connectionStatus'] ?? '') === 'active', 'runtime status reports active Hevy connector state after a successful sync');
    foundation_runtime_test_assert(($integrationResult['decoded']['connectors']['hevy']['syncStrategy'] ?? '') !== '', 'runtime status exposes Hevy incremental sync strategy metadata');
    foundation_runtime_test_assert(array_key_exists('cursorStartedAt', $integrationResult['decoded']['connectors']['hevy'] ?? []), 'runtime status exposes app-safe Hevy cursor timestamps');
    foundation_runtime_test_assert(!str_contains($integrationResult['stdout'] ?? '', 'test-hevy-key'), 'runtime status does not expose raw connector secrets');
}

if ($failed > 0) {
    fwrite(STDERR, "foundation-runtime-status-tests: {$passed} passed, {$failed} failed, {$skipped} skipped\n");
    exit(1);
}

fwrite(STDOUT, "foundation-runtime-status-tests: {$passed} passed, 0 failed, {$skipped} skipped\n");
