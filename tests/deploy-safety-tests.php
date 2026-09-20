<?php
declare(strict_types=1);

$repoRoot = dirname(__DIR__);
$temporaryRoot = sys_get_temp_dir() . '/mattrics-deploy-safety-' . bin2hex(random_bytes(6));
$passed = 0;
$failed = 0;

function deploy_assert(bool $condition, string $message): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }

    $failed++;
    fwrite(STDERR, "FAIL: {$message}\n");
}

function run_deploy_case(string $repoRoot, string $envFile): array
{
    $command = ['sh', $repoRoot . '/deploy.sh'];
    $pipes = [];
    $environment = getenv();
    $environment['MATTRICS_ENV_FILE'] = $envFile;
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $repoRoot, $environment);
    if (!is_resource($process)) {
        return [127, 'could not start deploy.sh'];
    }

    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), $output];
}

mkdir($temporaryRoot, 0700, true);
$keyPath = $temporaryRoot . '/deploy-key';
$pinnedPath = $temporaryRoot . '/known_hosts';
$emptyPinsPath = $temporaryRoot . '/empty_known_hosts';
file_put_contents($keyPath, "test key placeholder\n");
file_put_contents($pinnedPath, "example.test ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAITestOnly\n");
file_put_contents($emptyPinsPath, '');
chmod($keyPath, 0600);

$base = [
    'SFTP_HOST=example.test',
    'SFTP_PORT=22',
    'SFTP_USER=test-user',
    'SFTP_KEY_PATH=' . $keyPath,
    'MATTRICS_REMOTE_DIR=sites/mattrics',
];

$cases = [
    'password authentication is rejected' => [
        array_merge($base, ['SFTP_KNOWN_HOSTS=' . $pinnedPath, 'SFTP_PASSWORD=forbidden-test-value']),
        'password authentication is forbidden',
    ],
    'missing host pin is rejected' => [
        array_merge($base, ['SFTP_KNOWN_HOSTS=' . $emptyPinsPath]),
        'no host key pinned',
    ],
    'public_html target is rejected' => [
        array_merge(array_slice($base, 0, 4), [
            'SFTP_KNOWN_HOSTS=' . $pinnedPath,
            'MATTRICS_REMOTE_DIR=public_html/mattrics',
        ]),
        'never public_html',
    ],
];

foreach ($cases as $name => [$lines, $expected]) {
    $envPath = $temporaryRoot . '/' . preg_replace('/[^a-z]+/', '-', $name) . '.env';
    file_put_contents($envPath, implode("\n", $lines) . "\n");
    [$status, $output] = run_deploy_case($repoRoot, $envPath);
    deploy_assert($status !== 0, $name . ' exits non-zero');
    deploy_assert(str_contains($output, $expected), $name . ' reports the expected guard');
}

foreach (glob($temporaryRoot . '/*') ?: [] as $path) {
    unlink($path);
}
rmdir($temporaryRoot);

if ($failed > 0) {
    fwrite(STDERR, "deploy-safety-tests: {$passed} passed, {$failed} failed\n");
    exit(1);
}

fwrite(STDOUT, "deploy-safety-tests: {$passed} passed, 0 failed\n");
