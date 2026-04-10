<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap-auth.php';
require_once mattrics_lib_root() . '/WebAuthn/src/WebAuthn.php';

mattrics_auth_session_start();
mattrics_require_https_if_needed();
mattrics_enforce_rate_limit('verify');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$store = mattrics_load_credentials();

// If credentials already exist, user must be authenticated to add another
if ($store !== null && empty($_SESSION['mattrics_authed']) && !mattrics_recovery_session_is_valid()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication is required.']);
    exit;
}

$body = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body.']);
    exit;
}

try {
    $rpId      = mattrics_rp_id();
    $webAuthn  = new lbuchs\WebAuthn\WebAuthn('Mattrics', $rpId, ['none', 'packed', 'apple'], true);
    $clientDataJSON = mattrics_b64url_decode((string) ($body['clientDataJSON'] ?? ''));
    $challengeRecord = mattrics_validate_challenge_for_client_data('register', $clientDataJSON);
    if ($challengeRecord === null) {
        throw new RuntimeException('Invalid registration challenge.');
    }
    $challenge = mattrics_challenge_from_client_data($clientDataJSON);

    $data = $webAuthn->processCreate(
        $clientDataJSON,
        mattrics_b64url_decode((string) ($body['attestationObject'] ?? '')),
        $challenge,
        true,   // requireUserVerification
        true,   // requireUserPresent
        false   // failIfRootMismatch — shared hosting has no CA bundle
    );

    // Accept name from request body, fall back to "Default"
    $name = trim((string) ($body['name'] ?? 'Default'));
    if ($name === '') $name = 'Default';
    $now = gmdate('c');

    $newEntry = [
        'internalId'          => bin2hex(random_bytes(16)),
        'name'                => $name,
        'device_label'        => $name,
        'credentialId'        => base64_encode((string) $data->credentialId),
        'credentialPublicKey' => $data->credentialPublicKey,
        'signatureCounter'    => (int) ($data->signatureCounter ?? 0),
        'registeredAt'        => $now,
        'created_at'          => $now,
        'last_used_at'        => null,
    ];

    $isFirstRegistration = $store === null;
    if ($store === null) {
        $store = [
            'version'     => 2,
            'userId'      => (string) ($challengeRecord['user_id'] ?? ''),
            'credentials' => [],
            'recovery'    => [
                'generated_at' => null,
                'last_rotated_at' => null,
                'codes' => [],
            ],
        ];
    }
    if (($store['userId'] ?? '') === '' && !empty($challengeRecord['user_id'])) {
        $store['userId'] = (string) $challengeRecord['user_id'];
    }

    $store['credentials'][] = $newEntry;
    $recoveryCodes = null;
    if ($isFirstRegistration) {
        $recoveryCodes = mattrics_generate_recovery_codes($store);
        mattrics_audit_log('recovery_codes_generated', ['outcome' => 'success']);
    }
    mattrics_save_credentials($store);

    mattrics_consume_challenge('register', $challenge);
    unset($_SESSION['mattrics_recovery_verified'], $_SESSION['mattrics_recovery_verified_at']);
    mattrics_audit_log('passkey_added', ['outcome' => 'success', 'credential' => $newEntry['internalId']]);

    echo json_encode(['ok' => true, 'recoveryCodes' => $recoveryCodes]);

} catch (\Throwable $e) {
    http_response_code(400);
    mattrics_audit_log('passkey_add_failed', ['outcome' => 'failure']);
    echo json_encode(['error' => 'Passkey registration failed. Please try again.']);
}
