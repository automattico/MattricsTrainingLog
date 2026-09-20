<?php
declare(strict_types=1);

$tempRoot = sys_get_temp_dir() . '/mattrics-foundation-import-diagnostics-tests-' . bin2hex(random_bytes(4));
mkdir($tempRoot, 0775, true);

require_once dirname(__DIR__) . '/public/api/bootstrap.php';
require_once dirname(__DIR__) . '/public/api/foundation-read.php';
require_once dirname(__DIR__) . '/scripts/lib/foundation-import.php';

$passed = 0;
$failed = 0;
$skipped = 0;

function foundation_import_diagnostics_assert(bool $condition, string $message): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }

    $failed++;
    fwrite(STDERR, "FAIL: {$message}\n");
}

function foundation_import_diagnostics_skip(string $message): void
{
    global $skipped;
    $skipped++;
    fwrite(STDOUT, "SKIP: {$message}\n");
}

function foundation_import_diagnostics_write_php_config(string $path, array $config): void
{
    file_put_contents($path, "<?php\nreturn " . var_export($config, true) . ";\n");
}

function foundation_import_diagnostics_write_json(string $path, mixed $value): void
{
    file_put_contents($path, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function foundation_import_diagnostics_run_fixture(string $fixturePath): array
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

function foundation_import_diagnostics_apply_env(string $configPath, string $runtimeMode = 'foundation'): void
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
foundation_import_diagnostics_write_json($disabledRoot . '/data/exercise-configs.json', []);
foundation_import_diagnostics_write_json($disabledRoot . '/data/activity-type-configs.json', []);
foundation_import_diagnostics_write_json($disabledRoot . '/data/exercise-unknowns.json', []);
foundation_import_diagnostics_write_json($disabledRoot . '/cache/training-data.json', ['rows' => [], 'count' => 0, 'meta' => []]);
$disabledConfig = $disabledRoot . '/config.php';
foundation_import_diagnostics_write_php_config($disabledConfig, [
    'auth_require_https' => false,
    'foundation_database_url' => '',
    'foundation_user_key' => 'legacy-local-user',
]);
foundation_import_diagnostics_apply_env($disabledConfig, 'foundation');

$disabledResult = foundation_import_diagnostics_run_fixture(dirname(__DIR__) . '/tests/fixtures/run-import-diagnostics-endpoint.php');
foundation_import_diagnostics_assert(($disabledResult['exitCode'] ?? 1) === 0, 'import diagnostics fixture exits successfully in disabled mode');
foundation_import_diagnostics_assert(($disabledResult['decoded']['canonicalModeEnabled'] ?? true) === false, 'import diagnostics reports canonical mode disabled without DB config');
foundation_import_diagnostics_assert(($disabledResult['decoded']['summary']['sourceCount'] ?? 1) === 0, 'import diagnostics returns an empty summary when canonical mode is disabled');
foundation_import_diagnostics_assert(($disabledResult['decoded']['sources'] ?? null) === [], 'import diagnostics returns an empty sources list when canonical mode is disabled');

putenv('MATTRICS_RUNTIME_MODE=default');
putenv('MATTRICS_TEST_HTTP_HOST=example.com');
$blockedResult = foundation_import_diagnostics_run_fixture(dirname(__DIR__) . '/tests/fixtures/run-import-diagnostics-endpoint.php');
foundation_import_diagnostics_assert(($blockedResult['exitCode'] ?? 1) === 0, 'import diagnostics fixture exits successfully for blocked remote request');
foundation_import_diagnostics_assert(($blockedResult['decoded']['error'] ?? '') === 'Not found.', 'import diagnostics endpoint is hidden for non-local non-foundation requests');

$integrationDatabaseUrl = getenv('MATTRICS_FOUNDATION_TEST_DATABASE_URL') ?: 'postgres://mattrics:mattrics-local-only-change-me@127.0.0.1:5433/mattrics';
$integrationPdo = null;
try {
    $integrationPdo = mattrics_foundation_connect($integrationDatabaseUrl);
} catch (Throwable $throwable) {
    foundation_import_diagnostics_skip('Foundation import diagnostics integration tests skipped: ' . $throwable->getMessage());
}

if ($integrationPdo instanceof PDO) {
    $integrationRoot = $tempRoot . '/integration';
    mkdir($integrationRoot . '/data', 0775, true);
    mkdir($integrationRoot . '/cache', 0775, true);
    mkdir($integrationRoot . '/import-hevy', 0775, true);
    mkdir($integrationRoot . '/import-garmin', 0775, true);
    mkdir($integrationRoot . '/storage', 0775, true);

    foundation_import_diagnostics_write_json($integrationRoot . '/data/exercise-configs.json', [[
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
        'lastUpdatedAt' => '2026-06-09T00:00:00Z',
        'lastUpdatedType' => 'manual',
        'exerciseFamily' => 'horizontal_press',
        'fatigueArchetype' => 'freeweight_compound',
    ]]);
    foundation_import_diagnostics_write_json($integrationRoot . '/data/activity-type-configs.json', [
        [
            'id' => 'weight-training',
            'canonicalName' => 'WeightTraining',
            'normalizedName' => 'weight training',
            'aliases' => ['Strength'],
            'muscleWeights' => ['chest' => 0.45, 'quadriceps' => 0.45],
            'fatigueMultiplier' => 1.0,
            'status' => 'approved',
            'reviewNeeded' => false,
            'source' => 'manual',
            'lastUpdatedAt' => '2026-06-09T00:00:00Z',
            'lastUpdatedType' => 'manual',
            'exerciseFamily' => 'conditioning_lower',
            'fatigueArchetype' => 'conditioning_hybrid',
        ],
        [
            'id' => 'run',
            'canonicalName' => 'Run',
            'normalizedName' => 'run',
            'aliases' => ['Running', 'Treadmill Running', 'Trail Running', 'Virtual Running'],
            'muscleWeights' => ['quadriceps' => 1.0, 'hamstrings' => 0.65],
            'fatigueMultiplier' => 1.0,
            'status' => 'approved',
            'reviewNeeded' => false,
            'source' => 'manual',
            'lastUpdatedAt' => '2026-06-09T00:00:00Z',
            'lastUpdatedType' => 'manual',
            'exerciseFamily' => 'conditioning_lower',
            'fatigueArchetype' => 'conditioning_hybrid',
        ],
        [
            'id' => 'walk',
            'canonicalName' => 'Walk',
            'normalizedName' => 'walk',
            'aliases' => ['Walking'],
            'muscleWeights' => ['quadriceps' => 0.42, 'hamstrings' => 0.31, 'calves' => 0.41],
            'fatigueMultiplier' => 1.0,
            'status' => 'approved',
            'reviewNeeded' => false,
            'source' => 'manual',
            'lastUpdatedAt' => '2026-06-09T00:00:00Z',
            'lastUpdatedType' => 'manual',
            'exerciseFamily' => 'conditioning_lower',
            'fatigueArchetype' => 'isolation',
        ],
        [
            'id' => 'rowing',
            'canonicalName' => 'Rowing',
            'normalizedName' => 'rowing',
            'aliases' => ['Erg', 'Indoor Rowing', 'Row Erg'],
            'muscleWeights' => ['upperBack' => 1.35, 'quadriceps' => 0.93, 'hamstrings' => 0.64],
            'fatigueMultiplier' => 1.0,
            'status' => 'approved',
            'reviewNeeded' => false,
            'source' => 'manual',
            'lastUpdatedAt' => '2026-06-09T00:00:00Z',
            'lastUpdatedType' => 'manual',
            'exerciseFamily' => 'horizontal_pull',
            'fatigueArchetype' => 'conditioning_hybrid',
        ],
    ]);
    foundation_import_diagnostics_write_json($integrationRoot . '/data/exercise-unknowns.json', []);
    foundation_import_diagnostics_write_json($integrationRoot . '/storage/live-connectors.json', [
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
    ]);
    foundation_import_diagnostics_write_json($integrationRoot . '/cache/training-data.json', [
        'rows' => [
            [
                'Date' => '2026-06-05T07:30:00+02:00',
                'Type' => 'WeightTraining',
                'Name' => 'Foundation Runtime Lift',
                'Duration (min)' => '60',
                'Description' => "Logged with HevyApp.com\n\nBench Press\n80 kg x 5",
                'Device Name' => 'Hevy',
                'Activity ID' => 'legacy-hevy-duplicate-1',
                'Activity ID raw' => 'legacy-hevy-duplicate-raw-1',
            ],
            [
                'Date' => '2026-06-07T14:10:28+02:00',
                'Type' => 'Running',
                'Name' => 'Backnang Running',
                'Distance (km)' => '5.73',
                'Duration (min)' => '45.08',
                'Avg HR' => '143',
                'Max HR' => '161',
                'Avg Pace (min/km)' => '7.8667',
                'Avg Speed (km/h)' => '7.63',
                'Avg Cadence' => '146',
                'Device Name' => 'Garmin Forerunner 55',
                'Activity ID' => 'legacy-garmin-duplicate-1',
                'Activity ID raw' => 'legacy-garmin-duplicate-raw-1',
            ],
            [
                'Date' => '2026-06-01',
                'Type' => 'Walking',
                'Name' => '',
                'Duration (min)' => '70.58',
                'Device Name' => 'Garmin',
                'Activity ID' => 'legacy-garmin-ambiguous-1',
                'Activity ID raw' => 'legacy-garmin-ambiguous-raw-1',
            ],
        ],
        'count' => 3,
        'meta' => ['lastSuccessfulSyncAt' => '2026-06-09T00:00:00Z'],
    ]);
    file_put_contents($integrationRoot . '/import-hevy/workouts.csv', <<<CSV
"title","start_time","end_time","description","exercise_title","superset_id","exercise_notes","set_index","set_type","weight_kg","reps","distance_km","duration_seconds","rpe"
"Foundation Runtime Lift","05.06.2026, 07:30","05.06.2026, 08:30","Warm up","Bench Press","","Tempo","0","normal","80","5","","","8.5"
CSV);
    copy(dirname(__DIR__) . '/tests/fixtures/garmin-activities-expanded.csv', $integrationRoot . '/import-garmin/Activities.csv');

    $integrationUserKey = 'foundation-import-diagnostics-' . bin2hex(random_bytes(4));
    $integrationConfig = $integrationRoot . '/config.php';
    foundation_import_diagnostics_write_php_config($integrationConfig, [
        'auth_require_https' => false,
        'foundation_database_url' => $integrationDatabaseUrl,
        'foundation_user_key' => $integrationUserKey,
    ]);

    mattrics_foundation_run_import($integrationPdo, [
        'userKey' => $integrationUserKey,
        'displayName' => 'Foundation Import Diagnostics User',
        'timezone' => 'Europe/Berlin',
        'privateRoot' => $integrationRoot,
        'dryRun' => false,
    ]);
    mattrics_foundation_run_hevy_import($integrationPdo, [
        'userKey' => $integrationUserKey,
        'displayName' => 'Foundation Import Diagnostics User',
        'timezone' => 'Europe/Berlin',
        'privateRoot' => $integrationRoot,
        'dryRun' => false,
    ]);
    mattrics_foundation_run_garmin_import($integrationPdo, [
        'userKey' => $integrationUserKey,
        'displayName' => 'Foundation Import Diagnostics User',
        'timezone' => 'Europe/Berlin',
        'privateRoot' => $integrationRoot,
        'dryRun' => false,
    ]);
    mattrics_foundation_run_live_connector_sync($integrationPdo, [
        'userKey' => $integrationUserKey,
        'displayName' => 'Foundation Import Diagnostics User',
        'timezone' => 'Europe/Berlin',
        'privateRoot' => $integrationRoot,
        'dryRun' => false,
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
                    'title' => 'Live API Session',
                    'description' => 'Live sync workout',
                    'start_time' => '2026-06-08T07:30:00+02:00',
                    'end_time' => '2026-06-08T08:30:00+02:00',
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

    foundation_import_diagnostics_apply_env($integrationConfig, 'foundation');
    $integrationResult = foundation_import_diagnostics_run_fixture(dirname(__DIR__) . '/tests/fixtures/run-import-diagnostics-endpoint.php');
    $decoded = $integrationResult['decoded'] ?? null;
    foundation_import_diagnostics_assert(($integrationResult['exitCode'] ?? 1) === 0, 'import diagnostics fixture exits successfully in canonical mode');
    foundation_import_diagnostics_assert(($decoded['canonicalModeEnabled'] ?? false) === true, 'import diagnostics reports canonical mode enabled');
    foundation_import_diagnostics_assert(($decoded['databaseReachable'] ?? false) === true, 'import diagnostics reports reachable database');
    foundation_import_diagnostics_assert(($decoded['foundationUserFound'] ?? false) === true, 'import diagnostics reports configured foundation user found');
    foundation_import_diagnostics_assert(($decoded['canonicalReadStatus'] ?? '') === 'ready', 'import diagnostics reports ready canonical read status');

    $sources = is_array($decoded['sources'] ?? null) ? $decoded['sources'] : [];
    $sourcesByKey = [];
    foreach ($sources as $source) {
        $sourcesByKey[(string) ($source['sourceKey'] ?? '')] = $source;
    }

    foundation_import_diagnostics_assert(isset($sourcesByKey['legacy-google-sheet-snapshot']), 'import diagnostics includes the legacy snapshot source');
    foundation_import_diagnostics_assert(isset($sourcesByKey['hevy-workout-export']), 'import diagnostics includes the Hevy import source');
    foundation_import_diagnostics_assert(isset($sourcesByKey['garmin-activities-csv-export']), 'import diagnostics includes the Garmin import source');
    foundation_import_diagnostics_assert(isset($sourcesByKey['hevy-live-api']), 'import diagnostics includes the live Hevy source');
    foundation_import_diagnostics_assert(isset($sourcesByKey['garmin-connect-live']), 'import diagnostics includes the Garmin live groundwork source');
    foundation_import_diagnostics_assert(($sourcesByKey['garmin-activities-csv-export']['latestBatch']['batchKind'] ?? '') === 'garmin_export', 'import diagnostics exposes the latest Garmin batch kind');
    foundation_import_diagnostics_assert(($sourcesByKey['hevy-live-api']['latestBatch']['batchKind'] ?? '') === 'hevy_live_api_sync', 'import diagnostics exposes the latest Hevy live batch kind');
    foundation_import_diagnostics_assert(($sourcesByKey['hevy-live-api']['hasCredential'] ?? false) === true, 'import diagnostics exposes app-safe live connector credential presence');
    foundation_import_diagnostics_assert(($sourcesByKey['hevy-live-api']['syncStrategy'] ?? '') !== '', 'import diagnostics exposes Hevy incremental sync strategy metadata');
    foundation_import_diagnostics_assert(array_key_exists('cursorStartedAt', $sourcesByKey['hevy-live-api'] ?? []), 'import diagnostics exposes app-safe Hevy cursor timestamps');
    foundation_import_diagnostics_assert(array_key_exists('sync', $sourcesByKey['hevy-live-api'] ?? []), 'import diagnostics exposes nested Hevy sync metadata');
    foundation_import_diagnostics_assert(($sourcesByKey['garmin-activities-csv-export']['latestSuccessfulImportAt'] ?? '') !== '', 'import diagnostics exposes latest successful Garmin import time');
    foundation_import_diagnostics_assert(($sourcesByKey['garmin-activities-csv-export']['unresolvedActivityTypeCount'] ?? 0) === 1, 'import diagnostics leaves only ambiguous Garmin activity types unresolved after normalization hardening');
    foundation_import_diagnostics_assert(
        in_array('Other', $sourcesByKey['garmin-activities-csv-export']['unresolvedActivityTypesSample'] ?? [], true),
        'import diagnostics samples unresolved Garmin activity types'
    );
    foundation_import_diagnostics_assert(
        !in_array('Walking', $sourcesByKey['garmin-activities-csv-export']['unresolvedActivityTypesSample'] ?? [], true),
        'import diagnostics no longer samples high-confidence Garmin walking labels as unresolved'
    );
    foundation_import_diagnostics_assert(($sourcesByKey['legacy-google-sheet-snapshot']['suppressedActivityCount'] ?? 0) >= 2, 'import diagnostics reports suppressed legacy rows when direct imports win');
    foundation_import_diagnostics_assert(($sourcesByKey['legacy-google-sheet-snapshot']['selectedActivityCount'] ?? 0) >= 1, 'import diagnostics keeps ambiguous legacy rows selected when dedupe is non-deterministic');
    foundation_import_diagnostics_assert(($sourcesByKey['legacy-google-sheet-snapshot']['nonDeterministicActivityCount'] ?? 0) >= 1, 'import diagnostics counts non-deterministic legacy Garmin-like rows');
    foundation_import_diagnostics_assert(($sourcesByKey['hevy-workout-export']['selectedActivityCount'] ?? 0) >= 1, 'import diagnostics reports selected direct Hevy rows');
    foundation_import_diagnostics_assert(($sourcesByKey['garmin-activities-csv-export']['selectedActivityCount'] ?? 0) >= 3, 'import diagnostics reports selected direct Garmin rows');
    foundation_import_diagnostics_assert(($decoded['summary']['suppressedActivityCount'] ?? 0) >= 2, 'import diagnostics summary includes suppressed activity totals');
    foundation_import_diagnostics_assert(($decoded['summary']['metadataOverlayCount'] ?? 0) >= 1, 'import diagnostics summary counts metadata overlays from legacy donors');
    foundation_import_diagnostics_assert(($decoded['summary']['latestSuccessfulImportAt'] ?? '') !== '', 'import diagnostics summary exposes the latest successful import time');
    foundation_import_diagnostics_assert(($decoded['selectionDiagnostics']['legacySnapshot']['suppressedActivityCount'] ?? 0) >= 2, 'selection diagnostics explain suppressed legacy snapshot rows');
    foundation_import_diagnostics_assert(($decoded['selectionDiagnostics']['legacySnapshot']['nonDeterministicActivityCount'] ?? 0) >= 1, 'selection diagnostics count non-deterministic legacy snapshot rows');
    foundation_import_diagnostics_assert(($decoded['selectionDiagnostics']['directGarmin']['selectedActivityCount'] ?? 0) >= 3, 'selection diagnostics count selected direct Garmin rows');
    foundation_import_diagnostics_assert(!str_contains($integrationResult['stdout'] ?? '', $integrationRoot), 'import diagnostics response does not expose the private root path');
    foundation_import_diagnostics_assert(!str_contains($integrationResult['stdout'] ?? '', $integrationDatabaseUrl), 'import diagnostics response does not expose the database URL');
    foundation_import_diagnostics_assert(!str_contains($integrationResult['stdout'] ?? '', 'test-hevy-key'), 'import diagnostics response does not expose raw connector secrets');
}

if ($failed > 0) {
    fwrite(STDERR, "foundation-import-diagnostics-tests: {$passed} passed, {$failed} failed, {$skipped} skipped\n");
    exit(1);
}

fwrite(STDOUT, "foundation-import-diagnostics-tests: {$passed} passed, 0 failed, {$skipped} skipped\n");
