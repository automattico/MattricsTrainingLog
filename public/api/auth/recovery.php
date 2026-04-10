<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

mattrics_auth_session_start();
mattrics_require_https_if_needed();
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    mattrics_send_json(['error' => 'Method not allowed.'], 405);
}

$body = mattrics_read_json_body();
$action = (string) ($body['action'] ?? 'verify');

if ($action === 'regenerate') {
    mattrics_require_auth();
    mattrics_require_csrf();
    $store = mattrics_load_credentials();
    if ($store === null) {
        mattrics_send_json(['error' => 'Authentication is not available.'], 404);
    }
    $codes = mattrics_generate_recovery_codes($store);
    mattrics_save_credentials($store);
    mattrics_audit_log('recovery_codes_rotated', ['outcome' => 'success']);
    mattrics_send_json([
        'ok' => true,
        'recoveryCodes' => $codes,
        'recovery' => mattrics_recovery_status($store),
    ]);
}

if ($action !== 'verify') {
    mattrics_send_json(['error' => 'Unknown action.'], 400);
}

mattrics_enforce_rate_limit('recovery');

$store = mattrics_load_credentials();
if ($store === null) {
    mattrics_audit_log('recovery_failure', ['outcome' => 'failure']);
    mattrics_send_json(['error' => 'Recovery is not available.'], 400);
}

$code = (string) ($body['code'] ?? '');
if (!mattrics_use_recovery_code($store, $code)) {
    mattrics_save_credentials($store);
    mattrics_audit_log('recovery_failure', ['outcome' => 'failure']);
    mattrics_send_json(['error' => 'Recovery failed. Check the code and try again.'], 401);
}

mattrics_save_credentials($store);
$_SESSION['mattrics_recovery_verified'] = true;
$_SESSION['mattrics_recovery_verified_at'] = time();
mattrics_audit_log('recovery_code_used', ['outcome' => 'success']);

mattrics_send_json([
    'ok' => true,
    'redirect' => '/register.php?recovery=1',
]);
