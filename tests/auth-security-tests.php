<?php
declare(strict_types=1);

$tmpRoot = sys_get_temp_dir() . '/mattrics-auth-tests-' . bin2hex(random_bytes(4));
$privateRoot = $tmpRoot . '/private';
mkdir($privateRoot, 0775, true);
$configPath = $privateRoot . '/config.php';
file_put_contents($configPath, "<?php\nreturn [\n    'site_origin' => 'https://app.example.com',\n    'webauthn_rp_id' => 'example.com',\n    'auth_require_https' => true,\n    'session_idle_seconds' => 60,\n    'session_absolute_seconds' => 300,\n];\n");
putenv('MATTRICS_CONFIG=' . $configPath);

$_SERVER['HTTP_HOST'] = 'app.example.com';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_PORT'] = 443;
$_SERVER['REMOTE_ADDR'] = '203.0.113.10';

require_once dirname(__DIR__) . '/public/api/bootstrap-auth.php';

mattrics_auth_session_start();

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

function reset_auth_files(string $privateRoot): void
{
    foreach (['auth-challenges.json', 'auth-rate-limits.json', 'passkey-credential.json', 'auth-audit.log'] as $file) {
        $path = $privateRoot . '/' . $file;
        if (is_file($path)) {
            unlink($path);
        }
    }
}

function write_json(string $path, array $payload): void
{
    file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

reset_auth_files($privateRoot);

// Challenge lifecycle
$challenge = random_bytes(32);
mattrics_store_challenge('login', $challenge);
test_assert(mattrics_find_valid_challenge('login', $challenge) !== null, 'valid challenge can be found');
test_assert(mattrics_consume_challenge('login', $challenge) === true, 'valid challenge can be consumed');
test_assert(mattrics_find_valid_challenge('login', $challenge) === null, 'consumed challenge cannot be reused');

reset_auth_files($privateRoot);
$expired = random_bytes(32);
mattrics_store_challenge('login', $expired);
$path = $privateRoot . '/auth-challenges.json';
$store = mattrics_json_file_read($path, ['challenges' => []]);
$store['challenges'][0]['expires_at'] = time() - 1;
write_json($path, $store);
test_assert(mattrics_find_valid_challenge('login', $expired) === null, 'expired challenge is rejected');

reset_auth_files($privateRoot);
$mismatch = random_bytes(32);
mattrics_store_challenge('login', $mismatch);
test_assert(mattrics_find_valid_challenge('delete', $mismatch) === null, 'mismatched purpose is rejected');
test_assert(mattrics_find_valid_challenge('login', random_bytes(32)) === null, 'mismatched challenge is rejected');
$store = mattrics_json_file_read($privateRoot . '/auth-challenges.json', ['challenges' => []]);
$store['challenges'][0]['session_hash'] = hash('sha256', 'other-session');
write_json($privateRoot . '/auth-challenges.json', $store);
test_assert(mattrics_find_valid_challenge('login', $mismatch) === null, 'mismatched session is rejected');

reset_auth_files($privateRoot);
test_assert(mattrics_check_rate_limit('unit', 2, 2, 60) === true, 'rate limit request 1 allowed');
test_assert(mattrics_check_rate_limit('unit', 2, 2, 60) === true, 'rate limit request 2 allowed');
test_assert(mattrics_check_rate_limit('unit', 2, 2, 60) === false, 'rate-limited request rejected');

// Session and origin security
$_SESSION['mattrics_authed'] = true;
$_SESSION['mattrics_authed_at'] = time();
$_SESSION['mattrics_last_seen_at'] = time() - 120;
test_assert(mattrics_session_is_timed_out() === true, 'idle timeout is detected');
$_SESSION['mattrics_last_seen_at'] = time();
$_SESSION['mattrics_authed_at'] = time() - 400;
test_assert(mattrics_session_is_timed_out() === true, 'absolute timeout is detected');
$_SESSION = [];

$validClientData = json_encode(['origin' => 'https://app.example.com', 'challenge' => mattrics_b64url_encode('abc')]);
$invalidClientData = json_encode(['origin' => 'https://evil.example.com', 'challenge' => mattrics_b64url_encode('abc')]);
test_assert(mattrics_validate_client_origin((string) $validClientData, 'https://app.example.com') === true, 'valid exact origin accepted');
test_assert(mattrics_validate_client_origin((string) $invalidClientData, 'https://app.example.com') === false, 'invalid exact origin rejected');
test_assert(mattrics_origin_host_allows_rp_id('app.example.com', 'example.com') === true, 'real subdomain may use parent RP ID');
test_assert(mattrics_origin_host_allows_rp_id('badexample.com', 'example.com') === false, 'unsafe RP ID suffix rejected');

$_SESSION['mattrics_csrf_token'] = 'known-token';
$_SERVER['HTTP_X_CSRF_TOKEN'] = 'wrong-token';
test_assert(mattrics_csrf_is_valid((string) $_SERVER['HTTP_X_CSRF_TOKEN']) === false, 'CSRF mismatch is rejected by protected endpoint helper');
$_SERVER['HTTP_X_CSRF_TOKEN'] = 'known-token';
test_assert(mattrics_csrf_is_valid((string) $_SERVER['HTTP_X_CSRF_TOKEN']) === true, 'CSRF match is accepted');

// Recovery
$store = ['version' => 2, 'userId' => base64_encode('user'), 'credentials' => [], 'recovery' => ['codes' => []]];
$codes = mattrics_generate_recovery_codes($store, 2);
test_assert(count($codes) === 2 && mattrics_recovery_status($store)['activeCount'] === 2, 'recovery codes generated');
test_assert(mattrics_use_recovery_code($store, $codes[0]) === true, 'valid recovery code succeeds');
test_assert(mattrics_use_recovery_code($store, $codes[0]) === false, 'used recovery code cannot be reused');
test_assert(mattrics_use_recovery_code($store, 'NOPE-NOPE') === false, 'invalid recovery code fails');
$oldCode = $codes[1];
$newCodes = mattrics_generate_recovery_codes($store, 2);
test_assert(mattrics_use_recovery_code($store, $oldCode) === false, 'regeneration invalidates old codes');
test_assert(mattrics_use_recovery_code($store, $newCodes[0]) === true, 'new regenerated code works');

$_SESSION = ['mattrics_recovery_verified' => true, 'mattrics_recovery_verified_at' => time()];
test_assert(mattrics_recovery_session_is_valid() && empty($_SESSION['mattrics_authed']), 'recovery session allows replacement registration without app auth');
$_SESSION['mattrics_recovery_verified_at'] = time() - 700;
test_assert(mattrics_recovery_session_is_valid() === false, 'recovery session expires');

// Credential store migration
reset_auth_files($privateRoot);
write_json($privateRoot . '/passkey-credential.json', [
    'userId' => base64_encode('user'),
    'credentialId' => base64_encode('credential'),
    'credentialPublicKey' => 'public-key',
    'signatureCounter' => 1,
    'registeredAt' => '2026-01-01T00:00:00+00:00',
]);
$migrated = mattrics_load_credentials();
test_assert(($migrated['version'] ?? null) === 2, 'legacy single credential migrates to version 2');
test_assert(count($migrated['credentials'] ?? []) === 1, 'legacy migration preserves credential');
test_assert(array_key_exists('recovery', $migrated), 'legacy migration adds recovery block');

reset_auth_files($privateRoot);
write_json($privateRoot . '/passkey-credential.json', [
    'userId' => base64_encode('user'),
    'credentials' => [[
        'internalId' => 'abc',
        'name' => 'Phone',
        'credentialId' => base64_encode('credential'),
        'credentialPublicKey' => 'public-key',
        'signatureCounter' => 1,
        'registeredAt' => '2026-01-01T00:00:00+00:00',
    ]],
]);
$migrated = mattrics_load_credentials();
test_assert(($migrated['version'] ?? null) === 2, 'current multi-passkey store migrates to version 2');
test_assert(($migrated['credentials'][0]['internalId'] ?? '') === 'abc', 'multi-passkey migration preserves existing credential');
test_assert(array_key_exists('created_at', $migrated['credentials'][0]), 'multi-passkey migration adds created_at');

echo "Auth security tests: {$passed}/" . ($passed + $failed) . " passed\n";

if ($failed > 0) {
    exit(1);
}
