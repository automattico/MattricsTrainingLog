<?php
declare(strict_types=1);

$repoRoot = dirname(__DIR__);
$testCsrfToken = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
$testOrigin = 'http://127.0.0.1:8080';
$siteRoot = sys_get_temp_dir() . '/mattrics-router-tests-' . bin2hex(random_bytes(4));
mkdir($siteRoot . '/private/data', 0700, true);
symlink($repoRoot . '/public', $siteRoot . '/public');
symlink($repoRoot . '/api', $siteRoot . '/api');
symlink($repoRoot . '/lib', $siteRoot . '/lib');
copy($repoRoot . '/private/config.example.php', $siteRoot . '/private/config.php');
foreach (glob($repoRoot . '/private/data/*.json') ?: [] as $seed) {
    copy($seed, $siteRoot . '/private/data/' . basename($seed));
}

$passed = 0;
$failed = 0;
$servers = [];

function router_assert(bool $condition, string $message): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }
    $failed++;
    fwrite(STDERR, "FAIL: {$message}\n");
}

function router_start(string $repoRoot, string $siteRoot, int $port, bool $authenticated)
{
    putenv('MATTWARDEN_TEST_SITE_DIR=' . $siteRoot);
    putenv('MATTWARDEN_TEST_AUTHENTICATED=' . ($authenticated ? '1' : '0'));
    $command = escapeshellarg(PHP_BINARY)
        . ' -S 127.0.0.1:' . $port
        . ' ' . escapeshellarg($repoRoot . '/scripts/dev-router.php');
    $null = fopen('/dev/null', 'w');
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => $null, 2 => $null], $pipes, $repoRoot);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start the local router.');
    }
    fclose($pipes[0]);
    for ($attempt = 0; $attempt < 40; $attempt++) {
        $socket = @fsockopen('127.0.0.1', $port, $errorCode, $errorMessage, 0.1);
        if (is_resource($socket)) {
            fclose($socket);
            return [$process, $null];
        }
        usleep(50000);
    }
    proc_terminate($process);
    fclose($null);
    throw new RuntimeException('Local router did not become ready.');
}

function router_stop($process, $null): void
{
    proc_terminate($process);
    proc_close($process);
    fclose($null);
}

function router_request(int $port, string $path, string $method = 'GET', array $headers = [], ?array $body = null): array
{
    $headerLines = $headers;
    $content = '';
    if ($body !== null) {
        $content = json_encode($body, JSON_UNESCAPED_SLASHES);
        $headerLines[] = 'Content-Type: application/json';
    }
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headerLines),
            'content' => $content,
            'ignore_errors' => true,
            'timeout' => 5,
        ],
    ]);
    $responseBody = file_get_contents('http://127.0.0.1:' . $port . $path, false, $context);
    $responseHeaders = $http_response_header ?? [];
    preg_match('/\s(\d{3})\s/', (string) ($responseHeaders[0] ?? ''), $matches);
    return [
        'status' => isset($matches[1]) ? (int) $matches[1] : 0,
        'headers' => $responseHeaders,
        'body' => $responseBody === false ? '' : $responseBody,
        'json' => json_decode($responseBody === false ? '' : $responseBody, true),
    ];
}

$port = random_int(18100, 18900);
try {
    [$process, $null] = router_start($repoRoot, $siteRoot, $port, true);
    $servers[] = [$process, $null];

    $home = router_request($port, '/');
    router_assert($home['status'] === 200 && str_contains($home['body'], '<title>Mattrics Training Log</title>'), 'router serves the static shell at /');
    router_assert((bool) array_filter($home['headers'], static fn(string $header): bool => str_starts_with($header, 'Content-Security-Policy:')), 'router applies the default HTML CSP');

    $session = router_request($port, '/api/session');
    router_assert($session['status'] === 200 && ($session['json']['csrfToken'] ?? '') === $testCsrfToken, 'extensionless session bootstrap returns the fixed token');
    router_assert(($session['json']['appVersion'] ?? '') === '1', 'session bootstrap returns app version 1');

    $sessionAlias = router_request($port, '/api/session.php');
    router_assert($sessionAlias['status'] === 200, 'router supports the .php API alias');

    $exercisePath = router_request($port, '/api/exercises/path-probe');
    router_assert($exercisePath['status'] === 200 && is_array($exercisePath['json']['exercises'] ?? null), 'router dispatches exercise PATH_INFO');

    $settingsPayload = [
        'bodyWeightKg' => 80,
        'defaultRpe' => 7.5,
        'birthday' => '1990-01-01',
        'sex' => 'Prefer not to say',
        'heightCm' => 180,
        'experienceLevel' => 'Intermediate',
    ];
    $missingCsrf = router_request($port, '/api/settings', 'POST', ['Origin: ' . $testOrigin], $settingsPayload);
    router_assert($missingCsrf['status'] === 403 && ($missingCsrf['json']['error'] ?? '') === 'CSRF validation failed.', 'mutation without CSRF receives 403');

    $foreignOrigin = router_request($port, '/api/settings', 'POST', [
        'Origin: https://foreign.example',
        'X-CSRF-Token: ' . $testCsrfToken,
    ], $settingsPayload);
    router_assert($foreignOrigin['status'] === 403 && ($foreignOrigin['json']['error'] ?? '') === 'Origin validation failed.', 'mutation from a foreign origin receives 403');

    $validMutation = router_request($port, '/api/settings', 'POST', [
        'Origin: ' . $testOrigin,
        'X-CSRF-Token: ' . $testCsrfToken,
    ], $settingsPayload);
    if ($validMutation['status'] !== 200) {
        fwrite(STDERR, 'Valid mutation response: ' . $validMutation['status'] . ' ' . $validMutation['body'] . "\n");
    }
    router_assert($validMutation['status'] === 200 && (float) ($validMutation['json']['settings']['bodyWeightKg'] ?? 0) === 80.0, 'same-origin mutation with CSRF succeeds');

    router_assert(router_request($port, '/api/does-not-exist')['status'] === 404, 'unknown API script receives JSON 404');
    router_assert(router_request($port, '/api/session', 'OPTIONS')['status'] === 405, 'unsupported API method receives 405');
    router_assert(router_request($port, '/', 'POST')['status'] === 405, 'static POST receives 405');
    router_assert(router_request($port, '/.hidden')['status'] === 404, 'dotfile path receives 404');

    router_stop($process, $null);
    array_pop($servers);

    $unauthPort = $port + 1;
    [$unauthProcess, $unauthNull] = router_start($repoRoot, $siteRoot, $unauthPort, false);
    $servers[] = [$unauthProcess, $unauthNull];
    $unauthStatic = router_request($unauthPort, '/');
    router_assert($unauthStatic['status'] === 401 && str_contains($unauthStatic['body'], 'Authentication required'), 'unauthenticated static request receives login HTML');
    $unauthApi = router_request($unauthPort, '/api/session');
    router_assert($unauthApi['status'] === 401 && ($unauthApi['json']['error'] ?? '') === 'Authentication required.', 'unauthenticated API request receives JSON 401');
    router_stop($unauthProcess, $unauthNull);
    array_pop($servers);
} finally {
    foreach ($servers as [$runningProcess, $runningNull]) {
        router_stop($runningProcess, $runningNull);
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($siteRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isLink() || $item->isFile()) {
            unlink($item->getPathname());
        } else {
            rmdir($item->getPathname());
        }
    }
    rmdir($siteRoot);
}

if ($failed > 0) {
    fwrite(STDERR, "dev-router-tests: {$passed} passed, {$failed} failed\n");
    exit(1);
}
fwrite(STDOUT, "dev-router-tests: {$passed} passed, 0 failed\n");
