<?php
declare(strict_types=1);

$tempRoot = sys_get_temp_dir() . '/mattrics-connectors-api-tests-' . bin2hex(random_bytes(4));
mkdir($tempRoot, 0775, true);

require_once dirname(__DIR__) . '/scripts/lib/foundation-connectors.php';

$passed = 0;
$failed = 0;

function connectors_test_assert(bool $condition, string $message): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }

    $failed++;
    fwrite(STDERR, "FAIL: {$message}\n");
}

function connectors_test_write_php_config(string $path, array $config): void
{
    file_put_contents($path, "<?php\nreturn " . var_export($config, true) . ";\n");
}

function connectors_test_write_json(string $path, mixed $value): void
{
    file_put_contents($path, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function connectors_test_fixture_env(
    string $configPath,
    string $method = 'GET',
    string $host = 'localhost',
    bool $authed = true,
    string $csrf = '',
    string $requester = '',
    string $runtimeMode = 'foundation'
): array {
    return [
        'MATTRICS_CONFIG' => $configPath,
        'MATTRICS_RUNTIME_MODE' => $runtimeMode,
        'MATTRICS_AUTH_REQUIRE_HTTPS' => '0',
        'MATTRICS_TEST_CONNECTORS_METHOD' => $method,
        'MATTRICS_TEST_HTTP_HOST' => $host,
        'MATTRICS_TEST_CONNECTORS_AUTH' => $authed ? '1' : '0',
        'MATTRICS_TEST_CONNECTORS_CSRF' => $csrf,
        'MATTRICS_TEST_CONNECTORS_REQUESTER' => $requester,
    ];
}

function connectors_test_run_fixture(string $fixturePath, array $env, string $stdin = ''): array
{
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($fixturePath);
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptorSpec, $pipes, dirname(__DIR__), $env);

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

$privateRoot = $tempRoot . '/private';
mkdir($privateRoot . '/storage', 0775, true);
$configPath = $privateRoot . '/config.php';
connectors_test_write_php_config($configPath, [
    'auth_require_https' => false,
    'foundation_database_url' => '',
    'foundation_user_key' => 'legacy-local-user',
]);

$fixturePath = dirname(__DIR__) . '/tests/fixtures/run-connectors-endpoint.php';

$getResult = connectors_test_run_fixture(
    $fixturePath,
    connectors_test_fixture_env($configPath)
);
connectors_test_assert(($getResult['exitCode'] ?? 1) === 0, 'connectors GET fixture exits successfully');
connectors_test_assert(isset($getResult['decoded']['connectors']['hevy']), 'connectors GET exposes Hevy payload');
connectors_test_assert(!isset($getResult['decoded']['connectors']['hevy']['apiKey']), 'connectors GET never exposes raw API keys');
connectors_test_assert(($getResult['decoded']['connectors']['hevy']['connectionStatus'] ?? '') === 'paused', 'connectors GET defaults Hevy status to paused');

$remoteResult = connectors_test_run_fixture(
    $fixturePath,
    connectors_test_fixture_env($configPath, 'GET', 'example.com', true, '', '', 'default')
);
connectors_test_assert(($remoteResult['decoded']['error'] ?? '') === 'Not found.', 'connectors endpoint is hidden for non-local requests');

$unauthorizedResult = connectors_test_run_fixture(
    $fixturePath,
    connectors_test_fixture_env($configPath, 'POST', 'example.com', false, 'token-1', '', 'foundation'),
    json_encode(['connector' => 'hevy', 'action' => 'save', 'enabled' => true], JSON_UNESCAPED_SLASHES)
);
connectors_test_assert(($unauthorizedResult['decoded']['error'] ?? '') === 'Unauthorized.', 'connectors POST requires auth');

$csrfFailureResult = connectors_test_run_fixture(
    $fixturePath,
    connectors_test_fixture_env($configPath, 'POST', 'localhost', true, ''),
    json_encode(['connector' => 'hevy', 'action' => 'save', 'enabled' => true], JSON_UNESCAPED_SLASHES)
);
connectors_test_assert(($csrfFailureResult['decoded']['error'] ?? '') === 'Security check failed. Refresh and try again.', 'connectors POST requires CSRF');

$saveResult = connectors_test_run_fixture(
    $fixturePath,
    connectors_test_fixture_env($configPath, 'POST', 'localhost', true, 'token-save'),
    json_encode([
        'connector' => 'hevy',
        'action' => 'save',
        'enabled' => true,
        'apiKey' => 'test-hevy-key',
    ], JSON_UNESCAPED_SLASHES)
);
connectors_test_assert(($saveResult['exitCode'] ?? 1) === 0, 'connectors save fixture exits successfully');
connectors_test_assert(($saveResult['decoded']['connectors']['hevy']['hasCredential'] ?? false) === true, 'connectors save reports stored credential presence');
connectors_test_assert(!isset($saveResult['decoded']['connectors']['hevy']['apiKey']), 'connectors save response omits raw key');

$savedStore = mattrics_foundation_load_connector_store($privateRoot);
connectors_test_assert(($savedStore['version'] ?? 0) === 2, 'connectors save upgrades the connector store schema version');
connectors_test_assert(($savedStore['connectors']['hevy']['apiKey'] ?? '') === 'test-hevy-key', 'connectors save persists Hevy API keys privately');

$preserveKeyResult = connectors_test_run_fixture(
    $fixturePath,
    connectors_test_fixture_env($configPath, 'POST', 'localhost', true, 'token-preserve'),
    json_encode([
        'connector' => 'hevy',
        'action' => 'save',
        'enabled' => false,
        'apiKey' => '',
    ], JSON_UNESCAPED_SLASHES)
);
connectors_test_assert(($preserveKeyResult['decoded']['connectors']['hevy']['enabled'] ?? true) === false, 'connectors save updates enabled state');
$preservedStore = mattrics_foundation_load_connector_store($privateRoot);
connectors_test_assert(($preservedStore['connectors']['hevy']['apiKey'] ?? '') === 'test-hevy-key', 'connectors save preserves the existing key when the browser sends an empty key');

$testResult = connectors_test_run_fixture(
    $fixturePath,
    connectors_test_fixture_env($configPath, 'POST', 'localhost', true, 'token-test', 'success'),
    json_encode([
        'connector' => 'hevy',
        'action' => 'test',
    ], JSON_UNESCAPED_SLASHES)
);
connectors_test_assert(($testResult['decoded']['connectors']['hevy']['test']['lastSucceededAt'] ?? '') !== '', 'connectors test records successful test timestamps');
connectors_test_assert(!str_contains($testResult['stdout'] ?? '', 'test-hevy-key'), 'connectors test never leaks the stored key');

$clearResult = connectors_test_run_fixture(
    $fixturePath,
    connectors_test_fixture_env($configPath, 'POST', 'localhost', true, 'token-clear'),
    json_encode([
        'connector' => 'hevy',
        'action' => 'clear',
    ], JSON_UNESCAPED_SLASHES)
);
connectors_test_assert(($clearResult['decoded']['connectors']['hevy']['hasCredential'] ?? true) === false, 'connectors clear removes stored credentials');
$clearedStore = mattrics_foundation_load_connector_store($privateRoot);
connectors_test_assert(array_key_exists('apiKey', $clearedStore['connectors']['hevy']) && $clearedStore['connectors']['hevy']['apiKey'] === null, 'connectors clear removes the private key from disk');
connectors_test_assert(array_key_exists('cursorStartedAt', $clearedStore['connectors']['hevy']['syncState']) && $clearedStore['connectors']['hevy']['syncState']['cursorStartedAt'] === null, 'connectors clear resets incremental cursor state');
connectors_test_assert(array_key_exists('lastSucceededAt', $clearedStore['connectors']['hevy']['testState']) && $clearedStore['connectors']['hevy']['testState']['lastSucceededAt'] === null, 'connectors clear resets test status state');

if ($failed > 0) {
    fwrite(STDERR, "connectors-api-tests: {$passed} passed, {$failed} failed\n");
    exit(1);
}

fwrite(STDOUT, "connectors-api-tests: {$passed} passed, 0 failed\n");
